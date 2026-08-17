<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use app\common\service\InvoiceService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 后台发票管理控制器
 * 发票审核/开票/驳回
 */
class AdminInvoiceController extends BaseController
{
    /**
     * 发票申请列表（后台）
     * GET /api/v1/admin/finance/invoice/list
     */
    public function list(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('invoice_request')
            ->alias('i')
            ->leftJoin('order o', 'o.id = i.order_id')
            ->leftJoin('store s', 's.id = i.store_id');

        if ($keyword = $request->param('keyword', '')) {
            $query->where('i.request_no|o.order_no', 'like', '%' . $keyword . '%');
        }
        if ($status = $request->param('status/d')) {
            $query->where('i.status', $status);
        }
        if ($storeId = $request->param('store_id/d')) {
            $query->where('i.store_id', $storeId);
        }

        $total = $query->count();
        $list  = $query->field([
                'i.id as request_id', 'i.request_no', 'o.order_no',
                's.store_name', 'i.invoice_type', 'i.title', 'i.tax_no',
                'i.invoice_amount', 'i.status', 'i.created_at',
            ])
            ->order('i.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $typeMap   = [1 => '普票', 2 => '专票'];
        $statusMap = [1 => '待审核', 2 => '已审核待开票', 3 => '已开票', 4 => '已驳回'];

        foreach ($list as &$item) {
            $item['invoice_type_text'] = $typeMap[$item['invoice_type']] ?? '';
            $item['status_text']       = $statusMap[$item['status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 发票详情（后台）
     * GET /api/v1/admin/finance/invoice/detail
     */
    public function detail(): \think\Response
    {
        $requestId = (int) $this->app->request->param('request_id', 0);
        if ($requestId <= 0) {
            return $this->paramError('发票申请ID不能为空');
        }

        $detail = Db::name('invoice_request')
            ->alias('i')
            ->leftJoin('order o', 'o.id = i.order_id')
            ->leftJoin('store s', 's.id = i.store_id')
            ->where('i.id', $requestId)
            ->field(['i.*', 'o.order_no', 's.store_name', 's.store_no'])
            ->find();

        if (!$detail) {
            return $this->error('发票申请不存在', 1004);
        }

        return $this->success($detail);
    }

    /**
     * 发票审核（通过/驳回）
     * POST /api/v1/admin/finance/invoice/review
     */
    public function review(): \think\Response
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
     * 开票完成
     * POST /api/v1/admin/finance/invoice/issue
     */
    public function issue(): \think\Response
    {
        $data = $this->app->request->post();
        $requestId = (int) ($data['request_id'] ?? 0);

        if ($requestId <= 0 || empty($data['invoice_no']) || empty($data['invoice_code'])) {
            return $this->paramError('参数错误');
        }

        $invoiceService = new InvoiceService();

        try {
            $invoiceService->issueInvoice(
                $requestId,
                $data['invoice_no'],
                $data['invoice_code'],
                $this->getAccountId(),
                '管理员',
            );

            return $this->success(null, '开票成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1006);
        }
    }
}
