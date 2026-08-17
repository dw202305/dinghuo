import type { CustomerLevel, ChannelMode } from "./common"

/**
 * 门店（客户）列表项
 */
export interface CustomerListItem {
  id: number
  store_no: string
  store_name: string
  business_entity: string | null
  customer_level: CustomerLevel
  customer_level_text: string
  channel_mode: ChannelMode
  channel_mode_text: string
  partner_name: string | null
  primary_sales_name: string | null
  province: string | null
  city: string | null
  contact_phone: string | null
  primary_contact_name: string | null
  kit_available: number
  order_count: number
  order_amount: string
  status: 1 | 2 | 3
  status_text: string
  cooperation_start_date: string | null
  created_at: string
}

/**
 * 门店详情
 */
export interface CustomerDetail extends CustomerListItem {
  credit_code: string | null
  district: string | null
  address: string | null
  wechat: string | null
  showroom_photos: string[] | null
  invoice_title: string | null
  tax_no: string | null
  primary_contact: {
    name: string
    phone: string
  } | null
  contacts: CustomerContact[]
}

/**
 * 门店联系人
 */
export interface CustomerContact {
  id: number
  store_id: number
  contact_name: string
  phone: string
  wechat: string | null
  position: string | null
  contact_type: number
  contact_type_text: string
  is_primary: 0 | 1
  receive_order_notify: 0 | 1
  status: 0 | 1
}

/**
 * 客户列表查询参数
 */
export interface CustomerListParams {
  keyword?: string
  customer_level?: CustomerLevel
  channel_mode?: ChannelMode
  partner_id?: number
  status?: 1 | 2 | 3
  page?: number
  page_size?: number
}

/**
 * 新建客户参数
 */
export interface CustomerCreateParams {
  store_no: string
  store_name: string
  business_entity?: string
  customer_level: CustomerLevel
  channel_mode: ChannelMode
  partner_id?: number
  primary_sales_id: number
  province?: string
  city?: string
  district?: string
  address?: string
  contact_phone?: string
  primary_contact_name: string
  primary_contact_phone: string
}

/**
 * 城市合伙人
 */
export interface PartnerItem {
  id: number
  partner_no: string
  business_entity: string
  authorized_city: string | null
  primary_sales_name: string | null
  cooperation_stage: number
  partner_level: number
  status: 1 | 2 | 3
  status_text: string
  cooperation_start_date: string | null
}
