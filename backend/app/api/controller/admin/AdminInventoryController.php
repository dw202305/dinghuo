<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use app\common\service\InventoryService;
use think\exception\ValidateException;

/**
 * 后台库存管理控制器
 * 人工调整库存
 */
class AdminInventoryController extends BaseController
{
    protected InventoryService $inventoryService;

    protected function initialize(): void
    {
        $this->inventoryService = new InventoryService();
    }

    /**
     * 查看门店库存
     * GET /api/v1/admin/inventory/store
     */
    public function store(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $filters = [
            'store_id' => $request->param('store_id/d'),
            'kit_sku'  => $request->param('kit_sku', ''),
            'keyword'  => $request->param('keyword', ''),
        ];

        $result = $this->inventoryService->getAllStoreInventory($filters, $page, $pageSize);

        return $this->success($result);
    }

    /**
     * 人工调整库存
     * POST /api/v1/admin/inventory/adjust
     */
    public function adjust(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('inventory_adjust')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $result = $this->inventoryService->adjustInventory(
                (int) $data['store_id'],
                $data['kit_sku'],
                (int) $data['adjust_quantity'],
                $data['reason'],
                $this->getAccountId(),
                '管理员',
            );

            return $this->success($result);
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 5004);
        }
    }

    /**
     * 库存流水查询（后台）
     * GET /api/v1/admin/inventory/log
     */
    public function log(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $filters = [
            'store_id'      => $request->param('store_id/d'),
            'kit_sku'       => $request->param('kit_sku', ''),
            'log_type'      => $request->param('log_type/d'),
            'order_id'      => $request->param('order_id/d'),
            'operator_name' => $request->param('operator_name', ''),
            'start_date'    => $request->param('start_date', ''),
            'end_date'      => $request->param('end_date', ''),
        ];

        $result = $this->inventoryService->getGlobalInventoryLog($filters, $page, $pageSize);

        return $this->success($result);
    }
}
