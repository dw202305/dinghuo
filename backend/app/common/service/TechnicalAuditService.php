<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\enum\OrderStatus;
use app\common\exception\BusinessException;
use app\common\model\Order;
use think\facade\Cache;
use think\facade\Log;

/**
 * 技术审核服务
 *
 * 处理预审/后审双流程审核逻辑（PRD v3.2 §5.9）。
 * - 预审流程：提交 → 审核 → 通过后锁定价格/库存 → 支付 → 排产
 * - 后审流程：提交 → 锁定价格/库存 → 支付 → 审核 → 排产
 */
class TechnicalAuditService extends BaseService
{
    /** @var string Redis key 前缀：预审通过后30天支付超时 */
    private const REDIS_TIMEOUT_PREFIX = 'pre_audit:payment_timeout:';

    /** @var int 预审通过后支付超时秒数（30天） */
    private const PAYMENT_TIMEOUT_SECONDS = 30 * 24 * 3600;

    /** @var OrderStateService */
    private OrderStateService $stateService;

    public function __construct()
    {
        $this->stateService = app(OrderStateService::class);
    }

    /**
     * 门店申请预审
     *
     * 将订单标记为预审流程，订单状态保持 PENDING_PAY。
     *
     * @param string $orderNo 订单号
     * @param int $requestedBy 申请人ID（门店账号ID）
     * @return Order
     * @throws BusinessException
     */
    public function requestPreAudit(string $orderNo, int $requestedBy): Order
    {
        $order = $this->findOrderOrFail($orderNo);

        if ($order->isPreAudit()) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '该订单已经是预审流程');
        }

        $status = OrderStatus::from($order->order_status);
        if ($status !== OrderStatus::PENDING_PAY) {
            throw new BusinessException(
                ErrorCode::ILLEGAL_STATUS_TRANSITION,
                "只有待支付状态的订单可以申请预审，当前状态：{$status->label()}"
            );
        }

        $this->transaction(function () use ($order) {
            $order->save([
                'audit_type'   => 'pre_audit',
                'audit_status' => 0,
            ]);
        });

        $this->logOperation(
            module: 'technical_audit',
            action: '门店申请预审',
            targetType: 'order',
            targetId: (int) $order->id,
            targetNo: $order->order_no,
            operatorId: $requestedBy,
            operatorRole: 'store',
            remark: '门店主动申请预审流程',
        );

        Log::info('门店申请预审', ['order_no' => $orderNo, 'requested_by' => $requestedBy]);

        return $order;
    }

    /**
     * 后台管理员将订单切换为预审流程
     *
     * @param string $orderNo 订单号
     * @param int $adminId 管理员ID
     * @return Order
     * @throws BusinessException
     */
    public function switchToPreAudit(string $orderNo, int $adminId): Order
    {
        $order = $this->findOrderOrFail($orderNo);

        if ($order->isPreAudit()) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '该订单已经是预审流程');
        }

        $status = OrderStatus::from($order->order_status);
        if ($status !== OrderStatus::PENDING_PAY) {
            throw new BusinessException(
                ErrorCode::ILLEGAL_STATUS_TRANSITION,
                "只有待支付状态可切换为预审，当前状态：{$status->label()}"
            );
        }

        $this->transaction(function () use ($order) {
            $order->save([
                'audit_type'   => 'pre_audit',
                'audit_status' => 0,
            ]);
        });

        // 状态变更：PENDING_PAY → PAID_PENDING（审核中）
        $this->stateService->transition($order, OrderStatus::PAID_PENDING, 'admin', [
            'operator_id'   => $adminId,
            'operator_name' => '管理员',
            'reason'        => '切换为预审流程',
        ]);

        Log::info('订单切换为预审流程', ['order_no' => $orderNo, 'admin_id' => $adminId]);

        return $order;
    }

    /**
     * 提交审核结果
     *
     * @param string $orderNo 订单号
     * @param int $auditorId 审核人ID
     * @param string $result 审核结果：approved|needs_confirm|needs_supplement|cannot_produce
     * @param array $data 附加数据（如补充金额、审核意见等）
     * @return Order
     * @throws BusinessException
     */
    public function submitAuditResult(string $orderNo, int $auditorId, string $result, array $data = []): Order
    {
        $order = $this->findOrderOrFail($orderNo);

        $this->validateAuditResult($result);

        $status = OrderStatus::from($order->order_status);
        if ($status !== OrderStatus::PAID_PENDING) {
            throw new BusinessException(
                ErrorCode::ILLEGAL_STATUS_TRANSITION,
                "只有审核中状态可提交审核结果，当前状态：{$status->label()}"
            );
        }

        $auditStatusMap = [
            'approved'        => 1,
            'needs_confirm'   => 2,
            'needs_supplement' => 3,
            'cannot_produce'  => 4,
        ];

        $auditStatus = $auditStatusMap[$result];

        $this->transaction(function () use ($order, $auditStatus, $data) {
            $order->save([
                'audit_status' => $auditStatus,
                'audit_remark' => $data['remark'] ?? '',
            ]);
        });

        // 根据审核结果和流程类型执行状态转换
        if ($order->isPreAudit()) {
            $this->handlePreAuditResult($order, $result, $auditorId, $data);
        } else {
            $this->handlePostAuditResult($order, $result, $auditorId, $data);
        }

        Log::info('审核结果提交', [
            'order_no' => $orderNo,
            'result'   => $result,
            'auditor'  => $auditorId,
            'is_pre'   => $order->isPreAudit(),
        ]);

        return $order->fresh();
    }

    /**
     * 获取审核详情
     *
     * @param string $orderNo 订单号
     * @return array
     * @throws BusinessException
     */
    public function getAuditDetail(string $orderNo): array
    {
        $order = $this->findOrderOrFail($orderNo);

        $isPreAudit  = $order->isPreAudit();
        $timeoutKey  = self::REDIS_TIMEOUT_PREFIX . $order->order_no;
        $timeoutAt   = Cache::get($timeoutKey);

        return [
            'order_no'         => $order->order_no,
            'audit_type'       => $order->audit_type,
            'audit_type_label' => $isPreAudit ? '预审' : '后审',
            'audit_status'     => (int) $order->audit_status,
            'audit_status_label' => $this->getAuditStatusLabel((int) $order->audit_status),
            'audit_remark'     => $order->audit_remark ?? '',
            'order_status'     => (int) $order->order_status,
            'order_status_label' => OrderStatus::from($order->order_status)->label(),
            'price_locked_at'  => $order->price_locked_at,
            'price_locked_until' => $order->price_locked_until,
            'is_pre_audit'     => $isPreAudit,
            'payment_timeout_at' => $timeoutAt ?: null,
        ];
    }

    /**
     * 检查预审通过后的支付超时
     *
     * @param string $orderNo 订单号
     * @return array
     * @throws BusinessException
     */
    public function checkPaymentTimeout(string $orderNo): array
    {
        $order = $this->findOrderOrFail($orderNo);

        if (!$order->isPreAudit()) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '仅预审订单支持此检查');
        }

        $timeoutKey = self::REDIS_TIMEOUT_PREFIX . $orderNo;
        $timeoutAt  = Cache::get($timeoutKey);

        if (!$timeoutAt) {
            return [
                'order_no'    => $orderNo,
                'is_timed_out' => false,
                'message'     => '未设置支付超时',
            ];
        }

        $isTimedOut = time() > strtotime($timeoutAt);
        $remainingSeconds = max(0, strtotime($timeoutAt) - time());

        return [
            'order_no'           => $orderNo,
            'is_timed_out'       => $isTimedOut,
            'timeout_at'         => $timeoutAt,
            'remaining_seconds'  => $remainingSeconds,
            'remaining_days'     => round($remainingSeconds / 86400, 1),
            'message'            => $isTimedOut ? '支付已超时' : "剩余 {$remainingSeconds} 秒",
        ];
    }

    /**
     * 处理预审审核结果
     */
    private function handlePreAuditResult(Order $order, string $result, int $auditorId, array $data): void
    {
        switch ($result) {
            case 'approved':
                // 预审通过 → 状态回到 PENDING_PAY，锁定价格和库存，开始30天倒计时
                $this->stateService->transition($order, OrderStatus::PENDING_PAY, 'technical', [
                    'operator_id'   => $auditorId,
                    'operator_name' => $data['auditor_name'] ?? '审核员',
                    'reason'        => '预审通过，等待支付',
                ]);

                // 锁定价格30天
                $now = date('Y-m-d H:i:s');
                $lockedUntil = date('Y-m-d H:i:s', strtotime('+30 days'));
                $order->save([
                    'price_locked_at'    => $now,
                    'price_locked_until' => $lockedUntil,
                ]);

                // 记录支付超时
                $timeoutKey = self::REDIS_TIMEOUT_PREFIX . $order->order_no;
                Cache::set($timeoutKey, $lockedUntil, self::PAYMENT_TIMEOUT_SECONDS);

                Log::info('预审通过，价格锁定30天', [
                    'order_no'    => $order->order_no,
                    'locked_until' => $lockedUntil,
                ]);
                break;

            case 'needs_confirm':
                $this->stateService->transition($order, OrderStatus::NEED_CONFIRM, 'technical', [
                    'operator_id'   => $auditorId,
                    'operator_name' => $data['auditor_name'] ?? '审核员',
                    'reason'        => $data['remark'] ?? '需要门店确认',
                ]);
                break;

            case 'needs_supplement':
                $this->stateService->transition($order, OrderStatus::NEED_SUPPLEMENT, 'technical', [
                    'operator_id'   => $auditorId,
                    'operator_name' => $data['auditor_name'] ?? '审核员',
                    'reason'        => $data['remark'] ?? '需要补款',
                ]);
                break;

            case 'cannot_produce':
                $this->stateService->transition($order, OrderStatus::CANCELLED, 'admin', [
                    'operator_id'   => $auditorId,
                    'operator_name' => $data['auditor_name'] ?? '审核员',
                    'reason'        => $data['remark'] ?? '无法生产',
                ]);
                break;
        }
    }

    /**
     * 处理后审审核结果
     */
    private function handlePostAuditResult(Order $order, string $result, int $auditorId, array $data): void
    {
        switch ($result) {
            case 'approved':
                $this->stateService->transition($order, OrderStatus::APPROVED, 'technical', [
                    'operator_id'   => $auditorId,
                    'operator_name' => $data['auditor_name'] ?? '审核员',
                    'reason'        => '审核通过，等待排产',
                ]);
                break;

            case 'needs_confirm':
                $this->stateService->transition($order, OrderStatus::NEED_CONFIRM, 'technical', [
                    'operator_id'   => $auditorId,
                    'operator_name' => $data['auditor_name'] ?? '审核员',
                    'reason'        => $data['remark'] ?? '需要门店确认',
                ]);
                break;

            case 'needs_supplement':
                $this->stateService->transition($order, OrderStatus::NEED_SUPPLEMENT, 'technical', [
                    'operator_id'   => $auditorId,
                    'operator_name' => $data['auditor_name'] ?? '审核员',
                    'reason'        => $data['remark'] ?? '需要补款',
                ]);
                break;

            case 'cannot_produce':
                $this->stateService->transition($order, OrderStatus::CANCELLED, 'admin', [
                    'operator_id'   => $auditorId,
                    'operator_name' => $data['auditor_name'] ?? '审核员',
                    'reason'        => $data['remark'] ?? '无法生产',
                ]);
                break;
        }
    }

    /**
     * 校验审核结果值
     *
     * @throws BusinessException
     */
    private function validateAuditResult(string $result): void
    {
        $valid = ['approved', 'needs_confirm', 'needs_supplement', 'cannot_produce'];
        if (!in_array($result, $valid, true)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, "无效的审核结果：{$result}");
        }
    }

    /**
     * 查找订单，不存在则抛异常
     */
    private function findOrderOrFail(string $orderNo): Order
    {
        $order = Order::where('order_no', $orderNo)->find();
        if (!$order) {
            throw new BusinessException(ErrorCode::DATA_NOT_FOUND, "订单不存在：{$orderNo}");
        }
        return $order;
    }

    /**
     * 获取审核状态描述
     */
    private function getAuditStatusLabel(int $status): string
    {
        return match ($status) {
            0 => '未审核',
            1 => '审核通过',
            2 => '需门店确认',
            3 => '待补款',
            4 => '无法生产',
            default => '未知',
        };
    }
}
