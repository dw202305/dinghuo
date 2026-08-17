<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\model\Order;
use app\common\enum\OrderStatus;
use app\common\service\BalanceAccountService;
use app\common\service\OrderService;
use app\common\service\OrderStateService;
use app\api\validate\OrderValidate;
use think\exception\ValidateException;

/**
 * 订单控制器（门店端/小程序端）
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
     * GET /api/orders
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
     * 订单详情
     * GET /api/orders/:id
     */
    public function read(int $id): \think\Response
    {
        $storeId = $this->getStoreId();

        $order = Order::where('transaction_id', $storeId)
            ->with(['items'])
            ->find($id);

        if (!$order) {
            return $this->error('订单不存在', 1001);
        }

        return $this->success($order->toArray());
    }

    /**
     * 创建订单
     * POST /api/orders
     */
    public function save(): \think\Response
    {
        $data = $this->app->request->post();

        // 参数验证
        try {
            validate(OrderValidate::class)->check($data);
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

        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1002);
        }
    }

    /**
     * 取消订单
     * PUT /api/orders/:id/cancel
     */
    public function cancel(int $id): \think\Response
    {
        $storeId = $this->getStoreId();
        $accountId = $this->getAccountId();

        $order = Order::where('transaction_id', $storeId)->find($id);
        if (!$order) {
            return $this->error('订单不存在', 1001);
        }

        $reason = $this->app->request->post('reason', '');

        try {
            $orderStateService = new OrderStateService();
            $orderStateService->transition($order, OrderStatus::CANCELLED, 'store', [
                'reason' => $reason,
                'operator_id' => $accountId,
            ]);

            return $this->success(null, '取消成功');

        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 4003);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 5001);
        }
    }


    /**
     * 余额支付
     * POST /api/v1/orders/:order_no/pay-balance
     */
    public function payBalance(string $orderNo): \think\Response
    {
        $storeId = $this->getStoreId();
        $accountId = $this->getAccountId();

        $order = Order::where('order_no', $orderNo)
            ->where('transaction_id', $storeId)
            ->find();

        if (!$order) {
            return $this->error('订单不存在', 1004);
        }

        // 状态校验：只有待支付状态才能发起余额支付
        if ($order->order_status !== OrderStatus::PENDING_PAY->value) {
            return $this->error('订单状态不允许支付', 4003);
        }

        $unpaidCent = $order->total_amount_cent - $order->paid_amount_cent;
        if ($unpaidCent <= 0) {
            return $this->error('订单已支付完成', 4003);
        }

        try {
            $balanceService = new BalanceAccountService();
            $result = $balanceService->payByBalance($orderNo, $unpaidCent, $accountId, [
                'idempotent_key' => 'order_pay:' . $orderNo,
            ]);

            // 更新订单支付状态
            $order->save([
                'paid_amount_cent' => $order->paid_amount_cent + $unpaidCent,
                'payment_status' => 2,
                'paid_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->success(null, '余额支付成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 4103);
        }
    }

    /**
     * 确认收货
     * POST /api/v1/orders/:order_no/confirm-receive
     */
    public function confirmReceive(string $orderNo): \think\Response
    {
        $storeId = $this->getStoreId();
        $accountId = $this->getAccountId();

        $order = Order::where('order_no', $orderNo)
            ->where('transaction_id', $storeId)
            ->find();

        if (!$order) {
            return $this->error('订单不存在', 1004);
        }

        try {
            $orderStateService = new OrderStateService();
            $orderStateService->transition($order, OrderStatus::RECEIVED, 'store', [
                'operator_id' => $accountId,
            ]);

            return $this->success(null, '确认收货成功');

        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 4003);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 5001);
        }
    }

}
