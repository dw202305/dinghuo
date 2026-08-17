<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 门店联系人模型
 * @property int $id
 * @property int $store_id 所属门店ID
 * @property string $contact_name 姓名
 * @property string $phone 手机号
 * @property int $contact_type 联系人类型
 * @property int $is_primary 是否主联系人
 */
class StoreContact extends BaseModel
{
    protected $table = 'lj_store_contact';

    /**
     * 关联门店
     */
    public function store(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    /**
     * 关联登录账号
     */
    public function accounts(): \think\model\relation\HasMany
    {
        return $this->hasMany(Account::class, 'contact_id', 'id');
    }

    /**
     * 主联系人筛选
     * @param \think\db\Query $query
     * @return void
     */
    public function scopePrimary($query): void
    {
        $query->where('is_primary', 1);
    }

    /**
     * 按联系人类型筛选
     * @param \think\db\Query $query
     * @param int $type
     * @return void
     */
    public function scopeOfType($query, int $type): void
    {
        $query->where('contact_type', $type);
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
}
