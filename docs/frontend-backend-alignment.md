# 前后端路由对齐清单

> 生成时间：2026-07-22
> 后端路由文件：`backend/app/api/route/app.php`
> 说明：列出前端仍在用但后端旧版路径没有注册的接口，需后端补充。

---

## 一、门店端（`/api/v1/store/*` 或 `/api/v1/*`）需补充的路由

| # | 前端函数 | 前端请求路径 | HTTP 方法 | 说明 |
|---|---------|-------------|----------|------|
| 1 | `recordFabricUsage` | `/store/fabric/recent` | POST | 后端旧版仅有 GET /store/fabric/recent，无 POST |
| 2 | `getInvoiceableOrders` | `/store/invoice/uninvoiced-orders` | GET | 后端旧版完全没有此路由 |
| 3 | `getRechargeRecords` | `/balance-accounts/me/recharge-records` | GET | 新版 balance-accounts 下无 recharge-records 子路由 |

---

## 二、后台端（`/api/v1/admin/*`）需补充的路由

### 2.1 财务模块（`/admin/finance/*`）

| # | 前端函数 | 前端请求路径 | HTTP 方法 | 说明 |
|---|---------|-------------|----------|------|
| 1 | `getPaymentDetail` | `/admin/finance/payment-detail` | GET | 后端无此路由 |
| 2 | `markPaymentReceived` | `/admin/finance/mark-received` | POST | 后端无此路由 |
| 3 | `getInvoiceList` | `/admin/finance/invoice-list` | GET | 后端有 `/admin/invoice/list`，但 finance 前缀下没有 |
| 4 | `getInvoiceDetail` | `/admin/finance/invoice-detail` | GET | 后端无此路由 |
| 5 | `getReconciliationSummary` | `/admin/finance/reconciliation-summary` | GET | 后端无此路由（只有 reconciliation/export） |
| 6 | `getReconciliationDetail` | `/admin/finance/reconciliation-detail` | GET | 后端无此路由 |
| 7 | `adjustBalance` | `/admin/finance/adjust-balance` | POST | 后端无此路由 |
| 8 | `freezeAccount` / `unfreezeAccount` / `toggleAccountFreeze` | `/admin/finance/toggle-account-freeze` | POST | 后端无此路由 |
| 9 | `getAuditHistory` | `/admin/finance/recharge-audit-history` | GET | 后端无此路由 |
| 10 | `getBalanceLogList` | `/admin/finance/balance-log-list` | GET | 后端无此路由 |
| 11 | `createReversal` | `/admin/finance/balance-reversal` | POST | 后端无此路由 |
| 12 | `offlineRecharge` | `/admin/finance/offline-recharge` | POST | 后端无此路由 |

### 2.2 审核模块（`/admin/audit/*`）

| # | 前端函数 | 前端请求路径 | HTTP 方法 | 说明 |
|---|---------|-------------|----------|------|
| 1 | `getAuditList` | `/admin/audit/list` | GET | 后端新版有 GET /admin/orders/:id/audit，旧版没有 audit/list |
| 2 | `getAuditDetail` | `/admin/audit/detail` | GET | 后端无此路由 |
| 3 | `getAuditHistory` | `/admin/audit/history` | GET | 后端无此路由 |

### 2.3 物流模块（`/admin/logistics/*`）

| # | 前端函数 | 前端请求路径 | HTTP 方法 | 说明 |
|---|---------|-------------|----------|------|
| 1 | `getOrderItems` | `/admin/logistics/order-items` | GET | 后端无此路由（可考虑复用 /admin/order/detail） |
| 2 | `getOrderShippingInfo` | `/admin/logistics/shipping-info` | GET | 后端无此路由 |

### 2.4 客户模块

| # | 前端函数 | 前端请求路径 | HTTP 方法 | 说明 |
|---|---------|-------------|----------|------|
| 1 | `resetCustomerPassword` | `/admin/store/reset-password` | POST | 后端无此路由 |

### 2.5 客户等级模块（`/admin/customer-level/*`）

| # | 前端函数 | 前端请求路径 | HTTP 方法 | 说明 |
|---|---------|-------------|----------|------|
| 1 | `deleteCustomerLevel` | `/admin/customer-level/delete` | DELETE | 后端无此路由 |
| 2 | `updateCustomerLevelStatus` | `/admin/customer-level/update-status` | POST | 后端无此路由（可能可复用 update） |

### 2.6 系统管理模块（`/admin/system/*`）

| # | 前端函数 | 前端请求路径 | HTTP 方法 | 说明 |
|---|---------|-------------|----------|------|
| 1 | `resetAdminPassword` | `/admin/system/admin/reset-password` | POST | 后端无此路由 |
| 2 | `savePermission` | `/admin/system/permission/save` | POST | 后端无此路由 |
| 3 | `deletePermission` | `/admin/system/permission/delete` | DELETE | 后端无此路由 |

### 2.7 库存模块（`/admin/inventory/*`）

| # | 前端函数 | 前端请求路径 | HTTP 方法 | 说明 |
|---|---------|-------------|----------|------|
| 1 | `exportInventory` | `/admin/inventory/export` | GET | 后端无此路由 |

---

## 三、本次修正中已对齐的路由（后端已有，前端已修正）

### 门店端

| 前端函数 | 修正后路径 | 对应后端路由 |
|---------|-----------|-------------|
| `getAccountBalance` | `/balance-accounts/me` | `GET /api/v1/balance-accounts/:id` |
| `getBalanceTransactions` | `/balance-accounts/me/transactions` | `GET /api/v1/balance-accounts/:id/transactions` |
| `getTransactionDetail` | `/balance-accounts/me/transactions/:id` | `GET /api/v1/balance-accounts/:id/transactions` |
| `createRechargeOrder` | `/balance-accounts/me/recharge` | `POST /api/v1/balance-accounts/:id/recharge` |
| `cancelOrder` | `/store/order/cancel` (PUT) | `PUT /api/v1/store/order/cancel` |
| `confirmReceipt` | `/store/order/confirm-receive` | `POST /api/v1/store/order/confirm-receive` |
| `getFabricSeries` | `/store/fabric/series` | `GET /api/v1/store/fabric/series` |
| `getFabricFilterOptions` | `/store/fabric/filter-options` | `GET /api/v1/store/fabric/filter-options` |
| `getAddressDetail` | `/store/address/detail` | `GET /api/v1/store/address/detail` |
| `getWallControllerProducts` | `/store/product/wall-controller/list` | `GET /api/v1/store/product/wall-controller/list` |
| `getFabricStockList` | `/store/inventory/fabric-stock` | `GET /api/v1/store/inventory/fabric-stock` |

### 后台端

| 前端函数 | 修正后路径 | 对应后端路由 |
|---------|-----------|-------------|
| `getCustomerAccounts` | `/admin/finance/customer-accounts` | `GET /api/v1/admin/finance/customer-accounts` |
| `getRechargeAuditList` | `/admin/finance/recharge-audit/list` | `GET /api/v1/admin/finance/recharge-audit/list` |
| `approveRecharge` / `rejectRecharge` | `/admin/finance/recharge-audit/process` | `POST /api/v1/admin/finance/recharge-audit/process` |
| `getPendingShipments` | `/admin/logistics/list` | `GET /api/v1/admin/logistics/list` |
| `shipOrder` | `/admin/logistics/ship` | `POST /api/v1/admin/logistics/ship` |
| `saveCustomerLevel` | `/admin/customer-level/update` | `POST /api/v1/admin/customer-level/update` |
| `getCustomerLevelList` | `/admin/customer-level/list` | `GET /api/v1/admin/customer-level/list` |

---

## 四、建议后端优先补充的路由（按优先级排序）

### P0 - 核心业务流程阻断
1. **POST /api/v1/admin/finance/toggle-account-freeze** — 冻结/解冻账户，财务审核必需
2. **POST /api/v1/admin/finance/adjust-balance** — 调整余额，财务操作必需
3. **GET /api/v1/admin/finance/balance-log-list** — 余额流水查看，对账必需
4. **GET /api/v1/store/invoice/uninvoiced-orders** — 可开票订单查询，发票流程必需

### P1 - 重要功能缺失
5. **POST /api/v1/admin/finance/offline-recharge** — 线下充值录入
6. **POST /api/v1/admin/finance/balance-reversal** — 冲正操作
7. **GET /api/v1/admin/finance/reconciliation-summary** — 对账汇总
8. **GET /api/v1/admin/finance/reconciliation-detail** — 对账明细
9. **GET /api/v1/admin/logistics/order-items** — 发货明细

### P2 - 辅助功能
10. **POST /api/v1/admin/system/admin/reset-password** — 管理员密码重置
11. **POST /api/v1/admin/system/permission/save** — 权限节点管理
12. **DELETE /api/v1/admin/system/permission/delete** — 权限节点删除
13. **POST /api/v1/store/fabric/recent** — 记录面料使用
14. **GET /api/v1/admin/audit/list** — 细粒度审核列表
15. **GET /api/v1/admin/audit/detail** — 审核详情
16. **GET /api/v1/admin/audit/history** — 审核历史
