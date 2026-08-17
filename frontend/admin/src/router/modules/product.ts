import type { RouteRecordRaw } from "vue-router"

const productRoutes: RouteRecordRaw[] = [
  {
    path: "product",
    name: "Product",
    redirect: "/product/accessory",
    meta: { title: "商品管理", icon: "ShoppingCart" },
    children: [
      {
        path: "accessory",
        name: "AccessoryManage",
        component: () => import("@/views/product/AccessoryList.vue"),
        meta: { title: "选装配件管理" }
      },
      {
        path: "price",
        name: "PriceManage",
        component: () => import("@/views/product/PriceManage.vue"),
        meta: { title: "商品和价格管理" }
      }
    ]
  }
]

export default productRoutes
