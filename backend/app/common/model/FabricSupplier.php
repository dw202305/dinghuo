<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 面料供应商模型
 * @property int $id
 * @property string $supplier_name 供应商名称
 * @property string $contact_person 联系人
 * @property string $contact_phone 联系电话
 * @property int $business_status 经营状态：1正常 2停用
 */
class FabricSupplier extends BaseModel
{
    protected $table = 'lj_fabric_supplier';

    // 该表不使用软删除
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    /**
     * 关联面料映射
     */
    public function mappings(): \think\model\relation\HasMany
    {
        return $this->hasMany(FabricSupplierMapping::class, 'supplier_id', 'id');
    }

    /**
     * 正常状态筛选
     * @param \think\db\Query $query
     * @return void
     */
    public function scopeActive($query): void
    {
        $query->where('business_status', 1);
    }
}
