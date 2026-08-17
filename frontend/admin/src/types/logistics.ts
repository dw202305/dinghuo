/**
 * 发货管理类型定义
 */

/** 发货状态枚举（订单维度） */
export enum ShippingFilterStatus {
  ALL = 'all',
  PENDING = 'pending',
  PARTIAL = 'partial',
  SHIPPED = 'shipped'
}

/** 发货状态文字映射 */
export const ShippingFilterStatusMap: Record<ShippingFilterStatus, string> = {
  [ShippingFilterStatus.ALL]: '全部',
  [ShippingFilterStatus.PENDING]: '待发货',
  [ShippingFilterStatus.PARTIAL]: '部分发货',
  [ShippingFilterStatus.SHIPPED]: '已发货'
}

/** 发货列表查询参数 */
export interface ShipmentListParams {
  page: number
  page_size: number
  order_no?: string
  status?: ShippingFilterStatus
  start_date?: string
  end_date?: string
}

/** 待发货订单项 */
export interface PendingShipmentOrder {
  /** 订单主键 ID */
  id: number
  /** 订单号 */
  order_no: string
  /** 门店名称 */
  store_name: string
  /** 窗帘总数（订单下所有明细行数） */
  total_items: number
  /** 已发货明细行数 */
  shipped_items: number
  /** 下单时间 */
  order_time: string
  /** 期望交期 */
  expected_delivery_date: string
  /** 发货状态码：9=待发货，10=部分发货，11=已发货 */
  shipping_status: number
  /** 发货状态文字描述 */
  shipping_status_text: string
}

/** 发货列表结果 */
export interface ShipmentListResult {
  /** 列表数据 */
  list: PendingShipmentOrder[]
  /** 总数 */
  total: number
}

/** 订单窗帘明细（发货勾选用） */
export interface ShipmentOrderItem {
  /** 明细行 ID */
  item_id: number
  /** 明细编号 */
  item_no: string
  /** 安装位置 */
  position: string
  /** 尺寸（宽x高） */
  size: string
  /** 面料编号 */
  fabric_no: string
  /** 面料名称 */
  fabric_name: string
  /** 单号 */
  tracking_no: string | null
  /** 承运商 */
  carrier: string | null
  /** 发货状态：0=待发货，1=已发货 */
  shipping_status: number
}

/** 发货请求参数 */
export interface ShipOrderParams {
  /** 订单主键 ID */
  order_id: number
  /** 本次发货的窗帘明细 ID 列表 */
  item_ids: number[]
  /** 承运商名称 */
  carrier: string
  /** 物流单号 */
  tracking_no: string
}

/** 发货结果 */
export interface ShipOrderResult {
  /** 本次已发货的明细 ID 列表 */
  shipped_items: number[]
  /** 发货后订单状态码 */
  new_order_status: number
  /** 发货后订单状态文字 */
  new_order_status_text: string
}

/** 物流批次中的窗帘明细 */
export interface ShippingBatchItem {
  /** 明细行 ID */
  item_id: number
  /** 明细编号 */
  item_no: string
  /** 安装位置 */
  position: string
  /** 尺寸 */
  size: string
}

/** 物流批次信息 */
export interface ShippingInfo {
  /** 发货记录 ID */
  id: number
  /** 承运商名称 */
  carrier: string
  /** 运单号 */
  tracking_no: string
  /** 发货时间 */
  shipped_at: string
  /** 本批次发货明细 */
  shipped_items: ShippingBatchItem[]
}

/** 承运商选项 */
export interface CarrierOption {
  label: string
  value: string
}

/** 默认承运商列表 */
export const CARRIER_OPTIONS: CarrierOption[] = [
  { label: '顺丰速运', value: '顺丰速运' },
  { label: '德邦快递', value: '德邦快递' },
  { label: '京东物流', value: '京东物流' },
  { label: '安能物流', value: '安能物流' },
  { label: '其他', value: '其他' }
]
