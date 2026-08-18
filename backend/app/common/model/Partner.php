<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 城市合伙人模型
 * @property int $id
 * @property string $partner_no 合伙人编号
 * @property string $business_entity 企业或经营主体名称
 * @property string|null $credit_code 统一社会信用代码
 * @property int|null $primary_contact_id 主联系人ID
 * @property string|null $authorized_city 授权城市
 * @property string|null $authorized_region 授权区域
 * @property int|null $cooperation_stage 合作阶段
 * @property int|null $partner_level 合伙人等级
 * @property int|null $primary_sales_id 当前主归属销售ID
 * @property int|null $secondary_sales_id 协同销售ID
 * @property string|null $crm_customer_id CRM客户ID
 * @property int $status 状态
 */
class Partner extends BaseModel
{
    protected $table = 'lj_partner';

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'primary_contact_id' => 'integer',
        'cooperation_stage' => 'integer',
        'partner_level' => 'integer',
        'primary_sales_id' => 'integer',
        'secondary_sales_id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 关联下属门店
     */
    public function stores(): \think\model\relation\HasMany
    {
        return $this->hasMany(Store::class, 'partner_id', 'id');
    }

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
     * 关联归属历史
     */
    public function ownershipHistories(): \think\model\relation\HasMany
    {
        return $this->hasMany(CustomerOwnershipHistory::class, 'customer_id', 'id')
            ->where('customer_type', 'partner');
    }

    /**
     * 关联资金账户
     */
    public function balanceAccount(): \think\model\relation\HasOne
    {
        return $this->hasOne(CustomerBalanceAccount::class, 'customer_id', 'id')
            ->where('customer_type', \app\common\enum\CustomerType::PARTNER->value);
    }

    /**
     * 正常状态筛选
     */
    public function scopeActive($query): void
    {
        $query->where('status', 1);
    }

    /**
     * 获取合伙人编号（带格式化）
     */
    public function getPartnerNoAttr(): string
    {
        return $this->getData('partner_no');
    }
}
