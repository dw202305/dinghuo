<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 支付记录模型
 *
 * 字段对齐 deploy/mysql/init.sql lj_payment（批次2c）。
 * @property int $id
 * @property string $payment_no 支付单号
 * @property int $order_id 订单ID
 * @property string $order_no 订单号
 * @property string $payment_channel 支付渠道：balance/wechat/alipay
 * @property string $pay_method 支付方式：JSAPI/H5/NATIVE等
 * @property int $pay_amount_cent 支付金额(分)
 * @property string|null $transaction_id 第三方支付流水号
 * @property int $pay_status 支付状态：0待支付 1支付成功 2支付失败 3已退款
 * @property string|null $paid_at 支付成功时间
 * @property string|null $notify_content 支付回调原始内容
 * @property int|null $refund_amount_cent 退款金额(分)
 * @property string|null $refunded_at 退款时间
 * @property string|null $refund_reason 退款原因
 * @property int|null $balance_transaction_id 余额资金流水ID
 * @property string|null $idempotent_key 幂等键
 * @property int|null $transaction_subject_type 交易主体类型：1门店 2城市合伙人
 * @property int|null $transaction_subject_id 交易主体ID
 */
class Payment extends BaseModel
{
    protected $table = 'lj_payment';

    // 该表不使用软删除，财务表用 status 管理
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'order_id' => 'integer',
        'payment_channel' => 'string',
        'pay_amount_cent' => 'integer',
        'pay_status' => 'integer',
        'balance_transaction_id' => 'integer',
        'refund_amount_cent' => 'integer',
        'transaction_subject_type' => 'integer',
        'transaction_subject_id' => 'integer',
    ];

    /**
     * 关联订单
     */
    public function order(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 关联余额流水
     */
    public function balanceTransaction(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(CustomerBalanceTransaction::class, 'balance_transaction_id', 'id');
    }

    /**
     * 支付成功筛选
     */
    public function scopePaid($query): void
    {
        $query->where('pay_status', 1);
    }

    /**
     * 按支付状态筛选
     */
    public function scopeOfStatus($query, int $status): void
    {
        $query->where('pay_status', $status);
    }

    /**
     * 按订单筛选
     */
    public function scopeOfOrder($query, int $orderId): void
    {
        $query->where('order_id', $orderId);
    }

    /**
     * 是否已支付
     */
    public function isPaid(): bool
    {
        return $this->pay_status === 1;
    }

    /**
     * 是否已退款
     */
    public function isRefunded(): bool
    {
        return $this->pay_status === 3;
    }
}
