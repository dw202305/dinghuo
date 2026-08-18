/**
 * 客户管理 API（后台）
 * 对齐后端新版 RESTful 路由：/api/v1/admin/stores、/api/v1/admin/partners
 */

import { get, post, put } from "./index"
import type { PaginatedData } from "@/types/api"
import type { CustomerListItem, CustomerDetail, CustomerListParams, CustomerCreateParams, PartnerItem } from "@/types/customer"

/**
 * 获取客户列表
 * 对应后端路由: GET /api/v1/admin/stores
 */
export function getCustomerList(params: CustomerListParams) {
  return get<PaginatedData<CustomerListItem>>("/admin/stores", params as unknown as Record<string, unknown>)
}

/**
 * 获取客户详情
 * 对应后端路由: GET /api/v1/admin/stores/:id（控制器读 store_id 参数）
 */
export function getCustomerDetail(id: number) {
  return get<CustomerDetail>(`/admin/stores/${id}`, { store_id: id })
}

/**
 * 新建客户
 * 对应后端路由: POST /api/v1/admin/stores
 */
export function createCustomer(params: CustomerCreateParams) {
  return post<{ store_id: number; store_no: string }>("/admin/stores", params)
}

/**
 * 更新客户状态（启用/禁用）
 * 对应后端路由: PUT /api/v1/admin/stores/:id/status（控制器读 store_id/status）
 */
export function updateCustomerStatus(id: number, status: 1 | 2) {
  return put<null>(`/admin/stores/${id}/status`, { store_id: id, status })
}

// 后端需补路由：新版无重置客户密码端点
/**
 * 重置客户密码
 */
export function resetCustomerPassword(storeId: number, phone: string) {
  return post<null>("/admin/stores/reset-password", { store_id: storeId, phone })
}

/**
 * 获取城市合伙人列表
 * 对应后端路由: GET /api/v1/admin/partners
 */
export function getPartnerList(params: { keyword?: string; page?: number; page_size?: number }) {
  return get<PaginatedData<PartnerItem>>("/admin/partners", params as unknown as Record<string, unknown>)
}
