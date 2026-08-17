/**
 * 动态路由 / 菜单权限管理
 * 基于 RBAC 模型，根据管理员角色动态生成可访问路由
 */

import { defineStore } from "pinia"
import { ref } from "vue"
import type { RouteRecordRaw } from "vue-router"

/** 菜单项类型 */
export interface MenuItem {
  path: string
  title: string
  icon?: string
  children?: MenuItem[]
  hidden?: boolean
  permission?: string
}

export const usePermissionStore = defineStore("permission", () => {
  /** 动态添加的路由名称（用于移除） */
  const addedRoutes = ref<string[]>([])

  /** 侧边栏菜单 */
  const menuList = ref<MenuItem[]>([])

  /**
   * 根据权限过滤路由
   */
  function filterRoutesByPermission(routes: RouteRecordRaw[], permissions: string[]): RouteRecordRaw[] {
    return routes.filter((route) => {
      const meta = route.meta as { permission?: string } | undefined
      if (meta?.permission && !permissions.includes(meta.permission)) {
        return false
      }
      if (route.children && route.children.length > 0) {
        route.children = filterRoutesByPermission(route.children, permissions)
      }
      return true
    })
  }

  /**
   * 根据路由生成菜单
   */
  function generateMenu(routes: RouteRecordRaw[]): MenuItem[] {
    return routes
      .filter((route) => {
        const meta = route.meta as { hidden?: boolean; title?: string } | undefined
        return !meta?.hidden && meta?.title
      })
      .map((route) => {
        const meta = route.meta as { title?: string; icon?: string }
        const item: MenuItem = {
          path: route.path,
          title: meta?.title || "",
          icon: meta?.icon
        }
        if (route.children && route.children.length > 0) {
          item.children = generateMenu(route.children)
        }
        return item
      })
  }

  /**
   * 设置菜单列表
   */
  function setMenu(routes: RouteRecordRaw[]): void {
    menuList.value = generateMenu(routes)
  }

  /**
   * 记录已添加的路由
   */
  function addRouteName(name: string): void {
    addedRoutes.value.push(name)
  }

  /**
   * 重置
   */
  function resetPermission(): void {
    addedRoutes.value = []
    menuList.value = []
  }

  return {
    addedRoutes,
    menuList,
    filterRoutesByPermission,
    generateMenu,
    setMenu,
    addRouteName,
    resetPermission
  }
})
