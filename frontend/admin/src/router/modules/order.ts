import type { RouteRecordRaw } from "vue-router"

const orderRoutes: RouteRecordRaw[] = [
  {
    path: "order",
    name: "Order",
    redirect: "/order/list",
    meta: { title: "订单管理", icon: "Document" },
    children: [
      {
        path: "list",
        name: "OrderList",
        component: () => import("@/views/order/OrderList.vue"),
        meta: { title: "订单列表", permission: "order:view" }
      },
      {
        path: "detail/:id",
        name: "OrderDetail",
        component: () => import("@/views/order/OrderDetail.vue"),
        meta: { title: "订单详情", hidden: true, permission: "order:view" }
      }
    ]
  }
]

export default orderRoutes
