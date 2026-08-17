<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\AfterSaleValidate;
use app\common\service\AfterSaleService;
use think\exception\ValidateException;

/**
 * 售后控制器（门店端）
 * 申请/列表/详情/补充信息
 */
class AfterSaleController extends BaseController
{
    protected AfterSaleService $afterSaleService;

    protected function initialize(): void
    {
        $this->afterSaleService = new AfterSaleService();
    }

    /**
     * 申请售后
     * POST /api/v1/store/after-sale/create
     */
    public function create(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AfterSaleValidate::class)->scene('create')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $result = $this->afterSaleService->createAfterSale(
                $this->getStoreId(),
                $this->getAccountId(),
                $data,
            );

            return $this->success($result);
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1004);
        }
    }

    /**
     * 获取售后列表
     * GET /api/v1/store/after-sale/list
     */
    public function list(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();

        $filters = [
            'status' => $this->app->request->param('status/d'),
        ];

        $result = $this->afterSaleService->getStoreAfterSaleList(
            $this->getStoreId(),
            $filters,
            $page,
            $pageSize,
        );

        return $this->success($result);
    }

    /**
     * 获取售后详情
     * GET /api/v1/store/after-sale/detail
     */
    public function detail(): \think\Response
    {
        $afterSaleId = (int) $this->app->request->param('after_sale_id', 0);

        if ($afterSaleId <= 0) {
            return $this->paramError('售后单ID不能为空');
        }

        try {
            $result = $this->afterSaleService->getStoreAfterSaleDetail(
                $this->getStoreId(),
                $afterSaleId,
            );

            return $this->success($result);
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1004);
        }
    }

    /**
     * 补充售后信息
     * PUT /api/v1/store/after-sale/supplement
     */
    public function supplement(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AfterSaleValidate::class)->scene('supplement')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $this->afterSaleService->supplementAfterSale(
                $this->getStoreId(),
                (int) $data['after_sale_id'],
                $data,
            );

            return $this->success(null, '补充成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1006);
        }
    }
}
