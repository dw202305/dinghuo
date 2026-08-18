/**
 * 库存管理 API（后台）
 * 对齐后端新版 RESTful 路由：/api/v1/admin/inventories
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { InventoryRecord, InventoryLog, InventoryListParams } from "@/types/stock"

/**
 * 获取库存列表（按门店查询）
 * 对应后端路由: GET /api/v1/admin/inventories/stores
 */
export function getInventoryList(params: InventoryListParams) {
  return get<PaginatedData<InventoryRecord>>("/admin/inventories/stores", params as unknown as Record<string, unknown>)
}

/**
 * 获取库存流水
 * 对应后端路由: GET /api/v1/admin/inventories/logs
 */
export function getInventoryLogs(params: { store_id?: number; kit_sku?: string; log_type?: number; start_date?: string; end_date?: string; page?: number; page_size?: number }) {
  return get<PaginatedData<InventoryLog>>("/admin/inventories/logs", params as unknown as Record<string, unknown>)
}

/**
 * 人工调整库存
 * 对应后端路由: POST /api/v1/admin/inventories/adjust
 */
export function adjustInventory(storeId: number, kitSku: string, adjustQuantity: number, reason: string) {
  return post<{ before_available: number; after_available: number; log_id: number }>("/admin/inventories/adjust", {
    store_id: storeId,
    kit_sku: kitSku,
    adjust_quantity: adjustQuantity,
    reason
  })
}

// 后端需补路由：新版无库存统计端点
/**
 * 库存统计
 */
export function getInventoryStats(storeId?: number) {
  return get<Record<string, unknown>>("/admin/stats/inventory", storeId ? { store_id: storeId } : undefined)
}

// 后端需补路由：新版无库存导出端点
/**
 * 导出库存数据
 */
export function exportInventory(params: InventoryListParams) {
  return get<{ file_url: string; expire_at: string }>("/admin/inventories/export", params as unknown as Record<string, unknown>)
}
