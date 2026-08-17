/**
 * 系统管理 API（RBAC）
 * 后端旧版路由前缀: /api/v1/admin/system/*
 */

import { get, post, del } from "./index"
import type { PaginatedData } from "@/types/api"
import type { AdminInfo, AdminSaveParams, RoleInfo, RoleSaveParams, PermissionNode, PermissionNodeWithLabel, OperationLog } from "@/types/admin"

/**
 * 获取管理员列表
 * 对应后端路由: GET /api/v1/admin/system/admin/list
 */
export function getAdminList(params: { keyword?: string; role_id?: number; status?: number; page?: number; page_size?: number }) {
  return get<PaginatedData<AdminInfo>>("/admin/system/admin/list", params as unknown as Record<string, unknown>)
}

/**
 * 新增/编辑管理员
 * 对应后端路由: POST /api/v1/admin/system/admin/save
 */
export function saveAdmin(params: AdminSaveParams) {
  return post<{ admin_id: number }>("/admin/system/admin/save", params)
}

/**
 * 删除管理员
 * 对应后端路由: DELETE /api/v1/admin/system/admin/delete
 */
export function deleteAdmin(adminId: number) {
  return del<null>("/admin/system/admin/delete", { admin_id: adminId })
}

// 后端需补路由：后端旧版没有 /admin/system/admin/reset-password
/**
 * 重置管理员密码
 */
export function resetAdminPassword(adminId: number) {
  return post<null>("/admin/system/admin/reset-password", { admin_id: adminId })
}

/**
 * 更新管理员状态（启用/禁用）
 * 对应后端路由: POST /api/v1/admin/system/admin/save
 */
export function updateAdminStatus(adminId: number, status: 0 | 1) {
  return post<null>("/admin/system/admin/save", { admin_id: adminId, status })
}

/**
 * 获取角色列表
 * 对应后端路由: GET /api/v1/admin/system/role/list
 */
export function getRoleList(params?: { page?: number; page_size?: number }) {
  return get<PaginatedData<RoleInfo>>("/admin/system/role/list", params as unknown as Record<string, unknown>)
}

/**
 * 获取所有角色（不分页，用于下拉选择）
 * 对应后端路由: GET /api/v1/admin/system/role/list
 */
export function getAllRoles() {
  return get<{ list: RoleInfo[] }>("/admin/system/role/list", { page: 1, page_size: 999 })
}

/**
 * 新增/编辑角色
 * 对应后端路由: POST /api/v1/admin/system/role/save
 */
export function saveRole(params: RoleSaveParams) {
  return post<{ role_id: number }>("/admin/system/role/save", params)
}

/**
 * 删除角色
 * 对应后端路由: DELETE /api/v1/admin/system/role/delete
 */
export function deleteRole(roleId: number) {
  return del<null>("/admin/system/role/delete", { role_id: roleId })
}

/**
 * 获取权限树
 * 对应后端路由: GET /api/v1/admin/system/permission/tree
 */
export function getPermissionTree() {
  return get<{ tree: PermissionNode[] }>("/admin/system/permission/tree")
}

// 后端需补路由：后端旧版没有 /admin/system/permission/save
/**
 * 新增/编辑权限节点
 */
export function savePermission(params: Record<string, unknown>) {
  return post<{ permission_id: number }>("/admin/system/permission/save", params)
}

// 后端需补路由：后端旧版没有 /admin/system/permission/delete
/**
 * 删除权限节点
 */
export function deletePermission(permissionId: number) {
  return del<null>("/admin/system/permission/delete", { permission_id: permissionId })
}

/**
 * 获取操作日志
 * 对应后端路由: GET /api/v1/admin/system/operation-log
 */
export function getOperationLogs(params: Record<string, unknown>) {
  return get<PaginatedData<OperationLog>>("/admin/system/operation-log", params)
}
