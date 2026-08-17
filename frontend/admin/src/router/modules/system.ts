import type { RouteRecordRaw } from "vue-router"

const systemRoutes: RouteRecordRaw[] = [
  {
    path: "system",
    name: "System",
    redirect: "/system/admin",
    meta: { title: "系统管理", icon: "Setting" },
    children: [
      {
        path: "admin",
        name: "AdminList",
        component: () => import("@/views/system/AdminList.vue"),
        meta: { title: "管理员管理", permission: "system:admin" }
      },
      {
        path: "role",
        name: "RoleList",
        component: () => import("@/views/system/RoleList.vue"),
        meta: { title: "角色管理", permission: "system:role" }
      },
      {
        path: "permission",
        name: "PermissionList",
        component: () => import("@/views/system/PermissionList.vue"),
        meta: { title: "权限管理", permission: "system:permission" }
      },
      {
        path: "operation-log",
        name: "OperationLog",
        component: () => import("@/views/system/OperationLog.vue"),
        meta: { title: "操作日志" }
      }
    ]
  }
]

export default systemRoutes
