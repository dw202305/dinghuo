/**
 * 商品管理 API（轨道）
 * 对应后端新版路由前缀: /api/v1/admin/products/tracks
 */

import { get, post } from "./index"
import type { PaginatedData } from "@/types/api"
import type { TrackItem } from "@/types/product"

/**
 * 轨道列表查询参数
 */
export interface TrackListParams {
  track_type?: 1 | 2
  color?: string
  enabled?: 0 | 1
  page?: number
  page_size?: number
}

/**
 * 获取轨道列表
 * 对应后端路由: GET /api/v1/admin/products/tracks
 */
export function getTrackList(params?: TrackListParams) {
  return get<PaginatedData<TrackItem>>("/admin/products/tracks", params as unknown as Record<string, unknown>)
}

/**
 * 新增/编辑轨道
 * 对应后端路由: POST /api/v1/admin/products/tracks
 */
export function saveTrack(data: Partial<TrackItem>) {
  return post<{ id: number }>("/admin/products/tracks", data)
}
