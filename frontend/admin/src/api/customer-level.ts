/**
 * 客户等级管理 API（后台）
 * 对齐后端新版路由: GET /api/v1/admin/customer-levels, PUT /api/v1/admin/customer-levels/:id
 */

import { get, post, put, del } from "./index"
import type { PaginatedData } from "@/types/api"
import type { CustomerLevelItem, CustomerLevelListParams, CustomerLevelSaveParams } from "@/types/customer-level"

/**
 * 获取客户等级列表
 * 对应后端路由: GET /api/v1/admin/customer-levels
 */
export function getCustomerLevelList(params?: CustomerLevelListParams) {
  return get<PaginatedData<CustomerLevelItem>>("/admin/customer-levels", params as unknown as Record<string, unknown>)
}

/**
 * 新增/编辑客户等级
 * 对应后端路由: PUT /api/v1/admin/customer-levels/:id（控制器读 body customer_level）
 * 注：后端无新建端点，id 缺失时路径参数为 0；后端当前实现为按门店设置等级，与前端等级定义 CRUD 存在语义差异
 */
export function saveCustomerLevel(params: CustomerLevelSaveParams) {
  const id = params.id ?? 0
  return put<{ id: number }>(`/admin/customer-levels/${id}`, { ...params, customer_level: params.level })
}

// 后端需补路由：新版无 /admin/customer-levels 删除端点
/**
 * 删除客户等级
 */
export function deleteCustomerLevel(id: number) {
  return del<null>("/admin/customer-levels/delete", { id })
}

// 后端需补路由：新版无状态更新端点（可复用 PUT /admin/customer-levels/:id）
/**
 * 更新客户等级状态
 */
export function updateCustomerLevelStatus(id: number, status: 0 | 1) {
  return post<null>("/admin/customer-levels/update-status", { id, status })
}
