<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 储值订单模型（PRD 15.12）
 * @property int $id
 * @property string $recharge_no 储值单号
 * @property int $account_id 资金账户ID
 * @property string $customer_type 客户主体类型
 * @property int $customer_id 客户主体ID
 * @property int $amount_cent 储值金额(分)
 * @property string $recharge_method 储值方式：wechat/alipay/offline/test
 * @property string|null $platform_transaction_no 支付平台交易号
 * @property int $status 状态：0待支付 1已支付待审核 2已入账 3审核驳回 4已退款 5已关闭
 * @property int|null $applicant_id 申请人ID
 * @property int|null $reviewer_id 审核人ID
 * @property int $fund_type 资金属性：1真实 2测试
 */
class RechargeOrder extends BaseModel
{
    protected $table = 'lj_recharge_order';

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'account_id' => 'integer',
        'customer_id' => 'integer',
        'amount_cent' => 'integer',
        'status' => 'integer',
        'applicant_id' => 'integer',
        'reviewer_id' => 'integer',
        'fund_type' => 'integer',
    ];

    /**
     * 关联资金账户
     */
    public function account(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(CustomerBalanceAccount::class, 'account_id', 'id');
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
     * 已入账筛选
     */
    public function scopeCredited($query): void
    {
        $query->where('status', 2);
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
