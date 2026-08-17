/**
 * 管理员认证 API
 */

import { post, get } from "./index"
import type { AdminLoginResult } from "@/types/admin"

/**
 * 管理员登录
 */
export function adminLogin(username: string, password: string) {
  return post<AdminLoginResult>("/admin/auth/login", { username, password })
}

/**
 * 获取当前管理员信息
 */
export function getAdminProfile() {
  return get<AdminLoginResult>("/admin/auth/profile")
}

/**
 * 管理员退出登录
 */
export function adminLogout() {
  return post<null>("/admin/auth/logout")
}
