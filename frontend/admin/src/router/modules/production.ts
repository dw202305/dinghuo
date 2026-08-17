import type { RouteRecordRaw } from "vue-router"

const productionRoutes: RouteRecordRaw[] = [
  {
    path: "production",
    name: "Production",
    redirect: "/production/list",
    meta: { title: "生产单管理", icon: "SetUp" },
    children: [
      {
        path: "list",
        name: "ProductionList",
        component: () => import("@/views/production/ProductionList.vue"),
        meta: { title: "生产单列表", permission: "production:view" }
      }
    ]
  }
]

export default productionRoutes
