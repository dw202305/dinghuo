<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 客户资金账户模型（PRD 15.10）
 *
 * 字段对齐 deploy/mysql/init.sql lj_customer_balance_account（批次2c）：
 * customer_type TINYINT（CustomerType 枚举 1门店 2城市合伙人）、*_cent 列名、account_status。
 * @property int $id
 * @property int $customer_type 客户主体类型：1门店 2城市合伙人
 * @property int $customer_id 主体ID
 * @property string $currency 币种，首期CNY
 * @property int $available_balance_cent 可用余额(分)
 * @property int $frozen_balance_cent 冻结余额(分)
 * @property int $total_recharge_cent 累计储值(分)
 * @property int $total_consumed_cent 累计消费(分)
 * @property int $total_refund_cent 累计退款(分)
 * @property int $total_adjustment_cent 累计人工调整(分)
 * @property int $account_status 账户状态：1正常 2冻结 3注销
 * @property int $version 乐观锁版本号
 */
class CustomerBalanceAccount extends BaseModel
{
    protected $table = 'lj_customer_balance_account';

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'customer_type' => 'integer',
        'customer_id' => 'integer',
        'available_balance_cent' => 'integer',
        'frozen_balance_cent' => 'integer',
        'total_recharge_cent' => 'integer',
        'total_consumed_cent' => 'integer',
        'total_refund_cent' => 'integer',
        'total_adjustment_cent' => 'integer',
        'account_status' => 'integer',
        'version' => 'integer',
    ];

    /**
     * 关联资金流水
     */
    public function transactions(): \think\model\relation\HasMany
    {
        return $this->hasMany(CustomerBalanceTransaction::class, 'account_id', 'id');
    }

    /**
     * 关联储值订单
     */
    public function rechargeOrders(): \think\model\relation\HasMany
    {
        return $this->hasMany(RechargeOrder::class, 'account_id', 'id');
    }

    /**
     * 正常状态筛选（deploy 列 account_status）
     */
    public function scopeActive($query): void
    {
        $query->where('account_status', 1);
    }

    /**
     * 按客户主体查找（customer_type 为 CustomerType 枚举 int 值）
     */
    public static function findByCustomer(int $customerType, int $customerId, string $currency = 'CNY'): ?self
    {
        return self::where('customer_type', $customerType)
            ->where('customer_id', $customerId)
            ->where('currency', $currency)
            ->find();
    }
}
