/**
 * 库存记录
 */
export interface InventoryRecord {
  id: number
  store_id: number
  store_name: string
  store_no: string
  kit_sku: string
  kit_name: string
  total_purchased: number
  available: number
  locked: number
  consumed: number
  frozen: number
  return_pending: number
  adjusted: number
}

/**
 * 库存流水
 */
export interface InventoryLog {
  id: number
  store_id: number
  store_name: string
  kit_sku: string
  log_type: number
  log_type_text: string
  quantity: number
  before_quantity: number
  after_quantity: number
  order_no: string | null
  operator_name: string | null
  reason: string | null
  created_at: string
}

/**
 * 库存查询参数
 */
export interface InventoryListParams {
  store_id?: number
  kit_sku?: string
  keyword?: string
  page?: number
  page_size?: number
  /** 允许附加动态查询字段 */
  [key: string]: unknown
}
