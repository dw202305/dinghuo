import type {
  OrderStatus, PaymentStatus, DeliveryMethod,
  PowerType, RemoteType, WallControlType, TrackColor
} from './common';

/** 订单项（列表用） */
export interface OrderListItem {
  order_id: number;
  order_no: string;
  order_status: OrderStatus;
  order_status_text: string;
  project_name: string | null;
  end_customer: string | null;
  item_count: number;
  total_amount: string;
  paid_amount: string;
  payment_status: PaymentStatus;
  payment_status_text: string;
  created_at: string;
  expected_delivery_date: string | null;
}

/** 订单详情 */
export interface OrderDetail {
  order_id: number;
  order_no: string;
  order_status: OrderStatus;
  order_status_text: string;
  project_name: string | null;
  end_customer: string | null;
  delivery_method: DeliveryMethod;
  delivery_method_text: string;
  receiver: ReceiverInfo;
  expected_delivery_date: string | null;
  invoice_required: number;
  remark: string | null;
  attachments: string[];
  items: OrderItem[];
  summary: OrderSummary;
  payment: PaymentInfo;
  created_at: string;
  updated_at: string;
}

/** 收件人信息 */
export interface ReceiverInfo {
  name: string;
  phone: string;
  province: string;
  city: string;
  district: string;
  detail: string;
}

/** 窗帘明细 */
export interface OrderItem {
  item_id: number;
  item_no: string;
  sequence: number;
  install_position: string;
  width: string;
  height: string;
  area: string;
  track_color: TrackColor;
  track_horizontal_length: string;
  track_vertical_length: string;
  track_amount: string;
  fabric_no: string;
  fabric_name: string;
  fabric_price: string;
  fabric_amount: string;
  power_type: PowerType;
  power_type_text: string;
  power_surcharge: string;
  remote_type: RemoteType;
  remote_type_text: string;
  remote_surcharge: string;
  wall_control_type: WallControlType;
  wall_control_type_text: string;
  wall_control_quantity: number;
  wall_control_price: string;
  wall_control_amount: string;
  accessory_amount: string;
  use_inventory: number;
  kit_price: string;
  kit_amount: string;
  nonstandard_amount: string;
  item_total: string;
  install_condition: string | null;
  remark: string | null;
  technical_status: number;
  technical_status_text: string;
  production_status: number;
  production_status_text: string;
  shipping_status: number;
  shipping_status_text: string;
}

/** 订单汇总 */
export interface OrderSummary {
  item_count: number;
  track_amount: string;
  fabric_area_total: string;
  fabric_amount: string;
  inventory_used_count: number;
  new_purchase_count: number;
  new_purchase_amount: string;
  accessory_amount: string;
  shipping_method: string;
  nonstandard_amount: string;
  discount_amount: string;
  total_amount: string;
}

/** 支付信息 */
export interface PaymentInfo {
  payment_status: PaymentStatus;
  payment_status_text: string;
  paid_amount: string;
  price_locked_until: string | null;
}

/** 创建订单参数 */
export interface CreateOrderParams {
  project_name?: string;
  end_customer?: string;
  delivery_method?: DeliveryMethod;
  address_id?: number;
  receiver_name?: string;
  receiver_phone?: string;
  receiver_province?: string;
  receiver_city?: string;
  receiver_district?: string;
  receiver_detail?: string;
  expected_delivery_date?: string;
  invoice_required?: number;
  remark?: string;
  attachments?: string[];
  save_address?: number;
}

/** 创建订单响应 */
export interface CreateOrderResult {
  order_id: number;
  order_no: string;
}

/** 新增窗帘明细参数 */
export interface AddOrderItemParams {
  order_id: number;
  install_position: string;
  width: number;
  height: number;
  track_color: TrackColor;
  fabric_no: string;
  power_type?: PowerType;
  remote_type?: RemoteType;
  wall_control_type?: WallControlType;
  wall_control_quantity?: number;
  use_inventory?: number;
  install_condition?: string;
  remark?: string;
}

/** 新增窗帘明细响应 */
export interface AddOrderItemResult {
  item_id: number;
  item_no: string;
  track_amount: string;
  fabric_amount: string;
  accessory_amount: string;
  kit_amount: string;
  nonstandard_amount: string;
  item_total: string;
  is_nonstandard: boolean;
  nonstandard_hint: string | null;
}

/** 订单预览数据 */
export interface OrderPreviewData {
  order_no: string;
  items: OrderPreviewItem[];
  summary: OrderSummary;
  inventory_summary: InventorySummary;
}

/** 预览中的单个窗帘项 */
export interface OrderPreviewItem {
  item_no: string;
  install_position: string;
  width: string;
  height: string;
  area: string;
  track_amount: string;
  fabric_amount: string;
  accessory_amount: string;
  kit_amount: string;
  nonstandard_amount: string;
  item_total: string;
}

/** 库存概览 */
export interface InventorySummary {
  kit_available: number;
  kit_locked_other: number;
  kit_use_in_order: number;
  kit_remaining_after_order: number;
}

/** 提交订单参数 */
export interface SubmitOrderParams {
  order_id: number;
  confirmed: 1;
}

/** 订单列表查询参数 */
export interface OrderListParams {
  order_status?: OrderStatus;
  keyword?: string;
  start_date?: string;
  end_date?: string;
  page?: number;
  page_size?: number;
}

/** 支付渠道（v3.2 新增余额支付） */
export type PaymentChannel = 'balance' | 'wechat' | 'alipay';

/** 余额信息 */
export interface BalanceInfo {
  available_balance_cent: number;
  frozen_balance_cent: number;
  currency: string;
}

/** 余额支付结果 */
export interface BalancePaymentResult {
  payment_no: string;
  status: 'success' | 'failed';
  message?: string;
}

/** 预审结果 */
export interface PreAuditResult {
  audit_status: 'pending' | 'approved' | 'rejected';
  audit_no: string;
}
