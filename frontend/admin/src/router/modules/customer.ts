import type { RouteRecordRaw } from "vue-router"

const customerRoutes: RouteRecordRaw[] = [
  {
    path: "customer",
    name: "Customer",
    redirect: "/customer/list",
    meta: { title: "客户管理", icon: "User" },
    children: [
      {
        path: "list",
        name: "CustomerList",
        component: () => import("@/views/customer/CustomerList.vue"),
        meta: { title: "客户列表", permission: "customer:view" }
      },
      {
        path: "level",
        name: "CustomerLevel",
        component: () => import("@/views/customer/CustomerLevel.vue"),
        meta: { title: "等级管理", permission: "customer:level" }
      }
    ]
  }
]

export default customerRoutes
