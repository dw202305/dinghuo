/**
 * 订单管理 API（后台）
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { OrderListItem, OrderDetail, OrderListParams } from "@/types/order"

/**
 * 获取订单列表
 */
export function getOrderList(params: OrderListParams) {
  return get<PaginatedData<OrderListItem>>("/admin/order/list", params as unknown as Record<string, unknown>)
}

/**
 * 获取订单详情
 */
export function getOrderDetail(orderId: number) {
  return get<OrderDetail>("/admin/order/detail", { order_id: orderId })
}

/**
 * 后台取消订单
 */
export function cancelOrder(orderId: number, reason: string) {
  return post<null>("/admin/order/cancel", { order_id: orderId, cancel_reason: reason })
}

/**
 * 导出订单
 */
export function exportOrders(params: OrderListParams) {
  return get<{ file_url: string; expire_at: string }>("/admin/order/export", params as unknown as Record<string, unknown>)
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

/**
 * 获取订单操作日志
 */
export function getOrderTimeline(orderId: number) {
  return get<OrderTimeline[]>("/admin/order/timeline", { order_id: orderId })
}

/**
 * 后台审核通过订单
 */
export function auditPassOrder(orderId: number, remark: string) {
  return post<null>("/admin/order/audit-pass", { order_id: orderId, remark })
}

/**
 * 后台标记需门店确认
 */
export function auditNeedConfirm(orderId: number, remark: string, message: string) {
  return post<null>("/admin/order/need-confirm", { order_id: orderId, remark, confirm_message: message })
}

/**
 * 后台标记需补款
 */
export function auditNeedSupplement(orderId: number, remark: string, amount: number) {
  return post<null>("/admin/order/need-supplement", { order_id: orderId, remark, supplement_amount: amount })
}

/**
 * 后台标记无法生产
 */
export function auditCannotProduce(orderId: number, remark: string) {
  return post<null>("/admin/order/cannot-produce", { order_id: orderId, remark })
}

/**
 * 录入物流信息
 */
export function enterShipping(orderId: number, carrier: string, trackingNo: string) {
  return post<null>("/admin/order/ship", { order_id: orderId, carrier, tracking_no: trackingNo })
}
