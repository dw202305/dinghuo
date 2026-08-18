<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 客户资金流水类型枚举
 *
 * 对应 deploy lj_customer_balance_transaction.transaction_type 字段注释：
 * '流水类型：1储值 2消费 3退款 4冻结 5解冻 6调入 7调出 8冲正 9人工调整'
 * （批次2a逐项核对 deploy/mysql/init.sql）。
 *
 * @see deploy/mysql/init.sql lj_customer_balance_transaction
 */
enum BalanceTxnType: int
{
    /** 储值 */
    case RECHARGE = 1;

    /** 消费 */
    case CONSUME = 2;

    /** 退款 */
    case REFUND = 3;

    /** 冻结 */
    case FREEZE = 4;

    /** 解冻 */
    case UNFREEZE = 5;

    /** 调入 */
    case TRANSFER_IN = 6;

    /** 调出 */
    case TRANSFER_OUT = 7;

    /** 冲正 */
    case REVERSAL = 8;

    /** 人工调整 */
    case MANUAL_ADJUST = 9;

    /**
     * 获取类型标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::RECHARGE      => '储值',
            self::CONSUME       => '消费',
            self::REFUND        => '退款',
            self::FREEZE        => '冻结',
            self::UNFREEZE      => '解冻',
            self::TRANSFER_IN   => '调入',
            self::TRANSFER_OUT  => '调出',
            self::REVERSAL      => '冲正',
            self::MANUAL_ADJUST => '人工调整',
        };
    }

    /**
     * 常规资金方向（收入=true / 支出=false），
     * 冻结/解冻为余额内部结构变化，按可用余额口径：冻结视为支出、解冻视为收入。
     * @return bool
     */
    public function isIncome(): bool
    {
        return in_array($this, [self::RECHARGE, self::REFUND, self::UNFREEZE, self::TRANSFER_IN], true);
    }

    /**
     * 是否需要审批留痕（人工调整、调入调出、冲正）
     * @return bool
     */
    public function requiresApproval(): bool
    {
        return in_array($this, [self::TRANSFER_IN, self::TRANSFER_OUT, self::REVERSAL, self::MANUAL_ADJUST], true);
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
