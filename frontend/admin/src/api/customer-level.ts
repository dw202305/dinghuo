/**
 * 客户等级管理 API（后台）
 * 后端旧版路由: GET /api/v1/admin/customer-level/list, POST /api/v1/admin/customer-level/update
 */

import { get, post, del } from "./index"
import type { PaginatedData } from "@/types/api"
import type { CustomerLevelItem, CustomerLevelListParams, CustomerLevelSaveParams } from "@/types/customer-level"

/**
 * 获取客户等级列表
 * 对应后端路由: GET /api/v1/admin/customer-level/list
 */
export function getCustomerLevelList(params?: CustomerLevelListParams) {
  return get<PaginatedData<CustomerLevelItem>>("/admin/customer-level/list", params as unknown as Record<string, unknown>)
}

/**
 * 新增/编辑客户等级
 * 对应后端路由: POST /api/v1/admin/customer-level/update（更新接口，按 id 更新）
 */
export function saveCustomerLevel(params: CustomerLevelSaveParams) {
  return post<{ id: number }>("/admin/customer-level/update", params)
}

// 后端需补路由：后端旧版没有 /admin/customer-level/delete
/**
 * 删除客户等级
 */
export function deleteCustomerLevel(id: number) {
  return del<null>("/admin/customer-level/delete", { id })
}

// 后端需补路由：后端旧版没有 /admin/customer-level/update-status（可能可复用 update）
/**
 * 更新客户等级状态
 */
export function updateCustomerLevelStatus(id: number, status: 0 | 1) {
  return post<null>("/admin/customer-level/update-status", { id, status })
}
