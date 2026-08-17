<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\InvoiceValidate;
use app\common\service\InvoiceService;
use think\exception\ValidateException;

/**
 * 发票控制器（门店端）
 * 申请/列表/详情
 */
class InvoiceController extends BaseController
{
    protected InvoiceService $invoiceService;

    protected function initialize(): void
    {
        $this->invoiceService = new InvoiceService();
    }

    /**
     * 申请发票
     * POST /api/v1/store/invoice/create
     */
    public function create(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(InvoiceValidate::class)->scene('create')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $result = $this->invoiceService->createInvoice(
                $this->getStoreId(),
                $this->getAccountId(),
                $data,
            );

            return $this->success($result);
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), (int) str_contains($e->getMessage(), '未确认收货') ? 1006 : 1001);
        }
    }

    /**
     * 获取发票申请列表
     * GET /api/v1/store/invoice/list
     */
    public function list(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();

        $filters = [
            'status' => $this->app->request->param('status/d'),
        ];

        $result = $this->invoiceService->getStoreInvoiceList(
            $this->getStoreId(),
            $filters,
            $page,
            $pageSize,
        );

        return $this->success($result);
    }

    /**
     * 获取发票详情
     * GET /api/v1/store/invoice/detail
     */
    public function detail(): \think\Response
    {
        $requestId = (int) $this->app->request->param('request_id', 0);

        if ($requestId <= 0) {
            return $this->paramError('发票申请ID不能为空');
        }

        try {
            $result = $this->invoiceService->getStoreInvoiceDetail(
                $this->getStoreId(),
                $requestId,
            );

            return $this->success($result);
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1004);
        }
    }
}
