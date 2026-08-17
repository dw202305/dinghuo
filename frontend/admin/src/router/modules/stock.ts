import type { RouteRecordRaw } from "vue-router"

const stockRoutes: RouteRecordRaw[] = [
  {
    path: "stock",
    name: "Stock",
    redirect: "/stock/list",
    meta: { title: "库存管理", icon: "Box" },
    children: [
      {
        path: "list",
        name: "StockList",
        component: () => import("@/views/stock/StockList.vue"),
        meta: { title: "库存总览", permission: "inventory:view" }
      }
    ]
  }
]

export default stockRoutes
