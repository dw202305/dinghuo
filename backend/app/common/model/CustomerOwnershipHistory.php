<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 归属关系历史模型（PRD 15.7）
 * @property int $id
 * @property string $customer_type 客户主体类型：store/partner
 * @property int $customer_id 客户主体ID
 * @property string $channel_mode 渠道模式：partner_channel/direct
 * @property int|null $partner_id 城市合伙人ID
 * @property int|null $primary_sales_id 主归属销售ID
 * @property int|null $secondary_sales_id 协同销售ID
 * @property string $source 归属来源：develop/assign/inherit/transfer/migrate
 * @property string $effective_at 生效时间
 * @property string|null $expires_at 失效时间
 * @property int $is_current 是否当前有效
 */
class CustomerOwnershipHistory extends BaseModel
{
    protected $table = 'lj_customer_attribution_history';

    // 该表不使用软删除
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // 禁止更新
    protected $update = [];

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'customer_id' => 'integer',
        'partner_id' => 'integer',
        'primary_sales_id' => 'integer',
        'secondary_sales_id' => 'integer',
        'is_current' => 'integer',
        'is_cascade' => 'integer',
        'parent_relation_id' => 'integer',
    ];

    /**
     * 关联主归属销售
     */
    public function primarySales(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Sales::class, 'primary_sales_id', 'id');
    }

    /**
     * 关联协同销售
     */
    public function secondarySales(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Sales::class, 'secondary_sales_id', 'id');
    }

    /**
     * 关联门店（customer_type=store）
     */
    public function store(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Store::class, 'customer_id', 'id');
    }

    /**
     * 关联合伙人（customer_type=partner）
     */
    public function partner(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id', 'id');
    }

    /**
     * 当前有效记录筛选
     */
    public function scopeCurrent($query): void
    {
        $query->where('is_current', 1);
    }

    /**
     * 按销售筛选
     */
    public function scopeBySales($query, int $salesId): void
    {
        $query->where('primary_sales_id', $salesId);
    }

    /**
     * 按客户主体筛选
     */
    public function scopeOfCustomer($query, string $customerType, int $customerId): void
    {
        $query->where('customer_type', $customerType)
              ->where('customer_id', $customerId);
    }
}
