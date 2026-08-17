/**
 * 权限判断工具函数
 */

/**
 * 判断是否拥有指定权限
 * @param permissions 当前用户权限列表
 * @param code 需要判断的权限编码
 */
export function hasPermission(permissions: string[], code: string): boolean {
  if (!code) return true
  return permissions.includes(code)
}

/**
 * 判断是否拥有任意一个权限
 * @param permissions 当前用户权限列表
 * @param codes 权限编码数组
 */
export function hasAnyPermission(permissions: string[], codes: string[]): boolean {
  if (!codes || codes.length === 0) return true
  return codes.some((code) => permissions.includes(code))
}

/**
 * 判断是否拥有所有权限
 * @param permissions 当前用户权限列表
 * @param codes 权限编码数组
 */
export function hasAllPermissions(permissions: string[], codes: string[]): boolean {
  if (!codes || codes.length === 0) return true
  return codes.every((code) => permissions.includes(code))
}
