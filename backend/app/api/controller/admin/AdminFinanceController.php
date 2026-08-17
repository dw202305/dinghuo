<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use app\common\service\InvoiceService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 后台财务管理控制器
 * 支付记录/退款
 */
class AdminFinanceController extends BaseController
{
    /**
     * 支付记录查询
     * GET /api/v1/admin/finance/payment/list
     */
    public function paymentList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('payment')
            ->alias('p')
            ->leftJoin('order o', 'o.id = p.order_id')
            ->leftJoin('store s', 's.id = p.store_id');

        if ($keyword = $request->param('keyword', '')) {
            $query->where('p.payment_no|o.order_no|p.transaction_id', 'like', '%' . $keyword . '%');
        }
        if ($payChannel = $request->param('pay_channel/d')) {
            $query->where('p.pay_channel', $payChannel);
        }
        if ($payStatus = $request->param('pay_status/d')) {
            $query->where('p.pay_status', $payStatus);
        }
        if ($storeId = $request->param('store_id/d')) {
            $query->where('p.store_id', $storeId);
        }
        if ($startDate = $request->param('start_date', '')) {
            $query->where('p.created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate = $request->param('end_date', '')) {
            $query->where('p.created_at', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->field([
                'p.id as payment_id', 'p.payment_no', 'o.order_no',
                's.store_name', 'p.pay_channel', 'p.pay_method',
                'p.pay_amount', 'p.transaction_id', 'p.pay_status',
                'p.paid_at', 'p.refund_amount', 'p.refunded_at',
            ])
            ->order('p.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $channelMap = [1 => '微信支付', 2 => '支付宝'];
        $statusMap = [0 => '待支付', 1 => '支付成功', 2 => '支付失败', 3 => '已退款'];

        foreach ($list as &$item) {
            $item['pay_channel_text'] = $channelMap[$item['pay_channel']] ?? '';
            $item['pay_status_text']  = $statusMap[$item['pay_status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 退款处理
     * POST /api/v1/admin/finance/refund
     */
    public function refund(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['payment_id']) || !isset($data['refund_amount']) || empty($data['refund_reason'])) {
            return $this->paramError('必填项缺失');
        }

        $payment = Db::name('payment')->where('id', $data['payment_id'])->find();
        if (!$payment || $payment['pay_status'] !== 1) {
            return $this->error('支付记录状态不允许退款', 1006);
        }

        $refundAmount = (float) $data['refund_amount'];
        $maxRefundable = (float) $payment['pay_amount'] - (float) ($payment['refund_amount'] ?? 0);

        if ($refundAmount > $maxRefundable) {
            return $this->error('退款金额超过可退金额', 1001);
        }

        // TODO: 调用微信/支付宝退款接口

        $refundId = Db::name('refund_record')->insertGetId([
            'payment_id'    => $data['payment_id'],
            'refund_amount' => $refundAmount,
            'refund_reason' => $data['refund_reason'],
            'kit_return'    => $data['kit_return'] ?? 0,
            'refund_status' => 'processing',
            'created_by'    => $this->getAccountId(),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        // 更新支付记录已退款金额
        Db::name('payment')->where('id', $data['payment_id'])->update([
            'refund_amount' => (float) $payment['refund_amount'] + $refundAmount,
        ]);

        return $this->success([
            'refund_id'     => $refundId,
            'refund_amount' => number_format($refundAmount, 2, '.', ''),
            'refund_status' => 'processing',
        ]);
    }

    /**
     * 对账导出
     * GET /api/v1/admin/finance/reconciliation/export
     */
    public function reconciliationExport(): \think\Response
    {
        $startDate = $this->app->request->param('start_date', '');
        $endDate   = $this->app->request->param('end_date', '');

        if (empty($startDate) || empty($endDate)) {
            return $this->paramError('日期范围不能为空');
        }

        // TODO: 生成 Excel 文件并上传到 OSS
        return $this->success([
            'file_url'      => '',
            'expire_at'     => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'total_records' => 0,
            'total_amount'  => '0.00',
        ]);
    }

    /**
     * 发票审核
     * POST /api/v1/admin/finance/invoice/review
     */
    public function invoiceReview(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('invoice_review')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $invoiceService = new InvoiceService();

        try {
            $invoiceService->reviewInvoice(
                (int) $data['request_id'],
                (int) $data['action'],
                $data['invoice_no'] ?? null,
                $data['invoice_code'] ?? null,
                $data['reject_reason'] ?? null,
                $this->getAccountId(),
                '管理员',
            );

            return $this->success(null, '操作成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1006);
        }
    }


    /**
     * 客户资金账户列表
     * GET /api/v1/admin/finance/customer-accounts
     */
    public function customerAccounts(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('customer_balance_account')
            ->alias('a')
            ->leftJoin('store s', 's.id = a.store_id');

        if ($keyword = $request->param('keyword', '')) {
            $query->where('s.store_name|s.store_no', 'like', '%' . $keyword . '%');
        }
        if ($status = $request->param('status/d')) {
            $query->where('a.status', $status);
        }

        $total = $query->count();
        $list = $query->field([
                'a.id', 'a.store_id', 's.store_name', 's.store_no',
                'a.available_balance_cent', 'a.frozen_balance_cent',
                'a.total_recharge_cent', 'a.total_consume_cent',
                'a.status', 'a.created_at',
            ])
            ->order('a.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = [0 => '已冻结', 1 => '正常'];
        foreach ($list as &$item) {
            $item['status_text'] = $statusMap[$item['status']] ?? '未知';
        }

        return $this->success([
            'list' => $list, 'total' => $total,
            'page' => $page, 'page_size' => $pageSize,
        ]);
    }

    /**
     * 储值审核列表
     * GET /api/v1/admin/finance/recharge-audit
     */
    public function rechargeAudit(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('recharge_order')
            ->alias('r')
            ->leftJoin('store s', 's.id = r.store_id');

        if ($status = $request->param('status/d')) {
            $query->where('r.status', $status);
        }
        if ($storeId = $request->param('store_id/d')) {
            $query->where('r.store_id', $storeId);
        }

        $total = $query->count();
        $list = $query->field([
                'r.id', 'r.recharge_no', 'r.store_id', 's.store_name',
                'r.recharge_amount_cent', 'r.recharge_method',
                'r.status', 'r.remark', 'r.created_at',
            ])
            ->order('r.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = [0 => '待支付', 1 => '待审核', 2 => '已完成', 3 => '已取消'];
        foreach ($list as &$item) {
            $item['status_text'] = $statusMap[$item['status']] ?? '未知';
        }

        return $this->success([
            'list' => $list, 'total' => $total,
            'page' => $page, 'page_size' => $pageSize,
        ]);
    }

    /**
     * 储值审核处理
     * POST /api/v1/admin/finance/recharge-audit/:id
     */
    public function rechargeAuditProcess(int $id): \think\Response
    {
        $data = $this->app->request->post();
        $action = (int)($data['action'] ?? 0); // 1=通过 2=驳回

        $recharge = Db::name('recharge_order')->where('id', $id)->find();
        if (!$recharge) {
            return $this->error('储值单不存在', 1004);
        }
        if ($recharge['status'] !== 1) {
            return $this->error('该储值单不在待审核状态', 4001);
        }

        Db::startTrans();
        try {
            if ($action === 1) {
                // 审核通过：增加余额
                Db::name('recharge_order')->where('id', $id)->update([
                    'status' => 2,
                ]);
                Db::name('customer_balance_account')
                    ->where('store_id', $recharge['store_id'])
                    ->inc('available_balance_cent', $recharge['recharge_amount_cent'])
                    ->inc('total_recharge_cent', $recharge['recharge_amount_cent'])
                    ->update();

                // 记录流水
                Db::name('customer_balance_transaction')->insert([
                    'account_id' => $recharge['store_id'],
                    'store_id' => $recharge['store_id'],
                    'type' => 'recharge',
                    'direction' => 'in',
                    'amount_cent' => $recharge['recharge_amount_cent'],
                    'balance_before_cent' => 0,
                    'balance_after_cent' => $recharge['recharge_amount_cent'],
                    'biz_type' => 'recharge',
                    'biz_id' => $recharge['id'],
                    'biz_no' => $recharge['recharge_no'],
                    'remark' => '储值审核通过',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                // 审核驳回
                Db::name('recharge_order')->where('id', $id)->update([
                    'status' => 3,
                    'remark' => $data['remark'] ?? '审核驳回',
                ]);
            }

            Db::name('operation_log')->insert([
                'operator_type' => 'admin',
                'operator_id' => $this->getAccountId(),
                'operator_name' => '管理员',
                'action' => $action === 1 ? 'recharge_approve' : 'recharge_reject',
                'target_type' => 'recharge_order',
                'target_id' => $id,
                'target_no' => $recharge['recharge_no'],
                'detail' => $data['remark'] ?? ($action === 1 ? '审核通过' : '审核驳回'),
                'ip' => $this->app->request->ip(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('操作失败：' . $e->getMessage(), 5000);
        }

        return $this->success(null, $action === 1 ? '审核通过' : '已驳回');
    }

    /**
     * 资金对账
     * GET /api/v1/admin/finance/reconciliation
     */
    public function financeReconciliation(): \think\Response
    {
        $request = $this->app->request;
        $startDate = $request->param('start_date', '');
        $endDate = $request->param('end_date', '');

        if (empty($startDate) || empty($endDate)) {
            return $this->paramError('日期范围不能为空');
        }

        // 汇总数据
        $paymentSummary = Db::name('payment')
            ->where('pay_status', 1)
            ->where('paid_at', '>=', $startDate . ' 00:00:00')
            ->where('paid_at', '<=', $endDate . ' 23:59:59')
            ->field([
                'pay_channel',
                Db::raw('COUNT(*) as pay_count'),
                Db::raw('SUM(pay_amount_cent) as total_amount_cent'),
            ])
            ->group('pay_channel')
            ->select()
            ->toArray();

        $rechargeSummary = Db::name('recharge_order')
            ->where('status', 2)
            ->where('created_at', '>=', $startDate . ' 00:00:00')
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->field([
                Db::raw('COUNT(*) as recharge_count'),
                Db::raw('SUM(recharge_amount_cent) as total_amount_cent'),
            ])
            ->find();

        $refundSummary = Db::name('payment')
            ->where('pay_status', 3)
            ->where('refunded_at', '>=', $startDate . ' 00:00:00')
            ->where('refunded_at', '<=', $endDate . ' 23:59:59')
            ->field([
                Db::raw('COUNT(*) as refund_count'),
                Db::raw('SUM(refund_amount) as total_refund_amount'),
            ])
            ->find();

        return $this->success([
            'period' => ['start' => $startDate, 'end' => $endDate],
            'payments' => $paymentSummary,
            'recharge' => $rechargeSummary,
            'refund' => $refundSummary,
        ]);
    }

}
