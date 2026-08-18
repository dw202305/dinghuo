<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 支付单状态枚举（lj_payment.pay_status）
 *
 * 对应 deploy lj_payment.pay_status 字段注释：
 * '支付状态：0待支付 1支付成功 2支付失败 3已退款'（批次2a逐项核对 deploy/mysql/init.sql）。
 *
 * 注意与 PaymentStatus（订单表 payment_status：0未支付 1部分支付 2已支付）区分。
 *
 * @see deploy/mysql/init.sql lj_payment
 */
enum PayStatus: int
{
    /** 待支付 */
    case PENDING = 0;

    /** 支付成功 */
    case SUCCESS = 1;

    /** 支付失败 */
    case FAILED = 2;

    /** 已退款 */
    case REFUNDED = 3;

    /**
     * 获取状态标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING  => '待支付',
            self::SUCCESS  => '支付成功',
            self::FAILED   => '支付失败',
            self::REFUNDED => '已退款',
        };
    }

    /**
     * 是否可入账（仅待支付单可被回调推进为成功）
     * @return bool
     */
    public function canSettle(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * 是否已终态（成功/失败/已退款均不再变更入账状态）
     * @return bool
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED, self::REFUNDED], true);
    }

    /**
     * 是否可退款
     * @return bool
     */
    public function canRefund(): bool
    {
        return $this === self::SUCCESS;
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
