<?php

declare(strict_types=1);

namespace tests\Unit\Service;

use app\common\enum\OrderStatus;
use app\common\service\OrderStateService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * OrderStateService 状态机单元测试
 *
 * 测试状态转换矩阵的完整性和逻辑正确性。
 * 通过反射访问私有属性（转换矩阵）和私有方法，不依赖数据库。
 */
class OrderStateServiceTest extends TestCase
{
    private OrderStateService $service;
    private \ReflectionProperty $transitionsProp;

    protected function setUp(): void
    {
        $this->service = new OrderStateService();

        // 反射访问私有 transitions 属性
        $this->transitionsProp = new \ReflectionProperty(OrderStateService::class, 'transitions');
        $this->transitionsProp->setAccessible(true);
    }

    /**
     * 获取转换矩阵
     */
    private function getTransitions(): array
    {
        return $this->transitionsProp->getValue($this->service);
    }

    /**
     * 查找指定 from→to 的转换规则
     */
    private function findTransition(OrderStatus $from, OrderStatus $to): ?array
    {
        $transitions = $this->getTransitions();
        foreach ($transitions as $transition) {
            if ($transition['from']->value === $from->value && $transition['to']->value === $to->value) {
                return $transition;
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────────────
    // 合法转换
    // ─────────────────────────────────────────────────────

    #[Test]
    public function testValidTransition_DraftToPendingPay(): void
    {
        $transition = $this->findTransition(OrderStatus::DRAFT, OrderStatus::PENDING_PAY);

        $this->assertNotNull($transition, 'DRAFT → PENDING_PAY 转换应存在');
        $this->assertSame('提交订单', $transition['action']);
        $this->assertContains('store', $transition['roles']);
        $this->assertContains('admin', $transition['roles']);
    }

    #[Test]
    public function testValidTransition_PaidPendingToApproved(): void
    {
        $transition = $this->findTransition(OrderStatus::PAID_PENDING, OrderStatus::APPROVED);

        $this->assertNotNull($transition, 'PAID_PENDING → APPROVED 转换应存在');
        $this->assertSame('审核通过', $transition['action']);
        $this->assertContains('admin', $transition['roles']);
        $this->assertContains('technical', $transition['roles']);
    }

    #[Test]
    public function testValidTransition_ApprovedToProducing(): void
    {
        $transition = $this->findTransition(OrderStatus::APPROVED, OrderStatus::PRODUCING);

        $this->assertNotNull($transition, 'APPROVED → PRODUCING 转换应存在');
        $this->assertSame('开始生产', $transition['action']);
        $this->assertContains('admin', $transition['roles']);
        $this->assertContains('production', $transition['roles']);
    }

    #[Test]
    public function testValidTransition_ShippedToReceived(): void
    {
        $transition = $this->findTransition(OrderStatus::SHIPPED, OrderStatus::RECEIVED);

        $this->assertNotNull($transition, 'SHIPPED → RECEIVED 转换应存在');
        $this->assertSame('签收', $transition['action']);
        $this->assertContains('store', $transition['roles']);
    }

    #[Test]
    public function testValidTransition_ReceivedToCompleted(): void
    {
        $transition = $this->findTransition(OrderStatus::RECEIVED, OrderStatus::COMPLETED);

        $this->assertNotNull($transition, 'RECEIVED → COMPLETED 转换应存在');
        $this->assertSame('完成', $transition['action']);
    }

    // ─────────────────────────────────────────────────────
    // 非法转换
    // ─────────────────────────────────────────────────────

    /**
     * DRAFT → PRODUCING 非法（跳过多个中间步骤）
     */
    #[Test]
    public function testInvalidTransition_DraftToProducing(): void
    {
        $transition = $this->findTransition(OrderStatus::DRAFT, OrderStatus::PRODUCING);
        $this->assertNull($transition, 'DRAFT → PRODUCING 不应直接存在');
    }

    /**
     * DRAFT → SHIPPED 非法
     */
    #[Test]
    public function testInvalidTransition_DraftToShipped(): void
    {
        $transition = $this->findTransition(OrderStatus::DRAFT, OrderStatus::SHIPPED);
        $this->assertNull($transition, 'DRAFT → SHIPPED 不应直接存在');
    }

    /**
     * PENDING_PAY → COMPLETED 非法
     */
    #[Test]
    public function testInvalidTransition_PendingPayToCompleted(): void
    {
        $transition = $this->findTransition(OrderStatus::PENDING_PAY, OrderStatus::COMPLETED);
        $this->assertNull($transition, 'PENDING_PAY → COMPLETED 不应直接存在');
    }

    // ─────────────────────────────────────────────────────
    // 终态不可逆
    // ─────────────────────────────────────────────────────

    /**
     * CANCELLED 是终态，不可再转换到任何其他状态
     */
    #[Test]
    public function testInvalidTransition_CancelledToAny(): void
    {
        $transitions = $this->getTransitions();

        foreach ($transitions as $t) {
            $this->assertNotSame(
                OrderStatus::CANCELLED->value,
                $t['from']->value,
                "CANCELLED → {$t['to']->name} 不应存在（CANCELLED是终态）"
            );
        }
    }

    /**
     * COMPLETED 只能转到 AFTER_SALE（售后），不能转到其他状态
     */
    #[Test]
    public function testTransition_CompletedOnlyToAfterSale(): void
    {
        $transitions = $this->getTransitions();
        $completedTargets = [];

        foreach ($transitions as $t) {
            if ($t['from']->value === OrderStatus::COMPLETED->value) {
                $completedTargets[] = $t['to'];
            }
        }

        $this->assertCount(1, $completedTargets, 'COMPLETED 应只有一个出向转换');
        $this->assertSame(OrderStatus::AFTER_SALE->value, $completedTargets[0]->value);
    }

    /**
     * REFUNDED 是终态，无任何出向转换
     */
    #[Test]
    public function testInvalidTransition_RefundedToAny(): void
    {
        $transitions = $this->getTransitions();

        foreach ($transitions as $t) {
            $this->assertNotSame(
                OrderStatus::REFUNDED->value,
                $t['from']->value,
                "REFUNDED → {$t['to']->name} 不应存在（REFUNDED是终态）"
            );
        }
    }

    // ─────────────────────────────────────────────────────
    // 角色校验
    // ─────────────────────────────────────────────────────

    /**
     * 支付成功回调只有 system 角色可以执行
     */
    #[Test]
    public function testTransitionRequiresCorrectRole_PaymentCallback(): void
    {
        $transition = $this->findTransition(OrderStatus::PAYING, OrderStatus::PAID_PENDING);

        $this->assertNotNull($transition);
        $this->assertContains('system', $transition['roles']);
        $this->assertNotContains('store', $transition['roles'], 'store 不应能执行支付回调');
        $this->assertNotContains('admin', $transition['roles'], 'admin 不应能直接执行支付回调');
    }

    /**
     * 生产排产只有 admin/production 可以执行
     */
    #[Test]
    public function testTransitionRequiresCorrectRole_Production(): void
    {
        $transition = $this->findTransition(OrderStatus::APPROVED, OrderStatus::PRODUCING);

        $this->assertNotNull($transition);
        $this->assertContains('admin', $transition['roles']);
        $this->assertContains('production', $transition['roles']);
        $this->assertNotContains('store', $transition['roles'], 'store 不应能触发排产');
    }

    /**
     * 退款操作只有 admin/finance 可以执行
     */
    #[Test]
    public function testTransitionRequiresCorrectRole_Refund(): void
    {
        $transition = $this->findTransition(OrderStatus::AFTER_SALE, OrderStatus::REFUNDING);

        $this->assertNotNull($transition);
        $this->assertContains('admin', $transition['roles']);
        $this->assertContains('finance', $transition['roles']);
        $this->assertNotContains('store', $transition['roles']);
    }

    // ─────────────────────────────────────────────────────
    // 转换矩阵完整性
    // ─────────────────────────────────────────────────────

    /**
     * 所有转换规则的 from/to 都是有效的 OrderStatus 枚举值
     */
    #[Test]
    public function testAllTransitionsUseValidStatuses(): void
    {
        $transitions = $this->getTransitions();
        $validValues = array_map(fn(OrderStatus $s) => $s->value, OrderStatus::cases());

        foreach ($transitions as $key => $t) {
            $this->assertContains(
                $t['from']->value, $validValues,
                "转换 {$key} 的 from 状态无效"
            );
            $this->assertContains(
                $t['to']->value, $validValues,
                "转换 {$key} 的 to 状态无效"
            );
        }
    }

    /**
     * 所有转换规则都有 action 字段
     */
    #[Test]
    public function testAllTransitionsHaveAction(): void
    {
        $transitions = $this->getTransitions();

        foreach ($transitions as $key => $t) {
            $this->assertArrayHasKey('action', $t, "转换 {$key} 缺少 action 字段");
            $this->assertNotEmpty($t['action'], "转换 {$key} 的 action 为空");
        }
    }

    /**
     * 所有转换规则都有 roles 字段且非空
     */
    #[Test]
    public function testAllTransitionsHaveRoles(): void
    {
        $transitions = $this->getTransitions();

        foreach ($transitions as $key => $t) {
            $this->assertArrayHasKey('roles', $t, "转换 {$key} 缺少 roles 字段");
            $this->assertNotEmpty($t['roles'], "转换 {$key} 的 roles 为空");
        }
    }

    /**
     * 从 DRAFT 出发可达的状态集合
     */
    #[Test]
    public function testDraftReachableStates(): void
    {
        $transitions = $this->getTransitions();
        $reachable = [];

        foreach ($transitions as $t) {
            if ($t['from']->value === OrderStatus::DRAFT->value) {
                $reachable[] = $t['to']->value;
            }
        }

        // DRAFT 可以转到 PENDING_PAY 和 CANCELLED
        $this->assertContains(OrderStatus::PENDING_PAY->value, $reachable);
        $this->assertContains(OrderStatus::CANCELLED->value, $reachable);
    }

    /**
     * 子单生产状态枚举定义完整
     */
    #[Test]
    public function testItemProductionStatusDefined(): void
    {
        $statuses = OrderStateService::ITEM_PRODUCTION_STATUS;

        $this->assertCount(5, $statuses);
        $this->assertSame('待排产', $statuses[0]);
        $this->assertSame('生产中', $statuses[1]);
        $this->assertSame('质检中', $statuses[2]);
        $this->assertSame('质检通过', $statuses[3]);
        $this->assertSame('已发货', $statuses[4]);
    }

    /**
     * 完整流程路径可达性验证：DRAFT → ... → COMPLETED
     */
    #[Test]
    public function testHappyPathReachability(): void
    {
        // 定义正常下单到完成的完整路径
        $happyPath = [
            OrderStatus::DRAFT,
            OrderStatus::PENDING_PAY,
            OrderStatus::PAYING,
            OrderStatus::PAID_PENDING,
            OrderStatus::APPROVED,
            OrderStatus::PRODUCING,
            OrderStatus::QC,
            OrderStatus::PENDING_SHIP,
            OrderStatus::SHIPPED,
            OrderStatus::RECEIVED,
            OrderStatus::COMPLETED,
        ];

        // 验证相邻状态间的转换都存在
        for ($i = 0; $i < count($happyPath) - 1; $i++) {
            $from = $happyPath[$i];
            $to = $happyPath[$i + 1];
            $transition = $this->findTransition($from, $to);

            $this->assertNotNull(
                $transition,
                "Happy path: {$from->name} → {$to->name} 转换缺失"
            );
        }
    }
}
