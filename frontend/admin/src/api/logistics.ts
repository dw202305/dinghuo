/**
 * 发货管理 API（后台）
 * 后端旧版路由: GET /api/v1/admin/logistics/list, POST /api/v1/admin/logistics/ship
 */

import { get, post } from './index'
import type { ShipmentListParams, ShipmentListResult, ShipOrderParams, ShipOrderResult, ShippingInfo, ShipmentOrderItem } from '@/types/logistics'
import type { OrderDetail } from '@/types/order'

/**
 * 获取待发货订单列表
 * 对应后端路由: GET /api/v1/admin/logistics/list
 * @param params 查询参数（分页、筛选条件）
 */
export function getPendingShipments(params: ShipmentListParams): Promise<ShipmentListResult> {
  return get<ShipmentListResult>('/admin/logistics/list', params as unknown as Record<string, unknown>)
}

// 后端需补路由：后端旧版没有 /admin/logistics/order-items
/**
 * 获取订单窗帘明细（用于发货勾选）
 * @param orderId 订单主键 ID
 */
export function getOrderItems(orderId: number): Promise<ShipmentOrderItem[]> {
  return get<ShipmentOrderItem[]>('/admin/logistics/order-items', { order_id: orderId })
}

/**
 * 获取订单详情（发货弹窗用）
 * 对应后端路由: GET /api/v1/admin/order/detail
 * @param orderId 订单主键 ID
 */
export function getShipmentOrderDetail(orderId: number): Promise<OrderDetail> {
  return get<OrderDetail>('/admin/order/detail', { order_id: orderId })
}

/**
 * 执行发货
 * 对应后端路由: POST /api/v1/admin/logistics/ship
 * @param params 发货请求参数
 */
export function shipOrder(params: ShipOrderParams): Promise<ShipOrderResult> {
  return post<ShipOrderResult>('/admin/logistics/ship', params)
}

// 后端需补路由：后端旧版没有 /admin/logistics/shipping-info
/**
 * 获取订单物流信息
 * @param orderId 订单主键 ID
 */
export function getOrderShippingInfo(orderId: number): Promise<ShippingInfo[]> {
  return get<ShippingInfo[]>('/admin/logistics/shipping-info', { order_id: orderId })
}
