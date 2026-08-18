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
 * 支付回调金额不一致资金安全 Feature 测试（真实 MySQL）
 *
 * 回调金额 ≠ 支付单金额（或缺失）时：
 * - 拒绝入账：支付单置失败态（pay_status=2）、第三方流水号不落库；
 * - 订单状态与金额保持原样；
 * - 回调原文保留在 notify_content 供对账排查；
 * - 被拒的支付单为终态，后续正确金额的回调也不得"复活"入账。
 */
class PaymentAmountMismatchTest extends FeatureTestCase
{
    private const PAY_AMOUNT = 20000; // 支付单金额 200.00 元

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaymentService();
    }

    /**
     * 构造签名回调报文（金额可选）
     */
    private function makeSignedNotify(string $paymentNo, string $transactionId, ?int $payerTotalCent): array
    {
        $verifier = new MockVerifier();

        $payload = [
            'out_trade_no'   => $paymentNo,
            'transaction_id' => $transactionId,
        ];
        if ($payerTotalCent !== null) {
            $payload['amount'] = ['payer_total' => $payerTotalCent];
        }

        $rawBody = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = [MockVerifier::SIGN_HEADER => $verifier->sign($rawBody)];
        $this->assertTrue($verifier->verify($headers, $rawBody));

        return $verifier->parse($rawBody);
    }

    /**
     * 造"待支付订单 + 待支付微信支付单"现场
     *
     * @return array{order: array, payment: array}
     */
    private function makeScene(): array
    {
        $order   = $this->seedOrder(['total_amount_cent' => self::PAY_AMOUNT]);
        $payment = $this->seedPayment([
            'order_id'        => $order['id'],
            'order_no'        => $order['order_no'],
            'payment_channel' => PaymentChannel::WECHAT->value,
            'pay_amount_cent' => self::PAY_AMOUNT,
        ]);

        return ['order' => $order, 'payment' => $payment];
    }

    /**
     * 断言"拒绝入账"后的完整现场：支付单失败、订单原样、原文保留
     */
    private function assertRejectedScene(array $order, array $payment): void
    {
        $paymentFinal = $this->freshPayment((int) $payment['id']);
        $orderFinal   = $this->freshOrder((int) $order['id']);

        // 支付单：置失败态，不落第三方流水号，不落支付时间
        $this->assertSame(PayStatus::FAILED->value, (int) $paymentFinal['pay_status'], '金额不一致应置支付单失败态');
        $this->assertNull($paymentFinal['transaction_id'], '拒绝入账不得写入第三方流水号');
        $this->assertNull($paymentFinal['paid_at']);

        // 回调原文保留（供对账排查）
        $this->assertNotEmpty($paymentFinal['notify_content'], '拒绝入账应保留回调原文');
        $decoded = json_decode((string) $paymentFinal['notify_content'], true);
        $this->assertSame($payment['payment_no'], $decoded['out_trade_no'] ?? null);

        // 订单：状态与金额保持原样，未发生任何状态迁移
        $this->assertSame(OrderStatus::PENDING_PAY->value, (int) $orderFinal['order_status'], '订单状态不得变化');
        $this->assertSame(0, (int) $orderFinal['paid_amount_cent'], '订单实付不得变化');
        $this->assertSame(PaymentStatus::UNPAID->value, (int) $orderFinal['payment_status']);
        $this->assertNull($orderFinal['paid_at']);
        $this->assertSame(0, (int) Db::name('order_status_history')->where('order_id', $order['id'])->count(), '拒绝入账不得产生状态迁移');
    }

    /**
     * 用例1：回调金额（199.99 元）≠ 支付单金额（200.00 元）→ 拒绝入账
     */
    public function testMismatchAmountRejectsCredit(): void
    {
        $scene  = $this->makeScene();
        $notify = $this->makeSignedNotify($scene['payment']['payment_no'], 'WX4200T10MISMATCH', self::PAY_AMOUNT - 1);

        $this->assertFalse($this->service->handleWechatNotify($notify), '金额不一致必须拒绝入账');

        $this->assertRejectedScene($scene['order'], $scene['payment']);
    }

    /**
     * 用例2：回调金额高于支付单金额同样拒绝（防伪造多付入账）
     */
    public function testHigherAmountAlsoRejectsCredit(): void
    {
        $scene  = $this->makeScene();
        $notify = $this->makeSignedNotify($scene['payment']['payment_no'], 'WX4200T10HIGHER', self::PAY_AMOUNT + 100);

        $this->assertFalse($this->service->handleWechatNotify($notify));

        $this->assertRejectedScene($scene['order'], $scene['payment']);
    }

    /**
     * 用例3：回调金额缺失 → 拒绝入账
     */
    public function testMissingAmountRejectsCredit(): void
    {
        $scene  = $this->makeScene();
        $notify = $this->makeSignedNotify($scene['payment']['payment_no'], 'WX4200T10MISSING', null);

        $this->assertFalse($this->service->handleWechatNotify($notify));

        $this->assertRejectedScene($scene['order'], $scene['payment']);
    }

    /**
     * 用例4：被拒（失败终态）的支付单，后续正确金额回调也不得复活入账
     */
    public function testFailedPaymentCannotBeRevivedByCorrectAmount(): void
    {
        $scene = $this->makeScene();

        // 第一次：金额不一致 → 拒绝，支付单置失败态
        $badNotify = $this->makeSignedNotify($scene['payment']['payment_no'], 'WX4200T10BAD', self::PAY_AMOUNT - 1);
        $this->assertFalse($this->service->handleWechatNotify($badNotify));

        $paymentRejected = $this->freshPayment((int) $scene['payment']['id']);
        $this->assertSame(PayStatus::FAILED->value, (int) $paymentRejected['pay_status']);

        // 第二次：正确金额回调 → 失败终态一律拒绝入账
        $goodNotify = $this->makeSignedNotify($scene['payment']['payment_no'], 'WX4200T10GOOD', self::PAY_AMOUNT);
        $this->assertFalse($this->service->handleWechatNotify($goodNotify), '失败终态支付单不得被后续回调复活');

        $paymentFinal = $this->freshPayment((int) $scene['payment']['id']);
        $orderFinal   = $this->freshOrder((int) $scene['order']['id']);

        $this->assertSame(PayStatus::FAILED->value, (int) $paymentFinal['pay_status']);
        $this->assertNull($paymentFinal['transaction_id']);
        $this->assertSame(OrderStatus::PENDING_PAY->value, (int) $orderFinal['order_status']);
        $this->assertSame(0, (int) $orderFinal['paid_amount_cent']);

        // notify_content 保留的是首次被拒时的回调原文（终态拒绝后不再改写），仍供对账
        $decoded = json_decode((string) $paymentFinal['notify_content'], true);
        $this->assertSame(self::PAY_AMOUNT - 1, $decoded['amount']['payer_total'] ?? null);
    }
}
