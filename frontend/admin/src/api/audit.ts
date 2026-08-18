/**
 * 技术审核 API（后台）
 * 对齐后端新版路由: POST /api/v1/admin/orders/:id/audit, GET /api/v1/admin/orders
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { AuditSubmitParams } from "@/types/order"

/** 审核动作 → 后端 audit_result 映射（1=通过 2=需确认 3=需补款 4=无法生产） */
const AUDIT_RESULT_MAP: Record<string, number> = {
  pass: 1,
  need_confirm: 2,
  need_supplement: 3,
  cannot_produce: 4
}

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
 * 对应后端路由: GET /api/v1/admin/orders，通过订单状态筛选（status → order_status）
 */
export function getAuditOrderList(params: { keyword?: string; status?: number; page?: number; page_size?: number }) {
  const { status, ...rest } = params
  return get<PaginatedData<AuditOrderGroup>>("/admin/orders", {
    ...rest,
    order_status: status
  } as unknown as Record<string, unknown>)
}

// 后端需补路由：新版无待审核明细列表端点
/**
 * 获取待审核列表（细粒度）
 */
export function getAuditList(params: { keyword?: string; page?: number; page_size?: number }) {
  return get<PaginatedData<AuditPendingItem>>("/admin/audit/list", params as unknown as Record<string, unknown>)
}

// 后端需补路由：新版仅 GET /api/v1/admin/orders/:id/audit（订单级），无明细级审核详情
/**
 * 获取审核详情
 */
export function getAuditDetail(itemId: number) {
  return get<AuditPendingItem>("/admin/audit/detail", { item_id: itemId })
}

/**
 * 提交审核结果
 * 对应后端路由: POST /api/v1/admin/orders/:id/audit
 * 注：AuditSubmitParams 为明细级参数，当前无对应后端端点，暂保留旧路径待后端补齐
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
 * 对应后端路由: POST /api/v1/admin/orders/:id/audit（action 映射为 audit_result）
 */
export function batchAuditOrder(orderId: number, action: string, remark: string, supplementAmount?: number, attachments?: string[]) {
  return post<null>(`/admin/orders/${orderId}/audit`, {
    order_id: orderId,
    audit_result: AUDIT_RESULT_MAP[action] ?? 1,
    overall_remark: remark,
    supplement_amount: supplementAmount,
    attachments
  })
}
