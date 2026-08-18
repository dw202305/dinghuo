<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\model\Order;
use app\common\enum\CustomerType;
use app\common\enum\ErrorCode;
use app\common\enum\OrderStatus;
use app\common\enum\PaymentChannel;
use app\common\enum\PaymentStatus;
use app\common\exception\BusinessException;
use app\common\service\BalanceAccountService;
use app\common\service\OrderService;
use app\common\service\OrderStateService;
use app\common\service\PaymentService;
use app\common\service\TechnicalAuditService;
use app\api\validate\OrderValidate;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 订单控制器（门店端/小程序端）
 *
 * 分层约定（dev_specification §4 / §10.1）：
 * - Controller 只做 参数归一化 → Validate 校验 → 委托 Service → ApiResponse；
 * - 禁止直接操作 Db 写业务数据 / 改订单状态（批次1 遗留的 payBalance 事务除外，已评审）；
 * - 状态变更一律走 OrderStateService::transition。
 *
 * 批次3 签名修正：
 * - 路由 orders/:order_no 按变量名绑定，read/cancel 原为 int $id 不匹配，
 *   统一改为 string $order_no，方法内先按订单号查询再操作；
 * - payBalance/confirmReceive 参数 $orderNo 重命名为 $order_no（同上原因）；
 * - 带默认值以兼容旧版 deprecated 路由（无路由变量，order_no 走 query/body）。
 */
class OrderController extends BaseController
{
    protected OrderService $orderService;

    protected function initialize(): void
    {
        $this->orderService = new OrderService();
    }

    /**
     * 订单列表
     * GET /api/v1/orders
     */
    public function index(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();

        $storeId = $this->getStoreId();
        $status = $this->app->request->param('status/d');

        $query = Order::where('transaction_type', 1)
            ->where('transaction_id', $storeId);

        if ($status) {
            $query->where('order_status', $status);
        }

        $paginator = $query->order('id', 'desc')
            ->paginate($pageSize);

        return $this->paginate($paginator);
    }

    /**
     * 订单详情（批次3：改为按订单号查询）
     * GET /api/v1/orders/:order_no
     */
    public function read(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        $storeId = $this->getStoreId();

        // 越权防护：限门店主体 transaction_type=1（评审 Warning 7）
        $order = Order::where('order_no', $orderNo)
            ->where('transaction_id', $storeId)
            ->where('transaction_type', CustomerType::STORE->value)
            ->with(['items'])
            ->find();

        if (!$order) {
            return $this->error('订单不存在', ErrorCode::DATA_NOT_FOUND);
        }

        return $this->success($order->toArray());
    }

    /**
     * 创建订单（草稿）
     * POST /api/v1/orders
     */
    public function save(): \think\Response
    {
        $data = $this->app->request->post();

        // 参数验证（批次3：显式使用 create 场景，避免校验新增场景的必填字段）
        try {
            validate(OrderValidate::class)->scene('create')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $order = $this->orderService->createOrder(
                $this->getStoreId(),
                $this->getAccountId(),
                $data
            );

            return $this->success([
                'id'       => $order->id,
                'order_no' => $order->order_no,
            ], '创建成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), ErrorCode::PARAM_MISSING);
        }
    }

    /**
     * 更新订单基本信息（批次3新增，草稿/待支付可改）
     * PUT /api/v1/orders/:order_no
     */
    public function update(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        $data = $this->normalizeItemFields($this->app->request->put());

        try {
            validate(OrderValidate::class)->scene('update')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $order = $this->orderService->updateOrderInfo(
                $this->getStoreId(),
                $orderNo,
                $data,
                $this->getAccountId()
            );

            return $this->success([
                'id'       => $order->id,
                'order_no' => $order->order_no,
            ], '更新成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        }
    }

    /**
     * 新增窗帘明细（批次3新增，仅草稿单）
     * POST /api/v1/orders/:order_no/items
     */
    public function addItem(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        $data = $this->normalizeItemFields($this->app->request->post());

        try {
            validate(OrderValidate::class)->scene('addItem')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $item = $this->orderService->addItemToOrder($this->getStoreId(), $orderNo, $data);

            return $this->success([
                'item_id'         => $item->id,
                'item_no'         => $item->item_no,
                'item_total_cent' => (int) $item->item_total_cent,
            ], '明细添加成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::PARAM_INVALID);
        }
    }

    /**
     * 更新窗帘明细（批次3新增，仅草稿单）
     * PUT /api/v1/orders/:order_no/items/:item_id
     */
    public function updateItem(string $order_no = '', int $item_id = 0): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        $itemId  = $this->resolveItemId($item_id);
        if ($orderNo === '' || $itemId <= 0) {
            return $this->paramError('订单号和明细ID不能为空');
        }

        $data = $this->normalizeItemFields($this->app->request->put());

        try {
            validate(OrderValidate::class)->scene('updateItem')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $item = $this->orderService->updateOrderItem($this->getStoreId(), $orderNo, $itemId, $data);

            return $this->success([
                'item_id'         => $item->id,
                'item_total_cent' => (int) $item->item_total_cent,
            ], '明细更新成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::PARAM_INVALID);
        }
    }

    /**
     * 删除窗帘明细（批次3新增，仅草稿单）
     * DELETE /api/v1/orders/:order_no/items/:item_id
     */
    public function deleteItem(string $order_no = '', int $item_id = 0): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        $itemId  = $this->resolveItemId($item_id);
        if ($orderNo === '' || $itemId <= 0) {
            return $this->paramError('订单号和明细ID不能为空');
        }

        try {
            $this->orderService->deleteOrderItem($this->getStoreId(), $orderNo, $itemId);

            return $this->success(null, '明细删除成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        }
    }

    /**
     * 复制窗帘明细（批次3新增，仅草稿单）
     * POST /api/v1/orders/:order_no/items/copy
     */
    public function copyItem(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        $data = $this->normalizeItemFields($this->app->request->post());

        try {
            validate(OrderValidate::class)->scene('copyItem')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $item = $this->orderService->copyOrderItem(
                $this->getStoreId(),
                $orderNo,
                (int) $data['source_item_id'],
                $data
            );

            return $this->success([
                'item_id'         => $item->id,
                'item_no'         => $item->item_no,
                'item_total_cent' => (int) $item->item_total_cent,
            ], '明细复制成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::PARAM_INVALID);
        }
    }

    /**
     * 订单预览（批次3新增，读已落库价格快照）
     * GET /api/v1/orders/:order_no/preview
     */
    public function preview(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        try {
            $preview = $this->orderService->getOrderPreview($this->getStoreId(), $orderNo);

            return $this->success($preview);

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        }
    }

    /**
     * 提交订单（批次3新增，下单主流程入口）
     * POST /api/v1/orders/:order_no/submit
     *
     * 流程全部下沉 OrderService::submitOrder：
     * 草稿校验 → PriceService 最终计价落库 → 库存锁定 → 金额汇总
     * → 价格锁定 → OrderStateService 状态机提交（DRAFT → PENDING_PAY）。
     *
     * 影响面补漏 17：store 前端预审申请 requestPreAudit 实际复用本路由
     *（POST /store/order/submit，请求体仅含 order_no，无 confirmed）。
     * 对现有前端零改动：请求体含 confirmed 走正常提交（强校验），
     * 不含 confirmed 视为预审申请，路由到 TechnicalAuditService。
     */
    public function submit(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        $postData = $this->app->request->post();

        // 预审申请分支（前端 requestPreAudit 复用 submit，请求体无 confirmed）
        if (!isset($postData['confirmed'])) {
            return $this->requestPreAudit($orderNo);
        }

        try {
            validate(OrderValidate::class)->scene('submit')->check($postData);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $result = $this->orderService->submitOrder(
                $this->getStoreId(),
                $this->getAccountId(),
                $orderNo,
                // 批次4：前端 Idempotent-Key 头优先，缺省 submit:{storeId}:{orderNo} 短锁防双击
                (string) $this->app->request->header('Idempotent-Key', '')
            );

            return $this->success($result, '提交成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            // 状态机非法转换(4003) / 库存不足(4001) 等携带业务码
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::ILLEGAL_STATUS_TRANSITION);
        }
    }

    /**
     * 取消订单（批次3：改为按订单号查询）
     * PUT /api/v1/orders/:order_no/cancel
     */
    public function cancel(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        $storeId   = $this->getStoreId();
        $accountId = $this->getAccountId();

        // 越权防护：限门店主体 transaction_type=1（评审 Warning 7）
        $order = Order::where('order_no', $orderNo)
            ->where('transaction_id', $storeId)
            ->where('transaction_type', CustomerType::STORE->value)
            ->find();
        if (!$order) {
            return $this->error('订单不存在', ErrorCode::DATA_NOT_FOUND);
        }

        $reason = (string) $this->app->request->param('reason', '');
        if (mb_strlen($reason) > 500) {
            return $this->paramError('取消原因不能超过500字');
        }

        try {
            $orderStateService = new OrderStateService();
            $orderStateService->transition($order, OrderStatus::CANCELLED, 'store', [
                'reason' => $reason,
                'operator_id' => $accountId,
            ]);

            return $this->success(null, '取消成功');

        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::ILLEGAL_STATUS_TRANSITION);
        }
    }

    /**
     * 技术预审申请（批次3新增，门店端）
     * POST /api/v1/orders/:order_no/pre-audit/request
     */
    public function requestPreAudit(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        try {
            $order = app(TechnicalAuditService::class)
                ->requestPreAudit($orderNo, $this->getAccountId());

            return $this->success([
                'order_no'   => $order->order_no,
                'audit_type' => $order->audit_type,
            ], '预审申请成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        }
    }

    /**
     * 删除草稿订单（批次3新增，软删除）
     * DELETE /api/v1/orders/:order_no
     */
    public function delete(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        try {
            $this->orderService->deleteDraftOrder(
                $this->getStoreId(),
                $orderNo,
                $this->getAccountId()
            );

            return $this->success(null, '删除成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        }
    }

    /**
     * 价格预览（批次3新增，PriceService 实时重算，不落库）
     * POST /api/v1/orders/:order_no/price-preview
     */
    public function pricePreview(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        try {
            $preview = $this->orderService->repriceOrder($this->getStoreId(), $orderNo);

            return $this->success($preview);

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::PARAM_INVALID);
        }
    }

    /**
     * 余额支付
     * POST /api/v1/orders/:order_no/pay-balance
     *
     * 批次3签名修正：$orderNo → $order_no（路由变量按名称绑定）。
     *
     * 资金安全规则：
     * - 登录账号 ID 不直接当资金账户 ID：经 lj_account_customer 解析客户主体，
     *   再由 BalanceAccountService 定位资金账户；
     * - 余额扣减、写流水、写支付记录、订单状态更新放入同一个 Db::transaction；
     * - 状态变更一律走 OrderStateService（规范 10.1），禁止裸 save 状态；
     * - 不吞异常，异常上抛由 ExceptionHandle 统一处理。
     */
    public function payBalance(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        $storeId = $this->getStoreId();
        $accountId = $this->getAccountId();

        // 越权防护：限门店主体 transaction_type=1（评审 Warning 7）
        $order = Order::where('order_no', $orderNo)
            ->where('transaction_id', $storeId)
            ->where('transaction_type', CustomerType::STORE->value)
            ->find();

        if (!$order) {
            return $this->error('订单不存在', ErrorCode::DATA_NOT_FOUND);
        }

        // 状态校验：只有待支付状态才能发起余额支付
        if ($order->order_status !== OrderStatus::PENDING_PAY->value) {
            return $this->error('订单状态不允许支付', ErrorCode::ILLEGAL_STATUS_TRANSITION);
        }

        $unpaidCent = (int) $order->total_amount_cent - (int) $order->paid_amount_cent;
        if ($unpaidCent <= 0) {
            return $this->error('订单已支付完成', ErrorCode::ILLEGAL_STATUS_TRANSITION);
        }

        $balanceService = new BalanceAccountService();

        // 登录账号 → lj_account_customer 解析客户主体 → 定位资金账户
        $customer = $balanceService->resolveCustomerByAccount($accountId);
        $account = $balanceService->getOrCreateAccount($customer['customer_type'], $customer['customer_id']);

        // 批次4：支付幂等键含渠道维度（与 PaymentService 规范一致）
        $idempotentKey = 'order_pay:' . $orderNo . ':' . PaymentChannel::BALANCE->value;

        // 进事务前互斥校验（评审 Warning 3，PRD 4.9.4：同单已有其他渠道
        // 待支付/成功支付单时拒绝余额支付，抛 4104）
        $paymentService = new PaymentService();
        try {
            $paymentService->validatePaymentMutualExclusion((int) $order->id, PaymentChannel::BALANCE);
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::MIXED_PAYMENT_NOT_SUPPORTED);
        }

        // 同一事务：余额扣减 + 写流水 + 写支付记录 + 订单状态更新
        $result = Db::transaction(function () use ($balanceService, $paymentService, $order, $unpaidCent, $accountId, $account, $idempotentKey) {
            // 订单行锁后复检（评审 Warning 3）：状态/未付金额/渠道互斥以锁内最新值为准
            $lockedOrder = Order::where('id', $order->id)->lock(true)->find();
            if (!$lockedOrder) {
                throw new BusinessException(ErrorCode::DATA_NOT_FOUND, '订单不存在');
            }
            if ((int) $lockedOrder->order_status !== OrderStatus::PENDING_PAY->value) {
                throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '订单状态不允许支付');
            }
            $lockedUnpaidCent = (int) $lockedOrder->total_amount_cent - (int) $lockedOrder->paid_amount_cent;
            if ($lockedUnpaidCent !== $unpaidCent || $lockedUnpaidCent <= 0) {
                throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '订单金额已变更，请刷新后重试');
            }
            $paymentService->validatePaymentMutualExclusion((int) $lockedOrder->id, PaymentChannel::BALANCE);

            $payResult = $balanceService->payByBalanceWithinTransaction(
                $lockedOrder->order_no,
                $lockedUnpaidCent,
                (int) $account['id'],
                $idempotentKey,
                ['order_id' => (int) $lockedOrder->id, 'operator_id' => $accountId]
            );

            // 幂等命中：不重复更新订单
            if (!empty($payResult['idempotent'])) {
                return $payResult;
            }

            // 更新订单非状态支付字段（批次2c：deploy lj_order 无 payment_channel 列，
            // 渠道只存 lj_payment；补写 payment_status=2已支付；累加基于锁内最新值）
            Db::name('order')
                ->where('id', $lockedOrder->id)
                ->update([
                    'paid_amount_cent' => (int) $lockedOrder->paid_amount_cent + $lockedUnpaidCent,
                    'paid_at'          => date('Y-m-d H:i:s'),
                    'payment_status'   => PaymentStatus::PAID->value,
                ]);

            // 状态变更走状态机：PENDING_PAY → PAYING（store）→ PAID_PENDING（system）
            $stateService = new OrderStateService();
            $stateService->transition($lockedOrder, OrderStatus::PAYING, 'store', [
                'reason'      => '发起支付（余额支付）',
                'operator_id' => $accountId,
            ]);
            $stateService->transition($lockedOrder, OrderStatus::PAID_PENDING, 'system', [
                'reason'     => '余额支付成功',
                'payment_no' => $payResult['payment_no'],
            ]);

            return $payResult;
        });

        return $this->success([
            'payment_no' => $result['payment_no'] ?? null,
            'idempotent' => (bool) ($result['idempotent'] ?? false),
        ], '余额支付成功');
    }

    /**
     * 确认收货
     * POST /api/v1/orders/:order_no/confirm-receive
     *
     * 批次3签名修正：$orderNo → $order_no（路由变量按名称绑定）。
     */
    public function confirmReceive(string $order_no = ''): \think\Response
    {
        $orderNo = $this->resolveOrderNo($order_no);
        if ($orderNo === '') {
            return $this->paramError('订单号不能为空');
        }

        $storeId = $this->getStoreId();
        $accountId = $this->getAccountId();

        // 越权防护：限门店主体 transaction_type=1（评审 Warning 7）
        $order = Order::where('order_no', $orderNo)
            ->where('transaction_id', $storeId)
            ->where('transaction_type', CustomerType::STORE->value)
            ->find();

        if (!$order) {
            return $this->error('订单不存在', ErrorCode::DATA_NOT_FOUND);
        }

        try {
            $orderStateService = new OrderStateService();
            $orderStateService->transition($order, OrderStatus::RECEIVED, 'store', [
                'operator_id' => $accountId,
            ]);

            return $this->success(null, '确认收货成功');

        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::ILLEGAL_STATUS_TRANSITION);
        }
    }

    // ────────────────────────────────────────────────
    // 私有辅助（参数归一化，不含业务逻辑）
    // ────────────────────────────────────────────────

    /**
     * 解析订单号：优先路由变量，旧版路由回退 query/body 参数；
     * store 前端只传 order_id 时在本门店范围内按 id 解析 order_no
     *（影响面补漏 16，全链路 read/cancel/payBalance/confirmReceive 等可用）
     */
    private function resolveOrderNo(string $routeOrderNo): string
    {
        if ($routeOrderNo !== '') {
            return $routeOrderNo;
        }

        $orderNo = (string) $this->app->request->param('order_no', '');
        if ($orderNo !== '') {
            return $orderNo;
        }

        $orderId = (int) $this->app->request->param('order_id', 0);
        if ($orderId <= 0) {
            return '';
        }

        $resolved = Order::where('id', $orderId)
            ->where('transaction_id', $this->getStoreId())
            ->where('transaction_type', CustomerType::STORE->value)
            ->value('order_no');

        return (string) ($resolved ?? '');
    }

    /**
     * 解析明细ID：优先路由变量，旧版路由回退 query/body 参数
     */
    private function resolveItemId(int $routeItemId): int
    {
        if ($routeItemId > 0) {
            return $routeItemId;
        }

        return (int) $this->app->request->param('item_id', 0);
    }

    /**
     * 前端字段归一化：width/height → width_cm/height_cm（schema 对齐列名）
     */
    private function normalizeItemFields(array $data): array
    {
        if (isset($data['width']) && !isset($data['width_cm'])) {
            $data['width_cm'] = $data['width'];
        }
        if (isset($data['height']) && !isset($data['height_cm'])) {
            $data['height_cm'] = $data['height'];
        }

        return $data;
    }
}
