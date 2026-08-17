<?php
declare(strict_types=1);

namespace app\common\service;

use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 发票服务
 * 申请发票/列表/审核/开票
 */
class InvoiceService extends BaseService
{
    /**
     * 申请发票
     * @param int $storeId 门店ID
     * @param int $accountId 申请人ID
     * @param array $data 发票数据
     * @return array 发票申请信息
     */
    public function createInvoice(int $storeId, int $accountId, array $data): array
    {
        // 校验订单
        $order = Db::name('order')
            ->where('id', $data['order_id'])
            ->where('transaction_id', $storeId)
            ->find();

        if (!$order) {
            throw new ValidateException('订单不存在');
        }

        // 仅确认收货后可申请
        if ((int) $order['order_status'] < 13) {
            throw new ValidateException('订单未确认收货，不可申请发票');
        }

        // 校验可开票金额
        $invoicedAmount = (float) Db::name('invoice_request')
            ->where('order_id', $order['id'])
            ->where('status', 'in', [1, 2, 3])
            ->sum('invoice_amount');

        $maxInvoiceable = (float) $order['paid_amount'] - $invoicedAmount;
        if ((float) $data['invoice_amount'] > $maxInvoiceable) {
            throw new ValidateException('开票金额超过可开票金额');
        }

        // 专票字段校验
        if ((int) $data['invoice_type'] === 2) {
            if (empty($data['bank_name']) || empty($data['bank_account'])
                || empty($data['company_address']) || empty($data['company_phone'])) {
                throw new ValidateException('专票需填写完整银行及公司信息');
            }
        }

        $requestNo = $this->generateInvoiceNo();

        $requestId = $this->transaction(function () use ($storeId, $accountId, $data, $requestNo, $order) {
            return Db::name('invoice_request')->insertGetId([
                'request_no'       => $requestNo,
                'store_id'         => $storeId,
                'order_id'         => $data['order_id'],
                'invoice_type'     => $data['invoice_type'],
                'title'            => $data['title'],
                'tax_no'           => $data['tax_no'],
                'tax_rate'         => $data['tax_rate'] ?? null,
                'invoice_amount'   => $data['invoice_amount'],
                'bank_name'        => $data['bank_name'] ?? null,
                'bank_account'     => $data['bank_account'] ?? null,
                'company_address'  => $data['company_address'] ?? null,
                'company_phone'    => $data['company_phone'] ?? null,
                'delivery_method'  => $data['delivery_method'] ?? 1,
                'delivery_address' => $data['delivery_address'] ?? null,
                'status'           => 1, // 待审核
                'created_by'       => $accountId,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        });

        $this->logOperation(
            module: 'invoice',
            action: 'create',
            targetType: 'invoice_request',
            targetId: $requestId,
            targetNo: $requestNo,
            operatorId: $accountId,
            remark: '门店申请发票',
        );

        return [
            'request_id' => $requestId,
            'request_no' => $requestNo,
        ];
    }

    /**
     * 获取发票申请列表（门店端）
     * @param int $storeId 门店ID
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getStoreInvoiceList(int $storeId, array $filters, int $page, int $pageSize): array
    {
        $query = Db::name('invoice_request')
            ->alias('i')
            ->leftJoin('order o', 'o.id = i.order_id')
            ->where('i.store_id', $storeId);

        if (!empty($filters['status'])) {
            $query->where('i.status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->field([
                'i.id as request_id',
                'i.request_no',
                'o.order_no',
                'i.invoice_type',
                'i.title',
                'i.tax_no',
                'i.invoice_amount',
                'i.status',
                'i.created_at',
            ])
            ->order('i.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $invoiceTypeMap = [1 => '普票', 2 => '专票'];
        $statusMap      = [1 => '待审核', 2 => '已审核待开票', 3 => '已开票', 4 => '已驳回'];

        foreach ($list as &$item) {
            $item['invoice_type_text'] = $invoiceTypeMap[$item['invoice_type']] ?? '未知';
            $item['status_text']       = $statusMap[$item['status']] ?? '未知';
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /**
     * 获取发票详情（门店端）
     * @param int $storeId 门店ID
     * @param int $requestId 发票申请ID
     * @return array
     */
    public function getStoreInvoiceDetail(int $storeId, int $requestId): array
    {
        $detail = Db::name('invoice_request')
            ->alias('i')
            ->leftJoin('order o', 'o.id = i.order_id')
            ->where('i.store_id', $storeId)
            ->where('i.id', $requestId)
            ->field(['i.*', 'o.order_no'])
            ->find();

        if (!$detail) {
            throw new ValidateException('发票申请不存在');
        }

        $invoiceTypeMap = [1 => '普票', 2 => '专票'];
        $statusMap      = [1 => '待审核', 2 => '已审核待开票', 3 => '已开票', 4 => '已驳回'];
        $deliveryMap    = [1 => '电子', 2 => '邮寄'];

        $detail['invoice_type_text']    = $invoiceTypeMap[$detail['invoice_type']] ?? '未知';
        $detail['status_text']          = $statusMap[$detail['status']] ?? '未知';
        $detail['delivery_method_text'] = $deliveryMap[$detail['delivery_method']] ?? '未知';

        return $detail;
    }

    /**
     * 审核发票申请（后台）
     * @param int $requestId 发票申请ID
     * @param int $action 操作 2审核通过 4驳回
     * @param string|null $invoiceNo 发票号码
     * @param string|null $invoiceCode 发票代码
     * @param string|null $rejectReason 驳回原因
     * @param int $adminId 管理员ID
     * @param string $adminName 管理员名称
     * @return bool
     */
    public function reviewInvoice(
        int $requestId,
        int $action,
        ?string $invoiceNo,
        ?string $invoiceCode,
        ?string $rejectReason,
        int $adminId,
        string $adminName,
    ): bool {
        $invoice = Db::name('invoice_request')->where('id', $requestId)->find();

        if (!$invoice) {
            throw new ValidateException('发票申请不存在');
        }

        if ((int) $invoice['status'] !== 1) {
            throw new ValidateException('当前状态不允许审核');
        }

        $updateData = [
            'status'     => $action,
            'reviewed_by'   => $adminId,
            'reviewed_at'   => date('Y-m-d H:i:s'),
        ];

        if ($action === 2) {
            // 审核通过
            $updateData['invoice_no']   = $invoiceNo;
            $updateData['invoice_code'] = $invoiceCode;
            $updateData['status']       = 2; // 已审核待开票
        } elseif ($action === 4) {
            // 驳回
            if (empty($rejectReason)) {
                throw new ValidateException('请填写驳回原因');
            }
            $updateData['reject_reason'] = $rejectReason;
            $updateData['status']        = 4;
        }

        Db::name('invoice_request')->where('id', $requestId)->update($updateData);

        $this->logOperation(
            module: 'invoice',
            action: $action === 2 ? 'approve' : 'reject',
            targetType: 'invoice_request',
            targetId: $requestId,
            targetNo: $invoice['request_no'],
            operatorId: $adminId,
            operatorName: $adminName,
            remark: $action === 2 ? '发票审核通过' : '发票申请驳回：' . $rejectReason,
        );

        return true;
    }

    /**
     * 开票完成（后台）
     * @param int $requestId 发票申请ID
     * @param string $invoiceNo 发票号码
     * @param string $invoiceCode 发票代码
     * @param int $adminId 管理员ID
     * @param string $adminName 管理员名称
     * @return bool
     */
    public function issueInvoice(int $requestId, string $invoiceNo, string $invoiceCode, int $adminId, string $adminName): bool
    {
        $invoice = Db::name('invoice_request')->where('id', $requestId)->find();

        if (!$invoice) {
            throw new ValidateException('发票申请不存在');
        }

        if ((int) $invoice['status'] !== 2) {
            throw new ValidateException('当前状态不允许开票');
        }

        Db::name('invoice_request')->where('id', $requestId)->update([
            'status'       => 3,
            'invoice_no'   => $invoiceNo,
            'invoice_code' => $invoiceCode,
            'invoiced_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->logOperation(
            module: 'invoice',
            action: 'issue',
            targetType: 'invoice_request',
            targetId: $requestId,
            targetNo: $invoice['request_no'],
            operatorId: $adminId,
            operatorName: $adminName,
            remark: '开票完成',
        );

        return true;
    }

    /**
     * 生成发票申请号 INV{日期}{3位流水号}
     * @return string
     */
    private function generateInvoiceNo(): string
    {
        $date   = date('Ymd');
        $prefix = 'INV' . $date;

        $last = Db::name('invoice_request')
            ->where('request_no', 'like', $prefix . '%')
            ->order('id', 'desc')
            ->value('request_no');

        $seq = $last ? (int) substr($last, -3) + 1 : 1;

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
