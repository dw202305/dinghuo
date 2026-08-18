<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 储值订单模型（PRD 15.12）
 *
 * 字段对齐 deploy/mysql/init.sql lj_recharge_order（批次2c）：
 * recharge_method TINYINT（RechargeMethod）、状态 1-6（RechargeStatus）、
 * trade_no/offline_voucher/credited_at；deploy 无 fund_type/payer_name 列。
 * @property int $id
 * @property string $recharge_no 储值单号
 * @property int $account_id 资金账户ID
 * @property int $customer_type 客户主体类型：1门店 2城市合伙人
 * @property int $customer_id 客户主体ID
 * @property int $amount_cent 储值金额(分)
 * @property int $recharge_method 储值方式：1微信 2支付宝 3线下 4测试
 * @property string|null $trade_no 支付平台交易号
 * @property string|null $offline_voucher 线下凭证信息
 * @property int $status 状态：1待支付 2支付中 3待审核 4已入账 5已关闭 6已退款
 * @property int|null $applicant_id 申请人ID
 * @property string|null $applicant_name 申请人姓名
 * @property int|null $reviewer_id 审核人ID
 * @property string|null $reviewer_name 审核人姓名
 * @property string|null $paid_at 支付时间
 * @property string|null $reviewed_at 审核时间
 * @property string|null $credited_at 入账时间
 * @property string|null $idempotent_key 幂等键
 * @property string|null $remark 备注
 */
class RechargeOrder extends BaseModel
{
    protected $table = 'lj_recharge_order';

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'account_id' => 'integer',
        'customer_type' => 'integer',
        'customer_id' => 'integer',
        'amount_cent' => 'integer',
        'recharge_method' => 'integer',
        'status' => 'integer',
        'applicant_id' => 'integer',
        'reviewer_id' => 'integer',
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
     * 待审核筛选（RechargeStatus::PENDING_REVIEW）
     */
    public function scopePendingReview($query): void
    {
        $query->where('status', \app\common\enum\RechargeStatus::PENDING_REVIEW->value);
    }

    /**
     * 已入账筛选（RechargeStatus::CREDITED）
     */
    public function scopeCredited($query): void
    {
        $query->where('status', \app\common\enum\RechargeStatus::CREDITED->value);
    }

    /**
     * 按客户主体筛选（customer_type 为 CustomerType 枚举 int 值）
     */
    public function scopeOfCustomer($query, int $customerType, int $customerId): void
    {
        $query->where('customer_type', $customerType)
              ->where('customer_id', $customerId);
    }
}
