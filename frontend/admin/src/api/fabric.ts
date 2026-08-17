/**
 * 面料管理 API（后台）
 */

import { get, post, del } from "./index"
import type { PaginatedData } from "@/types/api"
import type { FabricListItem, FabricDetail, FabricSaveParams, FabricListParams } from "@/types/fabric"
import type { FabricBatchPriceParams } from "@/types/product"

/**
 * 获取面料列表
 */
export function getFabricList(params: FabricListParams) {
  return get<PaginatedData<FabricListItem>>("/admin/product/fabric/list", params as unknown as Record<string, unknown>)
}

/**
 * 获取面料详情
 */
export function getFabricDetail(id: number) {
  return get<FabricDetail>("/admin/product/fabric/detail", { id })
}

/**
 * 新增/编辑面料
 */
export function saveFabric(params: FabricSaveParams) {
  return post<{ id: number }>("/admin/product/fabric/save", params)
}

/**
 * 删除面料（软删除）
 */
export function deleteFabric(id: number) {
  return del<null>("/admin/product/fabric/delete", { id })
}

/**
 * 批量导入面料
 */
export function importFabrics(file: File) {
  const formData = new FormData()
  formData.append("file", file)
  return post<{ success_count: number; fail_count: number; errors: string[] }>("/admin/product/fabric/import", formData)
}

/**
 * 上架/下架面料（批量状态变更）
 */
export function toggleFabricStatus(id: number, listingStatus: 0 | 1) {
  return post<null>("/admin/product/fabric/batch-status", { ids: [id], listing_status: listingStatus })
}

/**
 * 面料批量调价
 * 对应后端路由: POST /api/v1/admin/product/fabric/batch-price
 * 后端参数要求: fabric_ids / adjust_type(fixed|percent) / adjust_value / effective_date / reason
 */
export function batchUpdateFabricPrice(data: FabricBatchPriceParams) {
  return post<{ affected_count: number; new_price_version: number }>("/admin/product/fabric/batch-price", data)
}
