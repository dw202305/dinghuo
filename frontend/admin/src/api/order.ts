/**
 * 订单管理 API（后台）
 * 对齐后端新版 RESTful 路由：/api/v1/admin/orders（backend/app/api/route/app.php v1/admin 组）
 * 注：控制器仍按 order_id 从 query/body 读取主键，故路径参数与 order_id 同传。
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { OrderListItem, OrderDetail, OrderListParams } from "@/types/order"

/**
 * 获取订单列表
 * 对应后端路由: GET /api/v1/admin/orders
 */
export function getOrderList(params: OrderListParams) {
  return get<PaginatedData<OrderListItem>>("/admin/orders", params as unknown as Record<string, unknown>)
}

/**
 * 获取订单详情
 * 对应后端路由: GET /api/v1/admin/orders/:id（控制器读 order_id 参数）
 */
export function getOrderDetail(orderId: number) {
  return get<OrderDetail>(`/admin/orders/${orderId}`, { order_id: orderId })
}

/**
 * 后台取消订单
 * 对应后端路由: POST /api/v1/admin/orders/:id/cancel
 */
export function cancelOrder(orderId: number, reason: string) {
  return post<null>(`/admin/orders/${orderId}/cancel`, { order_id: orderId, cancel_reason: reason })
}

// 后端需补路由：新版无订单导出端点（GET /api/v1/admin/orders/export 未定义）
/**
 * 导出订单
 */
export function exportOrders(params: OrderListParams) {
  return get<{ file_url: string; expire_at: string }>("/admin/orders/export", params as unknown as Record<string, unknown>)
}

/** 订单操作日志 */
export interface OrderTimeline {
  log_id: number
  action: string
  action_text: string
  operator_name: string
  remark: string | null
  created_at: string
}

// 后端需补路由：新版无订单时间线端点
/**
 * 获取订单操作日志
 */
export function getOrderTimeline(orderId: number) {
  return get<OrderTimeline[]>("/admin/orders/timeline", { order_id: orderId })
}

/**
 * 后台审核通过订单
 * 对应后端路由: POST /api/v1/admin/orders/:id/audit（audit_result: 1=通过 2=需确认 3=需补款 4=无法生产）
 */
export function auditPassOrder(orderId: number, remark: string) {
  return post<null>(`/admin/orders/${orderId}/audit`, { order_id: orderId, audit_result: 1, overall_remark: remark })
}

/**
 * 后台标记需门店确认
 * 对应后端路由: POST /api/v1/admin/orders/:id/audit
 */
export function auditNeedConfirm(orderId: number, remark: string, message: string) {
  return post<null>(`/admin/orders/${orderId}/audit`, {
    order_id: orderId,
    audit_result: 2,
    overall_remark: remark,
    confirm_message: message
  })
}

/**
 * 后台标记需补款
 * 对应后端路由: POST /api/v1/admin/orders/:id/audit
 */
export function auditNeedSupplement(orderId: number, remark: string, amount: number) {
  return post<null>(`/admin/orders/${orderId}/audit`, {
    order_id: orderId,
    audit_result: 3,
    overall_remark: remark,
    supplement_amount: amount
  })
}

/**
 * 后台标记无法生产
 * 对应后端路由: POST /api/v1/admin/orders/:id/audit
 */
export function auditCannotProduce(orderId: number, remark: string) {
  return post<null>(`/admin/orders/${orderId}/audit`, { order_id: orderId, audit_result: 4, overall_remark: remark })
}

/**
 * 录入物流信息
 * 对应后端路由: POST /api/v1/admin/orders/:id/ship
 * 注：后端要求 item_ids 非空，整单发货场景需由调用方补充明细 ID
 */
export function enterShipping(orderId: number, carrier: string, trackingNo: string) {
  return post<null>(`/admin/orders/${orderId}/ship`, { order_id: orderId, item_ids: [], carrier, tracking_no: trackingNo })
}
