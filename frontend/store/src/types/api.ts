/** API 统一响应结构 */
export interface ApiResponse<T = unknown> {
  code: number;
  message: string;
  data: T;
}

/** 分页请求参数 */
export interface PaginationParams {
  page?: number;
  page_size?: number;
}

/** 分页响应数据 */
export interface PaginatedData<T> {
  list: T[];
  total: number;
  page: number;
  page_size: number;
}

/** 上传文件响应 */
export interface UploadResult {
  url: string;
  file_name: string;
}
