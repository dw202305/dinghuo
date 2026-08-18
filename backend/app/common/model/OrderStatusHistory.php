<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 订单状态历史模型
 *
 * 对应 deploy/mysql/init.sql 2.27 lj_order_status_history。
 * 该表仅有 created_at（无 updated_at/deleted_at），关闭更新时间戳。
 *
 * @property int $id
 * @property int $order_id 订单ID
 * @property string $order_no 订单号
 * @property string $from_status 变更前状态（创建时为空串）
 * @property string $to_status 变更后状态
 * @property string $action 触发动作
 * @property string $role 操作角色：store/admin/system等
 * @property string|null $reason 原因
 * @property int|null $operator_id 操作人ID
 * @property string $created_at 创建时间
 */
class OrderStatusHistory extends BaseModel
{
    protected $table = 'lj_order_status_history';

    // 该表无 updated_at 列
    protected $updateTime = false;

    // 该表无 deleted_at 列
    protected $deleteTime = false;

    /**
     * 关联订单
     */
    public function order(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
