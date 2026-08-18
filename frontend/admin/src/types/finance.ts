/**
 * 财务管理模块类型定义
 * 储值账户、余额流水、对账等
 */

/** 客户资金账户 */
export interface CustomerAccount {
  id: number
  customer_id: number
  customer_name: string
  customer_no: string
  available_balance_cent: number
  frozen_balance_cent: number
  total_recharge_cent: number
  total_consumption_cent: number
  is_frozen: boolean
  last_operation_at: string
}

/** 客户资金账户列表查询参数 */
export interface AccountListParams {
  keyword?: string
  min_balance?: number
  max_balance?: number
  page: number
  page_size: number
  /** 允许附加动态查询字段 */
  [key: string]: unknown
}

/** 客户资金账户列表结果 */
export interface AccountListResult {
  list: CustomerAccount[]
  total: number
  page: number
  page_size: number
  /** 汇总：总客户数 */
  total_customer_count: number
  /** 汇总：总可用余额（分） */
  total_available_cent: number
  /** 汇总：总冻结余额（分） */
  total_frozen_cent: number
}

/** 余额调整参数 */
export interface AdjustBalanceParams {
  customer_id: number
  amount_cent: number
  reason: string
}

/** 储值审核记录 */
export interface RechargeAuditRecord {
  id: number
  recharge_no: string
  customer_name: string
  amount_cent: number
  payment_channel: string
  created_at: string
  status: 'pending' | 'approved' | 'rejected'
  auditor_name: string | null
  audit_remark: string | null
  audited_at: string | null
}

/** 储值审核列表查询参数 */
export interface AuditListParams {
  recharge_no?: string
  customer_name?: string
  status?: string
  start_date?: string
  end_date?: string
  page: number
  page_size: number
  /** 允许附加动态查询字段 */
  [key: string]: unknown
}

/** 储值审核列表结果 */
export interface AuditListResult {
  list: RechargeAuditRecord[]
  total: number
  page: number
  page_size: number
}

/** 审核记录项 */
export interface AuditHistoryItem {
  operator_name: string
  action: string
  remark: string
  created_at: string
}

/** 余额流水 */
export interface BalanceLog {
  id: number
  log_no: string
  customer_name: string
  type: 'recharge' | 'payment' | 'refund' | 'reversal' | 'adjustment'
  amount_cent: number
  balance_before_cent: number
  balance_after_cent: number
  related_order_no: string | null
  operator_name: string
  created_at: string
  remark: string
  approval_status?: 'pending' | 'approved' | 'rejected'
}

/** 余额流水查询参数 */
export interface BalanceLogParams {
  customer_id?: number
  keyword?: string
  type?: string
  start_date?: string
  end_date?: string
  page: number
  page_size: number
  /** 允许附加动态查询字段 */
  [key: string]: unknown
}

/** 余额流水列表结果 */
export interface BalanceLogResult {
  list: BalanceLog[]
  total: number
  page: number
  page_size: number
}

/** 冲正参数 */
export interface ReversalParams {
  original_log_id: number
  reason: string
}

/** 对账查询参数 */
export interface ReconciliationParams {
  period_type: 'day' | 'week' | 'month'
  date: string
  /** 允许附加动态查询字段 */
  [key: string]: unknown
}

/** 对账汇总 */
export interface ReconciliationSummary {
  period: string
  total_recharge_cent: number
  total_refund_cent: number
  net_income_cent: number
  wechat_received_cent: number
  alipay_received_cent: number
  balance_recharge_cent: number
  expected_cent: number
  actual_cent: number
  difference_cent: number
}

/** 对账明细查询参数 */
export interface ReconciliationDetailParams {
  period_type: 'day' | 'week' | 'month'
  start_date: string
  end_date: string
  page: number
  page_size: number
  /** 允许附加动态查询字段 */
  [key: string]: unknown
}

/** 对账明细 */
export interface ReconciliationDetail {
  date: string
  channel: string
  order_count: number
  expected_amount_cent: number
  actual_amount_cent: number
  difference_cent: number
  status: 'normal' | 'abnormal'
}

/** 对账明细列表结果 */
export interface ReconciliationDetailResult {
  list: ReconciliationDetail[]
  total: number
  page: number
  page_size: number
}
