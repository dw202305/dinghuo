<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 收货地址模型
 * @property int $id
 * @property int $store_id 所属门店ID
 * @property int $address_type 地址类型
 * @property string $address_label 地址标签
 * @property string $receiver_name 收件人
 * @property string $receiver_phone 手机号
 * @property string $province 省
 * @property string $city 市
 * @property string $district 区
 * @property string $detail_address 详细地址
 * @property int $is_default 是否默认
 * @property int $is_single_use 是否仅用于单次订单
 */
class StoreAddress extends BaseModel
{
    protected $table = 'lj_store_address';

    /**
     * 关联门店
     */
    public function store(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    /**
     * 关联创建账号
     */
    public function creator(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by', 'id');
    }

    /**
     * 默认地址筛选
     * @param \think\db\Query $query
     * @return void
     */
    public function scopeDefault($query): void
    {
        $query->where('is_default', 1);
    }

    /**
     * 非单次使用地址筛选
     * @param \think\db\Query $query
     * @return void
     */
    public function scopePersistent($query): void
    {
        $query->where('is_single_use', 0);
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
     * 获取完整地址
     * @return string
     */
    public function getFullAddressAttr(): string
    {
        return $this->province . $this->city . $this->district . $this->detail_address;
    }
}
