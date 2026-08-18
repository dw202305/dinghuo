/**
 * 面料管理 API（后台）
 * 对齐后端新版 RESTful 路由：/api/v1/admin/products/fabrics
 */

import { get, post, del } from "./index"
import type { PaginatedData } from "@/types/api"
import type { FabricListItem, FabricDetail, FabricSaveParams, FabricListParams } from "@/types/fabric"
import type { FabricBatchPriceParams } from "@/types/product"

/**
 * 获取面料列表
 * 对应后端路由: GET /api/v1/admin/products/fabrics
 */
export function getFabricList(params: FabricListParams) {
  return get<PaginatedData<FabricListItem>>("/admin/products/fabrics", params as unknown as Record<string, unknown>)
}

/**
 * 获取面料详情
 * 对应后端路由: GET /api/v1/admin/products/fabrics/:id（控制器读 fabric_id 参数）
 */
export function getFabricDetail(id: number) {
  return get<FabricDetail>(`/admin/products/fabrics/${id}`, { fabric_id: id })
}

/**
 * 新增/编辑面料
 * 对应后端路由: POST /api/v1/admin/products/fabrics
 */
export function saveFabric(params: FabricSaveParams) {
  return post<{ id: number }>("/admin/products/fabrics", params)
}

// 后端需补路由：新版无面料删除端点
/**
 * 删除面料（软删除）
 */
export function deleteFabric(id: number) {
  return del<null>("/admin/products/fabrics/delete", { id })
}

/**
 * 批量导入面料
 * 对应后端路由: POST /api/v1/admin/products/fabrics/import
 */
export function importFabrics(file: File) {
  const formData = new FormData()
  formData.append("file", file)
  return post<{ success_count: number; fail_count: number; errors: string[] }>("/admin/products/fabrics/import", formData)
}

/**
 * 上架/下架面料（批量状态变更）
 * 对应后端路由: POST /api/v1/admin/products/fabrics/batch-status（后端参数名为 fabric_ids）
 */
export function toggleFabricStatus(id: number, listingStatus: 0 | 1) {
  return post<null>("/admin/products/fabrics/batch-status", { fabric_ids: [id], listing_status: listingStatus })
}

/**
 * 面料批量调价
 * 对应后端路由: POST /api/v1/admin/products/fabrics/batch-price
 * 后端参数要求: fabric_ids / adjust_type(fixed|percent) / adjust_value / effective_date / reason
 */
export function batchUpdateFabricPrice(data: FabricBatchPriceParams) {
  return post<{ affected_count: number; new_price_version: number }>("/admin/products/fabrics/batch-price", data)
}
