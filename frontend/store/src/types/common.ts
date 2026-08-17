/** 订单状态枚举 */
export enum OrderStatus {
  /** 草稿 */
  DRAFT = 1,
  /** 待支付 */
  PENDING_PAYMENT = 2,
  /** 支付处理中 */
  PAYMENT_PROCESSING = 3,
  /** 已支付待审核 */
  PAID_PENDING_REVIEW = 4,
  /** 需要门店确认 */
  NEED_STORE_CONFIRM = 5,
  /** 待补款 */
  PENDING_SUPPLEMENT = 6,
  /** 审核通过待排产 */
  APPROVED_PENDING_SCHEDULE = 7,
  /** 生产中 */
  IN_PRODUCTION = 8,
  /** 质检中 */
  IN_QUALITY_CHECK = 9,
  /** 待发货 */
  PENDING_SHIPMENT = 10,
  /** 部分发货 */
  PARTIALLY_SHIPPED = 11,
  /** 已发货 */
  SHIPPED = 12,
  /** 已签收 */
  RECEIVED = 13,
  /** 已完成 */
  COMPLETED = 14,
  /** 售后处理中 */
  AFTER_SALE_PROCESSING = 15,
  /** 已取消 */
  CANCELLED = 16,
  /** 退款中 */
  REFUNDING = 17,
  /** 已退款 */
  REFUNDED = 18,
}

/** 订单状态文本映射 */
export const OrderStatusText: Record<OrderStatus, string> = {
  [OrderStatus.DRAFT]: '草稿',
  [OrderStatus.PENDING_PAYMENT]: '待支付',
  [OrderStatus.PAYMENT_PROCESSING]: '支付中',
  [OrderStatus.PAID_PENDING_REVIEW]: '待审核',
  [OrderStatus.NEED_STORE_CONFIRM]: '需确认',
  [OrderStatus.PENDING_SUPPLEMENT]: '待补款',
  [OrderStatus.APPROVED_PENDING_SCHEDULE]: '待排产',
  [OrderStatus.IN_PRODUCTION]: '生产中',
  [OrderStatus.IN_QUALITY_CHECK]: '质检中',
  [OrderStatus.PENDING_SHIPMENT]: '待发货',
  [OrderStatus.PARTIALLY_SHIPPED]: '部分发货',
  [OrderStatus.SHIPPED]: '已发货',
  [OrderStatus.RECEIVED]: '已签收',
  [OrderStatus.COMPLETED]: '已完成',
  [OrderStatus.AFTER_SALE_PROCESSING]: '售后中',
  [OrderStatus.CANCELLED]: '已取消',
  [OrderStatus.REFUNDING]: '退款中',
  [OrderStatus.REFUNDED]: '已退款',
};

/** 支付状态 */
export enum PaymentStatus {
  UNPAID = 0,
  PARTIAL = 1,
  PAID = 2,
}

/** 支付渠道 */
export enum PayChannel {
  WECHAT = 1,
  ALIPAY = 2,
}

/** 收货方式 */
export enum DeliveryMethod {
  TO_STORE = 1,
  TO_CUSTOMER = 2,
}

/** 电源类型 */
export enum PowerType {
  STANDARD = 1,
  LITHIUM_BATTERY = 2,
}

/** 遥控器类型 */
export enum RemoteType {
  STANDARD = 1,
  PRO = 2,
}

/** 墙面控制类型 */
export enum WallControlType {
  NONE = 0,
  STANDARD = 1,
  PRO = 2,
}

/** 轨道颜色 */
export type TrackColor = '黑色' | '白色' | '灰色';

/** 客户等级 */
export enum CustomerLevel {
  CERTIFIED_STORE = 1,
  CITY_PARTNER = 2,
  EXPERIENCE_CUSTOMER = 3,
  SPECIAL_CONTRACT = 4,
  LARGE_B = 5,
}

/** 售后问题类型 */
export enum ProblemType {
  MOTOR = 1,
  POWER = 2,
  REMOTE = 3,
  WALL_CONTROL = 4,
  TRACK = 5,
  FABRIC = 6,
  STRUCTURE = 7,
  INSTALLATION = 8,
  INITIALIZATION = 9,
  TRANSPORT_DAMAGE = 10,
  OTHER = 11,
}

/** 售后状态 */
export enum AfterSaleStatus {
  PENDING = 1,
  PROCESSING = 2,
  COMPLETED = 3,
  CLOSED = 4,
}

/** 发票类型 */
export enum InvoiceType {
  NORMAL = 1,
  SPECIAL = 2,
}


/** 订单状态字符串类型 - 用于状态机转换 */
export type OrderStatusKey =
  | 'draft' | 'pending_payment' | 'payment_processing'
  | 'paid_pending_audit' | 'needs_store_confirm' | 'needs_supplement'
  | 'audit_approved' | 'in_production' | 'quality_checking'
  | 'pending_shipment' | 'partial_shipment' | 'shipped'
  | 'received' | 'completed' | 'after_sale_processing'
  | 'cancelled' | 'refunding' | 'refunded';

/** 订单状态转换映射 - PRD §6 */
export const ALLOWED_TRANSITIONS: Record<OrderStatusKey, OrderStatusKey[]> = {
  draft: ['pending_payment', 'cancelled'],
  pending_payment: ['payment_processing', 'cancelled', 'draft'],
  payment_processing: ['paid_pending_audit', 'cancelled'],
  paid_pending_audit: ['needs_store_confirm', 'needs_supplement', 'audit_approved', 'cancelled'],
  needs_store_confirm: ['audit_approved', 'needs_supplement', 'cancelled'],
  needs_supplement: ['payment_processing', 'cancelled'],
  audit_approved: ['in_production', 'cancelled'],
  in_production: ['quality_checking'],
  quality_checking: ['pending_shipment'],
  pending_shipment: ['partial_shipment', 'shipped'],
  partial_shipment: ['shipped'],
  shipped: ['received', 'after_sale_processing'],
  received: ['completed', 'after_sale_processing'],
  completed: ['after_sale_processing'],
  after_sale_processing: ['completed', 'refunding', 'refunded'],
  cancelled: [],
  refunding: ['refunded'],
  refunded: []
};

/**
 * 检查状态转换是否合法
 * @param from - 当前状态
 * @param to - 目标状态
 * @returns 是否允许转换
 */
export function canTransition(from: OrderStatusKey, to: OrderStatusKey): boolean {
  return ALLOWED_TRANSITIONS[from]?.includes(to) ?? false;
}
