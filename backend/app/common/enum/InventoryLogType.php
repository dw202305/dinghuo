<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 库存流水变化类型枚举
 *
 * 对应 deploy lj_inventory_log.log_type 字段注释：
 * '变化类型：1采购入账 2订单锁定 3支付核销 4取消释放 5退款退回 6售后更换 7人工调整 8门店调拨'
 * （批次2a逐项核对 deploy/mysql/init.sql）。
 *
 * @see deploy/mysql/init.sql lj_inventory_log
 */
enum InventoryLogType: int
{
    /** 采购入账 */
    case PURCHASE = 1;

    /** 订单锁定 */
    case ORDER_LOCK = 2;

    /** 支付核销 */
    case PAY_CONSUME = 3;

    /** 取消释放 */
    case CANCEL_RELEASE = 4;

    /** 退款退回 */
    case REFUND_RETURN = 5;

    /** 售后更换 */
    case AFTER_SALE_SWAP = 6;

    /** 人工调整 */
    case MANUAL_ADJUST = 7;

    /** 门店调拨 */
    case STORE_TRANSFER = 8;

    /**
     * 获取类型标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PURCHASE        => '采购入账',
            self::ORDER_LOCK      => '订单锁定',
            self::PAY_CONSUME     => '支付核销',
            self::CANCEL_RELEASE  => '取消释放',
            self::REFUND_RETURN   => '退款退回',
            self::AFTER_SALE_SWAP => '售后更换',
            self::MANUAL_ADJUST   => '人工调整',
            self::STORE_TRANSFER  => '门店调拨',
        };
    }

    /**
     * 是否订单生命周期内的流转类型（锁定/核销/释放）
     * @return bool
     */
    public function isOrderLifecycle(): bool
    {
        return in_array($this, [self::ORDER_LOCK, self::PAY_CONSUME, self::CANCEL_RELEASE], true);
    }

    /**
     * 是否需要操作人留痕（人工调整、门店调拨）
     * @return bool
     */
    public function requiresOperator(): bool
    {
        return in_array($this, [self::MANUAL_ADJUST, self::STORE_TRANSFER], true);
    }

    /**
     * 获取所有类型选项
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
