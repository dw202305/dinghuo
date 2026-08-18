<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use app\common\enum\CustomerType;
use app\common\enum\PayStatus;
use app\common\enum\PaymentChannel;
use app\common\enum\RechargeStatus;
use app\common\service\BalanceAccountService;
use app\common\service\InvoiceService;
use app\common\support\Money;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 后台财务管理控制器
 * 支付记录/退款/资金账户/储值审核/对账
 *
 * 批次2c：全部字段对齐 deploy/mysql/init.sql（lj_payment.payment_channel、
 * pay_amount_cent/refund_amount_cent、账户 *_cent/account_status、
 * 储值单 amount_cent/recharge_method/状态1-6）；金额一律整数分，禁 float。
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

        // deploy lj_payment 无 store_id：主体存 transaction_subject_type/id，
        // 门店主体（1）关联门店表取名称
        $query = Db::name('payment')
            ->alias('p')
            ->leftJoin('order o', 'o.id = p.order_id')
            ->leftJoin('store s', 's.id = p.transaction_subject_id AND p.transaction_subject_type = ' . CustomerType::STORE->value);

        if ($keyword = $request->param('keyword', '')) {
            $query->where('p.payment_no|o.order_no|p.transaction_id', 'like', '%' . $keyword . '%');
        }
        // 批次2c：渠道过滤改为 deploy payment_channel（balance/wechat/alipay）
        if ($channel = $request->param('pay_channel', '')) {
            $query->where('p.payment_channel', (string) $channel);
        }
        if ($payStatus = $request->param('pay_status/d')) {
            $query->where('p.pay_status', $payStatus);
        }
        if ($storeId = $request->param('store_id/d')) {
            $query->where('p.transaction_subject_type', CustomerType::STORE->value)
                ->where('p.transaction_subject_id', $storeId);
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
                's.store_name', 'p.payment_channel', 'p.pay_method',
                'p.pay_amount_cent', 'p.transaction_id', 'p.pay_status',
                'p.paid_at', 'p.refund_amount_cent', 'p.refunded_at',
            ])
            ->order('p.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = PayStatus::options();

        foreach ($list as &$item) {
            $channelEnum = PaymentChannel::tryFrom((string) ($item['payment_channel'] ?? ''));
            $item['pay_channel_text'] = $channelEnum?->label() ?? '';
            $item['pay_status_text']  = $statusMap[(int) $item['pay_status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 退款处理
     * POST /api/v1/admin/finance/refund
     *
     * 批次2c：金额改整数分运算（入参元 → Money 转分，禁 float 累加）；
     * 退款额累计到 deploy lj_payment.refund_amount_cent；
     * deploy 无 refund_record 表，退款记录以支付单退款列 + 操作日志承载。
     */
    public function refund(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['payment_id']) || !isset($data['refund_amount']) || empty($data['refund_reason'])) {
            return $this->paramError('必填项缺失');
        }

        $payment = Db::name('payment')->where('id', $data['payment_id'])->find();
        if (!$payment || (int) $payment['pay_status'] !== PayStatus::SUCCESS->value) {
            return $this->error('支付记录状态不允许退款', 1006);
        }

        // 元 → 分（字符串运算，禁 float）
        $refundCent = Money::mulCent((string) $data['refund_amount'], 100);
        if ($refundCent <= 0) {
            return $this->paramError('退款金额必须大于0');
        }

        $paidCent = (int) $payment['pay_amount_cent'];
        $refundedCent = (int) ($payment['refund_amount_cent'] ?? 0);
        if ($refundCent + $refundedCent > $paidCent) {
            return $this->error('退款金额超过可退金额', 1001);
        }

        $channel = PaymentChannel::tryFrom((string) $payment['payment_channel']);

        try {
            if ($channel === PaymentChannel::BALANCE) {
                // 余额支付订单：退回原客户主体余额（事务内含流水与支付单状态更新）
                $balanceService = new BalanceAccountService();
                $balanceService->refundToBalance(
                    (string) $payment['payment_no'],
                    $refundCent,
                    [
                        'reason'      => (string) $data['refund_reason'],
                        'operator_id' => $this->getAccountId(),
                    ]
                );
            } else {
                // TODO: 调用微信/支付宝退款接口（批次外）
                Db::name('payment')->where('id', (int) $payment['id'])->update([
                    'refund_amount_cent' => $refundedCent + $refundCent,
                    'refunded_at'        => date('Y-m-d H:i:s'),
                    'refund_reason'      => (string) $data['refund_reason'],
                    // 全额退款置已退款态，部分退款保持支付成功
                    'pay_status'         => ($refundedCent + $refundCent >= $paidCent)
                        ? PayStatus::REFUNDED->value
                        : PayStatus::SUCCESS->value,
                ]);
            }
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1006);
        }

        return $this->success([
            'payment_id'       => (int) $payment['id'],
            'refund_amount'    => number_format($refundCent / 100, 2, '.', ''),
            'refund_amount_cent' => $refundCent,
            'refund_status'    => 'processing',
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
     *
     * 批次2c：账户主体为 customer_type + customer_id（1门店 2城市合伙人），
     * 列名对齐 deploy（*_cent/account_status）。
     */
    public function customerAccounts(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('customer_balance_account')
            ->alias('a')
            ->leftJoin('store s', 's.id = a.customer_id AND a.customer_type = ' . CustomerType::STORE->value)
            ->leftJoin('partner pt', 'pt.id = a.customer_id AND a.customer_type = ' . CustomerType::PARTNER->value);

        if ($keyword = $request->param('keyword', '')) {
            $query->where('s.store_name|s.store_no|pt.business_entity', 'like', '%' . $keyword . '%');
        }
        if ($status = $request->param('status/d')) {
            $query->where('a.account_status', $status);
        }

        $total = $query->count();
        $list = $query->field([
                'a.id', 'a.customer_type', 'a.customer_id',
                Db::raw('COALESCE(s.store_name, pt.business_entity) as customer_name'),
                'a.available_balance_cent', 'a.frozen_balance_cent',
                'a.total_recharge_cent', 'a.total_consumed_cent',
                'a.total_refund_cent', 'a.total_adjustment_cent',
                'a.account_status', 'a.created_at',
            ])
            ->order('a.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = [1 => '正常', 2 => '已冻结', 3 => '已注销'];
        foreach ($list as &$item) {
            $item['account_status_text'] = $statusMap[(int) $item['account_status']] ?? '未知';
            $item['customer_type_text']  = CustomerType::tryFrom((int) $item['customer_type'])?->label() ?? '未知';
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

        // deploy lj_recharge_order 无 store_id：主体为 customer_type + customer_id
        $query = Db::name('recharge_order')
            ->alias('r')
            ->leftJoin('store s', 's.id = r.customer_id AND r.customer_type = ' . CustomerType::STORE->value)
            ->leftJoin('partner pt', 'pt.id = r.customer_id AND r.customer_type = ' . CustomerType::PARTNER->value);

        if ($status = $request->param('status/d')) {
            $query->where('r.status', $status);
        }
        if ($storeId = $request->param('store_id/d')) {
            $query->where('r.customer_type', CustomerType::STORE->value)
                ->where('r.customer_id', $storeId);
        }

        $total = $query->count();
        $list = $query->field([
                'r.id', 'r.recharge_no', 'r.customer_type', 'r.customer_id',
                Db::raw('COALESCE(s.store_name, pt.business_entity) as customer_name'),
                'r.amount_cent', 'r.recharge_method', 'r.offline_voucher',
                'r.status', 'r.remark', 'r.created_at',
            ])
            ->order('r.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = RechargeStatus::options();
        foreach ($list as &$item) {
            $item['status_text'] = $statusMap[(int) $item['status']] ?? '未知';
        }

        return $this->success([
            'list' => $list, 'total' => $total,
            'page' => $page, 'page_size' => $pageSize,
        ]);
    }

    /**
     * 储值审核处理
     * POST /api/v1/admin/finance/recharge-audit/:id
     *
     * 批次2c：审核通过入账统一走 BalanceAccountService::confirmRecharge
     * （同事务更新储值单状态 + 余额 + 不可变流水），状态对齐 deploy 1-6。
     */
    public function rechargeAuditProcess(int $id): \think\Response
    {
        $data = $this->app->request->post();
        $action = (int) ($data['action'] ?? 0); // 1=通过 2=驳回

        $recharge = Db::name('recharge_order')->where('id', $id)->find();
        if (!$recharge) {
            return $this->error('储值单不存在', 1004);
        }
        if ((int) $recharge['status'] !== RechargeStatus::PENDING_REVIEW->value) {
            return $this->error('该储值单不在待审核状态', 4001);
        }

        $operatorId = $this->getAccountId();

        Db::startTrans();
        try {
            if ($action === 1) {
                // 审核通过：事务内入账（储值单状态 + 余额 + 流水）
                $balanceService = new BalanceAccountService();
                $balanceService->confirmRecharge(
                    (string) $recharge['recharge_no'],
                    $operatorId,
                    '管理员',
                );
            } else {
                // 审核驳回：关闭储值单
                Db::name('recharge_order')->where('id', $id)->update([
                    'status'        => RechargeStatus::CLOSED->value,
                    'reviewer_id'   => $operatorId,
                    'reviewer_name' => '管理员',
                    'reviewed_at'   => date('Y-m-d H:i:s'),
                    'remark'        => $data['remark'] ?? '审核驳回',
                ]);
            }

            Db::name('operation_log')->insert([
                'module'        => 'finance',
                'action'        => $action === 1 ? 'recharge_approve' : 'recharge_reject',
                'target_type'   => 'recharge_order',
                'target_id'     => $id,
                'target_no'     => $recharge['recharge_no'],
                'operator_id'   => $operatorId,
                'operator_name' => '管理员',
                'operator_role' => 'admin',
                'ip_address'    => $this->app->request->ip(),
                'remark'        => $data['remark'] ?? ($action === 1 ? '审核通过' : '审核驳回'),
                'created_at'    => date('Y-m-d H:i:s'),
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

        // 汇总数据（deploy 列名：payment_channel / pay_amount_cent / refund_amount_cent）
        $paymentSummary = Db::name('payment')
            ->where('pay_status', PayStatus::SUCCESS->value)
            ->where('paid_at', '>=', $startDate . ' 00:00:00')
            ->where('paid_at', '<=', $endDate . ' 23:59:59')
            ->field([
                'payment_channel',
                Db::raw('COUNT(*) as pay_count'),
                Db::raw('SUM(pay_amount_cent) as total_amount_cent'),
            ])
            ->group('payment_channel')
            ->select()
            ->toArray();

        $rechargeSummary = Db::name('recharge_order')
            ->where('status', RechargeStatus::CREDITED->value)
            ->where('created_at', '>=', $startDate . ' 00:00:00')
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->field([
                Db::raw('COUNT(*) as recharge_count'),
                Db::raw('SUM(amount_cent) as total_amount_cent'),
            ])
            ->find();

        $refundSummary = Db::name('payment')
            ->where('pay_status', PayStatus::REFUNDED->value)
            ->where('refunded_at', '>=', $startDate . ' 00:00:00')
            ->where('refunded_at', '<=', $endDate . ' 23:59:59')
            ->field([
                Db::raw('COUNT(*) as refund_count'),
                Db::raw('SUM(refund_amount_cent) as total_refund_amount_cent'),
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
