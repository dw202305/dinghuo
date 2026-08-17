<?php
declare(strict_types=1);

namespace app\common\enum;

/**
 * 订单状态枚举
 *
 * 定义所有订单状态值及元信息。
 * 所有状态变更必须通过 OrderStateService（规范 10.1）。
 *
 * @see docs/dev_specification_v1.0.md 第十节
 * @see docs/prd_v3.2.md 6
 */
enum OrderStatus: int
{
    case DRAFT           = 1;   // 草稿
    case PENDING_PAY     = 2;   // 待支付
    case PAYING          = 3;   // 支付处理中
    case PAID_PENDING    = 4;   // 已支付待审核
    case NEED_CONFIRM    = 5;   // 需要门店确认
    case NEED_SUPPLEMENT = 6;   // 待补款
    case APPROVED        = 7;   // 审核通过待排产
    case PRODUCING       = 8;   // 生产中
    case QC              = 9;   // 质检中
    case PENDING_SHIP    = 10;  // 待发货
    case PARTIAL_SHIP    = 11;  // 部分发货
    case SHIPPED         = 12;  // 已发货
    case RECEIVED        = 13;  // 已签收
    case COMPLETED       = 14;  // 已完成
    case AFTER_SALE      = 15;  // 售后处理中
    case CANCELLED       = 16;  // 已取消
    case REFUNDING       = 17;  // 退款中
    case REFUNDED        = 18;  // 已退款

    /**
     * 获取状态描述
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT           => '草稿',
            self::PENDING_PAY     => '待支付',
            self::PAYING          => '支付处理中',
            self::PAID_PENDING    => '已支付待审核',
            self::NEED_CONFIRM    => '需要门店确认',
            self::NEED_SUPPLEMENT => '待补款',
            self::APPROVED        => '审核通过待排产',
            self::PRODUCING       => '生产中',
            self::QC              => '质检中',
            self::PENDING_SHIP    => '待发货',
            self::PARTIAL_SHIP    => '部分发货',
            self::SHIPPED         => '已发货',
            self::RECEIVED        => '已签收',
            self::COMPLETED       => '已完成',
            self::AFTER_SALE      => '售后处理中',
            self::CANCELLED       => '已取消',
            self::REFUNDING       => '退款中',
            self::REFUNDED        => '已退款',
        };
    }

    /**
     * 是否处于预生产阶段（可取消）
     *
     * 进入生产后门店不可取消（规范 10.3）
     */
    public function isPreProduction(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::PENDING_PAY,
            self::PAYING,
            self::PAID_PENDING,
            self::NEED_CONFIRM,
            self::NEED_SUPPLEMENT,
            self::APPROVED,
        ]);
    }

    /**
     * 是否可取消（门店视角）
     *
     * 进入生产后门店不允许取消，后台管理员保留特殊取消权限（规范 10.3）
     */
    public function canCancel(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::PENDING_PAY,
            self::PAID_PENDING,
        ]);
    }

    /**
     * 是否已终结（不可再变更）
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::REFUNDED,
        ]);
    }

    /**
     * 是否已支付（含部分状态）
     */
    public function isPaid(): bool
    {
        return in_array($this, [
            self::PAID_PENDING,
            self::NEED_CONFIRM,
            self::NEED_SUPPLEMENT,
            self::APPROVED,
            self::PRODUCING,
            self::QC,
            self::PENDING_SHIP,
            self::PARTIAL_SHIP,
            self::SHIPPED,
            self::RECEIVED,
            self::COMPLETED,
        ]);
    }

    /**
     * 是否处于生产流程中
     */
    public function isProducing(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::PRODUCING,
            self::QC,
            self::PENDING_SHIP,
            self::PARTIAL_SHIP,
        ]);
    }

    /**
     * 是否可以进入售后
     */
    public function canAfterSale(): bool
    {
        return in_array($this, [
            self::SHIPPED,
            self::RECEIVED,
            self::COMPLETED,
        ]);
    }

    /**
     * 获取所有终态/非终态分类
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::CANCELLED,
            self::REFUNDED,
            self::COMPLETED,
        ]);
    }
}
