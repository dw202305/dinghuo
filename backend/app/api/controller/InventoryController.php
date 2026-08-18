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
     *
     * 批次2c：deploy/mysql/init.sql 无面料库存表（lj_store_inventory 仅存
     * 套件库存 kit_sku/total_purchased/available/locked 等列，旧代码引用的
     * sku_no/quantity/locked_quantity 均不存在）。面料库存数据源待 PRD 补充，
     * 接口暂返回空列表避免查询不存在的列。
     *
     * 影响面补漏 18：直接返回数组（FabricStockItem[]），对齐 store 端
     * getFabricStockList 的 FabricStockItem[] 类型声明，不再包分页对象。
     */
    public function fabricStock(): \think\Response
    {
        return $this->success([]);
    }

}
