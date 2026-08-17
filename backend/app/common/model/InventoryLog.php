<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 库存流水模型
 * @property int $id
 * @property int $store_id 门店ID
 * @property int $inventory_id 库存记录ID
 * @property int $log_type 变化类型
 * @property int $quantity 变化数量（正负）
 * @property int $before_quantity 变化前数量
 * @property int $after_quantity 变化后数量
 * @property int|null $order_id 关联订单ID
 * @property int|null $operator_id 操作人ID
 * @property string|null $idempotent_key 流水操作幂等键
 */
class InventoryLog extends BaseModel
{
    protected $table = 'lj_inventory_log';

    // 该表不使用软删除
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // 仅创建，不更新
    protected $update = [];

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'store_id' => 'integer',
        'inventory_id' => 'integer',
        'log_type' => 'integer',
        'quantity' => 'integer',
        'before_quantity' => 'integer',
        'after_quantity' => 'integer',
        'order_id' => 'integer',
        'operator_id' => 'integer',
    ];

    /**
     * 关联门店
     */
    public function store(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    /**
     * 关联库存记录
     */
    public function inventory(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(StoreInventory::class, 'inventory_id', 'id');
    }

    /**
     * 关联订单
     */
    public function order(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 按变化类型筛选
     */
    public function scopeOfType($query, int $type): void
    {
        $query->where('log_type', $type);
    }

    /**
     * 按门店筛选
     */
    public function scopeOfStore($query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * 按订单筛选
     */
    public function scopeOfOrder($query, int $orderId): void
    {
        $query->where('order_id', $orderId);
    }
}
