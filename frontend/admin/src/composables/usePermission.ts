/**
 * 按钮级权限判断组合式函数
 */

import { computed } from "vue"
import { useAuthStore } from "@/stores/auth"
import { hasPermission, hasAnyPermission } from "@/utils/permission"

export function usePermission() {
  const authStore = useAuthStore()

  /**
   * 判断是否拥有指定权限
   */
  function has(code: string): boolean {
    return hasPermission(authStore.permissions, code)
  }

  /**
   * 判断是否拥有任意一个权限
   */
  function hasAny(codes: string[]): boolean {
    return hasAnyPermission(authStore.permissions, codes)
  }

  /**
   * 是否显示（用于 v-if）
   */
  const show = computed(() => {
    return (code: string) => has(code)
  })

  return {
    has,
    hasAny,
    show
  }
}
