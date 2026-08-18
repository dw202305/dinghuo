/**
 * 选装配件管理 API（后台）
 * 对应后端新版路由前缀: /api/v1/admin/products/accessories
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { AccessoryItem, AccessorySaveParams } from "@/types/product"

/**
 * 配件列表查询参数
 */
export interface AccessoryListParams {
  config_group?: string
  enabled?: 0 | 1
  page?: number
  page_size?: number
}

/**
 * 获取配件列表
 * 对应后端路由: GET /api/v1/admin/products/accessories
 */
export function getAccessoryList(params?: AccessoryListParams) {
  return get<PaginatedData<AccessoryItem>>("/admin/products/accessories", params as unknown as Record<string, unknown>)
}

/**
 * 新增/编辑配件
 * 对应后端路由: POST /api/v1/admin/products/accessories
 */
export function saveAccessory(data: AccessorySaveParams) {
  return post<{ id: number }>("/admin/products/accessories", data)
}

/**
 * 启用/停用配件
 * 后端无独立状态接口，复用 save 只更新 enabled 字段
 */
export function toggleAccessoryEnabled(id: number, enabled: 0 | 1) {
  return post<{ id: number }>("/admin/products/accessories", { id, enabled })
}
