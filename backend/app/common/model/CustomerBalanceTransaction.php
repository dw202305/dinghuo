<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 客户资金流水模型（PRD 15.11）
 * 资金流水禁止物理删除和直接修改，冲正通过新的反向流水完成
 * @property int $id
 * @property string $transaction_no 流水号
 * @property int $account_id 资金账户ID
 * @property string $customer_type 客户主体类型
 * @property int $customer_id 客户主体ID
 * @property string $transaction_type 流水类型：recharge/consume/refund/freeze/unfreeze/adjust/transfer/reversal
 * @property int $fund_type 资金属性：1真实 2测试
 * @property int $amount 变动金额(分)，正数入账负数出账
 * @property int $direction 资金方向：1入 2出
 * @property int $balance_before 变动前余额(分)
 * @property int $balance_after 变动后余额(分)
 * @property string|null $ref_order_no 关联订单号
 * @property string|null $ref_payment_no 关联支付单号
 * @property string|null $ref_recharge_no 关联储值单号
 * @property string|null $ref_refund_no 关联退款单号
 * @property string $idempotent_key 唯一业务幂等键
 * @property string|null $payment_channel 支付渠道
 */
class CustomerBalanceTransaction extends BaseModel
{
    protected $table = 'lj_customer_balance_transaction';

    // 该表不使用软删除，流水不可删除
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // 仅创建，不更新
    protected $update = [];

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'account_id' => 'integer',
        'customer_id' => 'integer',
        'fund_type' => 'integer',
        'amount' => 'integer',
        'direction' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'operator_id' => 'integer',
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
     * 按流水类型筛选
     */
    public function scopeOfType($query, string $type): void
    {
        $query->where('transaction_type', $type);
    }

    /**
     * 按客户主体筛选
     */
    public function scopeOfCustomer($query, string $customerType, int $customerId): void
    {
        $query->where('customer_type', $customerType)
              ->where('customer_id', $customerId);
    }

    /**
     * 按资金账户筛选
     */
    public function scopeOfAccount($query, int $accountId): void
    {
        $query->where('account_id', $accountId);
    }

    /**
     * 真实资金筛选
     */
    public function scopeReal($query): void
    {
        $query->where('fund_type', 1);
    }

    /**
     * 测试资金筛选
     */
    public function scopeTest($query): void
    {
        $query->where('fund_type', 2);
    }
}
