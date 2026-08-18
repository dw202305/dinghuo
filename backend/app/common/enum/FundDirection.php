<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 资金方向枚举
 *
 * 对应 deploy lj_customer_balance_transaction.direction 字段注释：
 * '资金方向：1收入 2支出'（批次2a逐项核对 deploy/mysql/init.sql）。
 *
 * @see deploy/mysql/init.sql lj_customer_balance_transaction
 */
enum FundDirection: int
{
    /** 收入 */
    case INCOME = 1;

    /** 支出 */
    case EXPENSE = 2;

    /**
     * 获取方向标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::INCOME  => '收入',
            self::EXPENSE => '支出',
        };
    }

    /**
     * 是否收入方向
     * @return bool
     */
    public function isIncome(): bool
    {
        return $this === self::INCOME;
    }

    /**
     * 获取所有方向选项
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
