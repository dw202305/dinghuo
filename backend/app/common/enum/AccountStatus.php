<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 资金账户状态枚举（lj_customer_balance_account.account_status）
 *
 * 对应 deploy/mysql/init.sql 字段注释：
 * '账户状态：1正常 2冻结 3注销'（批次5魔法数字枚举化收尾时新增，
 * 取值逐项核对 docker/mysql/init.sql lj_customer_balance_account）。
 *
 * @see docker/mysql/init.sql lj_customer_balance_account
 */
enum AccountStatus: int
{
    /** 正常 */
    case NORMAL = 1;

    /** 冻结 */
    case FROZEN = 2;

    /** 注销 */
    case CANCELLED = 3;

    /**
     * 获取状态标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::NORMAL    => '正常',
            self::FROZEN    => '冻结',
            self::CANCELLED => '注销',
        };
    }

    /**
     * 是否允许资金操作（入账/支付/退款均要求正常状态）
     * @return bool
     */
    public function allowsFundOperation(): bool
    {
        return $this === self::NORMAL;
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
