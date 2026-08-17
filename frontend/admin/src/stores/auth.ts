/**
 * 管理员认证状态管理
 */

import { defineStore } from "pinia"
import { ref, computed } from "vue"
import { getToken, setToken, removeToken, getAdminUser, setAdminUser, clearAuth } from "@/utils/storage"
import { adminLogin, getAdminProfile, adminLogout } from "@/api/auth"
import router from "@/router"

export const useAuthStore = defineStore("auth", () => {
  const token = ref<string>(getToken())
  const adminId = ref<number>(0)
  const realName = ref<string>("")
  const username = ref<string>("")
  const roleId = ref<number>(0)
  const roleName = ref<string>("")
  const permissions = ref<string[]>([])
  const avatar = ref<string>("")
  /** 是否超级管理员（架构师决策 Q2：is_super_admin=1 硬跳过权限检查） */
  const isSuperAdmin = ref<boolean>(false)

  /** 是否已登录 */
  const isLoggedIn = computed<boolean>(() => !!token.value)

  /** 是否拥有全部权限（超管或 permissions 包含 *） */
  const hasAllPermissions = computed<boolean>(() => isSuperAdmin.value || permissions.value.includes("*"))

  /**
   * 管理员登录
   */
  async function login(loginUsername: string, loginPassword: string): Promise<void> {
    const result = await adminLogin(loginUsername, loginPassword)
    token.value = result.token
    adminId.value = result.admin_id
    realName.value = result.real_name
    username.value = result.username
    roleId.value = result.role_id
    roleName.value = result.role_name
    permissions.value = result.permissions
    isSuperAdmin.value = result.is_super_admin ?? false
    setToken(result.token)
    setAdminUser({
      admin_id: result.admin_id,
      real_name: result.real_name,
      username: result.username,
      role_id: result.role_id,
      role_name: result.role_name,
      permissions: result.permissions,
      is_super_admin: result.is_super_admin ?? false
    })
  }

  /**
   * 刷新管理员信息（从服务端拉取最新权限）
   */
  async function refreshProfile(): Promise<void> {
    try {
      const result = await getAdminProfile()
      adminId.value = result.admin_id
      realName.value = result.real_name
      username.value = result.username
      roleId.value = result.role_id
      roleName.value = result.role_name
      permissions.value = result.permissions
      isSuperAdmin.value = (result as { is_super_admin?: boolean }).is_super_admin ?? false
      setAdminUser({
        admin_id: result.admin_id,
        real_name: result.real_name,
        username: result.username,
        role_id: result.role_id,
        role_name: result.role_name,
        permissions: result.permissions,
        is_super_admin: (result as { is_super_admin?: boolean }).is_super_admin ?? false
      })
    } catch {
      logout()
    }
  }

  /**
   * 退出登录
   */
  async function logout(): Promise<void> {
    try {
      await adminLogout()
    } finally {
      resetState()
      clearAuth()
      router.push("/login")
    }
  }

  /**
   * 从本地缓存恢复登录态
   */
  function restoreFromStorage(): void {
    const user = getAdminUser()
    if (user && token.value) {
      adminId.value = (user.admin_id as number) || 0
      realName.value = (user.real_name as string) || ""
      username.value = (user.username as string) || ""
      roleId.value = (user.role_id as number) || 0
      roleName.value = (user.role_name as string) || ""
      permissions.value = (user.permissions as string[]) || []
      isSuperAdmin.value = (user.is_super_admin as boolean) || false
    }
  }

  /**
   * 重置状态
   */
  function resetState(): void {
    token.value = ""
    adminId.value = 0
    realName.value = ""
    username.value = ""
    roleId.value = 0
    roleName.value = ""
    permissions.value = []
    avatar.value = ""
    isSuperAdmin.value = false
  }

  return {
    token,
    adminId,
    realName,
    username,
    roleId,
    roleName,
    permissions,
    avatar,
    isSuperAdmin,
    hasAllPermissions,
    isLoggedIn,
    login,
    refreshProfile,
    logout,
    restoreFromStorage,
    resetState
  }
})
