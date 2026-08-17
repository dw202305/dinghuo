<?php
declare(strict_types=1);

namespace app\common\model;

use app\common\enum\OrderStatus;

/**
 * 订单模型
 *
 * 所有金额字段以"分"为单位存储（BIGINT），禁止使用 float/double（规范 7.2）。
 *
 * @property int $id
 * @property string $order_no 订单号
 * @property int $transaction_type 交易主体类型（1=门店, 2=合伙人）
 * @property int $transaction_id 交易主体ID
 * @property int $service_store_id 实际服务门店ID
 * @property int $partner_id_snapshot 城市合伙人快照ID
 * @property int $sales_id_snapshot 成交销售快照ID
 * @property int $current_service_sales_id 当前服务销售ID
 * @property int $collaborating_sales_id 协同销售ID
 * @property int $order_status 订单状态（OrderStatus 枚举值）
 * @property int $subtotal_amount_cent 商品小计（分）
 * @property int $discount_amount_cent 优惠金额（分）
 * @property int $payable_amount_cent 应付金额（分）
 * @property int $paid_amount_cent 已付金额（分）
 * @property int $refund_amount_cent 退款金额（分）
 * @property int $price_version_id 价格版本ID
 * @property string $idempotent_key 幂等键
 * @property string|null $price_locked_at 价格锁定时间
 * @property string|null $price_locked_until 价格锁定截止时间
 * @property string $audit_type 审核类型：post_audit|pre_audit
 * @property int $audit_status 审核状态：0未审核 1通过 2需确认 3待补款 4无法生产
 */
class Order extends BaseModel
{
    protected $table = 'lj_order';

    // JSON 字段
    protected $json = ['attachments'];

    /**
     * 关联窗帘明细
     */
    public function items(): \think\model\relation\HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    /**
     * 关联支付记录
     */
    public function payments(): \think\model\relation\HasMany
    {
        return $this->hasMany(Payment::class, 'order_id', 'id');
    }

    /**
     * 关联售后单
     */
    public function afterSales(): \think\model\relation\HasMany
    {
        return $this->hasMany(AfterSale::class, 'order_id', 'id');
    }

    /**
     * 关联交易门店
     */
    public function store(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Store::class, 'transaction_id', 'id')
            ->where('transaction_type', 1);
    }

    /**
     * 关联状态历史
     */
    public function statusHistories(): \think\model\relation\HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id', 'id');
    }

    /**
     * 获取当前状态枚举
     */
    public function getStatusEnum(): OrderStatus
    {
        return OrderStatus::from($this->order_status);
    }

    /**
     * 生成订单号
     * 格式：SS-YYYYMMDD-{storeNo}-{sequence}
     *
     * @param string $storeNo 门店编号
     * @return string
     */
    public static function generateOrderNo(string $storeNo): string
    {
        $date = date('Ymd');
        $prefix = "SS-{$date}-{$storeNo}";

        $lastOrder = self::where('order_no', 'like', "{$prefix}-%")
            ->order('id', 'desc')
            ->value('order_no');

        if ($lastOrder) {
            $lastSeq = (int) substr($lastOrder, -4);
            $seq = $lastSeq + 1;
        } else {
            $seq = 1;
        }

        return $prefix . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 获取审核类型
     */
    public function getAuditTypeAttr($value): string
    {
        return (string) $value;
    }

    /**
     * 设置审核类型
     */
    public function setAuditTypeAttr($value): string
    {
        $allowed = ['post_audit', 'pre_audit'];
        if (!in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid audit_type: {$value}");
        }
        return (string) $value;
    }

    /**
     * 是否为预审订单
     */
    public function isPreAudit(): bool
    {
        return $this->audit_type === 'pre_audit';
    }

    /**
     * 是否为后审订单（默认）
     */
    public function isPostAudit(): bool
    {
        return $this->audit_type === 'post_audit' || $this->audit_type === '';
    }

}
