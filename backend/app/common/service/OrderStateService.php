<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\OrderStatus;
use app\common\model\Order;
use app\common\model\OrderItem;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 订单状态机服务
 *
 * 所有订单状态变更必须通过此 Service，禁止直接修改 status 字段（规范 10.1）。
 * 每次状态转换写入状态历史表 lj_order_status_history。
 * 支持子单（OrderItem）独立状态管理及聚合（规范 10.2）。
 *
 * 错误码：4003 非法订单状态转换（规范 14.3）
 *
 * @see docs/dev_specification_v1.0.md 第十节
 * @see docs/prd_v3.2.md 第六节
 */
class OrderStateService extends BaseService
{
    /**
     * 合法状态转换矩阵
     *
     * 每个转换定义：
     * - from: 源状态
     * - to: 目标状态
     * - roles: 允许执行的角色
     * - condition: 条件描述
     * - sideEffect: 副作用描述
     *
     * @var array<string, array>
     */
    private array $transitions = [
        // ===== 下单流程 =====
        'draft:pending_payment' => [
            'from'   => OrderStatus::DRAFT,
            'to'     => OrderStatus::PENDING_PAY,
            'roles'  => ['store', 'admin'],
            'action' => '提交订单',
            'condition' => '订单明细不为空，价格已计算',
            'sideEffect' => '锁定库存，生成支付超时计时',
        ],
        'pending_payment:payment_processing' => [
            'from'   => OrderStatus::PENDING_PAY,
            'to'     => OrderStatus::PAYING,
            'roles'  => ['store', 'admin'],
            'action' => '发起支付',
            'condition' => '订单金额 > 0',
            'sideEffect' => '创建支付单，拉起第三方支付',
        ],
        'pending_payment:cancelled' => [
            'from'   => OrderStatus::PENDING_PAY,
            'to'     => OrderStatus::CANCELLED,
            'roles'  => ['store', 'admin', 'system'],
            'action' => '取消订单（超时/手动）',
            'condition' => '超时30天或门店手动取消',
            'sideEffect' => '释放锁定库存',
        ],
        // ===== 预审流程 =====
        'pending_payment:paid_pending_review' => [
            'from'   => OrderStatus::PENDING_PAY,
            'to'     => OrderStatus::PAID_PENDING,
            'roles'  => ['admin'],
            'action' => '切换预审流程（进入审核中）',
            'condition' => 'audit_type=pre_audit，后台管理员切换',
            'sideEffect' => null,
        ],
        'paid_pending_review:pending_payment' => [
            'from'   => OrderStatus::PAID_PENDING,
            'to'     => OrderStatus::PENDING_PAY,
            'roles'  => ['admin', 'technical'],
            'action' => '预审通过（等待支付）',
            'condition' => 'audit_type=pre_audit，审核通过',
            'sideEffect' => '锁定价格和库存，启动30天支付倒计时',
        ],

        // ===== 支付后流程 =====
        'payment_processing:paid_pending_review' => [
            'from'   => OrderStatus::PAYING,
            'to'     => OrderStatus::PAID_PENDING,
            'roles'  => ['system'],
            'action' => '支付成功',
            'condition' => '支付回调验签通过，金额匹配',
            'sideEffect' => '核销库存，锁定价格30天',
        ],
        'paid_pending_review:needs_store_confirm' => [
            'from'   => OrderStatus::PAID_PENDING,
            'to'     => OrderStatus::NEED_CONFIRM,
            'roles'  => ['admin', 'technical'],
            'action' => '需要门店确认',
            'condition' => '技术审核发现问题需门店确认',
            'sideEffect' => '通知门店',
        ],
        'paid_pending_review:pending_supplement' => [
            'from'   => OrderStatus::PAID_PENDING,
            'to'     => OrderStatus::NEED_SUPPLEMENT,
            'roles'  => ['admin', 'technical'],
            'action' => '需要补款',
            'condition' => '审核后需补款',
            'sideEffect' => '生成补款单',
        ],
        'paid_pending_review:approved_pending_production' => [
            'from'   => OrderStatus::PAID_PENDING,
            'to'     => OrderStatus::APPROVED,
            'roles'  => ['admin', 'technical'],
            'action' => '审核通过',
            'condition' => '技术审核通过',
            'sideEffect' => '进入待排产队列',
        ],
        'needs_store_confirm:pending_supplement' => [
            'from'   => OrderStatus::NEED_CONFIRM,
            'to'     => OrderStatus::NEED_SUPPLEMENT,
            'roles'  => ['admin', 'technical'],
            'action' => '确认补款',
            'condition' => '门店确认后需补款',
            'sideEffect' => '生成补款单',
        ],
        'needs_store_confirm:approved_pending_production' => [
            'from'   => OrderStatus::NEED_CONFIRM,
            'to'     => OrderStatus::APPROVED,
            'roles'  => ['admin', 'technical'],
            'action' => '确认通过',
            'condition' => '门店确认后审核通过',
            'sideEffect' => '进入待排产队列',
        ],
        'pending_supplement:paid_pending_review' => [
            'from'   => OrderStatus::NEED_SUPPLEMENT,
            'to'     => OrderStatus::PAID_PENDING,
            'roles'  => ['system'],
            'action' => '补款完成',
            'condition' => '补款支付成功',
            'sideEffect' => '恢复审核流程',
        ],
        // ===== 生产流程 =====
        'approved_pending_production:in_production' => [
            'from'   => OrderStatus::APPROVED,
            'to'     => OrderStatus::PRODUCING,
            'roles'  => ['admin', 'production'],
            'action' => '开始生产',
            'condition' => '排产完成',
            'sideEffect' => '分配套件编号，记录生产批次',
        ],
        'in_production:in_quality_check' => [
            'from'   => OrderStatus::PRODUCING,
            'to'     => OrderStatus::QC,
            'roles'  => ['admin', 'production', 'qc'],
            'action' => '质检',
            'condition' => '生产完成进入质检',
            'sideEffect' => null,
        ],
        'in_quality_check:pending_shipment' => [
            'from'   => OrderStatus::QC,
            'to'     => OrderStatus::PENDING_SHIP,
            'roles'  => ['admin', 'qc'],
            'action' => '质检通过',
            'condition' => '质检合格',
            'sideEffect' => '进入待发货队列',
        ],
        // ===== 发货流程 =====
        'pending_shipment:partial_shipment' => [
            'from'   => OrderStatus::PENDING_SHIP,
            'to'     => OrderStatus::PARTIAL_SHIP,
            'roles'  => ['admin', 'warehouse'],
            'action' => '部分发货',
            'condition' => '部分明细已发货',
            'sideEffect' => '记录物流单号',
        ],
        'partial_shipment:partial_shipment' => [
            'from'   => OrderStatus::PARTIAL_SHIP,
            'to'     => OrderStatus::PARTIAL_SHIP,
            'roles'  => ['admin', 'warehouse'],
            'action' => '继续发货',
            'condition' => '更多明细发货',
            'sideEffect' => '追加物流单号',
        ],
        'pending_shipment:shipped' => [
            'from'   => OrderStatus::PENDING_SHIP,
            'to'     => OrderStatus::SHIPPED,
            'roles'  => ['admin', 'warehouse'],
            'action' => '全部发货',
            'condition' => '所有明细已发货',
            'sideEffect' => '记录所有物流单号',
        ],
        'partial_shipment:shipped' => [
            'from'   => OrderStatus::PARTIAL_SHIP,
            'to'     => OrderStatus::SHIPPED,
            'roles'  => ['admin', 'warehouse'],
            'action' => '全部发货完成',
            'condition' => '剩余明细全部发货',
            'sideEffect' => '追加物流单号',
        ],
        // ===== 签收完成流程 =====
        'shipped:received' => [
            'from'   => OrderStatus::SHIPPED,
            'to'     => OrderStatus::RECEIVED,
            'roles'  => ['store', 'admin', 'system'],
            'action' => '签收',
            'condition' => '门店确认签收或物流签收后自动确认',
            'sideEffect' => '开始计算发票申请时间',
        ],
        'received:completed' => [
            'from'   => OrderStatus::RECEIVED,
            'to'     => OrderStatus::COMPLETED,
            'roles'  => ['store', 'admin', 'system'],
            'action' => '完成',
            'condition' => '签收后无售后',
            'sideEffect' => '订单归档',
        ],
        // ===== 售后流程 =====
        'completed:after_sale_processing' => [
            'from'   => OrderStatus::COMPLETED,
            'to'     => OrderStatus::AFTER_SALE,
            'roles'  => ['store', 'admin'],
            'action' => '申请售后',
            'condition' => '在售后期限内',
            'sideEffect' => '创建售后工单',
        ],
        'shipped:after_sale_processing' => [
            'from'   => OrderStatus::SHIPPED,
            'to'     => OrderStatus::AFTER_SALE,
            'roles'  => ['store', 'admin'],
            'action' => '申请售后',
            'condition' => '签收前发现问题',
            'sideEffect' => '创建售后工单',
        ],
        'received:after_sale_processing' => [
            'from'   => OrderStatus::RECEIVED,
            'to'     => OrderStatus::AFTER_SALE,
            'roles'  => ['store', 'admin'],
            'action' => '申请售后',
            'condition' => '签收后发现质量问题',
            'sideEffect' => '创建售后工单',
        ],
        'after_sale_processing:refunding' => [
            'from'   => OrderStatus::AFTER_SALE,
            'to'     => OrderStatus::REFUNDING,
            'roles'  => ['admin', 'finance'],
            'action' => '退款',
            'condition' => '售后判定需退款，经审批',
            'sideEffect' => '发起退款流程',
        ],
        'after_sale_processing:completed' => [
            'from'   => OrderStatus::AFTER_SALE,
            'to'     => OrderStatus::COMPLETED,
            'roles'  => ['admin'],
            'action' => '售后完成（无需退款）',
            'condition' => '售后处理完毕，无需退款',
            'sideEffect' => '关闭售后工单',
        ],
        'refunding:refunded' => [
            'from'   => OrderStatus::REFUNDING,
            'to'     => OrderStatus::REFUNDED,
            'roles'  => ['system', 'finance'],
            'action' => '已退款',
            'condition' => '退款到账确认',
            'sideEffect' => '释放或退回相关库存',
        ],
        // ===== 取消流程（预生产阶段） =====
        'draft:cancelled' => [
            'from'   => OrderStatus::DRAFT,
            'to'     => OrderStatus::CANCELLED,
            'roles'  => ['store', 'admin'],
            'action' => '取消草稿订单',
            'condition' => null,
            'sideEffect' => null,
        ],
        'paid_pending_review:cancelled' => [
            'from'   => OrderStatus::PAID_PENDING,
            'to'     => OrderStatus::CANCELLED,
            'roles'  => ['admin'],
            'action' => '特殊取消（需权限）',
            'condition' => '管理员审批，需记录原因',
            'sideEffect' => '退款处理，库存释放',
        ],
        'needs_store_confirm:cancelled' => [
            'from'   => OrderStatus::NEED_CONFIRM,
            'to'     => OrderStatus::CANCELLED,
            'roles'  => ['admin'],
            'action' => '特殊取消（需权限）',
            'condition' => '管理员审批',
            'sideEffect' => '退款处理',
        ],
    ];

    /**
     * 子单独立生产/发货状态枚举
     *
     * 每副窗帘拥有独立的生产、质检和发货状态（PRD 10.2）
     */
    const ITEM_PRODUCTION_STATUS = [
        0 => '待排产',
        1 => '生产中',
        2 => '质检中',
        3 => '质检通过',
        4 => '已发货',
    ];

    /**
     * 执行订单状态转换
     *
     * 所有状态变更唯一入口。非法转换抛出业务异常（4003）。
     *
     * @param Order $order 订单模型
     * @param OrderStatus $targetStatus 目标状态
     * @param string $role 操作角色（store/admin/system/technical/production/warehouse/finance/qc）
     * @param array $context 额外上下文（reason, operator_id, operator_name 等）
     * @return bool
     * @throws ValidateException
     */
    public function transition(Order $order, OrderStatus $targetStatus, string $role, array $context = []): bool
    {
        $currentStatus = OrderStatus::from($order->order_status);

        // 查找转换规则
        $key = $this->buildTransitionKey($currentStatus, $targetStatus);
        $transition = $this->transitions[$key] ?? null;

        if (!$transition) {
            throw new ValidateException(
                "非法状态转换：{$currentStatus->label()}({$currentStatus->value}) → {$targetStatus->label()}({$targetStatus->value})",
                4003
            );
        }

        // 角色校验
        if (!in_array($role, $transition['roles'], true)) {
            throw new ValidateException(
                "无权限执行此状态转换：角色 {$role} 不允许执行 {$transition['action']}",
                4003
            );
        }

        // 业务条件校验（可扩展为回调）
        if (!$this->validateTransitionCondition($order, $transition, $context)) {
            throw new ValidateException(
                "状态转换条件不满足：{$transition['action']}",
                4003
            );
        }

        // 执行转换（事务内）
        return $this->transaction(function () use ($order, $currentStatus, $targetStatus, $transition, $context) {
            $oldStatus = $order->order_status;

            // 更新订单状态
            $order->save(['order_status' => $targetStatus->value]);

            // 写入状态历史
            $this->writeStatusHistory($order, $currentStatus, $targetStatus, $transition, $context);

            // 执行副作用
            $this->executeSideEffect($order, $currentStatus, $targetStatus, $transition, $context);

            // 记录操作日志
            $this->logOperation(
                module: 'order_state',
                action: $transition['action'],
                targetType: 'order',
                targetId: (int) $order->id,
                targetNo: $order->order_no,
                beforeData: ['order_status' => $oldStatus, 'status_label' => $currentStatus->label()],
                afterData: ['order_status' => $targetStatus->value, 'status_label' => $targetStatus->label()],
                operatorId: (int) ($context['operator_id'] ?? 0),
                operatorName: (string) ($context['operator_name'] ?? ''),
                operatorRole: $role,
                remark: $context['reason'] ?? $transition['action'],
            );

            Log::info('订单状态变更', [
                'order_no' => $order->order_no,
                'from'     => $currentStatus->label(),
                'to'       => $targetStatus->label(),
                'action'   => $transition['action'],
                'role'     => $role,
            ]);

            return true;
        });
    }

    /**
     * 执行子单状态转换
     *
     * 每副窗帘拥有独立生产和发货状态（规范 10.2）。
     * 子单状态变化后，自动聚合更新主订单状态。
     *
     * @param OrderItem $item 窗帘明细
     * @param int $targetProductionStatus 目标生产状态
     * @param string $role 操作角色
     * @param array $context 上下文
     * @return bool
     * @throws ValidateException
     */
    public function transitionItem(OrderItem $item, int $targetProductionStatus, string $role, array $context = []): bool
    {
        $currentStatus = (int) $item->production_status;

        // 子单生产状态合法转换
        $validTransitions = $this->getItemTransitions();
        $key = "{$currentStatus}:{$targetProductionStatus}";

        if (!isset($validTransitions[$key])) {
            throw new ValidateException(
                "非法子单状态转换：{$currentStatus} → {$targetProductionStatus}",
                4003
            );
        }

        return $this->transaction(function () use ($item, $currentStatus, $targetProductionStatus, $role, $context) {
            // 更新子单生产状态
            $item->save([
                'production_status' => $targetProductionStatus,
            ]);

            // TODO: 子单状态历史表待 database.md 补充后启用
            // Db::name('order_item_status_history')->insert([...]);

            // 聚合更新主订单状态
            $this->aggregateOrderStatusFromItems((int) $item->order_id);

            return true;
        });
    }

    /**
     * 聚合子单状态，更新主订单状态
     *
     * 聚合规则（规范 10.2）：
     * - 所有子单已发货 → 主单已发货
     * - 部分子单已发货 → 主单部分发货
     * - 所有子单已完成 → 主单已完成
     *
     * @param int $orderId 订单ID
     * @return void
     */
    public function aggregateOrderStatusFromItems(int $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order) {
            return;
        }

        $items = OrderItem::where('order_id', $orderId)->select();
        if ($items->isEmpty()) {
            return;
        }

        $totalItems = count($items);
        $shippedCount = 0;
        $completedCount = 0;

        foreach ($items as $item) {
            $status = (int) $item->production_status;
            if ($status >= 4) { // 已发货
                $shippedCount++;
            }
            if ($status >= 5) { // 已完成（签收）
                $completedCount++;
            }
        }

        // 聚合逻辑
        $currentOrderStatus = OrderStatus::from($order->order_status);
        $newOrderStatus = null;

        if ($completedCount === $totalItems && $currentOrderStatus->value < OrderStatus::COMPLETED->value) {
            $newOrderStatus = OrderStatus::COMPLETED;
        } elseif ($shippedCount === $totalItems && !in_array($currentOrderStatus, [OrderStatus::SHIPPED, OrderStatus::RECEIVED, OrderStatus::COMPLETED])) {
            $newOrderStatus = OrderStatus::SHIPPED;
        } elseif ($shippedCount > 0 && $shippedCount < $totalItems) {
            $newOrderStatus = OrderStatus::PARTIAL_SHIP;
        }

        if ($newOrderStatus !== null && $newOrderStatus->value !== $currentOrderStatus->value) {
            $order->save(['order_status' => $newOrderStatus->value]);

            // 写入主单状态历史
            $this->writeStatusHistory($order, $currentOrderStatus, $newOrderStatus, [
                'action' => '子单聚合自动更新',
                'sideEffect' => null,
            ], [
                'reason' => "子单发货状态聚合：{$shippedCount}/{$totalItems} 已发货",
                'auto_aggregate' => true,
            ]);
        }
    }

    /**
     * 获取订单状态转换历史
     *
     * @param int $orderId 订单ID
     * @return array
     */
    public function getStatusHistory(int $orderId): array
    {
        // TODO: 状态历史表待 database.md 补充后启用
        return [];
    }

    /**
     * 获取指定订单的合法下一步状态
     *
     * @param OrderStatus $currentStatus 当前状态
     * @param string $role 当前操作角色
     * @return array<OrderStatus>
     */
    public function getAvailableTransitions(OrderStatus $currentStatus, string $role = ''): array
    {
        $available = [];

        foreach ($this->transitions as $key => $transition) {
            if ($transition['from']->value !== $currentStatus->value) {
                continue;
            }
            if ($role !== '' && !in_array($role, $transition['roles'], true)) {
                continue;
            }
            $available[] = [
                'status'      => $transition['to'],
                'action'      => $transition['action'],
                'condition'   => $transition['condition'],
                'side_effect' => $transition['sideEffect'],
            ];
        }

        return $available;
    }

    /**
     * 确保状态历史表存在
     *
     * 如表不存在则自动建表（Migration 优先使用 Migration）。
     *
     * @return void
     */
    public function ensureStatusHistoryTable(): void
    {
        // TODO: lj_order_status_history / lj_order_item_status_history 待 database.md 补充后启用
        return;
    }

    /**
     * 写入状态变更历史
     *
     * @param Order $order 订单
     * @param OrderStatus $from 原状态
     * @param OrderStatus $to 目标状态
     * @param array $transition 转换规则
     * @param array $context 上下文
     * @return void
     */
    private function writeStatusHistory(Order $order, OrderStatus $from, OrderStatus $to, array $transition, array $context): void
    {
        // TODO: 状态历史表待 database.md 补充后启用
        \think\facade\Log::info("OrderStatus: {$order->order_no} {$from->label()} -> {$to->label()}");

    }
    /**
     * 执行状态转换副作用
     *
     * @param Order $order 订单
     * @param OrderStatus $from 原状态
     * @param OrderStatus $to 目标状态
     * @param array $transition 转换规则
     * @param array $context 上下文
     * @return void
     */
    private function executeSideEffect(Order $order, OrderStatus $from, OrderStatus $to, array $transition, array $context): void
    {
        $action = $transition['action'] ?? '';

        switch ($action) {
            case '取消订单（超时/手动）':
            case '取消草稿订单':
                // 释放锁定库存
                $this->triggerInventoryRelease($order, $context);
                break;

            case '支付成功':
                // 锁定价格30天
                $now = date('Y-m-d H:i:s');
                $order->save([
                    'price_locked_at'    => $now,
                    'price_locked_until' => date('Y-m-d H:i:s', strtotime('+30 days')),
                ]);
                // 核销库存（锁定→已消耗）由 PaymentService 调用 InventoryService 处理
                break;

            case '提交订单':
                // 锁定库存
                $this->triggerInventoryLock($order, $context);
                break;
        }
    }

    /**
     * 校验转换条件是否满足
     *
     * @param Order $order 订单
     * @param array $transition 转换规则
     * @param array $context 上下文
     * @return bool
     */
    private function validateTransitionCondition(Order $order, array $transition, array $context): bool
    {
        $action = $transition['action'] ?? '';

        // 特殊取消需要原因
        if (str_contains($action, '特殊取消')) {
            if (empty($context['reason'])) {
                return false;
            }
        }

        // 提交订单需要明细
        if ($action === '提交订单') {
            $itemCount = OrderItem::where('order_id', $order->id)->count();
            if ($itemCount === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取子单生产状态合法转换
     *
     * @return array
     */
    private function getItemTransitions(): array
    {
        return [
            '0:1' => ['from' => 0, 'to' => 1, 'action' => '开始生产'],
            '1:2' => ['from' => 1, 'to' => 2, 'action' => '进入质检'],
            '2:3' => ['from' => 2, 'to' => 3, 'action' => '质检通过'],
            '3:4' => ['from' => 3, 'to' => 4, 'action' => '已发货'],
        ];
    }

    /**
     * 构建转换键名
     *
     * @param OrderStatus $from 源状态
     * @param OrderStatus $to 目标状态
     * @return string
     */
    private function buildTransitionKey(OrderStatus $from, OrderStatus $to): string
    {
        // 将枚举值映射为矩阵中的 key
        $nameMap = [
            OrderStatus::DRAFT->value          => 'draft',
            OrderStatus::PENDING_PAY->value    => 'pending_payment',
            OrderStatus::PAYING->value         => 'payment_processing',
            OrderStatus::PAID_PENDING->value   => 'paid_pending_review',
            OrderStatus::NEED_CONFIRM->value   => 'needs_store_confirm',
            OrderStatus::NEED_SUPPLEMENT->value => 'pending_supplement',
            OrderStatus::APPROVED->value       => 'approved_pending_production',
            OrderStatus::PRODUCING->value      => 'in_production',
            OrderStatus::QC->value             => 'in_quality_check',
            OrderStatus::PENDING_SHIP->value   => 'pending_shipment',
            OrderStatus::PARTIAL_SHIP->value   => 'partial_shipment',
            OrderStatus::SHIPPED->value        => 'shipped',
            OrderStatus::RECEIVED->value       => 'received',
            OrderStatus::COMPLETED->value      => 'completed',
            OrderStatus::AFTER_SALE->value     => 'after_sale_processing',
            OrderStatus::CANCELLED->value      => 'cancelled',
            OrderStatus::REFUNDING->value      => 'refunding',
            OrderStatus::REFUNDED->value       => 'refunded',
        ];

        $fromKey = $nameMap[$from->value] ?? 'unknown';
        $toKey   = $nameMap[$to->value] ?? 'unknown';

        return "{$fromKey}:{$toKey}";
    }

    /**
     * 触发库存锁定（提交订单时）
     *
     * @param Order $order 订单
     * @param array $context 上下文
     * @return void
     */
    private function triggerInventoryLock(Order $order, array $context): void
    {
        // 委托 InventoryService 处理，此处仅做示意
        // 实际实现时通过依赖注入获取 InventoryService
        Log::info('触发库存锁定', ['order_no' => $order->order_no]);
    }

    /**
     * 触发库存释放（取消订单时）
     *
     * @param Order $order 订单
     * @param array $context 上下文
     * @return void
     */
    private function triggerInventoryRelease(Order $order, array $context): void
    {
        Log::info('触发库存释放', ['order_no' => $order->order_no]);
    }
}
