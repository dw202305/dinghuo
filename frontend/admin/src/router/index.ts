/**
 * 路由配置
 */

import { createRouter, createWebHistory } from "vue-router"
import type { RouteRecordRaw } from "vue-router"
import NProgress from "nprogress"
import "nprogress/nprogress.css"
import { getToken } from "@/utils/storage"

NProgress.configure({ showSpinner: false })

/** 公开路由 */
const publicRoutes: RouteRecordRaw[] = [
  {
    path: "/login",
    name: "Login",
    component: () => import("@/views/login/LoginPage.vue"),
    meta: { title: "登录", hidden: true }
  }
]

/** 需要认证的路由（使用 DefaultLayout） */
const authenticatedRoutes: RouteRecordRaw[] = [
  {
    path: "/",
    component: () => import("@/layouts/DefaultLayout.vue"),
    redirect: "/dashboard",
    children: [
      {
        path: "dashboard",
        name: "Dashboard",
        component: () => import("@/views/dashboard/DashboardPage.vue"),
        meta: { title: "工作台", icon: "Odometer" }
      },
      // 技术审核（一级菜单）
      {
        path: "audit",
        name: "AuditWorkbench",
        component: () => import("@/views/audit/AuditWorkbench.vue"),
        meta: { title: "技术审核", icon: "Check", permission: "audit:view" }
      },
      // 发票管理（一级菜单）
      {
        path: "invoice",
        name: "InvoiceList",
        component: () => import("@/views/invoice/InvoiceList.vue"),
        meta: { title: "发票管理", icon: "Tickets", permission: "invoice:view" }
      }
    ]
  }
]

/** 404 兜底 */
const fallbackRoutes: RouteRecordRaw[] = [
  {
    path: "/:pathMatch(.*)*",
    name: "NotFound",
    component: () => import("@/views/login/LoginPage.vue"),
    meta: { title: "404", hidden: true }
  }
]

/** 动态路由模块 */
const routeModules = import.meta.glob("./modules/*.ts", { eager: true })
const dynamicModuleRoutes: RouteRecordRaw[] = []

Object.values(routeModules).forEach((mod: unknown) => {
  const module = mod as { default: RouteRecordRaw[] }
  if (module.default && Array.isArray(module.default)) {
    dynamicModuleRoutes.push(...module.default)
  }
})

// 将动态模块路由插入到 authenticatedRoutes 的 children 中
if (authenticatedRoutes[0]?.children) {
  authenticatedRoutes[0].children.push(...dynamicModuleRoutes)
}

const router = createRouter({
  history: createWebHistory(),
  routes: [...publicRoutes, ...authenticatedRoutes, ...fallbackRoutes],
  scrollBehavior: () => ({ top: 0 })
})

/** 白名单路由 */
const whiteList = ["/login"]

/** 路由守卫 */
router.beforeEach((to, _from, next) => {
  NProgress.start()
  document.title = `${(to.meta.title as string) || ""} - 世尚后台管理`

  const token = getToken()

  if (token) {
    if (to.path === "/login") {
      next({ path: "/" })
    } else {
      next()
    }
  } else {
    if (whiteList.includes(to.path)) {
      next()
    } else {
      next(`/login?redirect=${to.path}`)
    }
  }
})

router.afterEach(() => {
  NProgress.done()
})

export default router
