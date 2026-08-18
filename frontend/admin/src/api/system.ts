/**
 * 系统管理 API（RBAC）
 * 对齐后端新版路由前缀: /api/v1/admin/system/*
 */

import { get, post, del } from "./index"
import type { PaginatedData } from "@/types/api"
import type { AdminInfo, AdminSaveParams, RoleInfo, RoleSaveParams, PermissionNode, OperationLog } from "@/types/admin"

/**
 * 获取管理员列表
 * 对应后端路由: GET /api/v1/admin/system/admins
 */
export function getAdminList(params: { keyword?: string; role_id?: number; status?: number; page?: number; page_size?: number }) {
  return get<PaginatedData<AdminInfo>>("/admin/system/admins", params as unknown as Record<string, unknown>)
}

/**
 * 新增/编辑管理员
 * 对应后端路由: POST /api/v1/admin/system/admins
 */
export function saveAdmin(params: AdminSaveParams) {
  return post<{ admin_id: number }>("/admin/system/admins", params)
}

/**
 * 删除管理员
 * 对应后端路由: DELETE /api/v1/admin/system/admins/:id（控制器读 body admin_id）
 */
export function deleteAdmin(adminId: number) {
  return del<null>(`/admin/system/admins/${adminId}`, undefined, { data: { admin_id: adminId } })
}

// 后端需补路由：新版无重置管理员密码端点
/**
 * 重置管理员密码
 */
export function resetAdminPassword(adminId: number) {
  return post<null>("/admin/system/admins/reset-password", { admin_id: adminId })
}

/**
 * 更新管理员状态（启用/禁用）
 * 对应后端路由: POST /api/v1/admin/system/admins
 */
export function updateAdminStatus(adminId: number, status: 0 | 1) {
  return post<null>("/admin/system/admins", { admin_id: adminId, status })
}

/**
 * 获取角色列表
 * 对应后端路由: GET /api/v1/admin/system/roles
 */
export function getRoleList(params?: { page?: number; page_size?: number }) {
  return get<PaginatedData<RoleInfo>>("/admin/system/roles", params as unknown as Record<string, unknown>)
}

/**
 * 获取所有角色（不分页，用于下拉选择）
 * 对应后端路由: GET /api/v1/admin/system/roles
 */
export function getAllRoles() {
  return get<{ list: RoleInfo[] }>("/admin/system/roles", { page: 1, page_size: 999 })
}

/**
 * 新增/编辑角色
 * 对应后端路由: POST /api/v1/admin/system/roles
 */
export function saveRole(params: RoleSaveParams) {
  return post<{ role_id: number }>("/admin/system/roles", params)
}

/**
 * 删除角色
 * 对应后端路由: DELETE /api/v1/admin/system/roles/:id（控制器读 body role_id）
 */
export function deleteRole(roleId: number) {
  return del<null>(`/admin/system/roles/${roleId}`, undefined, { data: { role_id: roleId } })
}

/**
 * 获取权限树
 * 对应后端路由: GET /api/v1/admin/system/permissions/tree
 */
export function getPermissionTree() {
  return get<{ tree: PermissionNode[] }>("/admin/system/permissions/tree")
}

// 后端需补路由：新版无权限节点保存端点
/**
 * 新增/编辑权限节点
 */
export function savePermission(params: Record<string, unknown>) {
  return post<{ permission_id: number }>("/admin/system/permissions/save", params)
}

// 后端需补路由：新版无权限节点删除端点
/**
 * 删除权限节点
 */
export function deletePermission(permissionId: number) {
  return del<null>("/admin/system/permissions/delete", { permission_id: permissionId })
}

/**
 * 获取操作日志
 * 对应后端路由: GET /api/v1/admin/system/operation-logs
 */
export function getOperationLogs(params: Record<string, unknown>) {
  return get<PaginatedData<OperationLog>>("/admin/system/operation-logs", params)
}
