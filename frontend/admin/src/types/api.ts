/**
 * 统一 API 响应类型
 */
export interface ApiResponse<T = unknown> {
  code: number
  message: string
  data: T
}

/**
 * 分页响应类型
 */
export interface PaginatedData<T> {
  list: T[]
  total: number
  page: number
  page_size: number
}

/**
 * 分页请求参数
 */
export interface PaginationParams {
  page?: number
  page_size?: number
}

/**
 * 通用排序参数
 */
export interface SortParams {
  sort_field?: string
  sort_order?: "asc" | "desc"
}
