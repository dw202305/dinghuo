import type { StockStatus } from "./common"

/**
 * 面料列表项
 */
export interface FabricListItem {
  id: number
  fabric_no: string
  series: string | null
  name: string
  material: string | null
  color_name: string | null
  color_code: string | null
  price_per_sqm: string
  main_image: string | null
  stock_status: StockStatus
  listing_status: 0 | 1
  orderable: 0 | 1
  sort_weight: number
  created_at: string
}

/**
 * 面料详情
 */
export interface FabricDetail extends FabricListItem {
  texture_tags: string[] | null
  function_tags: string[] | null
  detail_images: string[] | null
  fabric_width: string | null
  min_billing_area: string | null
  loss_coefficient: string
  effective_date: string | null
  price_version: number
  updated_at: string
}

/**
 * 面料新增/编辑请求参数
 */
export interface FabricSaveParams {
  id?: number
  fabric_no: string
  series?: string
  name: string
  material?: string
  color_name?: string
  color_code?: string
  texture_tags?: string[]
  function_tags?: string[]
  price_per_sqm: number
  main_image?: string
  detail_images?: string[]
  fabric_width?: number
  min_billing_area?: number
  loss_coefficient?: number
  stock_status?: StockStatus
  listing_status?: 0 | 1
  orderable?: 0 | 1
  sort_weight?: number
  effective_date?: string
}

/**
 * 面料列表查询参数
 */
export interface FabricListParams {
  keyword?: string
  series?: string
  stock_status?: StockStatus
  listing_status?: 0 | 1
  orderable?: 0 | 1
  page?: number
  page_size?: number
}

/**
 * 面料供应商映射
 */
export interface FabricSupplierMapping {
  id: number
  fabric_id: number
  fabric_no: string
  supplier_id: number
  supplier_name: string
  supplier_fabric_no: string
  supplier_color_desc: string | null
  purchase_price: string | null
  delivery_days: number | null
  effective_date: string | null
  expire_date: string | null
  is_default_supplier: 0 | 1
  is_backup_supplier: 0 | 1
  quality_remark: string | null
  status: 0 | 1
}

/**
 * 面料供应商
 */
export interface FabricSupplier {
  id: number
  supplier_name: string
  contact_person: string | null
  contact_phone: string | null
  business_status: 1 | 2
  cooperation_start_date: string | null
  cooperation_end_date: string | null
}
