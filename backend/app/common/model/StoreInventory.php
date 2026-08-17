<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 门店套件库存模型
 * @property int $id
 * @property int $store_id 门店ID
 * @property string $kit_sku 套件SKU
 * @property int $total_purchased 已采购总数
 * @property int $available 可用数量
 * @property int $locked 已锁定数量
 * @property int $consumed 已核销数量
 * @property int $frozen 售后冻结数量
 * @property int $return_pending 退回待检数量
 * @property int $adjusted 调整数量
 * @property string|null $idempotent_key 库存操作幂等键
 */
class StoreInventory extends BaseModel
{
    protected $table = 'lj_store_inventory';

    // 该表不使用软删除
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'store_id' => 'integer',
        'total_purchased' => 'integer',
        'available' => 'integer',
        'locked' => 'integer',
        'consumed' => 'integer',
        'frozen' => 'integer',
        'return_pending' => 'integer',
        'adjusted' => 'integer',
    ];

    /**
     * 关联门店
     */
    public function store(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    /**
     * 关联库存流水
     */
    public function logs(): \think\model\relation\HasMany
    {
        return $this->hasMany(InventoryLog::class, 'inventory_id', 'id');
    }

    /**
     * 按门店筛选
     */
    public function scopeOfStore($query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * 有库存筛选（可用数量 > 0）
     */
    public function scopeInStock($query): void
    {
        $query->where('available', '>', 0);
    }

    /**
     * 计算实际可用库存
     */
    public function getEffectiveAvailable(): int
    {
        return max(0, $this->available - $this->locked - $this->frozen);
    }
}
