<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 后台订单管理控制器
 * 订单审核/确认排产/确认发货/取消/整单备注
 */
class AdminOrderController extends BaseController
{
    /**
     * 订单列表
     * GET /api/v1/admin/order/list
     */
    public function list(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('order')
            ->alias('o')
            ->leftJoin('store s', 's.id = o.transaction_id')
            ->leftJoin('partner p', 'p.id = s.partner_id')
            ->leftJoin('sales_person sp', 'sp.id = s.primary_sales_id');

        if ($keyword = $request->param('keyword', '')) {
            $query->where('o.order_no|o.project_name|o.end_customer', 'like', '%' . $keyword . '%');
        }
        if ($orderStatus = $request->param('order_status/d')) {
            $query->where('o.order_status', $orderStatus);
        }
        if ($paymentStatus = $request->param('payment_status/d')) {
            $query->where('o.payment_status', $paymentStatus);
        }
        if ($auditStatus = $request->param('audit_status/d')) {
            $query->where('o.audit_status', $auditStatus);
        }
        if ($transactionType = $request->param('transaction_type/d')) {
            $query->where('o.transaction_type', $transactionType);
        }
        if ($partnerId = $request->param('partner_id/d')) {
            $query->where('s.partner_id', $partnerId);
        }
        if ($storeId = $request->param('store_id/d')) {
            $query->where('o.transaction_id', $storeId);
        }
        if ($primarySalesId = $request->param('primary_sales_id/d')) {
            $query->where('s.primary_sales_id', $primarySalesId);
        }
        if ($startDate = $request->param('start_date', '')) {
            $query->where('o.created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate = $request->param('end_date', '')) {
            $query->where('o.created_at', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->field([
                'o.id as order_id', 'o.order_no', 'o.order_status', 'o.transaction_type',
                's.store_name', 's.store_no', 'p.business_entity as partner_name',
                'sp.name as primary_sales_name', 'o.project_name', 'o.end_customer',
                'o.item_count', 'o.total_amount', 'o.paid_amount', 'o.payment_status',
                'o.audit_status', 'o.created_by', 'o.created_at', 'o.paid_at',
            ])
            ->order('o.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = $this->getOrderStatusMap();
        $payMap = [0 => '未支付', 1 => '部分支付', 2 => '已支付'];
        $auditMap = [0 => '未审核', 1 => '通过', 2 => '需确认', 3 => '待补款', 4 => '无法生产'];

        foreach ($list as &$item) {
            $item['order_status_text'] = $statusMap[$item['order_status']] ?? '';
            $item['transaction_type_text'] = $item['transaction_type'] === 1 ? '门店' : '合伙人';
            $item['payment_status_text'] = $payMap[$item['payment_status']] ?? '';
            $item['audit_status_text'] = $auditMap[$item['audit_status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 订单详情（后台）
     * GET /api/v1/admin/order/detail
     */
    public function detail(): \think\Response
    {
        $orderId = (int) $this->app->request->param('order_id', 0);
        if ($orderId <= 0) {
            return $this->paramError('订单ID不能为空');
        }

        $order = Db::name('order')
            ->alias('o')
            ->leftJoin('store s', 's.id = o.transaction_id')
            ->where('o.id', $orderId)
            ->field(['o.*', 's.store_name', 's.store_no', 's.partner_id'])
            ->find();

        if (!$order) {
            return $this->error('订单不存在', 1004);
        }

        // 窗帘明细
        $items = Db::name('order_item')->where('order_id', $orderId)->order('sequence', 'asc')->select()->toArray();

        $order['items'] = $items;

        return $this->success($order);
    }

    /**
     * 技术审核
     * POST /api/v1/admin/order/audit
     */
    public function audit(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('order_audit')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $orderId = (int) $data['order_id'];
        $auditResult = (int) $data['audit_result'];

        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order) {
            return $this->error('订单不存在', 1004);
        }

        if ((int) $order['order_status'] !== 4) {
            return $this->error('订单当前状态不允许审核', 1006);
        }

        $newOrderStatus = match ($auditResult) {
            1 => 7,  // 通过 → 审核通过待排产
            2 => 5,  // 需门店确认
            3 => 6,  // 需补款
            4 => 16, // 无法生产 → 取消
            default => $order['order_status'],
        };

        Db::transaction(function () use ($orderId, $auditResult, $newOrderStatus, $data) {
            Db::name('order')->where('id', $orderId)->update([
                'audit_status'  => $auditResult,
                'order_status'  => $newOrderStatus,
                'supplement_amount' => $data['supplement_amount'] ?? null,
                'audit_remark'  => $data['overall_remark'] ?? null,
                'audited_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            // 逐副审核
            if (!empty($data['item_audits'])) {
                foreach ($data['item_audits'] as $itemAudit) {
                    Db::name('order_item')
                        ->where('id', $itemAudit['item_id'])
                        ->where('order_id', $orderId)
                        ->update([
                            'technical_status' => $itemAudit['technical_status'],
                            'technical_remark' => $itemAudit['remark'] ?? null,
                        ]);
                }
            }
        });

        $statusMap = $this->getOrderStatusMap();
        $auditMap = [1 => '审核通过', 2 => '需门店确认', 3 => '需补款', 4 => '无法生产'];

        return $this->success([
            'order_id'             => $orderId,
            'audit_status'         => $auditResult,
            'audit_status_text'    => $auditMap[$auditResult] ?? '',
            'new_order_status'     => $newOrderStatus,
            'new_order_status_text' => $statusMap[$newOrderStatus] ?? '',
        ]);
    }

    /**
     * 更新生产状态
     * POST /api/v1/admin/order/production
     */
    public function production(): \think\Response
    {
        $data = $this->app->request->post();
        $orderId = (int) ($data['order_id'] ?? 0);

        if ($orderId <= 0 || empty($data['production_status'])) {
            return $this->paramError('参数错误');
        }

        $updateData = ['production_status' => (int) $data['production_status']];

        if (!empty($data['item_ids'])) {
            // 更新指定明细
            Db::name('order_item')
                ->where('order_id', $orderId)
                ->where('id', 'in', $data['item_ids'])
                ->update($updateData);
        } else {
            // 更新整单
            Db::name('order_item')->where('order_id', $orderId)->update($updateData);
        }

        // 更新订单状态
        $productionStatus = (int) $data['production_status'];
        $orderStatusMap = [1 => 8, 2 => 9, 3 => 10]; // 生产中/质检中/待发货
        if (isset($orderStatusMap[$productionStatus])) {
            Db::name('order')->where('id', $orderId)->update([
                'order_status' => $orderStatusMap[$productionStatus],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->success(null, '更新成功');
    }

    /**
     * 发货管理
     * POST /api/v1/admin/order/ship
     */
    public function ship(): \think\Response
    {
        $data = $this->app->request->post();
        $orderId = (int) ($data['order_id'] ?? 0);

        if ($orderId <= 0 || empty($data['item_ids']) || empty($data['carrier']) || empty($data['tracking_no'])) {
            return $this->paramError('参数错误');
        }

        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order || !in_array($order['order_status'], [10, 11])) {
            return $this->error('订单当前状态不允许发货', 1006);
        }

        $itemIds = (array) $data['item_ids'];

        Db::transaction(function () use ($orderId, $itemIds, $data) {
            // 更新明细发货状态
            Db::name('order_item')
                ->where('order_id', $orderId)
                ->where('id', 'in', $itemIds)
                ->update([
                    'shipping_status' => 1,
                    'carrier'         => $data['carrier'],
                    'tracking_no'     => $data['tracking_no'],
                    'shipped_at'      => date('Y-m-d H:i:s'),
                ]);

            // 判断是否全部发货
            $totalItems = Db::name('order_item')->where('order_id', $orderId)->count();
            $shippedItems = Db::name('order_item')
                ->where('order_id', $orderId)
                ->where('shipping_status', 1)
                ->count();

            $newStatus = ($shippedItems >= $totalItems) ? 12 : 11; // 已发货 or 部分发货

            Db::name('order')->where('id', $orderId)->update([
                'order_status' => $newStatus,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        });

        $shippedItems = Db::name('order_item')
            ->where('order_id', $orderId)
            ->where('shipping_status', 1)
            ->column('id');

        $orderStatus = Db::name('order')->where('id', $orderId)->value('order_status');
        $statusMap = $this->getOrderStatusMap();

        return $this->success([
            'shipped_items'       => $shippedItems,
            'new_order_status'    => (int) $orderStatus,
            'new_order_status_text' => $statusMap[(int) $orderStatus] ?? '',
        ]);
    }

    /**
     * 取消订单（管理员）
     * POST /api/v1/admin/order/cancel
     */
    public function cancel(): \think\Response
    {
        $data = $this->app->request->post();
        $orderId = (int) ($data['order_id'] ?? 0);
        $cancelReason = $data['cancel_reason'] ?? '';

        if ($orderId <= 0 || empty($cancelReason)) {
            return $this->paramError('参数错误');
        }

        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order) {
            return $this->error('订单不存在', 1004);
        }

        Db::name('order')->where('id', $orderId)->update([
            'order_status'   => 16,
            'cancelled_at'   => date('Y-m-d H:i:s'),
            'cancel_reason'  => $cancelReason,
            'production_progress' => $data['production_progress'] ?? null,
            'material_cost'  => $data['material_cost'] ?? null,
            'refund_amount'  => $data['refund_amount'] ?? null,
            'kit_return'     => $data['kit_return'] ?? 0,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return $this->success(null, '取消成功');
    }

    /**
     * 改价
     * POST /api/v1/admin/order/adjust-price
     */
    public function adjustPrice(): \think\Response
    {
        $data = $this->app->request->post();
        $orderId = (int) ($data['order_id'] ?? 0);

        if ($orderId <= 0 || empty($data['adjust_field']) || !isset($data['adjust_value']) || empty($data['reason'])) {
            return $this->paramError('参数错误');
        }

        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order) {
            return $this->error('订单不存在', 1004);
        }

        $oldTotal = (float) $order['total_amount'];
        $adjustField = $data['adjust_field'];
        $adjustValue = (float) $data['adjust_value'];

        $allowedFields = ['discount_amount', 'nonstandard_amount', 'item_total'];
        if (!in_array($adjustField, $allowedFields)) {
            return $this->paramError('调整字段无效');
        }

        Db::transaction(function () use ($orderId, $adjustField, $adjustValue, $data, $order) {
            if ($adjustField === 'item_total' && !empty($data['item_id'])) {
                Db::name('order_item')
                    ->where('id', $data['item_id'])
                    ->update(['item_total' => $adjustValue]);
            } else {
                Db::name('order')->where('id', $orderId)->update([$adjustField => $adjustValue]);
            }

            // TODO: 改价日志表待 database.md 补充后启用，暂用操作日志记录
            Db::name('operation_log')->insert([
                'module'      => 'order',
                'action'      => 'adjust_price',
                'target_type' => 'order',
                'target_id'   => $orderId,
                'before_data' => json_encode(['field' => $adjustField]),
                'after_data'  => json_encode(['value' => $adjustValue, 'reason' => $data['reason']]),
                'operator_id' => $this->getAccountId(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        });

        $newTotal = (float) Db::name('order')->where('id', $orderId)->value('total_amount');

        return $this->success([
            'old_total' => number_format($oldTotal, 2, '.', ''),
            'new_total' => number_format($newTotal, 2, '.', ''),
        ]);
    }

    /**
     * 订单状态映射
     */
    private function getOrderStatusMap(): array
    {
        return [
            1 => '草稿', 2 => '待支付', 3 => '支付处理中', 4 => '已支付待审核',
            5 => '需门店确认', 6 => '待补款', 7 => '审核通过待排产', 8 => '生产中',
            9 => '质检中', 10 => '待发货', 11 => '部分发货', 12 => '已发货',
            13 => '已签收', 14 => '已完成', 15 => '售后处理中', 16 => '已取消',
            17 => '退款中', 18 => '已退款',
        ];
    }


    /**
     * 待发货订单列表
     * GET /api/v1/admin/logistics
     */
    public function logisticsList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('order')
            ->alias('o')
            ->leftJoin('store s', 's.id = o.transaction_id')
            ->where('o.order_status', '>=', 40)
            ->where('o.order_status', '<', 60); // 已排产到已收货之间

        if ($keyword = $request->param('keyword', '')) {
            $query->where('o.order_no|s.store_name', 'like', '%' . $keyword . '%');
        }
        if ($storeId = $request->param('store_id/d')) {
            $query->where('o.transaction_id', $storeId);
        }

        $total = $query->count();
        $list = $query->field([
                'o.id as order_id', 'o.order_no', 'o.order_status',
                's.store_name', 's.store_no', 'o.project_name',
                'o.item_count', 'o.total_amount_cent', 'o.created_at',
            ])
            ->order('o.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = [40 => '已排产', 50 => '已发货', 55 => '运输中'];
        foreach ($list as &$item) {
            $item['order_status_text'] = $statusMap[$item['order_status']] ?? '未知';
        }

        return $this->success([
            'list' => $list, 'total' => $total,
            'page' => $page, 'page_size' => $pageSize,
        ]);
    }

    /**
     * 确认发货
     * POST /api/v1/admin/logistics/:id/ship
     */
    public function logisticsShip(int $id): \think\Response
    {
        $data = $this->app->request->post();

        $order = Db::name('order')->where('id', $id)->find();
        if (!$order) {
            return $this->error('订单不存在', 1004);
        }
        if ($order['order_status'] < 40) {
            return $this->error('订单未排产，无法发货', 4001);
        }

        Db::name('order')->where('id', $id)->update([
            'order_status' => 50,
            'shipped_at' => date('Y-m-d H:i:s'),
        ]);

        Db::name('operation_log')->insert([
            'operator_type' => 'admin',
            'operator_id' => $this->getAccountId(),
            'operator_name' => '管理员',
            'action' => 'ship',
            'target_type' => 'order',
            'target_id' => $id,
            'target_no' => $order['order_no'],
            'detail' => json_encode([
                'tracking_no' => $data['tracking_no'] ?? '',
                'logistics_company' => $data['logistics_company'] ?? '',
                'remark' => $data['remark'] ?? '',
            ], JSON_UNESCAPED_UNICODE),
            'ip' => $this->app->request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success(null, '发货成功');
    }

    /**
     * 待排产订单列表
     * GET /api/v1/admin/production
     */
    public function productionList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('order')
            ->alias('o')
            ->leftJoin('store s', 's.id = o.transaction_id')
            ->where('o.order_status', 30); // 已审核，待排产

        if ($keyword = $request->param('keyword', '')) {
            $query->where('o.order_no|s.store_name', 'like', '%' . $keyword . '%');
        }

        $total = $query->count();
        $list = $query->field([
                'o.id as order_id', 'o.order_no', 'o.order_status',
                's.store_name', 'o.project_name', 'o.end_customer',
                'o.item_count', 'o.total_amount_cent', 'o.audit_status',
                'o.created_at',
            ])
            ->order('o.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return $this->success([
            'list' => $list, 'total' => $total,
            'page' => $page, 'page_size' => $pageSize,
        ]);
    }

    /**
     * 确认排产
     * POST /api/v1/admin/production/:id/confirm
     */
    public function productionConfirm(int $id): \think\Response
    {
        $order = Db::name('order')->where('id', $id)->find();
        if (!$order) {
            return $this->error('订单不存在', 1004);
        }
        if ($order['order_status'] !== 30) {
            return $this->error('订单状态不允许排产', 4001);
        }

        Db::name('order')->where('id', $id)->update([
            'order_status' => 40,
            'production_at' => date('Y-m-d H:i:s'),
        ]);

        Db::name('operation_log')->insert([
            'operator_type' => 'admin',
            'operator_id' => $this->getAccountId(),
            'operator_name' => '管理员',
            'action' => 'production_confirm',
            'target_type' => 'order',
            'target_id' => $id,
            'target_no' => $order['order_no'],
            'detail' => '确认排产',
            'ip' => $this->app->request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success(null, '排产成功');
    }

}
