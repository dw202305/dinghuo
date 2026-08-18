<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 客户主体类型枚举
 *
 * 对应 deploy 多表的 customer_type / transaction_type 字段注释：
 * '客户主体类型：1门店 2城市合伙人'（批次2a逐项核对 deploy/mysql/init.sql，
 * 覆盖 lj_account_customer、lj_customer_balance_account、lj_customer_balance_transaction、
 * lj_recharge_order、lj_order.transaction_type 等）。
 *
 * @see deploy/mysql/init.sql
 */
enum CustomerType: int
{
    /** 门店 */
    case STORE = 1;

    /** 城市合伙人 */
    case PARTNER = 2;

    /**
     * 获取类型标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::STORE   => '门店',
            self::PARTNER => '城市合伙人',
        };
    }

    /**
     * 是否门店主体
     * @return bool
     */
    public function isStore(): bool
    {
        return $this === self::STORE;
    }

    /**
     * 获取业务别名（BalanceAccountService 等旧接口使用 store/partner 字符串）
     * @return string
     */
    public function alias(): string
    {
        return match ($this) {
            self::STORE   => 'store',
            self::PARTNER => 'partner',
        };
    }

    /**
     * 从业务别名解析
     * @param string $alias store|partner
     * @return self
     */
    public static function fromAlias(string $alias): self
    {
        return match ($alias) {
            'store'   => self::STORE,
            'partner' => self::PARTNER,
            default   => throw new \InvalidArgumentException("未知客户主体别名：{$alias}"),
        };
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
