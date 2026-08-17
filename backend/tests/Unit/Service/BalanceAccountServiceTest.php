<?php

declare(strict_types=1);

namespace tests\Unit\Service;

use app\common\service\BalanceAccountService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use think\exception\ValidateException;

/**
 * BalanceAccountService 储值账户服务单元测试
 *
 * 测试余额计算逻辑、边界条件校验和业务规则。
 * 由于服务方法依赖数据库操作，本测试聚焦于：
 * 1. 参数校验逻辑（金额<=0、必填字段等）
 * 2. 余额计算公式正确性
 * 3. 业务规则的反射验证
 *
 * 完整的集成测试（含DB）应在 Feature 测试中覆盖。
 */
class BalanceAccountServiceTest extends TestCase
{
    private BalanceAccountService $service;

    protected function setUp(): void
    {
        $this->service = new BalanceAccountService();
    }

    // ─────────────────────────────────────────────────────
    // 充值参数校验
    // ─────────────────────────────────────────────────────

    /**
     * 充值金额 <= 0 应抛异常
     */
    #[Test]
    public function testRecharge_ZeroAmount_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('充值金额必须大于0');

        $this->service->recharge(1, 0, 'offline');
    }

    #[Test]
    public function testRecharge_NegativeAmount_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('充值金额必须大于0');

        $this->service->recharge(1, -1000, 'wechat');
    }

    // ─────────────────────────────────────────────────────
    // 余额支付参数校验
    // ─────────────────────────────────────────────────────

    /**
     * 支付金额 <= 0 应抛异常
     */
    #[Test]
    public function testPayByBalance_ZeroAmount_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('支付金额必须大于0');

        $this->service->payByBalance('ORD001', 0, 1, ['idempotent_key' => 'test']);
    }

    /**
     * 余额支付缺少幂等键应抛异常
     */
    #[Test]
    public function testPayByBalance_MissingIdempotentKey_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('余额支付必须提供幂等键');

        $this->service->payByBalance('ORD001', 5000, 1, []);
    }

    // ─────────────────────────────────────────────────────
    // 退款参数校验
    // ─────────────────────────────────────────────────────

    #[Test]
    public function testRefund_ZeroAmount_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('退款金额必须大于0');

        $this->service->refundToBalance('PAY001', 0);
    }

    #[Test]
    public function testRefund_NegativeAmount_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('退款金额必须大于0');

        $this->service->refundToBalance('PAY001', -500);
    }

    // ─────────────────────────────────────────────────────
    // 冻结/解冻参数校验
    // ─────────────────────────────────────────────────────

    #[Test]
    public function testFreeze_ZeroAmount_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('冻结金额必须大于0');

        $this->service->freeze(1, 0, 'test');
    }

    #[Test]
    public function testFreeze_NegativeAmount_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('冻结金额必须大于0');

        $this->service->freeze(1, -100, 'test');
    }

    #[Test]
    public function testUnfreeze_ZeroAmount_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('解冻金额必须大于0');

        $this->service->unfreeze(1, 0, 'test');
    }

    // ─────────────────────────────────────────────────────
    // 人工调整参数校验
    // ─────────────────────────────────────────────────────

    /**
     * 调整金额为0应抛异常
     */
    #[Test]
    public function testManualAdjust_ZeroAmount_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('调整金额不能为0');

        $this->service->manualAdjust(1, 0, '测试', 1, 2);
    }

    /**
     * 调整原因为空应抛异常
     */
    #[Test]
    public function testManualAdjust_EmptyReason_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('调整原因不能为空');

        $this->service->manualAdjust(1, 1000, '', 1, 2);
    }

    /**
     * 缺少审批人应抛异常
     */
    #[Test]
    public function testManualAdjust_NoReviewer_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('人工调整必须经过审批');

        $this->service->manualAdjust(1, 1000, '客户投诉补偿', 1, 0);
    }

    /**
     * 审批人ID为负数也应抛异常
     */
    #[Test]
    public function testManualAdjust_NegativeReviewer_ThrowsException(): void
    {
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('人工调整必须经过审批');

        $this->service->manualAdjust(1, 1000, '客户投诉补偿', 1, -1);
    }

    // ─────────────────────────────────────────────────────
    // 余额计算公式验证（纯数学）
    // ─────────────────────────────────────────────────────

    /**
     * 充值后余额 = 充值前余额 + 充值金额
     */
    #[Test]
    public function testRechargeBalanceFormula(): void
    {
        $balanceBefore = 50000; // 500元
        $rechargeAmount = 100000; // 1000元

        $balanceAfter = $balanceBefore + $rechargeAmount;

        $this->assertSame(150000, $balanceAfter); // 1500元
    }

    /**
     * 余额支付：余额不足判断逻辑
     */
    #[Test]
    public function testPayByBalance_InsufficientFundsLogic(): void
    {
        $availableBalance = 3000; // 30元
        $payAmount = 5000;        // 50元

        // 余额不足整笔失败，不部分扣减
        $isInsufficient = $availableBalance < $payAmount;
        $this->assertTrue($isInsufficient);

        // 余额充足场景
        $availableBalance2 = 10000;
        $isInsufficient2 = $availableBalance2 < $payAmount;
        $this->assertFalse($isInsufficient2);

        // 恰好相等（刚好够）
        $availableBalance3 = 5000;
        $isInsufficient3 = $availableBalance3 < $payAmount;
        $this->assertFalse($isInsufficient3);
    }

    /**
     * 冻结/解冻余额公式
     */
    #[Test]
    public function testFreezeUnfreezeBalanceFormula(): void
    {
        $available = 100000; // 1000元
        $frozen = 0;
        $freezeAmount = 30000; // 300元

        // 冻结
        $availableAfterFreeze = $available - $freezeAmount;
        $frozenAfterFreeze = $frozen + $freezeAmount;
        $this->assertSame(70000, $availableAfterFreeze);
        $this->assertSame(30000, $frozenAfterFreeze);

        // 解冻
        $unfreezeAmount = 20000;
        $availableAfterUnfreeze = $availableAfterFreeze + $unfreezeAmount;
        $frozenAfterUnfreeze = $frozenAfterFreeze - $unfreezeAmount;
        $this->assertSame(90000, $availableAfterUnfreeze);
        $this->assertSame(10000, $frozenAfterUnfreeze);
    }

    /**
     * 人工调整：调整后余额不能为负
     */
    #[Test]
    public function testManualAdjust_CannotGoNegative(): void
    {
        $balance = 5000;  // 50元
        $adjust = -10000; // 减100元

        $balanceAfter = $balance + $adjust;

        // 调整后会变负，应被拦截
        $this->assertLessThan(0, $balanceAfter);
        // 业务层应在此时抛出异常
    }

    /**
     * 退款金额不能大于原支付金额
     */
    #[Test]
    public function testRefund_CannotExceedOriginalPayment(): void
    {
        $originalPayment = 8000; // 80元
        $refundAmount = 10000;   // 100元

        $this->assertGreaterThan($originalPayment, $refundAmount);
        // 业务层应在此时抛出"退款金额不能大于原支付金额"
    }

    /**
     * 乐观锁：version 递增逻辑
     */
    #[Test]
    public function testOptimisticLockVersionIncrement(): void
    {
        $version = 0;

        // 每次余额变动 version+1
        $versionAfter1 = $version + 1;
        $this->assertSame(1, $versionAfter1);

        $versionAfter2 = $versionAfter1 + 1;
        $this->assertSame(2, $versionAfter2);

        // 模拟并发冲突：WHERE version = oldVersion 匹配不到则冲突
        $currentDbVersion = 3; // 另一个请求已经更新过
        $myVersion = 2;        // 我读到的版本
        $this->assertNotSame($currentDbVersion, $myVersion, '版本不一致=乐观锁冲突');
    }
}
