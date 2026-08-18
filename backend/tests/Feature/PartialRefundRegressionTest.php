<?php

declare(strict_types=1);

namespace tests\Feature;

use app\common\enum\BalanceTxnType;
use app\common\enum\OrderStatus;
use app\common\enum\PayStatus;
use app\common\enum\PaymentChannel;
use app\common\service\BalanceAccountService;
use app\common\service\PaymentService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 部分退款回归 Feature 测试（评审 Warning 6 / 相关修复）
 *
 * 规则：
 * - 部分退款可多次执行，累计退款不得超过原支付金额；
 * - 部分退款时支付单保持 SUCCESS，满额才置 REFUNDED 终态；
 * - 已退满后再退、超剩余可退金额再退，均必须被拒。
 *
 * 路径：余额支付单 → BalanceAccountService::refundToBalance
 * （AdminFinanceController::refund 余额分支的底层实现，同一累计校验逻辑）。
 */
class PartialRefundRegressionTest extends FeatureTestCase
{
    private const PAY_AMOUNT = 20000;
    private const SEED_BALANCE = 30000;

    private PaymentService $paymentService;
    private BalanceAccountService $balanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = new PaymentService();
        $this->balanceService = new BalanceAccountService();
    }

    /**
     * 造一笔已余额支付成功的订单，返回 [order, payment_no, account_id]
     */
    private function createPaidBalanceOrder(): array
    {
        $order = $this->seedOrder(['total_amount_cent' => self::PAY_AMOUNT]);

        $account = $this->balanceService->getOrCreateAccount('store', self::STORE_ID);
        Db::name('customer_balance_account')
            ->where('id', $account['id'])
            ->update([
                'available_balance_cent' => self::SEED_BALANCE,
                'total_recharge_cent'    => self::SEED_BALANCE,
            ]);

        $result = $this->paymentService->createPayment(
            self::STORE_ID,
            (int) $order['id'],
            3, // API 渠道编码：3=余额
            '',
            ['idempotent_key' => "order_pay:{$order['order_no']}:balance"]
        );

        $this->assertSame(PaymentChannel::BALANCE->value, $result['pay_channel']);
        $this->assertSame(PayStatus::SUCCESS->value, (int) $result['pay_status']);
        $this->assertSame(
            OrderStatus::PAID_PENDING->value,
            (int) $this->freshOrder((int) $order['id'])['order_status']
        );

        return [$order, (string) $result['payment_no'], (int) $account['id']];
    }

    /**
     * 用例1：两次部分退款均成功，累计不超原金额，满额才置 REFUNDED，余额如数退回
     */
    public function testTwoPartialRefundsThenFullRefunded(): void
    {
        [$order, $paymentNo, $accountId] = $this->createPaidBalanceOrder();

        // 支付后余额 = 30000 - 20000 = 10000
        $this->assertSame(
            self::SEED_BALANCE - self::PAY_AMOUNT,
            (int) Db::name('customer_balance_account')->where('id', $accountId)->value('available_balance_cent')
        );

        // 第一次部分退款 8000：支付单保持 SUCCESS
        $r1 = $this->balanceService->refundToBalance($paymentNo, 8000, ['reason' => '部分退款1']);
        $this->assertSame(PayStatus::SUCCESS->value, (int) $r1['pay_status'], '部分退款后支付单应保持支付成功');
        $payment = Db::name('payment')->where('payment_no', $paymentNo)->find();
        $this->assertSame(8000, (int) $payment['refund_amount_cent'], '累计退款额应为 8000');
        $this->assertSame(PayStatus::SUCCESS->value, (int) $payment['pay_status']);

        // 第二次部分退款 12000：累计 20000 = 原金额 → REFUNDED
        $r2 = $this->balanceService->refundToBalance($paymentNo, 12000, ['reason' => '部分退款2']);
        $this->assertSame(PayStatus::REFUNDED->value, (int) $r2['pay_status'], '累计退满应置已退款终态');
        $payment = Db::name('payment')->where('payment_no', $paymentNo)->find();
        $this->assertSame(self::PAY_AMOUNT, (int) $payment['refund_amount_cent']);
        $this->assertSame(PayStatus::REFUNDED->value, (int) $payment['pay_status']);

        // 余额如数退回：10000 + 8000 + 12000 = 30000
        $this->assertSame(
            self::SEED_BALANCE,
            (int) Db::name('customer_balance_account')->where('id', $accountId)->value('available_balance_cent')
        );

        // 两笔退款流水（类型 3=退款）
        $refundTxnCount = (int) Db::name('customer_balance_transaction')
            ->where('account_id', $accountId)
            ->where('transaction_type', BalanceTxnType::REFUND->value)
            ->count();
        $this->assertSame(2, $refundTxnCount, '两次部分退款应各产生一条退款流水');

        // 已退满后再退必须被拒
        try {
            $this->balanceService->refundToBalance($paymentNo, 1, ['reason' => '超额退']);
            $this->fail('已全额退款的支付单不得再次退款');
        } catch (ValidateException $e) {
            $this->assertStringContainsString('全额退款', $e->getMessage());
        }

        // 拒绝后累计退款额不变
        $payment = Db::name('payment')->where('payment_no', $paymentNo)->find();
        $this->assertSame(self::PAY_AMOUNT, (int) $payment['refund_amount_cent']);
    }

    /**
     * 用例2：累计退款不得超过原金额——超剩余可退金额的部分退款被拒且无副作用
     */
    public function testRefundExceedingRemainingRejected(): void
    {
        [$order, $paymentNo, $accountId] = $this->createPaidBalanceOrder();

        $this->balanceService->refundToBalance($paymentNo, 8000, ['reason' => '部分退款']);

        $balanceBefore = (int) Db::name('customer_balance_account')
            ->where('id', $accountId)->value('available_balance_cent');

        try {
            $this->balanceService->refundToBalance($paymentNo, 13000, ['reason' => '超退']);
            $this->fail('累计退款超过原金额必须被拒');
        } catch (ValidateException $e) {
            $this->assertStringContainsString('原支付金额', $e->getMessage());
        }

        // 无副作用：累计退款额、余额、支付单状态均不变
        $payment = Db::name('payment')->where('payment_no', $paymentNo)->find();
        $this->assertSame(8000, (int) $payment['refund_amount_cent']);
        $this->assertSame(PayStatus::SUCCESS->value, (int) $payment['pay_status']);
        $this->assertSame(
            $balanceBefore,
            (int) Db::name('customer_balance_account')->where('id', $accountId)->value('available_balance_cent')
        );

        // 剩余可退金额内仍可继续退：12000 补齐满额 → REFUNDED
        $r = $this->balanceService->refundToBalance($paymentNo, 12000, ['reason' => '补齐退款']);
        $this->assertSame(PayStatus::REFUNDED->value, (int) $r['pay_status']);
        $payment = Db::name('payment')->where('payment_no', $paymentNo)->find();
        $this->assertSame(self::PAY_AMOUNT, (int) $payment['refund_amount_cent']);
    }
}
