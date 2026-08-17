/**
 * 技术审核 API（后台）
 * 后端已有路由: POST /api/v1/admin/order/audit, GET /api/v1/admin/order/list
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { AuditSubmitParams } from "@/types/order"

/** 待审核窗帘明细项 */
export interface AuditPendingItem {
  item_id: number
  item_no: string
  order_no: string
  store_name: string
  install_position: string
  fabric_no: string
  fabric_name: string
  width: string
  height: string
  area: string
  track_color: string
  technical_status: number
  technical_status_text: string
  remark: string | null
  created_at: string
}

/**
 * 获取待审核订单列表
 * 对应后端路由: GET /api/v1/admin/order/list，通过审核状态筛选
 */
export function getAuditOrderList(params: { keyword?: string; status?: number; page?: number; page_size?: number }) {
  return get<PaginatedData<AuditOrderGroup>>("/admin/order/list", params as unknown as Record<string, unknown>)
}

// 后端需补路由：后端新版有 GET /api/v1/admin/orders/:id/audit，旧版没有 audit/list
/**
 * 获取待审核列表（细粒度）
 */
export function getAuditList(params: { keyword?: string; page?: number; page_size?: number }) {
  return get<PaginatedData<AuditPendingItem>>("/admin/audit/list", params as unknown as Record<string, unknown>)
}

// 后端需补路由：后端旧版没有 /admin/audit/detail
/**
 * 获取审核详情
 */
export function getAuditDetail(itemId: number) {
  return get<AuditPendingItem>("/admin/audit/detail", { item_id: itemId })
}

/**
 * 提交审核结果
 * 对应后端路由: POST /api/v1/admin/order/audit
 */
export function submitAudit(params: AuditSubmitParams) {
  return post<null>("/admin/order/audit", params)
}

/** 审核历史记录 */
export interface AuditHistoryItem {
  log_id: number
  item_id: number
  action: string
  action_text: string
  remark: string
  operator_name: string
  attachments: string[]
  created_at: string
}

// 后端需补路由：后端旧版没有 /admin/audit/history
/**
 * 获取审核历史记录
 */
export function getAuditHistory(itemId: number) {
  return get<AuditHistoryItem[]>("/admin/audit/history", { item_id: itemId })
}

/**
 * 订单级审核列表（按订单号聚合的待审核项）
 */
export interface AuditOrderGroup {
  order_id: number
  order_no: string
  store_name: string
  project_name: string | null
  item_count: number
  pending_count: number
  created_at: string
  order_status: number
  order_status_text: string
}

/**
 * 订单批量审核
 * 对应后端路由: POST /api/v1/admin/order/audit
 */
export function batchAuditOrder(orderId: number, action: string, remark: string, supplementAmount?: number, attachments?: string[]) {
  return post<null>("/admin/order/audit", { order_id: orderId, action, remark, supplement_amount: supplementAmount, attachments })
}
