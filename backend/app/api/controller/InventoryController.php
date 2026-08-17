<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\service\InventoryService;
use think\facade\Db;

/**
 * 库存控制器（门店端）
 * 套件库存/库存流水
 */
class InventoryController extends BaseController
{
    protected InventoryService $inventoryService;

    protected function initialize(): void
    {
        $this->inventoryService = new InventoryService();
    }

    /**
     * 获取套件库存
     * GET /api/v1/store/inventory/kit
     */
    public function kit(): \think\Response
    {
        $list = $this->inventoryService->getStoreKitInventory($this->getStoreId());

        return $this->success(['list' => $list]);
    }

    /**
     * 获取库存流水
     * GET /api/v1/store/inventory/log
     */
    public function log(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $filters = [
            'kit_sku'    => $request->param('kit_sku', ''),
            'log_type'   => $request->param('log_type/d'),
            'start_date' => $request->param('start_date', ''),
            'end_date'   => $request->param('end_date', ''),
        ];

        $result = $this->inventoryService->getInventoryLog(
            $this->getStoreId(),
            $filters,
            $page,
            $pageSize,
        );

        return $this->success($result);
    }


    /**
     * 面料库存查询
     * GET /api/v1/inventory/fabric-stock
     */
    public function fabricStock(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;
        $storeId = $this->getStoreId();

        $query = Db::name('store_inventory')
            ->alias('si')
            ->leftJoin('fabric f', 'f.fabric_no = si.sku_no')
            ->where('si.store_id', $storeId);

        if ($keyword = $request->param('keyword', '')) {
            $query->where('si.sku_no|f.name', 'like', '%' . $keyword . '%');
        }

        $total = $query->count();
        $list = $query->field([
                'si.id', 'si.sku_no', 'si.quantity', 'si.locked_quantity',
                'f.name as fabric_name', 'f.series', 'f.main_image',
                'f.price_per_sqm', 'f.stock_status',
            ])
            ->order('si.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['available_quantity'] = $item['quantity'] - $item['locked_quantity'];
        }

        return $this->success([
            'list' => $list, 'total' => $total,
            'page' => $page, 'page_size' => $pageSize,
        ]);
    }

}
