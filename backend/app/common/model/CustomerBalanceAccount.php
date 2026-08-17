<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 客户资金账户模型（PRD 15.10）
 * @property int $id
 * @property string $customer_type 主体类型：store/partner
 * @property int $customer_id 主体ID
 * @property string $currency 币种，首期CNY
 * @property int $available_balance 可用余额(分)
 * @property int $frozen_balance 冻结余额(分)
 * @property int $total_recharge 累计储值(分)
 * @property int $total_consumed 累计消费(分)
 * @property int $total_refund 累计退款(分)
 * @property int $total_adjustment 累计调整(分)
 * @property int $status 状态：1正常 2冻结 3注销
 * @property int $version 乐观锁版本号
 */
class CustomerBalanceAccount extends BaseModel
{
    protected $table = 'lj_customer_balance_account';

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'customer_id' => 'integer',
        'available_balance' => 'integer',
        'frozen_balance' => 'integer',
        'total_recharge' => 'integer',
        'total_consumed' => 'integer',
        'total_refund' => 'integer',
        'total_adjustment' => 'integer',
        'status' => 'integer',
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
     * 正常状态筛选
     */
    public function scopeActive($query): void
    {
        $query->where('status', 1);
    }

    /**
     * 按客户主体查找
     */
    public static function findByCustomer(string $customerType, int $customerId, string $currency = 'CNY'): ?self
    {
        return self::where('customer_type', $customerType)
            ->where('customer_id', $customerId)
            ->where('currency', $currency)
            ->find();
    }
}
