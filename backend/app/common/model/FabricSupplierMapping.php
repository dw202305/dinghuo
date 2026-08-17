<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 面料供应商映射模型
 * @property int $id
 * @property int $fabric_id 面料ID
 * @property string $fabric_no 世尚面料编号
 * @property int $supplier_id 供应商ID
 * @property string $supplier_fabric_no 供应商原始面料编号
 * @property int|null $purchase_price_cent 采购价格(分)
 * @property int $is_default_supplier 是否默认供应商
 * @property int $is_backup_supplier 是否备选供应商
 * @property int $status 状态
 */
class FabricSupplierMapping extends BaseModel
{
    protected $table = 'lj_fabric_supplier_mapping';

    // 该表不使用软删除
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'fabric_id' => 'integer',
        'supplier_id' => 'integer',
        'purchase_price_cent' => 'integer',
        'is_default_supplier' => 'integer',
        'is_backup_supplier' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 关联面料
     */
    public function fabric(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Fabric::class, 'fabric_id', 'id');
    }

    /**
     * 关联供应商
     */
    public function supplier(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(FabricSupplier::class, 'supplier_id', 'id');
    }

    /**
     * 有效映射筛选
     */
    public function scopeActive($query): void
    {
        $query->where('status', 1);
    }

    /**
     * 默认供应商筛选
     */
    public function scopeDefault($query): void
    {
        $query->where('is_default_supplier', 1);
    }

    /**
     * 备选供应商筛选
     */
    public function scopeBackup($query): void
    {
        $query->where('is_backup_supplier', 1);
    }

    /**
     * 按面料编号筛选
     */
    public function scopeOfFabricNo($query, string $fabricNo): void
    {
        $query->where('fabric_no', $fabricNo);
    }
}
