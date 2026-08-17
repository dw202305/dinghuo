/**
 * Axios 实例封装
 * 统一请求/响应拦截，对齐 API 规范
 */

import axios, { type AxiosInstance, type AxiosRequestConfig, type InternalAxiosRequestConfig, type AxiosResponse } from "axios"
import { ElMessage } from "element-plus"
import { getToken, clearAuth } from "@/utils/storage"
import router from "@/router"
import type { ApiResponse } from "@/types/api"

/** 创建 Axios 实例 */
const service: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL as string,
  timeout: 30000,
  headers: { "Content-Type": "application/json" }
})

/** 请求拦截器：注入 Token */
service.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = getToken()
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error: unknown) => Promise.reject(error)
)

/** 响应拦截器：统一错误处理 */
service.interceptors.response.use(
  (response: AxiosResponse<ApiResponse<unknown>>) => {
    const { data: body } = response

    // code === 0 成功
    if (body.code === 0) {
      return body.data as unknown as AxiosResponse
    }

    // code === 2001 → 未登录 / Token 无效
    if (body.code === 2001) {
      clearAuth()
      router.push("/login")
      ElMessage.error("登录已过期，请重新登录")
      return Promise.reject(new Error(body.message))
    }

    // code === 3001 / 3002 → 无权限
    if (body.code === 3001 || body.code === 3002) {
      ElMessage.error(body.message || "暂无操作权限")
      return Promise.reject(new Error(body.message))
    }

    // 其他非 0 → 业务错误
    ElMessage.error(body.message || "请求失败")
    return Promise.reject(new Error(body.message))
  },
  (error: unknown) => {
    if (axios.isAxiosError(error)) {
      if (error.response?.status === 401) {
        clearAuth()
        router.push("/login")
        ElMessage.error("登录已过期，请重新登录")
      } else {
        ElMessage.error(error.message || "网络异常")
      }
    }
    return Promise.reject(error)
  }
)

/**
 * GET 请求
 */
export function get<T>(url: string, params?: Record<string, unknown>, config?: AxiosRequestConfig): Promise<T> {
  return service.get(url, { params, ...config }) as Promise<T>
}

/**
 * POST 请求
 */
export function post<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T> {
  return service.post(url, data, config) as Promise<T>
}

/**
 * PUT 请求
 */
export function put<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T> {
  return service.put(url, data, config) as Promise<T>
}

/**
 * DELETE 请求
 */
export function del<T>(url: string, params?: Record<string, unknown>, config?: AxiosRequestConfig): Promise<T> {
  return service.delete(url, { params, ...config }) as Promise<T>
}

export default service
