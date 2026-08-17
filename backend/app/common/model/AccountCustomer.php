<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 账号门店关联模型
 * @property int $id
 * @property int $account_id 账号ID
 * @property int $customer_type 客户主体类型：1门店 2城市合伙人
 * @property int $customer_id 客户主体ID
 * @property int $role_in_customer 在该客户下的角色
 * @property int $is_default_store 是否默认登录门店
 * @property int $status 状态
 */
class AccountCustomer extends BaseModel
{
    protected $table = 'lj_account_customer';

    // JSON 字段
    protected $json = ['permission_scope'];

    /**
     * 关联账号
     */
    public function account(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    /**
     * 关联门店（customer_type=1时）
     */
    public function store(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Store::class, 'customer_id', 'id')
            ->where('customer_type', 1);
    }

    /**
     * 关联合伙人（customer_type=2时）
     */
    public function partner(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id', 'id')
            ->where('customer_type', 2);
    }

    /**
     * 正常状态筛选
     * @param \think\db\Query $query
     * @return void
     */
    public function scopeActive($query): void
    {
        $query->where('status', 1);
    }

    /**
     * 按客户类型筛选
     * @param \think\db\Query $query
     * @param int $type
     * @return void
     */
    public function scopeOfCustomerType($query, int $type): void
    {
        $query->where('customer_type', $type);
    }

    /**
     * 默认登录门店筛选
     * @param \think\db\Query $query
     * @return void
     */
    public function scopeDefaultStore($query): void
    {
        $query->where('is_default_store', 1);
    }
}
