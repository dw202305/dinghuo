/**
 * 生产单管理 API（后台）
 * 对齐后端新版路由: GET /api/v1/admin/production, POST /api/v1/admin/production/:id/confirm
 * 注：后端生产模块为订单粒度；前端明细粒度端点（详情/状态/批量/发货/打印）后端未定义，待补路由
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { ProductionListItem, ProductionListParams, ShipParams, BatchUpdateStatusParams } from "@/types/production"

/**
 * 获取生产单列表
 * 对应后端路由: GET /api/v1/admin/production
 */
export function getProductionList(params: ProductionListParams) {
  return get<PaginatedData<ProductionListItem>>("/admin/production", params as unknown as Record<string, unknown>)
}

// 后端需补路由：新版无生产单详情端点
/**
 * 获取生产单详情
 */
export function getProductionDetail(itemId: number) {
  return get<ProductionListItem>("/admin/production/detail", { item_id: itemId })
}

// 后端需补路由：新版仅订单粒度 POST /admin/orders/:id/production，无明细粒度状态更新
/**
 * 更新生产状态
 */
export function updateProductionStatus(itemId: number, status: number) {
  return post<null>("/admin/production/update-status", { item_id: itemId, status })
}

// 后端需补路由：新版无批量状态更新端点
/**
 * 批量更新生产状态
 */
export function batchUpdateProductionStatus(params: BatchUpdateStatusParams) {
  return post<null>("/admin/production/batch-update-status", params)
}

// 后端需补路由：新版仅订单粒度 POST /admin/logistics/:id/ship，无明细粒度发货
/**
 * 发货
 */
export function shipItem(params: ShipParams) {
  return post<null>("/admin/production/ship", params)
}

// 后端需补路由：新版无生产单打印端点
/**
 * 打印生产单
 */
export function printProductionOrder(itemId: number) {
  return get<{ file_url: string }>("/admin/production/print", { item_id: itemId })
}
