/**
 * 本地存储封装
 * 基于 localStorage，支持过期时间
 */

const TOKEN_KEY = "shishang_admin_token"
const USER_KEY = "shishang_admin_user"

/**
 * 获取 Token
 */
export function getToken(): string {
  return localStorage.getItem(TOKEN_KEY) ?? ""
}

/**
 * 设置 Token
 */
export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

/**
 * 移除 Token
 */
export function removeToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

/**
 * 获取管理员信息
 */
export function getAdminUser(): Record<string, unknown> | null {
  const raw = localStorage.getItem(USER_KEY)
  if (!raw) return null
  try {
    return JSON.parse(raw) as Record<string, unknown>
  } catch {
    return null
  }
}

/**
 * 设置管理员信息
 */
export function setAdminUser(user: Record<string, unknown>): void {
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

/**
 * 移除管理员信息
 */
export function removeAdminUser(): void {
  localStorage.removeItem(USER_KEY)
}

/**
 * 清除所有认证数据
 */
export function clearAuth(): void {
  removeToken()
  removeAdminUser()
}
