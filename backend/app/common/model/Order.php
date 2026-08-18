<?php
declare(strict_types=1);

namespace app\common\model;

use app\common\enum\OrderStatus;
use app\common\support\SequenceNo;

/**
 * 订单模型
 *
 * 所有金额字段以"分"为单位存储（BIGINT），禁止使用 float/double（规范 7.2）。
 *
 * @property int $id
 * @property string $order_no 订单号
 * @property int $transaction_type 交易主体类型（1=门店, 2=合伙人）
 * @property int $transaction_id 交易主体ID
 * @property int|null $service_store_id 实际服务门店ID
 * @property int|null $partner_snapshot_id 城市合伙人归属快照ID
 * @property int|null $primary_sales_snapshot_id 公司主归属销售快照ID
 * @property int|null $current_service_sales_id 当前服务销售ID
 * @property int|null $secondary_sales_snapshot_id 协同销售快照ID
 * @property string|null $crm_customer_snapshot_id CRM客户ID快照
 * @property string|null $crm_opportunity_id CRM商机ID
 * @property int $created_by 创建账号ID
 * @property int $order_status 订单状态（OrderStatus 枚举值）
 * @property int $item_count 窗帘副数
 * @property int $track_amount_cent 轨道费用合计（分）
 * @property string $fabric_area_total 面料总面积（平方米）
 * @property int $fabric_amount_cent 面料费用合计（分）
 * @property int $inventory_used_count 库存套件使用数量
 * @property int $new_purchase_count 新购套件数量
 * @property int $new_purchase_amount_cent 新购套件费用（分）
 * @property int $accessory_amount_cent 选装配件费用（分）
 * @property string $shipping_method 运费方式
 * @property int $nonstandard_amount_cent 非标费用（分）
 * @property int $discount_amount_cent 优惠金额（分）
 * @property int $total_amount_cent 应付总额（分）
 * @property int $paid_amount_cent 实付金额（分）
 * @property int $payment_status 支付状态：0未支付 1部分支付 2已支付
 * @property string|null $price_locked_at 价格锁定时间
 * @property string|null $price_locked_until 价格锁定截止时间
 * @property string|null $paid_at 支付时间
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
     * 关联交易门店（仅门店主体订单有效；lj_store 无 transaction_type 列）
     */
    public function store(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Store::class, 'transaction_id', 'id');
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
     * 格式：SS-YYYYMMDD-{storeNo}-{sequence}（序号位宽保持4位不变）
     *
     * 批次2a：取号机制改为 SequenceNo（Redis INCR + MySQL 降级），
     * 替换原"like 前缀查最大值+1"实现（并发下会重号）。
     *
     * @param string $storeNo 门店编号
     * @return string
     */
    public static function generateOrderNo(string $storeNo): string
    {
        $date = date('Ymd');
        $prefix = "SS-{$date}-{$storeNo}";

        $seq = SequenceNo::next('order');

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
