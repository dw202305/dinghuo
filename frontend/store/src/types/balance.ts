/** 余额信息 */
export interface BalanceInfo {
  /** 可用余额，单位分 */
  available_balance_cent: number
  /** 冻结金额，单位分 */
  frozen_balance_cent: number
  /** 币种 */
  currency: string
}

/** 流水类型 */
export type TransactionType = 'recharge' | 'payment' | 'refund' | 'reversal'

/** 流水记录 */
export interface BalanceTransaction {
  /** 流水ID */
  id: number
  /** 流水号 */
  transaction_no: string
  /** 流水类型 */
  type: TransactionType
  /** 类型标签 */
  type_label: string
  /** 金额，单位分（正数收入，负数支出） */
  amount_cent: number
  /** 变动前余额，单位分 */
  balance_before_cent: number
  /** 变动后余额，单位分 */
  balance_after_cent: number
  /** 关联订单号 */
  related_order_no: string | null
  /** 备注 */
  remark: string
  /** 创建时间 */
  created_at: string
}

/** 流水列表参数 */
export interface TransactionListParams {
  /** 页码 */
  page: number
  /** 每页数量 */
  page_size: number
  /** 流水类型筛选 */
  type?: TransactionType
  /** 开始日期 YYYY-MM-DD */
  start_date?: string
  /** 结束日期 YYYY-MM-DD */
  end_date?: string
}

/** 流水列表结果 */
export interface TransactionListResult {
  /** 流水列表 */
  list: BalanceTransaction[]
  /** 总条数 */
  total: number
  /** 收入合计，单位分 */
  income_total_cent: number
  /** 支出合计，单位分 */
  expense_total_cent: number
}

/** 充值参数 */
export interface RechargeParams {
  /** 充值金额，单位分 */
  amount_cent: number
  /** 支付渠道 */
  payment_channel: 'wechat' | 'alipay'
  /** 幂等键 */
  idempotency_key: string
}

/** 充值结果 */
export interface RechargeResult {
  /** 充值单号 */
  recharge_no: string
  /** 第三方支付参数 */
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  payment_params: any
}

/** 充值记录参数 */
export interface RechargeListParams {
  /** 页码 */
  page: number
  /** 每页数量 */
  page_size: number
  /** 充值状态筛选 */
  status?: 'pending' | 'success' | 'failed'
}

/** 充值记录 */
export interface RechargeRecord {
  /** 记录ID */
  id: number
  /** 充值单号 */
  recharge_no: string
  /** 充值金额，单位分 */
  amount_cent: number
  /** 充值状态 */
  status: 'pending' | 'success' | 'failed'
  /** 支付渠道 */
  payment_channel: string
  /** 创建时间 */
  created_at: string
  /** 完成时间 */
  completed_at: string | null
}
