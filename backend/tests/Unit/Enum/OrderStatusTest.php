<?php

declare(strict_types=1);

namespace tests\Unit\Enum;

use app\common\enum\OrderStatus;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * OrderStatus 枚举单元测试
 *
 * 验证所有状态枚举的标签、分组判断方法的正确性。
 * 纯逻辑测试，不依赖数据库。
 */
class OrderStatusTest extends TestCase
{
    /**
     * 所有状态枚举都有非空 label
     */
    #[Test]
    public function testAllStatusesHaveLabels(): void
    {
        foreach (OrderStatus::cases() as $case) {
            $label = $case->label();
            $this->assertNotEmpty($label, "状态 {$case->name}({$case->value}) 缺少 label");
            $this->assertIsString($label);
        }
    }

    /**
     * 所有 18 个状态都已定义
     */
    #[Test]
    public function testTotalCaseCount(): void
    {
        $this->assertCount(18, OrderStatus::cases());
    }

    /**
     * isPreProduction：预生产阶段状态正确
     */
    #[Test]
    public function testIsPreProduction(): void
    {
        $preProduction = [
            OrderStatus::DRAFT,
            OrderStatus::PENDING_PAY,
            OrderStatus::PAYING,
            OrderStatus::PAID_PENDING,
            OrderStatus::NEED_CONFIRM,
            OrderStatus::NEED_SUPPLEMENT,
            OrderStatus::APPROVED,
        ];

        $nonPreProduction = [
            OrderStatus::PRODUCING,
            OrderStatus::QC,
            OrderStatus::PENDING_SHIP,
            OrderStatus::PARTIAL_SHIP,
            OrderStatus::SHIPPED,
            OrderStatus::RECEIVED,
            OrderStatus::COMPLETED,
            OrderStatus::AFTER_SALE,
            OrderStatus::CANCELLED,
            OrderStatus::REFUNDING,
            OrderStatus::REFUNDED,
        ];

        foreach ($preProduction as $status) {
            $this->assertTrue(
                $status->isPreProduction(),
                "{$status->name} 应在预生产阶段"
            );
        }

        foreach ($nonPreProduction as $status) {
            $this->assertFalse(
                $status->isPreProduction(),
                "{$status->name} 不应在预生产阶段"
            );
        }
    }

    /**
     * canCancel：只有草稿、待支付、已支付待审核可取消
     */
    #[Test]
    public function testCanCancel(): void
    {
        $cancellable = [
            OrderStatus::DRAFT,
            OrderStatus::PENDING_PAY,
            OrderStatus::PAID_PENDING,
        ];

        $nonCancellable = [
            OrderStatus::PAYING,
            OrderStatus::NEED_CONFIRM,
            OrderStatus::NEED_SUPPLEMENT,
            OrderStatus::APPROVED,
            OrderStatus::PRODUCING,
            OrderStatus::QC,
            OrderStatus::PENDING_SHIP,
            OrderStatus::PARTIAL_SHIP,
            OrderStatus::SHIPPED,
            OrderStatus::RECEIVED,
            OrderStatus::COMPLETED,
            OrderStatus::AFTER_SALE,
            OrderStatus::CANCELLED,
            OrderStatus::REFUNDING,
            OrderStatus::REFUNDED,
        ];

        foreach ($cancellable as $status) {
            $this->assertTrue(
                $status->canCancel(),
                "{$status->name} 应可取消"
            );
        }

        foreach ($nonCancellable as $status) {
            $this->assertFalse(
                $status->canCancel(),
                "{$status->name} 不应可取消"
            );
        }
    }

    /**
     * isFinal：已完成、已取消、已退款为终态
     */
    #[Test]
    public function testIsFinal(): void
    {
        $finals = [
            OrderStatus::COMPLETED,
            OrderStatus::CANCELLED,
            OrderStatus::REFUNDED,
        ];

        $nonFinals = array_filter(
            OrderStatus::cases(),
            fn(OrderStatus $s) => !in_array($s, $finals)
        );

        foreach ($finals as $status) {
            $this->assertTrue(
                $status->isFinal(),
                "{$status->name} 应为终态"
            );
        }

        foreach ($nonFinals as $status) {
            $this->assertFalse(
                $status->isFinal(),
                "{$status->name} 不应为终态"
            );
        }
    }

    /**
     * isPaid：已支付（含后续状态）正确返回
     */
    #[Test]
    public function testIsPaid(): void
    {
        $paidStatuses = [
            OrderStatus::PAID_PENDING,
            OrderStatus::NEED_CONFIRM,
            OrderStatus::NEED_SUPPLEMENT,
            OrderStatus::APPROVED,
            OrderStatus::PRODUCING,
            OrderStatus::QC,
            OrderStatus::PENDING_SHIP,
            OrderStatus::PARTIAL_SHIP,
            OrderStatus::SHIPPED,
            OrderStatus::RECEIVED,
            OrderStatus::COMPLETED,
        ];

        $unpaidStatuses = [
            OrderStatus::DRAFT,
            OrderStatus::PENDING_PAY,
            OrderStatus::PAYING,
            OrderStatus::AFTER_SALE,
            OrderStatus::CANCELLED,
            OrderStatus::REFUNDING,
            OrderStatus::REFUNDED,
        ];

        foreach ($paidStatuses as $status) {
            $this->assertTrue(
                $status->isPaid(),
                "{$status->name} 应为已支付"
            );
        }

        foreach ($unpaidStatuses as $status) {
            $this->assertFalse(
                $status->isPaid(),
                "{$status->name} 不应为已支付"
            );
        }
    }

    /**
     * canAfterSale：只有已发货、已签收、已完成可售后
     */
    #[Test]
    public function testCanAfterSale(): void
    {
        $afterSaleable = [
            OrderStatus::SHIPPED,
            OrderStatus::RECEIVED,
            OrderStatus::COMPLETED,
        ];

        $nonAfterSaleable = array_filter(
            OrderStatus::cases(),
            fn(OrderStatus $s) => !in_array($s, $afterSaleable)
        );

        foreach ($afterSaleable as $status) {
            $this->assertTrue(
                $status->canAfterSale(),
                "{$status->name} 应可进入售后"
            );
        }

        foreach ($nonAfterSaleable as $status) {
            $this->assertFalse(
                $status->canAfterSale(),
                "{$status->name} 不应可进入售后"
            );
        }
    }

    /**
     * isTerminal：与 isFinal 结果一致
     */
    #[Test]
    public function testIsTerminal_ConsistentWithIsFinal(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertSame(
                $status->isFinal(),
                $status->isTerminal(),
                "{$status->name}: isTerminal() 应与 isFinal() 一致"
            );
        }
    }

    /**
     * 枚举值连续性检查（1~18）
     */
    #[Test]
    public function testEnumValuesAreSequential(): void
    {
        $values = array_map(fn(OrderStatus $s) => $s->value, OrderStatus::cases());
        sort($values);
        $this->assertSame(range(1, 18), $values);
    }
}
