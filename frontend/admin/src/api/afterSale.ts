/**
 * 售后管理 API（后台）
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
 */
export function getAfterSaleList(params: Record<string, unknown>) {
  return get<PaginatedData<AfterSaleListItem>>("/admin/after-sale/list", params)
}

/**
 * 获取售后详情
 */
export function getAfterSaleDetail(afterSaleId: number) {
  return get<AfterSaleDetail>("/admin/after-sale/detail", { after_sale_id: afterSaleId })
}

/**
 * 处理售后
 */
export function processAfterSale(params: Record<string, unknown>) {
  return post<null>("/admin/after-sale/process", params)
}

/**
 * 分配处理人
 */
export function assignAfterSaleHandler(afterSaleId: number, handlerId: number) {
  return post<null>("/admin/after-sale/process", { after_sale_id: afterSaleId, handler_id: handlerId, status: 2 })
}

/**
 * 关闭售后
 */
export function closeAfterSale(afterSaleId: number, closeReason: string) {
  return post<null>("/admin/after-sale/close", { after_sale_id: afterSaleId, close_reason: closeReason })
}
