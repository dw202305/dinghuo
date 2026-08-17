/**
 * 库存管理 API（后台）
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { InventoryRecord, InventoryLog, InventoryListParams } from "@/types/stock"

/**
 * 获取库存列表（按门店查询）
 */
export function getInventoryList(params: InventoryListParams) {
  return get<PaginatedData<InventoryRecord>>("/admin/inventory/store", params as unknown as Record<string, unknown>)
}

/**
 * 获取库存流水
 */
export function getInventoryLogs(params: { store_id?: number; kit_sku?: string; log_type?: number; start_date?: string; end_date?: string; page?: number; page_size?: number }) {
  return get<PaginatedData<InventoryLog>>("/admin/inventory/log", params as unknown as Record<string, unknown>)
}

/**
 * 人工调整库存
 */
export function adjustInventory(storeId: number, kitSku: string, adjustQuantity: number, reason: string) {
  return post<{ before_available: number; after_available: number; log_id: number }>("/admin/inventory/adjust", {
    store_id: storeId,
    kit_sku: kitSku,
    adjust_quantity: adjustQuantity,
    reason
  })
}

/**
 * 库存统计
 */
export function getInventoryStats(storeId?: number) {
  return get<Record<string, unknown>>("/admin/stats/inventory", storeId ? { store_id: storeId } : undefined)
}

// 后端需补路由：后端旧版没有 /admin/inventory/export
/**
 * 导出库存数据
 */
export function exportInventory(params: InventoryListParams) {
  return get<{ file_url: string; expire_at: string }>("/admin/inventory/export", params as unknown as Record<string, unknown>)
}
