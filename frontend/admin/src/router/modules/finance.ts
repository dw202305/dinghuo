import type { RouteRecordRaw } from "vue-router"

const financeRoutes: RouteRecordRaw[] = [
  {
    path: "finance",
    name: "Finance",
    redirect: "/finance/list",
    meta: { title: "财务管理", icon: "Money" },
    children: [
      {
        path: "list",
        name: "FinanceList",
        component: () => import("@/views/finance/FinanceList.vue"),
        meta: { title: "财务列表", permission: "finance:view" }
      },
      {
        path: "account",
        name: "CustomerAccount",
        component: () => import("@/views/finance/account/index.vue"),
        meta: { title: "客户资金账户", permission: "finance:account:view" }
      },
      {
        path: "recharge-audit",
        name: "RechargeAudit",
        component: () => import("@/views/finance/recharge-audit/index.vue"),
        meta: { title: "储值审核", permission: "finance:recharge-audit:view" }
      },
      {
        path: "balance-log",
        name: "BalanceLog",
        component: () => import("@/views/finance/balance-log/index.vue"),
        meta: { title: "余额流水", permission: "finance:balance-log:view" }
      },
      {
        path: "reconciliation",
        name: "Reconciliation",
        component: () => import("@/views/finance/reconciliation/index.vue"),
        meta: { title: "资金对账", permission: "finance:reconciliation:view" }
      }
    ]
  }
]

export default financeRoutes
