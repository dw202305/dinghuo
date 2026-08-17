/**
 * 客户等级管理类型定义
 */

/**
 * 客户等级列表项
 */
export interface CustomerLevelItem {
  id: number
  level: number
  name: string
  code: string
  discount_rate: number
  points_multiplier: number
  min_consumption: string
  store_count: number
  sort_order: number
  status: 0 | 1
  description: string
  created_at: string
}

/**
 * 客户等级列表查询参数
 */
export interface CustomerLevelListParams {
  status?: 0 | 1
  page?: number
  page_size?: number
}

/**
 * 客户等级保存参数
 */
export interface CustomerLevelSaveParams {
  id?: number
  level: number
  name: string
  code: string
  discount_rate: number
  points_multiplier: number
  min_consumption: number
  sort_order: number
  status?: 0 | 1
  description?: string
}
