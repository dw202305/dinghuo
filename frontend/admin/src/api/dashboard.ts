/**
 * 仪表盘统计 API（后台）
 */

import { get } from "./index"

/** 仪表盘统计数据 */
export interface DashboardStats {
  /** 总订单数 */
  total_orders: number
  /** 待支付 */
  pending_payment: number
  /** 生产中 */
  in_production: number
  /** 待发货 */
  pending_ship: number
  /** 已完成 */
  completed: number
  /** 待处理事项 */
  pending_items: {
    /** 待审核（已支付待审核） */
    pending_audit: number
    /** 待发货 */
    pending_ship: number
    /** 处理中售后 */
    after_sale: number
  }
}

/**
 * 获取仪表盘统计数据
 */
export function getDashboardStats() {
  return get<DashboardStats>("/admin/dashboard/stats")
}
