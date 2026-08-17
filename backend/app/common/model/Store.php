<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 门店模型
 * @property int $id
 * @property string $store_no 门店编号
 * @property string $store_name 门店名称
 * @property int $store_type 门店类型
 * @property int $customer_level 客户等级
 * @property int $channel_mode 渠道模式
 * @property int|null $partner_id 所属城市合伙人ID
 * @property int|null $primary_sales_id 当前主归属销售ID
 * @property int $status 状态
 */
class Store extends BaseModel
{
    protected $table = 'lj_store';

    // JSON 字段
    protected $json = ['showroom_photos', 'invoice_info'];

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'store_type' => 'integer',
        'customer_level' => 'integer',
        'channel_mode' => 'integer',
        'partner_id' => 'integer',
        'primary_sales_id' => 'integer',
        'primary_contact_id' => 'integer',
        'admin_account_id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 关联联系人
     */
    public function contacts(): \think\model\relation\HasMany
    {
        return $this->hasMany(StoreContact::class, 'store_id', 'id');
    }

    /**
     * 关联收货地址
     */
    public function addresses(): \think\model\relation\HasMany
    {
        return $this->hasMany(StoreAddress::class, 'store_id', 'id');
    }

    /**
     * 关联订单
     */
    public function orders(): \think\model\relation\HasMany
    {
        return $this->hasMany(Order::class, 'transaction_id', 'id')
            ->where('transaction_type', 1);
    }

    /**
     * 关联库存
     */
    public function inventories(): \think\model\relation\HasMany
    {
        return $this->hasMany(StoreInventory::class, 'store_id', 'id');
    }

    /**
     * 关联所属合伙人
     */
    public function partner(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'id');
    }

    /**
     * 关联资金账户
     */
    public function balanceAccount(): \think\model\relation\HasOne
    {
        return $this->hasOne(CustomerBalanceAccount::class, 'customer_id', 'id')
            ->where('customer_type', 'store');
    }

    /**
     * 关联归属历史
     */
    public function ownershipHistories(): \think\model\relation\HasMany
    {
        return $this->hasMany(CustomerOwnershipHistory::class, 'customer_id', 'id')
            ->where('customer_type', 'store');
    }
}
