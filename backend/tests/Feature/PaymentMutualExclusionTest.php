<?php

declare(strict_types=1);

namespace tests\Feature;

use app\common\enum\OrderStatus;
use app\common\enum\PaymentChannel;
use app\common\enum\PayStatus;
use app\common\service\PaymentService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 支付方式互斥资金安全 Feature 测试（真实 MySQL）
 *
 * PRD 4.9.4：一张订单只能选余额/微信/支付宝一种支付方式。
 * - 同单已有微信进行中支付单时，再创建余额支付必须被拒（业务码 4104），
 *   且不产生任何支付单与余额扣减流水；
 * - 同渠道重复发起允许（刷新原支付单参数，不新建支付单）；
 * - 其他渠道已支付成功时，任何新渠道被拒（4104）。
 *
 * 说明：createPayment 首次成功后会把订单推进为"支付处理中"，门店再次打开
 * 收银台属正常业务场景，故用例中用 Db 将订单状态复位为"待支付"属于
 * 测试数据准备（模拟用户未完成支付即重新发起），并非绕过状态机。
 */
class PaymentMutualExclusionTest extends FeatureTestCase
{
    private const PAY_AMOUNT = 20000;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaymentService();
    }

    /**
     * 复位订单至待支付（测试数据准备，见类注释）
     */
    private function resetOrderToPendingPay(int $orderId): void
    {
        Db::name('order')->where('id', $orderId)->update([
            'order_status' => OrderStatus::PENDING_PAY->value,
        ]);
    }

    /**
     * 用例1：微信进行中支付单存在时，余额支付被拒（4104）且无副作用
     */
    public function testBalanceRejectedWhenWechatPaymentPending(): void
    {
        $order = $this->seedOrder(['total_amount_cent' => self::PAY_AMOUNT]);

        // 先创建微信支付单（进行中）
        $wechatResult = $this->service->createPayment(
            self::STORE_ID,
            (int) $order['id'],
            1, // API 渠道编码：1=微信
            'JSAPI',
            ['idempotent_key' => "order_pay:{$order['order_no']}:wechat"]
        );
        $this->assertArrayHasKey('payment_no', $wechatResult);
        $this->assertSame(PaymentChannel::WECHAT->value, $wechatResult['pay_channel']);
        $this->assertSame(OrderStatus::PAYING->value, (int) $this->freshOrder((int) $order['id'])['order_status']);

        // 数据准备：模拟支付未完成，门店重新打开收银台
        $this->resetOrderToPendingPay((int) $order['id']);

        // 切换到余额支付 → 必须以 4104 拒绝
        try {
            $this->service->createPayment(
                self::STORE_ID,
                (int) $order['id'],
                3, // API 渠道编码：3=余额
                '',
                ['idempotent_key' => "order_pay:{$order['order_no']}:balance"]
            );
            $this->fail('已有微信进行中支付单时，余额支付应被互斥规则拒绝');
        } catch (ValidateException $e) {
            $this->assertSame(4104, $e->getCode(), '支付方式互斥业务码应为 4104');
            $this->assertStringContainsString('支付方式', $e->getMessage());
        }

        // 无副作用：未新建支付单，未创建余额账户/流水
        $this->assertSame(1, (int) Db::name('payment')->where('order_id', $order['id'])->count(), '不得新建支付单');
        $this->assertSame(1, (int) Db::name('payment')
            ->where('order_id', $order['id'])
            ->where('payment_channel', PaymentChannel::WECHAT->value)
            ->count(), '原微信支付单保持不变');
        $this->assertSame(0, (int) Db::name('customer_balance_account')->count(), '不得创建余额账户');
        $this->assertSame(0, (int) Db::name('customer_balance_transaction')->count(), '不得产生余额流水');
        $this->assertSame(0, (int) $this->freshOrder((int) $order['id'])['paid_amount_cent']);
    }

    /**
     * 用例2：同渠道重复发起允许——刷新原支付单参数，不新建支付单
     */
    public function testSameChannelRefreshAllowed(): void
    {
        $order = $this->seedOrder(['total_amount_cent' => self::PAY_AMOUNT]);

        $first = $this->service->createPayment(
            self::STORE_ID,
            (int) $order['id'],
            1,
            'JSAPI',
            ['idempotent_key' => "order_pay:{$order['order_no']}:wechat"]
        );

        $this->resetOrderToPendingPay((int) $order['id']);

        // 同渠道再次发起 → 走刷新支付参数路径
        $second = $this->service->createPayment(
            self::STORE_ID,
            (int) $order['id'],
            1,
            'JSAPI',
            ['idempotent_key' => "order_pay:{$order['order_no']}:wechat"]
        );

        $this->assertSame($first['payment_no'], $second['payment_no'], '同渠道刷新应复用原支付单');
        $this->assertArrayHasKey('wechat_params', $second, '刷新应返回新的支付参数');
        $this->assertSame(1, (int) Db::name('payment')->where('order_id', $order['id'])->count(), '不得新建支付单');

        $payment = Db::name('payment')->where('order_id', $order['id'])->find();
        $this->assertSame(PayStatus::PENDING->value, (int) $payment['pay_status']);
    }

    /**
     * 用例3：其他渠道已支付成功时，新渠道被拒（4104）
     */
    public function testOtherChannelSuccessBlocksNewChannel(): void
    {
        $order = $this->seedOrder(['total_amount_cent' => self::PAY_AMOUNT]);

        // 数据准备：该订单已有一张支付宝成功支付单
        $this->seedPayment([
            'order_id'        => $order['id'],
            'order_no'        => $order['order_no'],
            'payment_channel' => PaymentChannel::ALIPAY->value,
            'pay_amount_cent' => self::PAY_AMOUNT,
            'pay_status'      => PayStatus::SUCCESS->value,
            'transaction_id'  => 'ALI2026T10PAID001',
            'paid_at'         => date('Y-m-d H:i:s'),
        ]);

        try {
            $this->service->createPayment(
                self::STORE_ID,
                (int) $order['id'],
                1,
                'JSAPI',
                ['idempotent_key' => "order_pay:{$order['order_no']}:wechat"]
            );
            $this->fail('其他渠道已支付成功时，新渠道应被互斥规则拒绝');
        } catch (ValidateException $e) {
            $this->assertSame(4104, $e->getCode());
            $this->assertStringContainsString('支付方式', $e->getMessage());
        }

        // 未新建任何支付单
        $this->assertSame(1, (int) Db::name('payment')->where('order_id', $order['id'])->count());
    }
}
