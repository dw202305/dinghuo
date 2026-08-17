/** 门店库存概览 */
export interface StoreInventory {
  id: number;
  store_id: number;
  kit_sku: string;
  total_purchased: number;
  available: number;
  locked: number;
  consumed: number;
  frozen: number;
  return_pending: number;
  adjusted: number;
}

/** 库存流水项 */
export interface InventoryLogItem {
  id: number;
  store_id: number;
  inventory_id: number;
  log_type: InventoryLogType;
  log_type_text: string;
  quantity: number;
  before_quantity: number;
  after_quantity: number;
  order_id: number | null;
  order_no: string | null;
  operator_name: string | null;
  reason: string | null;
  created_at: string;
}

/** 库存流水类型 */
export enum InventoryLogType {
  PURCHASE = 1,
  ORDER_LOCK = 2,
  PAYMENT_CONSUME = 3,
  CANCEL_RELEASE = 4,
  REFUND_RETURN = 5,
  AFTER_SALE_REPLACE = 6,
  MANUAL_ADJUST = 7,
  STORE_TRANSFER = 8,
}

/** 库存流水查询参数 */
export interface InventoryLogParams {
  log_type?: InventoryLogType;
  start_date?: string;
  end_date?: string;
  page?: number;
  page_size?: number;
}

/** 套件库存概览（汇总） */
export interface StockOverview {
  kit_available: number;
  kit_locked: number;
  kit_total: number;
}

/** 面料库存项 */
export interface FabricStockItem {
  fabric_no: string;
  fabric_name: string;
  series: string;
  available_area: string;
  reserved_area: string;
}

/** 库存流水类型（前端筛选用） */
export type FlowFilterType = 'all' | 'in' | 'out' | 'lock' | 'unlock';
