/**
 * 商品管理类型定义（轨道/选装配件/批量调价）
 * 字段与后端 lj_track / lj_accessory 表对齐，价格字段单位为分
 */

/**
 * 选装配件列表项
 */
export interface AccessoryItem {
  id: number
  sku: string
  name: string
  image: string | null
  /** 配置组：power/remote/wall_control */
  config_group: string
  /** 类型：1标准 2升级 3新增 */
  option_type: 1 | 2 | 3
  option_type_text?: string
  /** 加价或补差价（分） */
  surcharge_cent: number
  /** 升级价格（分） */
  upgrade_price_cent: number | null
  /** 合伙人加价（分） */
  partner_surcharge_cent: number | null
  /** 是否必选：0否 1是 */
  required: 0 | 1
  /** 选择模式：1单选 2多选 */
  select_mode: 1 | 2
  /** 是否允许数量：0否 1是 */
  allow_quantity: 0 | 1
  max_quantity: number | null
  applicable_products: unknown
  compatibility_rules: unknown
  stock_status: number
  /** 是否启用：1是 0否 */
  enabled: 0 | 1
  effective_date: string | null
  price_version: number
  created_at?: string
  updated_at?: string
}

/**
 * 配件新增/编辑请求参数
 */
export interface AccessorySaveParams {
  id?: number
  sku: string
  name: string
  image?: string
  config_group: string
  option_type: 1 | 2 | 3
  surcharge_cent: number
  upgrade_price_cent?: number | null
  partner_surcharge_cent?: number | null
  required?: 0 | 1
  select_mode?: 1 | 2
  allow_quantity?: 0 | 1
  max_quantity?: number | null
  enabled?: 0 | 1
  effective_date?: string | null
}

/**
 * 轨道列表项
 */
export interface TrackItem {
  id: number
  sku: string
  /** 类型：1横轨 2竖轨 */
  track_type: 1 | 2
  track_type_text?: string
  color: string
  /** 标准原料长度（米） */
  standard_length: string
  /** 门店单价（分/米） */
  price_per_meter_cent: number
  /** 合伙人价格（分） */
  partner_price_cent: number | null
  enabled: 0 | 1
  effective_date: string | null
  price_version: number
  remark: string | null
  created_at?: string
  updated_at?: string
}

/**
 * 面料批量调价请求参数
 * 对应后端 POST /api/v1/admin/product/fabric/batch-price
 * adjust_type: fixed=固定金额增减（元/㎡） percent=百分比调整
 */
export interface FabricBatchPriceParams {
  fabric_ids: number[]
  adjust_type: "fixed" | "percent"
  adjust_value: number
  effective_date: string
  reason: string
}
