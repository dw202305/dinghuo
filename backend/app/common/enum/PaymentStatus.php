<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 支付状态枚举
 * 对应订单表 payment_status 字段
 */
enum PaymentStatus: int
{
    /** 未支付 */
    case UNPAID = 0;

    /** 支付中 */
    case PAYING = 1;

    /** 已支付 */
    case PAID = 2;

    /** 已退款 */
    case REFUNDED = 3;

    /** 部分退款 */
    case PARTIAL_REFUND = 4;

    /**
     * 获取状态标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::UNPAID         => '未支付',
            self::PAYING         => '支付中',
            self::PAID           => '已支付',
            self::REFUNDED       => '已退款',
            self::PARTIAL_REFUND => '部分退款',
        };
    }

    /**
     * 是否可支付
     * @return bool
     */
    public function canPay(): bool
    {
        return in_array($this, [self::UNPAID, self::PAYING]);
    }

    /**
     * 是否可退款
     * @return bool
     */
    public function canRefund(): bool
    {
        return in_array($this, [self::PAID, self::PARTIAL_REFUND]);
    }

    /**
     * 是否已终态（已支付或已退款）
     * @return bool
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::PAID, self::REFUNDED]);
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
