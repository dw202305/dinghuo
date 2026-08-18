<?php

declare(strict_types=1);

namespace tests\Feature;

use app\common\enum\OrderStatus;
use app\common\enum\PayStatus;
use app\common\service\PaymentService;
use think\facade\Db;

/**
 * PAYING 死锁补偿回归 Feature 测试（真实 MySQL，评审 Critical 2）
 *
 * 场景：发起第三方支付后订单进入 PAYING；渠道侧失败/支付单卡死时，
 * 补偿逻辑必须将支付单置 FAILED，并把订单从 PAYING 回退 PENDING_PAY，
 * 随后可重新发起支付，杜绝订单永久卡死在"支付处理中"。
 *
 * 注入方式说明：callPayChannel 当前为不抛异常的 mock 骨架，无法在
 * createPayment 内直接注入渠道异常；本用例通过"支付单过期补偿"路径
 * （queryPaymentStatus → expirePendingPaymentIfNeeded → rollbackPayingToPending）
 * 触发与渠道调用失败补偿完全相同的回退方法，端到端验证回退+重新支付链路。
 */
class PayingRollbackCompensationTest extends FeatureTestCase
{
    private const PAY_AMOUNT = 20000;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaymentService();
    }

    /**
     * 用例1：渠道失败/支付单卡死 → 支付单 FAILED、订单回退待支付、可重新发起支付
     */
    public function testPayingRollbackToPendingAndCanRepay(): void
    {
        $order = $this->seedOrder(['total_amount_cent' => self::PAY_AMOUNT]);

        // 发起微信支付：订单推进 PAYING，支付单 PENDING
        $result = $this->service->createPayment(
            self::STORE_ID,
            (int) $order['id'],
            1,
            'JSAPI',
            ['idempotent_key' => "order_pay:{$order['order_no']}:wechat"]
        );
        $paymentNo = (string) $result['payment_no'];
        $this->assertSame(OrderStatus::PAYING->value, (int) $this->freshOrder((int) $order['id'])['order_status']);

        // 模拟渠道调用失败导致支付单卡死：将创建时间回拨超过拉起时效（1800s）
        Db::name('payment')
            ->where('payment_no', $paymentNo)
            ->update(['created_at' => date('Y-m-d H:i:s', time() - 7200)]);

        // 查询支付状态触发过期补偿：支付单置失败 + 订单回退
        $status = $this->service->queryPaymentStatus(self::STORE_ID, (int) $order['id']);

        $payment = Db::name('payment')->where('payment_no', $paymentNo)->find();
        $this->assertNotNull($payment);
        $this->assertSame(PayStatus::FAILED->value, (int) $payment['pay_status'], '卡死支付单应被置为失败态');
        $this->assertSame(PayStatus::FAILED->value, (int) $status['pay_status']);
        $this->assertSame(
            OrderStatus::PENDING_PAY->value,
            (int) $this->freshOrder((int) $order['id'])['order_status'],
            '订单必须从 PAYING 回退到 PENDING_PAY，不得卡死在支付中'
        );

        // 状态历史留有补偿回退记录（writeStatusHistory 正常落库）
        $history = Db::name('order_status_history')
            ->where('order_id', $order['id'])
            ->where('from_status', 'paying')
            ->where('to_status', 'pending_pay')
            ->where('role', 'system')
            ->find();
        $this->assertNotNull($history, 'PAYING→PENDING_PAY 补偿回退应写入状态历史');

        // 回退后可重新发起支付：新建支付单，订单再次进入 PAYING
        // （换新幂等键模拟门店重新拉起收银台；原失败单幂等键仍在，命中会回查原单）
        $retry = $this->service->createPayment(
            self::STORE_ID,
            (int) $order['id'],
            1,
            'JSAPI',
            ['idempotent_key' => "order_pay:{$order['order_no']}:wechat:retry1"]
        );
        $this->assertNotSame($paymentNo, $retry['payment_no'], '重新发起应生成新支付单而非复用失败单');
        $this->assertSame(OrderStatus::PAYING->value, (int) $this->freshOrder((int) $order['id'])['order_status']);
        $this->assertSame(2, (int) Db::name('payment')->where('order_id', $order['id'])->count());
    }

    /**
     * 用例2：未过期支付单不触发补偿（PAYING 保持，支付单保持待支付）
     */
    public function testFreshPendingPaymentNotCompensated(): void
    {
        $order = $this->seedOrder(['total_amount_cent' => self::PAY_AMOUNT]);

        $result = $this->service->createPayment(
            self::STORE_ID,
            (int) $order['id'],
            1,
            'JSAPI',
            ['idempotent_key' => "order_pay:{$order['order_no']}:wechat"]
        );
        $paymentNo = (string) $result['payment_no'];

        $status = $this->service->queryPaymentStatus(self::STORE_ID, (int) $order['id']);

        $this->assertSame(PayStatus::PENDING->value, (int) $status['pay_status'], '未过期支付单不得被提前置失败');
        $this->assertSame(OrderStatus::PAYING->value, (int) $this->freshOrder((int) $order['id'])['order_status']);

        $payment = Db::name('payment')->where('payment_no', $paymentNo)->find();
        $this->assertSame(PayStatus::PENDING->value, (int) $payment['pay_status']);
    }
}
