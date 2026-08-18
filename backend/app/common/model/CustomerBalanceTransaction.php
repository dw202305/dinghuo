<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 客户资金流水模型（PRD 15.11）
 * 资金流水禁止物理删除和直接修改，冲正通过新的反向流水完成
 *
 * 字段对齐 deploy/mysql/init.sql lj_customer_balance_transaction（批次2c）：
 * 枚举列均 TINYINT（BalanceTxnType/FundDirection/FundType），金额列 *_cent，
 * 关联列改 ref_order_id/ref_payment_id/ref_recharge_id/refund_id。
 * @property int $id
 * @property string $transaction_no 流水号
 * @property int $account_id 资金账户ID
 * @property int $customer_type 客户主体类型快照：1门店 2城市合伙人
 * @property int $customer_id 客户主体ID快照
 * @property int $transaction_type 流水类型（BalanceTxnType）：1储值 2消费 3退款 4冻结 5解冻 6调入 7调出 8冲正 9人工调整
 * @property int $fund_type 资金属性：1真实资金 2测试资金
 * @property int $direction 资金方向：1收入 2支出
 * @property int $amount_cent 变动金额(分)
 * @property int $before_balance_cent 变动前余额(分)
 * @property int $after_balance_cent 变动后余额(分)
 * @property int|null $ref_order_id 关联订单ID
 * @property int|null $ref_payment_id 关联支付单ID
 * @property int|null $ref_recharge_id 关联储值单ID
 * @property int|null $refund_id 关联退款单ID
 * @property string $idempotent_key 唯一业务幂等键
 * @property string|null $payment_channel 支付渠道：wechat/alipay/offline/test
 * @property string|null $reason 原因
 * @property string|null $remark 备注
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
        'customer_type' => 'integer',
        'customer_id' => 'integer',
        'transaction_type' => 'integer',
        'fund_type' => 'integer',
        'direction' => 'integer',
        'amount_cent' => 'integer',
        'before_balance_cent' => 'integer',
        'after_balance_cent' => 'integer',
        'ref_order_id' => 'integer',
        'ref_payment_id' => 'integer',
        'ref_recharge_id' => 'integer',
        'refund_id' => 'integer',
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
     * 按流水类型筛选（BalanceTxnType 枚举 int 值）
     */
    public function scopeOfType($query, int $type): void
    {
        $query->where('transaction_type', $type);
    }

    /**
     * 按客户主体筛选（customer_type 为 CustomerType 枚举 int 值）
     */
    public function scopeOfCustomer($query, int $customerType, int $customerId): void
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
