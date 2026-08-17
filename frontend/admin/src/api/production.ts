/**
 * 生产单管理 API（后台）
 * TODO: 后端需补路由 - /admin/production/* 全部路由
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { ProductionListItem, ProductionListParams, ShipParams, BatchUpdateStatusParams } from "@/types/production"

/**
 * 获取生产单列表
 */
export function getProductionList(params: ProductionListParams) {
  return get<PaginatedData<ProductionListItem>>("/admin/production/list", params as unknown as Record<string, unknown>)
}

/**
 * 获取生产单详情
 */
export function getProductionDetail(itemId: number) {
  return get<ProductionListItem>("/admin/production/detail", { item_id: itemId })
}

/**
 * 更新生产状态
 */
export function updateProductionStatus(itemId: number, status: number) {
  return post<null>("/admin/production/update-status", { item_id: itemId, status })
}

/**
 * 批量更新生产状态
 */
export function batchUpdateProductionStatus(params: BatchUpdateStatusParams) {
  return post<null>("/admin/production/batch-update-status", params)
}

/**
 * 发货
 */
export function shipItem(params: ShipParams) {
  return post<null>("/admin/production/ship", params)
}

/**
 * 打印生产单
 */
export function printProductionOrder(itemId: number) {
  return get<{ file_url: string }>("/admin/production/print", { item_id: itemId })
}
