/**
 * 认证相关组合式函数
 */

import { computed } from "vue"
import { useAuthStore } from "@/stores/auth"

export function useAuth() {
  const authStore = useAuthStore()

  const isLoggedIn = computed(() => authStore.isLoggedIn)
  const adminName = computed(() => authStore.realName)
  const adminRole = computed(() => authStore.roleName)

  return {
    isLoggedIn,
    adminName,
    adminRole,
    permissions: computed(() => authStore.permissions),
    login: authStore.login,
    logout: authStore.logout,
    refreshProfile: authStore.refreshProfile
  }
}
