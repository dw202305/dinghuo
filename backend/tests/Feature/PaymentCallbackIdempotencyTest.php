<?php

declare(strict_types=1);

namespace tests\Feature;

use app\common\enum\OrderStatus;
use app\common\enum\PaymentChannel;
use app\common\enum\PaymentStatus;
use app\common\enum\PayStatus;
use app\common\service\pay\MockVerifier;
use app\common\service\PaymentService;
use think\facade\Db;

/**
 * 支付回调幂等资金安全 Feature 测试（真实 MySQL：事务 + 行锁 + 唯一约束）
 *
 * 用 MockVerifier 构造约定签名的微信回调报文，模拟同一报文连续投递 3 次，
 * 断言：仅第一次入账——订单状态迁移一次、lj_payment 只更新一次、
 * 第三方流水号（uk_payment_transaction_id 唯一索引）只写入一次。
 */
class PaymentCallbackIdempotencyTest extends FeatureTestCase
{
    private const PAY_AMOUNT = 20000; // 200.00 元

    private const TRANSACTION_ID = 'WX4200T10IDEM001';

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaymentService();
    }

    /**
     * 构造 MockVerifier 签名并验签通过的微信回调报文（模拟控制器层流程）
     */
    private function makeSignedNotify(string $paymentNo, int $payerTotalCent, string $transactionId): array
    {
        $verifier = new MockVerifier();

        $rawBody = (string) json_encode([
            'out_trade_no'   => $paymentNo,
            'transaction_id' => $transactionId,
            'amount'         => ['payer_total' => $payerTotalCent],
        ], JSON_UNESCAPED_UNICODE);

        $headers = [MockVerifier::SIGN_HEADER => $verifier->sign($rawBody)];
        $this->assertTrue($verifier->verify($headers, $rawBody), 'Mock 签名报文应验签通过');

        return $verifier->parse($rawBody);
    }

    /**
     * 用例1：同一回调报文连续投递 3 次，仅第一次入账
     */
    public function testTripleDeliveryCreditsOnlyOnce(): void
    {
        $order   = $this->seedOrder(['total_amount_cent' => self::PAY_AMOUNT]);
        $payment = $this->seedPayment([
            'order_id'        => $order['id'],
            'order_no'        => $order['order_no'],
            'payment_channel' => PaymentChannel::WECHAT->value,
            'pay_amount_cent' => self::PAY_AMOUNT,
        ]);

        $notify = $this->makeSignedNotify($payment['payment_no'], self::PAY_AMOUNT, self::TRANSACTION_ID);

        // ── 第 1 次投递：正常入账 ──
        $this->assertTrue($this->service->handleWechatNotify($notify), '首次回调应入账成功');

        $paymentAfterFirst = $this->freshPayment((int) $payment['id']);
        $orderAfterFirst   = $this->freshOrder((int) $order['id']);

        // 支付单：成功态 + 第三方流水号唯一写入
        $this->assertSame(PayStatus::SUCCESS->value, (int) $paymentAfterFirst['pay_status']);
        $this->assertSame(self::TRANSACTION_ID, (string) $paymentAfterFirst['transaction_id']);
        $this->assertNotEmpty($paymentAfterFirst['paid_at']);
        $this->assertNotEmpty($paymentAfterFirst['notify_content']);

        // 订单：状态迁移一次（待支付 → 支付处理中 → 已支付待审核），实付入账
        $this->assertSame(OrderStatus::PAID_PENDING->value, (int) $orderAfterFirst['order_status']);
        $this->assertSame(self::PAY_AMOUNT, (int) $orderAfterFirst['paid_amount_cent']);
        $this->assertSame(PaymentStatus::PAID->value, (int) $orderAfterFirst['payment_status']);
        $this->assertNotEmpty($orderAfterFirst['paid_at']);

        // 落库快照：重复投递后逐字段比对
        $historyCountAfterFirst   = Db::name('order_status_history')->where('order_id', $order['id'])->count();
        $notifyLogCountAfterFirst = Db::name('operation_log')->where('target_type', 'payment')->where('target_id', $payment['id'])->count();
        $this->assertSame(2, $historyCountAfterFirst, '首次入账产生 paying + paid_pending 两条状态历史');
        $this->assertSame(1, $notifyLogCountAfterFirst, '首次入账只写一条回调操作日志');

        // ── 第 2、3 次投递：幂等成功，绝不重复入账 ──
        for ($i = 2; $i <= 3; $i++) {
            $this->assertTrue(
                $this->service->handleWechatNotify($notify),
                "第 {$i} 次重复回调应幂等返回成功"
            );
        }

        $paymentFinal = $this->freshPayment((int) $payment['id']);
        $orderFinal   = $this->freshOrder((int) $order['id']);

        // 支付单只更新一次：所有字段与首次入账快照一致
        $this->assertSame(PayStatus::SUCCESS->value, (int) $paymentFinal['pay_status']);
        $this->assertSame(self::TRANSACTION_ID, (string) $paymentFinal['transaction_id']);
        $this->assertSame((string) $paymentAfterFirst['paid_at'], (string) $paymentFinal['paid_at'], 'paid_at 不应被重复投递改写');
        $this->assertSame((string) $paymentAfterFirst['updated_at'], (string) $paymentFinal['updated_at'], '支付单不应被二次更新');
        $this->assertSame((string) $paymentAfterFirst['notify_content'], (string) $paymentFinal['notify_content']);

        // 该订单支付单仍只有一张（无重复支付单）
        $this->assertSame(1, (int) Db::name('payment')->where('order_id', $order['id'])->count());

        // 订单不被二次入账：金额与状态保持首次结果
        $this->assertSame(OrderStatus::PAID_PENDING->value, (int) $orderFinal['order_status']);
        $this->assertSame(self::PAY_AMOUNT, (int) $orderFinal['paid_amount_cent'], '重复回调不得重复累加实付金额');
        $this->assertSame((string) $orderAfterFirst['paid_at'], (string) $orderFinal['paid_at']);

        // 状态历史与操作日志数量不变："支付成功"状态迁移只发生一次
        $this->assertSame($historyCountAfterFirst, (int) Db::name('order_status_history')->where('order_id', $order['id'])->count());
        $this->assertSame(1, (int) Db::name('order_status_history')
            ->where('order_id', $order['id'])
            ->where('to_status', 'paid_pending')
            ->count(), '订单状态只迁移到已支付待审核一次');
        $this->assertSame($notifyLogCountAfterFirst, (int) Db::name('operation_log')->where('target_type', 'payment')->where('target_id', $payment['id'])->count());
    }

    /**
     * 用例2：终态（已成功）支付单的任意后续回调一律不再入账
     */
    public function testTerminalPaymentRejectsFurtherCredit(): void
    {
        $order   = $this->seedOrder(['total_amount_cent' => self::PAY_AMOUNT]);
        $payment = $this->seedPayment([
            'order_id'        => $order['id'],
            'order_no'        => $order['order_no'],
            'payment_channel' => PaymentChannel::WECHAT->value,
            'pay_amount_cent' => self::PAY_AMOUNT,
            'pay_status'      => PayStatus::SUCCESS->value,
            'transaction_id'  => self::TRANSACTION_ID,
            'paid_at'         => date('Y-m-d H:i:s'),
        ]);

        // 订单已处于已支付待审核（模拟首次入账完成后的现场）
        Db::name('order')->where('id', $order['id'])->update([
            'order_status'   => OrderStatus::PAID_PENDING->value,
            'paid_amount_cent' => self::PAY_AMOUNT,
            'payment_status' => PaymentStatus::PAID->value,
        ]);

        $notify = $this->makeSignedNotify($payment['payment_no'], self::PAY_AMOUNT, self::TRANSACTION_ID);

        // 终态支付单的回调：幂等返回成功（对第三方友好），但绝不产生任何写入
        $this->assertTrue($this->service->handleWechatNotify($notify));

        $orderFinal = $this->freshOrder((int) $order['id']);
        $this->assertSame(self::PAY_AMOUNT, (int) $orderFinal['paid_amount_cent']);
        $this->assertSame(0, (int) Db::name('order_status_history')->where('order_id', $order['id'])->count(), '终态回调不得产生状态迁移');
        $this->assertSame(0, (int) Db::name('operation_log')->where('target_id', $payment['id'])->count(), '终态回调不得产生操作日志');
    }
}
