<?php

declare(strict_types=1);

namespace tests\Unit\Service;

use app\common\enum\PaymentChannel;
use app\common\service\PaymentService;
use app\common\service\pay\MockVerifier;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCase;
use think\exception\ValidateException;

/**
 * 支付回调资金安全单元测试（Mock 级）
 *
 * 不依赖真实数据库与外部支付服务，聚焦支付安全的纯逻辑决策：
 * 1. 重复回调只入账一次（幂等/终态决策）
 * 2. 金额不一致拒绝入账（回调金额解析 + 校验）
 * 3. 支付方式互斥（同单已有进行中支付单时新渠道被拒）
 * 4. MockVerifier 约定签名串校验（验签骨架）
 *
 * 依赖 DB 的完整链路（回调事务、状态机落库）在 Feature 测试中覆盖。
 */
class PaymentCallbackSafetyTest extends TestCase
{
    private PaymentService $service;

    protected function setUp(): void
    {
        $this->service = new PaymentService();
    }

    // ─────────────────────────────────────────────────────
    // 用例1：重复回调只入账一次
    // ─────────────────────────────────────────────────────

    /**
     * 首次回调正常处理；重复回调（已支付）幂等返回，绝不重复入账；
     * 失败/已退款等终态支付单的回调一律拒绝入账。
     */
    #[Test]
    public function testDuplicateNotify_CreditedOnlyOnce(): void
    {
        // 首次回调：支付单待支付 → 正常处理
        $this->assertSame('process', $this->service->notifyDecision(['pay_status' => 0]));

        // 重复回调：支付单已支付 → 幂等成功，不重复入账
        $this->assertSame('idempotent_success', $this->service->notifyDecision(['pay_status' => 1]));

        // 失败支付单（如金额不一致被阻断后）的回调 → 拒绝入账
        $this->assertSame('reject', $this->service->notifyDecision(['pay_status' => 2]));

        // 已退款支付单的回调 → 拒绝入账
        $this->assertSame('reject', $this->service->notifyDecision(['pay_status' => 3]));

        // 幂等语义模拟：同一回调两次到达，第二次命中终态不再处理
        $paymentAfterFirstCredit = ['pay_status' => 1, 'payment_no' => 'PAY20260818000001'];
        $this->assertSame('idempotent_success', $this->service->notifyDecision($paymentAfterFirstCredit));
    }

    // ─────────────────────────────────────────────────────
    // 用例2：金额不一致拒绝入账
    // ─────────────────────────────────────────────────────

    /**
     * 回调金额解析正确（整数分，不走 float）；
     * 金额缺失或与支付单金额不一致一律拒绝入账。
     */
    #[Test]
    public function testAmountMismatch_RejectCredit(): void
    {
        // 微信回调金额解析（单位：分）
        $this->assertSame(
            10000,
            $this->service->extractNotifyAmountCent(['amount' => ['payer_total' => 10000]], 'wechat')
        );
        // 微信兼容 amount.total
        $this->assertSame(
            8800,
            $this->service->extractNotifyAmountCent(['amount' => ['total' => 8800]], 'wechat')
        );

        // 支付宝回调金额解析（元 → 分，字符串整数化）
        $this->assertSame(10000, $this->service->extractNotifyAmountCent(['total_amount' => '100.00'], 'alipay'));
        $this->assertSame(1, $this->service->extractNotifyAmountCent(['total_amount' => '0.01'], 'alipay'));
        $this->assertSame(99990, $this->service->extractNotifyAmountCent(['total_amount' => '999.9'], 'alipay'));
        $this->assertSame(12300, $this->service->extractNotifyAmountCent(['total_amount' => '123'], 'alipay'));

        // 金额一致才允许入账
        $this->assertTrue($this->service->isNotifyAmountValid(10000, 10000));

        // 金额不一致 → 拒绝入账（支付单将被置失败态）
        $this->assertFalse($this->service->isNotifyAmountValid(9999, 10000));
        $this->assertFalse($this->service->isNotifyAmountValid(10001, 10000));

        // 回调金额缺失 → 拒绝入账
        $this->assertFalse($this->service->isNotifyAmountValid(null, 10000));
        $this->assertNull($this->service->extractNotifyAmountCent([], 'wechat'));
        $this->assertNull($this->service->extractNotifyAmountCent([], 'alipay'));
        $this->assertNull($this->service->extractNotifyAmountCent(['total_amount' => 'abc'], 'alipay'));

        // 支付单金额非法（<=0）→ 拒绝入账
        $this->assertFalse($this->service->isNotifyAmountValid(0, 0));
        $this->assertFalse($this->service->isNotifyAmountValid(100, -1));
    }

    // ─────────────────────────────────────────────────────
    // 用例3：支付方式互斥
    // ─────────────────────────────────────────────────────

    /**
     * 同单已有其他渠道进行中（pay_status=0）或已成功（pay_status=1）的支付单时，
     * 新渠道一律被拒（PRD 4.9.4）；同渠道刷新与失败单切换不受限。
     * 批次2c：渠道字段对齐 deploy lj_payment.payment_channel（balance/wechat/alipay）。
     */
    #[Test]
    public function testPayChannelMutualExclusion_RejectNewChannelWhenProcessing(): void
    {
        // 已有进行中微信支付单，新支付宝渠道被拒
        try {
            $this->service->assertPayChannelExclusive(
                [['payment_channel' => PaymentChannel::WECHAT->value, 'pay_status' => 0]],
                PaymentChannel::ALIPAY
            );
            $this->fail('同单已有其他渠道进行中支付单时应阻断新渠道');
        } catch (ValidateException $e) {
            $this->assertSame(4104, $e->getCode());
        }

        // 已有其他渠道成功支付单，新渠道被拒
        try {
            $this->service->assertPayChannelExclusive(
                [['payment_channel' => PaymentChannel::ALIPAY->value, 'pay_status' => 1]],
                PaymentChannel::WECHAT
            );
            $this->fail('其他渠道已支付成功时应阻断新渠道');
        } catch (ValidateException $e) {
            $this->assertSame(4104, $e->getCode());
        }

        // 余额渠道进行中支付单同样阻断微信渠道
        try {
            $this->service->assertPayChannelExclusive(
                [['payment_channel' => PaymentChannel::BALANCE->value, 'pay_status' => 0]],
                PaymentChannel::WECHAT
            );
            $this->fail('余额支付进行中时应阻断微信渠道');
        } catch (ValidateException $e) {
            $this->assertSame(4104, $e->getCode());
        }

        // 同渠道进行中支付单不构成阻断（走刷新支付参数）
        $this->service->assertPayChannelExclusive(
            [['payment_channel' => PaymentChannel::WECHAT->value, 'pay_status' => 0]],
            PaymentChannel::WECHAT
        );

        // 失败（2）支付单不阻断切换渠道
        $this->service->assertPayChannelExclusive(
            [['payment_channel' => PaymentChannel::WECHAT->value, 'pay_status' => 2]],
            PaymentChannel::ALIPAY
        );

        // 已退款（3）支付单不阻断切换渠道
        $this->service->assertPayChannelExclusive(
            [['payment_channel' => PaymentChannel::WECHAT->value, 'pay_status' => 3]],
            PaymentChannel::ALIPAY
        );
    }

    // ─────────────────────────────────────────────────────
    // 用例4：MockVerifier 验签骨架
    // ─────────────────────────────────────────────────────

    /**
     * 约定签名串：X-Mock-Sign = HMAC-SHA256(rawBody, secret)。
     * 签名正确放行，篡改/错签/缺签一律拒绝。
     */
    #[Test]
    public function testMockVerifier_SignatureCheck(): void
    {
        $verifier = new MockVerifier();
        $rawBody = (string) json_encode([
            'out_trade_no'   => 'PAY20260818000001',
            'transaction_id' => 'WX4200001234',
            'amount'         => ['payer_total' => 10000],
        ], JSON_UNESCAPED_UNICODE);

        // 正确签名放行
        $headers = [MockVerifier::SIGN_HEADER => $verifier->sign($rawBody)];
        $this->assertTrue($verifier->verify($headers, $rawBody));

        // 请求体被篡改 → 拒绝
        $this->assertFalse($verifier->verify($headers, $rawBody . 'tampered'));

        // 错误签名 → 拒绝
        $this->assertFalse($verifier->verify([MockVerifier::SIGN_HEADER => 'bad-sign'], $rawBody));

        // 缺少签名头 → 拒绝
        $this->assertFalse($verifier->verify([], $rawBody));

        // 头名称大小写不敏感
        $this->assertTrue($verifier->verify(['x-mock-sign' => $verifier->sign($rawBody)], $rawBody));

        // parse 还原回调参数
        $data = $verifier->parse($rawBody);
        $this->assertSame('PAY20260818000001', $data['out_trade_no']);
        $this->assertSame(10000, $data['amount']['payer_total']);

        // 不同密钥的验签器互不认签
        $otherVerifier = new MockVerifier('another-secret');
        $this->assertFalse($otherVerifier->verify($headers, $rawBody));
    }
}
