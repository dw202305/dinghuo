<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\OrderStatus;
use app\common\exception\CodedValidateException;
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
        'payment_processing:pending_payment' => [
            'from'   => OrderStatus::PAYING,
            'to'     => OrderStatus::PENDING_PAY,
            'roles'  => ['system'],
            'action' => '支付失败/超时回退待支付',
            'condition' => '渠道调用失败、支付单过期或支付失败补偿',
            'sideEffect' => null,
        ],
        'payment_processing:cancelled' => [
            'from'   => OrderStatus::PAYING,
            'to'     => OrderStatus::CANCELLED,
            'roles'  => ['system', 'admin'],
            'action' => '支付中取消订单',
            'condition' => '支付处理中放弃支付（system/admin 补偿取消）',
            'sideEffect' => '释放锁定库存',
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
            throw new CodedValidateException(
                "非法状态转换：{$currentStatus->label()}({$currentStatus->value}) → {$targetStatus->label()}({$targetStatus->value})",
                4003
            );
        }

        // 角色校验
        if (!in_array($role, $transition['roles'], true)) {
            throw new CodedValidateException(
                "无权限执行此状态转换：角色 {$role} 不允许执行 {$transition['action']}",
                4003
            );
        }

        // 业务条件校验（可扩展为回调）
        if (!$this->validateTransitionCondition($order, $transition, $context)) {
            throw new CodedValidateException(
                "状态转换条件不满足：{$transition['action']}",
                4003
            );
        }

        // 执行转换（事务内）
        return $this->transaction(function () use ($order, $currentStatus, $targetStatus, $transition, $role, $context) {
            // 并发护栏：事务内行锁重读订单，以最新状态重新校验矩阵，
            // 防止取消/回调等并发路径基于过期快照互相覆盖（评审 Warning 5）
            $lockedOrder = Order::where('id', $order->id)->lock(true)->find();
            if (!$lockedOrder) {
                throw new CodedValidateException('订单不存在', 4003);
            }

            $latestStatus = OrderStatus::from((int) $lockedOrder->order_status);
            if ($latestStatus->value !== $currentStatus->value) {
                // 状态已被并发变更：以最新状态重校验转换矩阵与角色
                $latestKey = $this->buildTransitionKey($latestStatus, $targetStatus);
                $latestTransition = $this->transitions[$latestKey] ?? null;
                if (!$latestTransition || !in_array($role, $latestTransition['roles'], true)) {
                    throw new CodedValidateException(
                        "非法状态转换：{$latestStatus->label()}({$latestStatus->value}) → {$targetStatus->label()}({$targetStatus->value})",
                        4003
                    );
                }
                $currentStatus = $latestStatus;
                $transition = $latestTransition;
            }

            // 业务条件校验（行锁内重校验）
            if (!$this->validateTransitionCondition($lockedOrder, $transition, $context)) {
                throw new CodedValidateException(
                    "状态转换条件不满足：{$transition['action']}",
                    4003
                );
            }

            $oldStatus = $lockedOrder->order_status;

            // 更新订单状态
            $lockedOrder->save(['order_status' => $targetStatus->value]);
            // 同步入参模型状态，供链式转换（如 PAYING→PAID_PENDING）读取最新值
            $order->order_status = $targetStatus->value;

            // 写入状态历史（lj_order_status_history）
            $this->writeStatusHistory($lockedOrder, $currentStatus, $targetStatus, $transition, $role, $context);

            // 执行副作用
            $this->executeSideEffect($lockedOrder, $currentStatus, $targetStatus, $transition, $context);

            // 记录操作日志
            $this->logOperation(
                module: 'order_state',
                action: $transition['action'],
                targetType: 'order',
                targetId: (int) $lockedOrder->id,
                targetNo: (string) $lockedOrder->order_no,
                beforeData: ['order_status' => $oldStatus, 'status_label' => $currentStatus->label()],
                afterData: ['order_status' => $targetStatus->value, 'status_label' => $targetStatus->label()],
                operatorId: (int) ($context['operator_id'] ?? 0),
                operatorName: (string) ($context['operator_name'] ?? ''),
                operatorRole: $role,
                remark: $context['reason'] ?? $transition['action'],
            );

            Log::info('订单状态变更', [
                'order_no' => $lockedOrder->order_no,
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
            throw new CodedValidateException(
                "非法子单状态转换：{$currentStatus} → {$targetProductionStatus}",
                4003
            );
        }

        return $this->transaction(function () use ($item, $currentStatus, $targetProductionStatus, $role, $context) {
            // 更新子单生产状态
            $item->save([
                'production_status' => $targetProductionStatus,
            ]);

            // 子单状态历史：deploy/init.sql 未定义子单状态历史表，仅主订单写 lj_order_status_history

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
            // 子单 production_status 取值范围为 0~4（最大 4=已发货），
            // 子单维度无独立签收态，全部已发货即视为子单完成（评审 Critical 1）
            if ($status >= 4) { // 已完成（全部已发货）
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

            // 写入主单状态历史（聚合为系统行为，角色 system；writeStatusHistory 为 6 参签名）
            $this->writeStatusHistory($order, $currentOrderStatus, $newOrderStatus, [
                'action' => '子单聚合自动更新',
                'sideEffect' => null,
            ], 'system', [
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
     * 写入状态变更历史（lj_order_status_history）
     *
     * 表无 updated_at/deleted_at 列，直接 Db 写入（批次2c）。
     * 状态值存小写状态 key（如 pending_payment），与转换矩阵 key 一致。
     *
     * @param Order $order 订单
     * @param OrderStatus $from 原状态
     * @param OrderStatus $to 目标状态
     * @param array $transition 转换规则
     * @param string $role 操作角色
     * @param array $context 上下文
     * @return void
     */
    private function writeStatusHistory(Order $order, OrderStatus $from, OrderStatus $to, array $transition, string $role, array $context): void
    {
        Db::name('order_status_history')->insert([
            'order_id'    => (int) $order->id,
            'order_no'    => (string) $order->order_no,
            'from_status' => strtolower($from->name),
            'to_status'   => strtolower($to->name),
            'action'      => (string) ($transition['action'] ?? ''),
            'role'        => $role,
            'reason'      => isset($context['reason']) ? (string) $context['reason'] : null,
            'operator_id' => isset($context['operator_id']) ? (int) $context['operator_id'] : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
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
            case '特殊取消（需权限）':
            case '支付中取消订单':
                // 释放锁定库存（批次4：实接 InventoryService）
                $this->triggerInventoryRelease($order, $context);
                break;

            case '支付成功':
                // 锁定价格30天
                $now = date('Y-m-d H:i:s');
                $order->save([
                    'price_locked_at'    => $now,
                    'price_locked_until' => date('Y-m-d H:i:s', strtotime('+30 days')),
                ]);
                // 核销库存（锁定→已消耗）：批次4 实接 InventoryService，
                // 余额/第三方支付路径在此统一触发（失败抛异常回滚转换）
                $this->triggerInventoryConsume($order, $context);
                break;

            case '提交订单':
                // 锁定库存（批次4：实接 InventoryService）
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
     * 批次4：实接 InventoryService::lockInventory。遍历订单 use_inventory
     * 明细，kit_sku 由 kit_id → lj_kit.kit_sku 动态取；幂等键
     * lock:{order_no}:{item_id}（与 OrderService::submitOrder 直调同键，
     * 重复触发幂等空转）。锁定失败抛业务异常回滚状态转换。
     *
     * @param Order $order 订单
     * @param array $context 上下文
     * @return void
     * @throws ValidateException
     */
    private function triggerInventoryLock(Order $order, array $context): void
    {
        $items = OrderItem::where('order_id', $order->id)
            ->where('use_inventory', 1)
            ->select();

        if ($items->isEmpty()) {
            return;
        }

        $storeId = $this->inventoryStoreId($order);
        $inventoryService = app(InventoryService::class);

        foreach ($items as $item) {
            $kitSku = $this->kitSkuOfItem((int) $item->kit_id);
            if ($kitSku === '') {
                throw new CodedValidateException(
                    "库存锁定失败：窗帘明细 {$item->item_no} 未关联有效套件",
                    4001
                );
            }

            $inventoryService->lockInventory(
                $storeId,
                $kitSku,
                1,
                (int) $order->id,
                (string) $order->order_no,
                "lock:{$order->order_no}:{$item->id}",
            );
        }
    }

    /**
     * 触发库存释放（取消订单时）
     *
     * 批次4：实接 InventoryService::releaseInventory。逐明细守卫：
     * - 无对应 lock 流水（从未锁定，如草稿取消）→ 跳过；
     * - 已有 consume 流水（已核销）→ 跳过，库存退回属退款/售后
     *   流程（REFUND_RETURN），不在此处理。
     * 释放失败抛异常回滚取消转换（库存完整性优先）。
     *
     * @param Order $order 订单
     * @param array $context 上下文
     * @return void
     * @throws ValidateException
     */
    private function triggerInventoryRelease(Order $order, array $context): void
    {
        $items = OrderItem::where('order_id', $order->id)
            ->where('use_inventory', 1)
            ->select();

        if ($items->isEmpty()) {
            return;
        }

        $storeId = $this->inventoryStoreId($order);
        $inventoryService = app(InventoryService::class);

        foreach ($items as $item) {
            // 从未锁定（如草稿取消）：无锁定流水则跳过
            $lockLogExists = Db::name('inventory_log')
                ->where('idempotent_key', "lock:{$order->order_no}:{$item->id}")
                ->find();
            if (!$lockLogExists) {
                continue;
            }

            // 已核销：释放不适用于已消耗库存，留待退款/售后流程处理
            $consumedLogExists = Db::name('inventory_log')
                ->where('idempotent_key', "consume:{$order->order_no}:{$item->id}")
                ->find();
            if ($consumedLogExists) {
                Log::info('取消释放跳过：明细已核销，库存退回转入退款/售后流程', [
                    'order_no' => $order->order_no,
                    'item_id'  => (int) $item->id,
                ]);
                continue;
            }

            $kitSku = $this->kitSkuOfItem((int) $item->kit_id);
            if ($kitSku === '') {
                throw new CodedValidateException(
                    "库存释放失败：窗帘明细 {$item->item_no} 未关联有效套件",
                    4001
                );
            }

            $inventoryService->releaseInventory(
                $storeId,
                $kitSku,
                1,
                (int) $order->id,
                (string) $order->order_no,
                "release:{$order->order_no}:{$item->id}",
            );
        }
    }

    /**
     * 触发库存核销（支付成功时：锁定 → 已消耗）
     *
     * 批次4：实接 InventoryService::consumeInventory，替换
     * PaymentService::consumeInventoryOnPaid 旧实现（硬编码/无幂等）。
     * kit_sku 由 kit_id → lj_kit.kit_sku 动态取；数量为明细真实抵扣数
     * （每副 use_inventory 明细 1 套件）；前后库存量由行锁后真实值记录；
     * 幂等键 consume:{order_no}:{item_id}（重复回调/重复转换不重复核销）。
     * 核销失败抛异常回滚支付成功转换（第三方会重试回调）。
     *
     * @param Order $order 订单
     * @param array $context 上下文
     * @return void
     * @throws ValidateException
     */
    private function triggerInventoryConsume(Order $order, array $context): void
    {
        $items = OrderItem::where('order_id', $order->id)
            ->where('use_inventory', 1)
            ->select();

        if ($items->isEmpty()) {
            return;
        }

        $storeId = $this->inventoryStoreId($order);
        $inventoryService = app(InventoryService::class);

        foreach ($items as $item) {
            $kitSku = $this->kitSkuOfItem((int) $item->kit_id);
            if ($kitSku === '') {
                // 与 lock/release 对称：缺失套件时抛异常回滚支付成功转换，
                // 第三方会重试回调，避免静默跳过造成库存漏核销（评审 Warning 10）
                throw new CodedValidateException(
                    "支付核销库存失败：窗帘明细 {$item->item_no} 未关联有效套件",
                    4001
                );
            }

            $inventoryService->consumeInventory(
                $storeId,
                $kitSku,
                1,
                (int) $order->id,
                (string) $order->order_no,
                "consume:{$order->order_no}:{$item->id}",
            );
        }
    }

    /**
     * 库存操作门店归属：实际服务门店优先，合伙人自营订单回退交易主体
     *
     * @param Order $order 订单
     * @return int
     */
    private function inventoryStoreId(Order $order): int
    {
        $serviceStoreId = (int) ($order->service_store_id ?? 0);

        return $serviceStoreId > 0 ? $serviceStoreId : (int) $order->transaction_id;
    }

    /**
     * 由套件ID取套件SKU（lj_kit.kit_sku，批次2c 列名）
     *
     * @param int $kitId 套件ID
     * @return string 无效时返回空串
     */
    private function kitSkuOfItem(int $kitId): string
    {
        if ($kitId <= 0) {
            return '';
        }

        $kit = Db::name('kit')->where('id', $kitId)->find();

        return (string) ($kit['kit_sku'] ?? '');
    }
}
