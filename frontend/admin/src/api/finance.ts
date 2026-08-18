/**
 * 财务管理 API（后台）
 * 对应后端旧版路由前缀: /api/v1/admin/
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { ReceiptRecord, ReceiptDetail, InvoiceDetail } from "@/types/admin"
import type {
  AccountListParams,
  AccountListResult,
  AdjustBalanceParams,
  AuditListParams,
  AuditListResult,
  AuditHistoryItem,
  BalanceLogParams,
  BalanceLogResult,
  ReversalParams,
  ReconciliationParams,
  ReconciliationSummary,
  ReconciliationDetailParams,
  ReconciliationDetailResult
} from "@/types/finance"

/** 支付记录项（兼容旧代码） */
export interface PaymentRecord {
  id: number
  payment_no: string
  order_no: string
  store_name: string
  pay_channel: number
  pay_channel_text: string
  pay_amount: string
  pay_status: number
  pay_status_text: string
  paid_at: string | null
  created_at: string
}

/** 发票申请项（兼容旧代码） */
export interface InvoiceItem {
  id: number
  request_no: string
  order_no: string
  store_name: string
  invoice_type: number
  invoice_type_text: string
  title: string
  tax_no: string
  invoice_amount: string
  status: number
  status_text: string
  created_at: string
}

/**
 * 获取支付记录列表（收款记录）
 * 对应后端路由: GET /api/v1/admin/finance/payment/list
 */
export function getPaymentList(params: Record<string, unknown>) {
  return get<PaginatedData<ReceiptRecord>>("/admin/finance/payment/list", params)
}

// 后端需补路由：后端旧版没有 /admin/finance/payment-detail
/**
 * 获取收款详情
 */
export function getPaymentDetail(paymentId: number) {
  return get<ReceiptDetail>("/admin/finance/payment-detail", { payment_id: paymentId })
}

// 后端需补路由：后端旧版没有 /admin/finance/mark-received
/**
 * 标记已收款
 */
export function markPaymentReceived(params: Record<string, unknown>) {
  return post<null>("/admin/finance/mark-received", params)
}

// 后端需补路由：后端旧版没有 /admin/finance/invoice-list（有 /admin/invoice/list）
/**
 * 获取发票申请列表
 */
export function getInvoiceList(params: Record<string, unknown>) {
  return get<PaginatedData<InvoiceItem>>("/admin/finance/invoice-list", params)
}

// 后端需补路由：后端旧版没有 /admin/finance/invoice-detail
/**
 * 获取发票详情
 */
export function getInvoiceDetail(requestId: number) {
  return get<InvoiceDetail>("/admin/finance/invoice-detail", { request_id: requestId })
}

/**
 * 发票审核
 * 对应后端路由: POST /api/v1/admin/finance/invoice/review
 */
export function reviewInvoice(params: Record<string, unknown>) {
  return post<null>("/admin/finance/invoice/review", params)
}

/**
 * 开具发票（更新状态为已开票）
 * 对应后端路由: POST /api/v1/admin/finance/invoice/review
 */
export function issueInvoice(requestId: number, invoiceNo: string, invoiceCode: string) {
  return post<null>("/admin/finance/invoice/review", { request_id: requestId, action: 2, invoice_no: invoiceNo, invoice_code: invoiceCode })
}

/**
 * 作废发票
 * 对应后端路由: POST /api/v1/admin/finance/invoice/review
 */
export function invalidateInvoice(requestId: number, reason: string) {
  return post<null>("/admin/finance/invoice/review", { request_id: requestId, action: 3, reject_reason: reason })
}

/**
 * 对账导出
 * 对应后端路由: GET /api/v1/admin/finance/reconciliation/export
 */
export function exportReconciliation(params: Record<string, unknown>) {
  return get<{ file_url: string }>("/admin/finance/reconciliation/export", params)
}

// 后端需补路由：后端旧版没有 /admin/finance/reconciliation-summary
/**
 * 获取资金对账汇总
 */
export function getReconciliationSummary(params: ReconciliationParams): Promise<ReconciliationSummary> {
  return get<ReconciliationSummary>("/admin/finance/reconciliation-summary", params as unknown as Record<string, unknown>)
}

// 后端需补路由：后端旧版没有 /admin/finance/reconciliation-detail
/**
 * 获取资金对账明细
 */
export function getReconciliationDetail(params: ReconciliationDetailParams): Promise<ReconciliationDetailResult> {
  return get<ReconciliationDetailResult>("/admin/finance/reconciliation-detail", params as unknown as Record<string, unknown>)
}

/** ============================================
 *  储值账户 & 余额相关 API
 *  对应后端旧版路由前缀: /api/v1/admin/finance/*
 *  ============================================ */

/**
 * 获取客户资金账户列表
 * 对应后端路由: GET /api/v1/admin/finance/customer-accounts
 */
export function getCustomerAccounts(params: AccountListParams): Promise<AccountListResult> {
  return get<AccountListResult>("/admin/finance/customer-accounts", params as unknown as Record<string, unknown>)
}

/**
 * 获取客户资金账户详情
 * 对应后端路由: GET /api/v1/admin/finance/customer-accounts（传 id 参数）
 * 注：后端可能需要补充独立的详情接口
 * @param id 账户ID
 */
export function getBalanceAccountDetail(id: number) {
  return get<AccountListResult>("/admin/finance/customer-accounts", { id } as Record<string, unknown>)
}

// 后端需补路由：后端旧版没有 /admin/finance/adjust-balance
/**
 * 调整客户余额（需审批记录）
 */
export function adjustBalance(params: AdjustBalanceParams): Promise<void> {
  return post<void>("/admin/finance/adjust-balance", params)
}

/**
 * 冻结客户账户
 * 对应后端路由: POST /api/v1/admin/finance/toggle-account-freeze
 * 注：后端需补此路由
 */
export function freezeAccount(accountId: number): Promise<void> {
  return post<void>("/admin/finance/toggle-account-freeze", { account_id: accountId, freeze: true })
}

/**
 * 解冻客户账户
 * 对应后端路由: POST /api/v1/admin/finance/toggle-account-freeze
 * 注：后端需补此路由
 */
export function unfreezeAccount(accountId: number): Promise<void> {
  return post<void>("/admin/finance/toggle-account-freeze", { account_id: accountId, freeze: false })
}

/**
 * 冻结/解冻客户账户（兼容旧调用方式）
 * 对应后端路由: POST /api/v1/admin/finance/toggle-account-freeze
 * 注：后端需补此路由
 */
export function toggleAccountFreeze(accountId: number, freeze: boolean): Promise<void> {
  return post<void>("/admin/finance/toggle-account-freeze", { account_id: accountId, freeze })
}

/**
 * 获取储值审核列表
 * 对应后端路由: GET /api/v1/admin/finance/recharge-audit/list
 */
export function getRechargeAuditList(params: AuditListParams): Promise<AuditListResult> {
  return get<AuditListResult>("/admin/finance/recharge-audit/list", params as unknown as Record<string, unknown>)
}

/**
 * 审核通过充值
 * 对应后端路由: POST /api/v1/admin/finance/recharge-audit/process
 */
export function approveRecharge(rechargeNo: string, remark?: string): Promise<void> {
  return post<void>("/admin/finance/recharge-audit/process", { id: rechargeNo, action: 1, remark })
}

/**
 * 审核拒绝充值
 * 对应后端路由: POST /api/v1/admin/finance/recharge-audit/process
 */
export function rejectRecharge(rechargeNo: string, reason: string): Promise<void> {
  return post<void>("/admin/finance/recharge-audit/process", { id: rechargeNo, action: 2, reason })
}

// 后端需补路由：后端旧版没有 /admin/finance/recharge-audit-history
/**
 * 获取审核历史记录
 */
export function getAuditHistory(rechargeNo: string): Promise<AuditHistoryItem[]> {
  return get<AuditHistoryItem[]>("/admin/finance/recharge-audit-history", { recharge_no: rechargeNo })
}

// 后端需补路由：后端旧版没有 /admin/finance/balance-log-list
/**
 * 获取余额流水列表
 */
export function getBalanceLogList(params: BalanceLogParams): Promise<BalanceLogResult> {
  return get<BalanceLogResult>("/admin/finance/balance-log-list", params as unknown as Record<string, unknown>)
}

// 后端需补路由：后端旧版没有 /admin/finance/balance-reversal
/**
 * 发起冲正（新建反向流水）
 */
export function createReversal(params: ReversalParams): Promise<void> {
  return post<void>("/admin/finance/balance-reversal", params)
}

// 后端需补路由：后端旧版没有 /admin/finance/offline-recharge
/**
 * 线下充值（管理员操作）
 */
export function offlineRecharge(params: { account_id: number; amount_cent: number; remark?: string }): Promise<void> {
  return post<void>("/admin/finance/offline-recharge", params)
}
