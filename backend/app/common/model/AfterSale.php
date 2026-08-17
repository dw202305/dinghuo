<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 售后申请模型
 * @property int $id
 * @property string $after_sale_no 售后单号
 * @property int $order_id 订单ID
 * @property string $order_no 订单号
 * @property int|null $item_id 窗帘明细ID
 * @property string|null $item_no 窗帘编号
 * @property int $problem_type 问题类型
 * @property string $problem_desc 问题描述
 * @property int $status 状态：1待处理 2处理中 3已完成 4已关闭
 * @property int|null $responsibility 责任判断
 * @property int $accessory_cost_cent 配件费用(分)
 * @property int $labor_cost_cent 人工费用(分)
 * @property int $logistics_cost_cent 物流费用(分)
 * @property int $created_by 创建人ID
 */
class AfterSale extends BaseModel
{
    protected $table = 'lj_after_sale';

    // 该表不使用软删除，财务相关表用 status 管理
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // JSON 字段
    protected $json = ['images', 'videos'];

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'order_id' => 'integer',
        'item_id' => 'integer',
        'problem_type' => 'integer',
        'status' => 'integer',
        'responsibility' => 'integer',
        'accessory_cost_cent' => 'integer',
        'labor_cost_cent' => 'integer',
        'logistics_cost_cent' => 'integer',
        'created_by' => 'integer',
        'handler_id' => 'integer',
    ];

    /**
     * 关联订单
     */
    public function order(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 关联窗帘明细
     */
    public function orderItem(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'item_id', 'id');
    }

    /**
     * 关联创建人
     */
    public function creator(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by', 'id');
    }

    /**
     * 按状态筛选
     */
    public function scopeOfStatus($query, int $status): void
    {
        $query->where('status', $status);
    }

    /**
     * 待处理筛选
     */
    public function scopePending($query): void
    {
        $query->where('status', 1);
    }

    /**
     * 处理中筛选
     */
    public function scopeProcessing($query): void
    {
        $query->where('status', 2);
    }

    /**
     * 按订单筛选
     */
    public function scopeOfOrder($query, int $orderId): void
    {
        $query->where('order_id', $orderId);
    }

    /**
     * 是否待处理
     */
    public function isPending(): bool
    {
        return $this->status === 1;
    }

    /**
     * 计算售后总费用(分)
     */
    public function getTotalCostCent(): int
    {
        return (int) $this->accessory_cost_cent + (int) $this->labor_cost_cent + (int) $this->logistics_cost_cent;
    }
}
