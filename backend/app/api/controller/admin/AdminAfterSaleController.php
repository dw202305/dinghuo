<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use app\common\service\AfterSaleService;
use think\exception\ValidateException;

/**
 * 后台售后管理控制器
 * 售后处理（诊断/责任判定/解决方案/完成）
 */
class AdminAfterSaleController extends BaseController
{
    protected AfterSaleService $afterSaleService;

    protected function initialize(): void
    {
        $this->afterSaleService = new AfterSaleService();
    }

    /**
     * 售后列表（后台）
     * GET /api/v1/admin/after-sale/list
     */
    public function list(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $filters = [
            'keyword'      => $request->param('keyword', ''),
            'status'       => $request->param('status/d'),
            'problem_type' => $request->param('problem_type/d'),
            'store_id'     => $request->param('store_id/d'),
            'start_date'   => $request->param('start_date', ''),
            'end_date'     => $request->param('end_date', ''),
        ];

        $result = $this->afterSaleService->getAdminAfterSaleList($filters, $page, $pageSize);

        return $this->success($result);
    }

    /**
     * 售后详情（后台）
     * GET /api/v1/admin/after-sale/detail
     */
    public function detail(): \think\Response
    {
        $afterSaleId = (int) $this->app->request->param('after_sale_id', 0);
        if ($afterSaleId <= 0) {
            return $this->paramError('售后单ID不能为空');
        }

        try {
            $result = $this->afterSaleService->getAdminAfterSaleDetail($afterSaleId);
            return $this->success($result);
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1004);
        }
    }

    /**
     * 处理售后
     * POST /api/v1/admin/after-sale/process
     */
    public function process(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('after_sale_process')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $this->afterSaleService->processAfterSale(
                (int) $data['after_sale_id'],
                $data,
                $this->getAccountId(),
                '管理员',
            );

            return $this->success(null, '处理成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1006);
        }
    }

    /**
     * 关闭售后
     * POST /api/v1/admin/after-sale/close
     */
    public function close(): \think\Response
    {
        $data = $this->app->request->post();
        $afterSaleId = (int) ($data['after_sale_id'] ?? 0);
        $closeReason = $data['close_reason'] ?? '';

        if ($afterSaleId <= 0 || empty($closeReason)) {
            return $this->paramError('参数错误');
        }

        try {
            $this->afterSaleService->closeAfterSale(
                $afterSaleId,
                $closeReason,
                $this->getAccountId(),
                '管理员',
            );

            return $this->success(null, '关闭成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1006);
        }
    }
}
