<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 订单支付状态枚举
 * 对应订单表 payment_status 字段
 *
 * 批次2a修订：原枚举 1=支付中/3=已退款/4=部分退款 与 deploy 注释冲突，
 * 现对齐 deploy/mysql/init.sql lj_order.payment_status：
 * '支付状态：0未支付 1部分支付 2已支付'。
 * （支付单自身的状态请使用 PayStatus 枚举）
 */
enum PaymentStatus: int
{
    /** 未支付 */
    case UNPAID = 0;

    /** 部分支付 */
    case PARTIAL_PAID = 1;

    /** 已支付 */
    case PAID = 2;

    /**
     * 获取状态标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::UNPAID       => '未支付',
            self::PARTIAL_PAID => '部分支付',
            self::PAID         => '已支付',
        };
    }

    /**
     * 是否可继续支付（未支付/部分支付均可发起支付）
     * @return bool
     */
    public function canPay(): bool
    {
        return in_array($this, [self::UNPAID, self::PARTIAL_PAID], true);
    }

    /**
     * 是否可退款（仅已支付可退款）
     * @return bool
     */
    public function canRefund(): bool
    {
        return $this === self::PAID;
    }

    /**
     * 是否已终态（已支付即订单支付维度终态）
     * @return bool
     */
    public function isTerminal(): bool
    {
        return $this === self::PAID;
    }

    /**
     * 获取所有状态选项
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
