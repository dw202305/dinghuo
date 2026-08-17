import type { ProductionStatus, QcStatus, ShippingStatus } from "./common"

/**
 * 生产单列表项
 */
export interface ProductionListItem {
  item_id: number
  item_no: string
  order_no: string
  order_id: number
  store_name: string
  store_id: number
  install_position: string
  fabric_no: string
  fabric_name: string
  width: string
  height: string
  area: string
  production_status: ProductionStatus
  production_status_text: string
  qc_status: QcStatus
  qc_status_text: string
  shipping_status: ShippingStatus
  shipping_status_text: string
  tracking_no: string | null
  carrier: string | null
  planned_date: string | null
  completed_at: string | null
  created_at: string
}

/**
 * 生产单查询参数
 */
export interface ProductionListParams {
  keyword?: string
  production_status?: ProductionStatus
  qc_status?: QcStatus
  shipping_status?: ShippingStatus
  store_id?: number
  store_name?: string
  start_date?: string
  end_date?: string
  page?: number
  page_size?: number
}

/**
 * 发货请求参数
 */
export interface ShipParams {
  item_id: number
  tracking_no: string
  carrier: string
}

/**
 * 批量更新生产状态参数
 */
export interface BatchUpdateStatusParams {
  item_ids: number[]
  status: ProductionStatus
}
