import type {
  OrderStatus,
  PaymentStatus,
  TechnicalStatus,
  ProductionStatus,
  QcStatus,
  ShippingStatus,
  DeliveryMethod,
  PowerType,
  RemoteType,
  WallControlType
} from "./common"

/**
 * 订单列表项
 */
export interface OrderListItem {
  order_id: number
  order_no: string
  order_status: OrderStatus
  order_status_text: string
  project_name: string | null
  end_customer: string | null
  item_count: number
  total_amount: string
  paid_amount: string
  payment_status: PaymentStatus
  payment_status_text: string
  store_name?: string
  created_at: string
  expected_delivery_date: string | null
}

/**
 * 订单详情
 */
export interface OrderDetail {
  order_id: number
  order_no: string
  order_status: OrderStatus
  order_status_text: string
  project_name: string | null
  end_customer: string | null
  delivery_method: DeliveryMethod
  delivery_method_text: string
  receiver: {
    name: string
    phone: string
    province: string
    city: string
    district: string
    detail: string
  }
  expected_delivery_date: string | null
  invoice_required: 0 | 1
  remark: string | null
  attachments: string[]
  items: OrderItemDetail[]
  summary: OrderSummary
  payment: OrderPayment
  store_name?: string
  store_no?: string
  created_at: string
  updated_at: string
}

/**
 * 订单窗帘明细
 */
export interface OrderItemDetail {
  item_id: number
  item_no: string
  sequence: number
  install_position: string
  width: string
  height: string
  area: string
  track_color: string
  track_horizontal_length: string
  track_vertical_length: string
  track_amount: string
  fabric_no: string
  fabric_name: string
  fabric_price: string
  fabric_amount: string
  power_type: PowerType
  power_type_text: string
  power_surcharge: string
  remote_type: RemoteType
  remote_type_text: string
  remote_surcharge: string
  wall_control_type: WallControlType
  wall_control_type_text: string
  wall_control_quantity: number
  wall_control_price: string
  wall_control_amount: string
  accessory_amount: string
  use_inventory: 0 | 1
  kit_price: string
  kit_amount: string
  nonstandard_amount: string
  item_total: string
  install_condition: string | null
  remark: string | null
  technical_status: TechnicalStatus
  technical_status_text: string
  production_status: ProductionStatus
  production_status_text: string
  qc_status?: QcStatus
  shipping_status: ShippingStatus
  shipping_status_text: string
  tracking_no?: string | null
  carrier?: string | null
}

/**
 * 订单汇总
 */
export interface OrderSummary {
  item_count: number
  track_amount: string
  fabric_area_total: string
  fabric_amount: string
  inventory_used_count: number
  new_purchase_count: number
  new_purchase_amount: string
  accessory_amount: string
  shipping_method: string
  nonstandard_amount: string
  discount_amount: string
  total_amount: string
}

/**
 * 订单支付信息
 */
export interface OrderPayment {
  payment_status: PaymentStatus
  payment_status_text: string
  paid_amount: string
  price_locked_until: string | null
}

/**
 * 订单列表查询参数
 */
export interface OrderListParams {
  order_status?: OrderStatus
  keyword?: string
  start_date?: string
  end_date?: string
  store_id?: number
  page?: number
  page_size?: number
  /** 允许附加动态查询字段 */
  [key: string]: unknown
}

/**
 * 后台审核请求参数
 */
export interface AuditSubmitParams {
  item_id: number
  action: "pass" | "need_confirm" | "need_supplement" | "cannot_produce"
  remark?: string
  nonstandard_amount?: number
  confirm_message?: string
}
