<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 储值单状态枚举
 *
 * 对应 deploy lj_recharge_order.status 字段注释：
 * '状态：1待支付 2支付中 3待审核 4已入账 5已关闭 6已退款'
 * （批次2a逐项核对 deploy/mysql/init.sql）。
 *
 * @see deploy/mysql/init.sql lj_recharge_order
 */
enum RechargeStatus: int
{
    /** 待支付 */
    case PENDING_PAY = 1;

    /** 支付中 */
    case PAYING = 2;

    /** 待审核 */
    case PENDING_REVIEW = 3;

    /** 已入账 */
    case CREDITED = 4;

    /** 已关闭 */
    case CLOSED = 5;

    /** 已退款 */
    case REFUNDED = 6;

    /**
     * 获取状态标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAY    => '待支付',
            self::PAYING         => '支付中',
            self::PENDING_REVIEW => '待审核',
            self::CREDITED       => '已入账',
            self::CLOSED         => '已关闭',
            self::REFUNDED       => '已退款',
        };
    }

    /**
     * 是否可推进入账（支付回调或审核通过后可入账）
     * @return bool
     */
    public function canCredit(): bool
    {
        return in_array($this, [self::PENDING_PAY, self::PAYING, self::PENDING_REVIEW], true);
    }

    /**
     * 是否已终态
     * @return bool
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::CREDITED, self::CLOSED, self::REFUNDED], true);
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
