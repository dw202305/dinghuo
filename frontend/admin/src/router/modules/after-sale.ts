import type { RouteRecordRaw } from "vue-router"

const afterSaleRoutes: RouteRecordRaw[] = [
  {
    path: "after-sale",
    name: "AfterSale",
    redirect: "/after-sale/list",
    meta: { title: "售后管理", icon: "Service" },
    children: [
      {
        path: "list",
        name: "AfterSaleList",
        component: () => import("@/views/after-sale/AfterSaleList.vue"),
        meta: { title: "售后列表", permission: "after_sale:view" }
      }
    ]
  }
]

export default afterSaleRoutes
