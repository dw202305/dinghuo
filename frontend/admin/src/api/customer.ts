/**
 * 客户管理 API（后台）
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { CustomerListItem, CustomerDetail, CustomerListParams, CustomerCreateParams, PartnerItem } from "@/types/customer"

/**
 * 获取客户列表
 */
export function getCustomerList(params: CustomerListParams) {
  return get<PaginatedData<CustomerListItem>>("/admin/store/list", params as unknown as Record<string, unknown>)
}

/**
 * 获取客户详情
 */
export function getCustomerDetail(id: number) {
  return get<CustomerDetail>("/admin/store/detail", { store_id: id })
}

/**
 * 新建客户
 */
export function createCustomer(params: CustomerCreateParams) {
  return post<{ store_id: number; store_no: string }>("/admin/store/create", params)
}

/**
 * 更新客户状态（启用/禁用）
 */
export function updateCustomerStatus(id: number, status: 1 | 2) {
  return post<null>("/admin/store/status", { store_id: id, status })
}

// 后端需补路由：后端旧版没有 /admin/store/reset-password
/**
 * 重置客户密码
 */
export function resetCustomerPassword(storeId: number, phone: string) {
  return post<null>("/admin/store/reset-password", { store_id: storeId, phone })
}

/**
 * 获取城市合伙人列表
 */
export function getPartnerList(params: { keyword?: string; page?: number; page_size?: number }) {
  return get<PaginatedData<PartnerItem>>("/admin/partner/list", params as unknown as Record<string, unknown>)
}
