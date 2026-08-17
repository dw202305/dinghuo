<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 发票申请模型
 * @property int $id
 * @property string $request_no 申请编号
 * @property int $order_id 订单ID
 * @property string $order_no 订单号
 * @property int $store_id 门店ID
 * @property int $invoice_type 发票类型：1普票 2专票
 * @property string $title 发票抬头
 * @property string $tax_no 税号
 * @property float $tax_rate 税率(%)
 * @property int $invoice_amount_cent 开票金额(分)
 * @property int $status 状态：1待审核 2已审核待开票 3已开票 4已驳回
 * @property int $created_by 创建人ID
 */
class InvoiceRequest extends BaseModel
{
    protected $table = 'lj_invoice_request';

    // 该表不使用软删除，财务表用 status 管理
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'order_id' => 'integer',
        'store_id' => 'integer',
        'invoice_type' => 'integer',
        'tax_rate' => 'float',
        'invoice_amount_cent' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
        'reviewer_id' => 'integer',
    ];

    /**
     * 关联订单
     */
    public function order(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 关联门店
     */
    public function store(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
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
     * 待审核筛选
     */
    public function scopePendingReview($query): void
    {
        $query->where('status', 1);
    }

    /**
     * 已开票筛选
     */
    public function scopeInvoiced($query): void
    {
        $query->where('status', 3);
    }

    /**
     * 按门店筛选
     */
    public function scopeOfStore($query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * 是否为增值税专用发票
     */
    public function isSpecialInvoice(): bool
    {
        return $this->invoice_type === 2;
    }
}
