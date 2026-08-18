<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 资金属性枚举
 *
 * 对应 deploy lj_customer_balance_transaction.fund_type 字段注释：
 * '资金属性：1真实资金 2测试资金'（批次2a逐项核对 deploy/mysql/init.sql）。
 *
 * @see deploy/mysql/init.sql lj_customer_balance_transaction
 */
enum FundType: int
{
    /** 真实资金 */
    case REAL = 1;

    /** 测试资金 */
    case TEST = 2;

    /**
     * 获取属性标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::REAL => '真实资金',
            self::TEST => '测试资金',
        };
    }

    /**
     * 是否真实资金（测试资金不得参与真实对账与结算）
     * @return bool
     */
    public function isReal(): bool
    {
        return $this === self::REAL;
    }

    /**
     * 获取所有属性选项
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
