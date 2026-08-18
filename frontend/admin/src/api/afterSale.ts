/**
 * 售后管理 API（后台）
 * 对齐后端新版 RESTful 路由：/api/v1/admin/after-sales
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"

/** 售后列表项 */
export interface AfterSaleListItem {
  after_sale_id: number
  after_sale_no: string
  order_no: string
  store_name: string
  problem_type: number
  problem_type_text: string
  status: number
  status_text: string
  handler_name: string | null
  created_at: string
}

/** 售后详情 */
export interface AfterSaleDetail extends AfterSaleListItem {
  store_no: string
  item_no: string
  problem_desc: string
  images: string[]
  videos: string[]
  install_date: string | null
  affect_usage: number
  contact_name: string
  contact_phone: string
  expected_solution: string | null
  diagnosis: string | null
  responsibility: number | null
  solution: string | null
  accessory_cost: string
  labor_cost: string
  logistics_cost: string
  created_by_name: string
}

/**
 * 获取售后列表
 * 对应后端路由: GET /api/v1/admin/after-sales
 */
export function getAfterSaleList(params: Record<string, unknown>) {
  return get<PaginatedData<AfterSaleListItem>>("/admin/after-sales", params)
}

/**
 * 获取售后详情
 * 对应后端路由: GET /api/v1/admin/after-sales/:id（控制器读 after_sale_id 参数）
 */
export function getAfterSaleDetail(afterSaleId: number) {
  return get<AfterSaleDetail>(`/admin/after-sales/${afterSaleId}`, { after_sale_id: afterSaleId })
}

/**
 * 处理售后
 * 对应后端路由: POST /api/v1/admin/after-sales/:id/process（body 需含 after_sale_id）
 */
export function processAfterSale(params: Record<string, unknown>) {
  const afterSaleId = Number(params.after_sale_id ?? 0)
  return post<null>(`/admin/after-sales/${afterSaleId}/process`, params)
}

/**
 * 分配处理人
 * 对应后端路由: POST /api/v1/admin/after-sales/:id/process
 */
export function assignAfterSaleHandler(afterSaleId: number, handlerId: number) {
  return post<null>(`/admin/after-sales/${afterSaleId}/process`, { after_sale_id: afterSaleId, handler_id: handlerId, status: 2 })
}

/**
 * 关闭售后
 * 对应后端路由: POST /api/v1/admin/after-sales/:id/close
 */
export function closeAfterSale(afterSaleId: number, closeReason: string) {
  return post<null>(`/admin/after-sales/${afterSaleId}/close`, { after_sale_id: afterSaleId, close_reason: closeReason })
}
