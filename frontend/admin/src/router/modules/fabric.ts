import type { RouteRecordRaw } from "vue-router"

const fabricRoutes: RouteRecordRaw[] = [
  {
    path: "fabric",
    name: "Fabric",
    redirect: "/fabric/list",
    meta: { title: "面料管理", icon: "Goods" },
    children: [
      {
        path: "list",
        name: "FabricList",
        component: () => import("@/views/fabric/FabricList.vue"),
        meta: { title: "面料列表", permission: "fabric:view" }
      },
      {
        path: "form/:id?",
        name: "FabricForm",
        component: () => import("@/views/fabric/FabricForm.vue"),
        meta: { title: "面料编辑", hidden: true, permission: "fabric:edit" }
      },
      {
        path: "import",
        name: "FabricImport",
        component: () => import("@/views/fabric/FabricImport.vue"),
        meta: { title: "批量导入", permission: "fabric:import" }
      }
    ]
  }
]

export default fabricRoutes
