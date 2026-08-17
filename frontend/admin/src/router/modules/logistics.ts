/**
 * 发货管理路由模块
 */

import type { RouteRecordRaw } from 'vue-router'

const logisticsRoutes: RouteRecordRaw[] = [
  {
    path: 'logistics',
    name: 'Logistics',
    redirect: '/logistics/shipping',
    meta: { title: '发货管理', icon: 'Truck' },
    children: [
      {
        path: 'shipping',
        name: 'ShippingManagement',
        component: () => import('@/views/logistics/ShippingManagement.vue'),
        meta: { title: '发货管理', permission: 'logistics:view' }
      }
    ]
  }
]

export default logisticsRoutes
