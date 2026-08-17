import { get } from './index';
import type { PaginatedData } from '@/types/api';
import type {
  StoreInventory, InventoryLogItem, InventoryLogParams,
  StockOverview, FabricStockItem
} from '@/types/stock';

/** 获取门店套件库存（原始数据） */
export function getStoreInventory() {
  return get<StoreInventory>('/store/inventory/kit');
}

/** 获取库存概览（可用/已锁定/总数） */
export function getStockOverview() {
  return get<StockOverview>('/store/inventory/kit');
}

/** 获取面料库存列表
 * 对应后端路由: GET /api/v1/store/inventory/fabric-stock
 */
export function getFabricStockList() {
  return get<FabricStockItem[]>('/store/inventory/fabric-stock');
}

/** 获取库存流水列表 */
export function getInventoryLogs(params: InventoryLogParams) {
  return get<PaginatedData<InventoryLogItem>>('/store/inventory/log', params as unknown as Record<string, unknown>);
}

/** 获取库存流水列表（别名，按页面规范要求） */
export function getStockFlowList(params: InventoryLogParams) {
  return get<PaginatedData<InventoryLogItem>>('/store/inventory/log', params as unknown as Record<string, unknown>);
}
