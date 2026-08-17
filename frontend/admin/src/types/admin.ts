/**
 * 管理员信息
 */
export interface AdminInfo {
  admin_id: number
  username: string
  real_name: string
  phone: string | null
  email: string | null
  avatar: string | null
  role_id: number
  role_name: string
  role_ids?: number[]
  role_names?: string[]
  status: 0 | 1
  status_text: string
  /** 是否超级管理员 */
  is_super_admin?: boolean
  last_login_at: string | null
  login_count: number
  created_at?: string
}

/**
 * 管理员登录响应
 */
export interface AdminLoginResult {
  token: string
  expires_in: number
  admin_id: number
  real_name: string
  username: string
  role_id: number
  role_name: string
  permissions: string[]
  /** 是否超级管理员（架构师决策 Q2：is_super_admin=1 硬跳过权限检查） */
  is_super_admin: boolean
}

/**
 * 管理员保存请求参数
 */
export interface AdminSaveParams {
  admin_id?: number
  username: string
  password?: string
  real_name: string
  phone?: string
  email?: string
  avatar?: string
  role_id?: number
  role_ids?: number[]
  status?: 0 | 1
}

/**
 * 角色信息
 */
export interface RoleInfo {
  role_id: number
  role_name: string
  role_code: string
  description: string | null
  admin_count: number
  status: 0 | 1
  permission_ids?: number[]
}

/**
 * 角色保存请求参数
 */
export interface RoleSaveParams {
  role_id?: number
  role_name: string
  role_code: string
  description?: string
  sort_order?: number
  permission_ids: number[]
  status?: 0 | 1
}

/**
 * 权限节点
 */
export interface PermissionNode {
  permission_id: number
  parent_id: number
  permission_name: string
  permission_code: string
  permission_type: 1 | 2 | 3
  path: string | null
  icon: string | null
  sort_order: number
  status: 0 | 1
  children: PermissionNode[]
}

/**
 * 操作日志
 */
export interface OperationLog {
  log_id: number
  module: string
  action: string
  target_type: string
  target_id: number
  target_no: string | null
  before_data: Record<string, unknown> | null
  after_data: Record<string, unknown> | null
  operator_id: number
  operator_name: string
  operator_role: string | null
  ip_address: string | null
  remark: string | null
  created_at: string
}

/**
 * 收款记录项（财务管理页用）
 */
export interface ReceiptRecord {
  id: number
  payment_no: string
  order_no: string
  store_name: string
  pay_channel: number
  pay_channel_text: string
  pay_amount: string
  receivable_amount: string
  actual_amount: string
  pay_status: number
  pay_status_text: string
  paid_at: string | null
  transaction_id: string | null
  operator_name: string | null
  created_at: string
}

/**
 * 收款详情
 */
export interface ReceiptDetail extends ReceiptRecord {
  refund_amount: string | null
  refunded_at: string | null
  pay_method: string | null
  remark: string | null
}

/**
 * 发票详情
 */
export interface InvoiceDetail {
  id: number
  request_no: string
  order_no: string
  store_name: string
  invoice_type: number
  invoice_type_text: string
  title: string
  tax_no: string
  invoice_amount: string
  invoice_content: string
  invoice_no: string | null
  invoice_code: string | null
  status: number
  status_text: string
  related_order_count: number
  created_at: string
  invoiced_at: string | null
  rejected_at: string | null
  reject_reason: string | null
}

/**
 * 权限节点（含 label）
 */
export interface PermissionNodeWithLabel extends PermissionNode {
  label?: string
}
