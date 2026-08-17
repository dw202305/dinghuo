/**
 * 订单状态枚举
 */
export enum OrderStatus {
  DRAFT = 1,
  PENDING_PAYMENT = 2,
  PAYMENT_PROCESSING = 3,
  PAID_PENDING_AUDIT = 4,
  NEED_STORE_CONFIRM = 5,
  PENDING_SUPPLEMENT = 6,
  AUDIT_PASSED_PENDING_PRODUCTION = 7,
  IN_PRODUCTION = 8,
  QUALITY_CHECK = 9,
  PENDING_SHIPMENT = 10,
  PARTIAL_SHIPPED = 11,
  SHIPPED = 12,
  RECEIVED = 13,
  COMPLETED = 14,
  AFTER_SALE_PROCESSING = 15,
  CANCELLED = 16,
  REFUNDING = 17,
  REFUNDED = 18
}

/**
 * 订单状态文字映射
 */
export const OrderStatusMap: Record<OrderStatus, string> = {
  [OrderStatus.DRAFT]: "草稿",
  [OrderStatus.PENDING_PAYMENT]: "待支付",
  [OrderStatus.PAYMENT_PROCESSING]: "支付处理中",
  [OrderStatus.PAID_PENDING_AUDIT]: "已支付待审核",
  [OrderStatus.NEED_STORE_CONFIRM]: "需门店确认",
  [OrderStatus.PENDING_SUPPLEMENT]: "待补款",
  [OrderStatus.AUDIT_PASSED_PENDING_PRODUCTION]: "审核通过待排产",
  [OrderStatus.IN_PRODUCTION]: "生产中",
  [OrderStatus.QUALITY_CHECK]: "质检中",
  [OrderStatus.PENDING_SHIPMENT]: "待发货",
  [OrderStatus.PARTIAL_SHIPPED]: "部分发货",
  [OrderStatus.SHIPPED]: "已发货",
  [OrderStatus.RECEIVED]: "已签收",
  [OrderStatus.COMPLETED]: "已完成",
  [OrderStatus.AFTER_SALE_PROCESSING]: "售后处理中",
  [OrderStatus.CANCELLED]: "已取消",
  [OrderStatus.REFUNDING]: "退款中",
  [OrderStatus.REFUNDED]: "已退款"
}

/**
 * 支付状态枚举
 */
export enum PaymentStatus {
  UNPAID = 0,
  PARTIAL = 1,
  PAID = 2
}

/**
 * 支付渠道枚举
 */
export enum PayChannel {
  WECHAT = 1,
  ALIPAY = 2
}

/**
 * 审核状态枚举
 */
export enum AuditStatus {
  PENDING = 0,
  PASSED = 1,
  NEED_CONFIRM = 2,
  NEED_SUPPLEMENT = 3,
  CANNOT_PRODUCE = 4
}

/**
 * 技术状态枚举
 */
export enum TechnicalStatus {
  PENDING = 0,
  PASSED = 1,
  NEED_CONFIRM = 2,
  NEED_SUPPLEMENT = 3,
  CANNOT_PRODUCE = 4
}

/**
 * 生产状态枚举
 */
export enum ProductionStatus {
  PENDING = 0,
  IN_PRODUCTION = 1,
  QC = 2,
  COMPLETED = 3
}

/**
 * 质检状态枚举
 */
export enum QcStatus {
  PENDING = 0,
  PASSED = 1,
  FAILED = 2
}

/**
 * 发货状态枚举
 */
export enum ShippingStatus {
  PENDING = 0,
  SHIPPED = 1,
  RECEIVED = 2
}

/**
 * 客户等级枚举
 */
export enum CustomerLevel {
  CERTIFIED_STORE = 1,
  CITY_PARTNER = 2,
  EXPERIENCE_CUSTOMER = 3,
  SPECIAL_CONTRACT = 4,
  LARGE_B = 5
}

/**
 * 客户等级文字映射
 */
export const CustomerLevelMap: Record<CustomerLevel, string> = {
  [CustomerLevel.CERTIFIED_STORE]: "认证合作门店",
  [CustomerLevel.CITY_PARTNER]: "城市合伙人",
  [CustomerLevel.EXPERIENCE_CUSTOMER]: "产品体验客户",
  [CustomerLevel.SPECIAL_CONTRACT]: "特殊合同客户",
  [CustomerLevel.LARGE_B]: "大B客户"
}

/**
 * 渠道模式枚举
 */
export enum ChannelMode {
  PARTNER_CHANNEL = 1,
  DIRECT = 2
}

/**
 * 售后状态枚举
 */
export enum AfterSaleStatus {
  PENDING = 1,
  PROCESSING = 2,
  COMPLETED = 3,
  CLOSED = 4
}

/**
 * 问题类型枚举
 */
export enum ProblemType {
  MOTOR = 1,
  POWER = 2,
  REMOTE = 3,
  WALL_CONTROL = 4,
  TRACK = 5,
  FABRIC = 6,
  STRUCTURE = 7,
  INSTALL = 8,
  INIT = 9,
  TRANSPORT_DAMAGE = 10,
  OTHER = 11
}

/**
 * 责任判断枚举
 */
export enum Responsibility {
  SHISHANG = 1,
  STORE = 2,
  LOGISTICS = 3,
  OTHER = 4
}

/**
 * 发票状态枚举
 */
export enum InvoiceStatus {
  PENDING_REVIEW = 1,
  REVIEWED_PENDING = 2,
  INVOICED = 3,
  REJECTED = 4
}

/**
 * 面料库存状态枚举
 */
export enum StockStatus {
  SUFFICIENT = 1,
  TIGHT = 2,
  OUT_OF_STOCK = 3
}

/**
 * 通用状态枚举
 */
export enum CommonStatus {
  DISABLED = 0,
  ENABLED = 1
}

/**
 * 收货方式枚举
 */
export enum DeliveryMethod {
  TO_STORE = 1,
  TO_CUSTOMER = 2
}

/**
 * 电源类型枚举
 */
export enum PowerType {
  STANDARD = 1,
  LITHIUM = 2
}

/**
 * 遥控器类型枚举
 */
export enum RemoteType {
  STANDARD = 1,
  PRO = 2
}

/**
 * 墙面控制类型枚举
 */
export enum WallControlType {
  NONE = 0,
  STANDARD = 1,
  PRO = 2
}
