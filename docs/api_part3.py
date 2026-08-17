content = r'''

### 3.5 订单管理（/api/v1/admin/order/*）

---

#### 订单列表
- 路径：GET /api/v1/admin/order/list
- 说明：获取全局订单列表，支持多维度筛选
- 权限：后台-订单管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索关键词（订单号/项目名/终端客户） |
| order_status | int | 否 | 订单状态筛选 |
| payment_status | int | 否 | 支付状态：0未支付 1部分 2已支付 |
| audit_status | int | 否 | 审核状态：0未审核 1通过 2需确认 3待补款 4无法生产 |
| transaction_type | int | 否 | 交易主体类型：1门店 2合伙人 |
| partner_id | int | 否 | 合伙人ID筛选 |
| store_id | int | 否 | 门店ID筛选 |
| primary_sales_id | int | 否 | 归属销售筛选 |
| start_date | string | 否 | 创建日期起始 |
| end_date | string | 否 | 创建日期截止 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "order_id": 1008,
        "order_no": "SS-20260817-HN001-0008",
        "order_status": 4,
        "order_status_text": "已支付待审核",
        "transaction_type": 1,
        "transaction_type_text": "门店",
        "store_name": "长沙旗舰店",
        "store_no": "HN001",
        "partner_name": "湖南城市合伙人",
        "primary_sales_name": "李经理",
        "project_name": "万科样板间",
        "end_customer": "王先生",
        "item_count": 3,
        "total_amount": "2837.10",
        "paid_amount": "2837.10",
        "payment_status": 2,
        "payment_status_text": "已支付",
        "audit_status": 0,
        "audit_status_text": "未审核",
        "created_by_name": "张三",
        "created_at": "2026-08-17 10:30:00",
        "paid_at": "2026-08-17 11:05:00"
      }
    ],
    "total": 580,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 订单详情（后台）
- 路径：GET /api/v1/admin/order/detail
- 说明：获取订单完整详情，包含归属快照、技术审核信息、生产进度、发货信息等
- 权限：后台-订单管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "order_id": 1008,
    "order_no": "SS-20260817-HN001-0008",
    "order_status": 4,
    "order_status_text": "已支付待审核",
    "transaction": {
      "type": 1,
      "type_text": "门店",
      "store_id": 1,
      "store_no": "HN001",
      "store_name": "长沙旗舰店"
    },
    "attribution_snapshot": {
      "partner_id": 1,
      "partner_name": "湖南城市合伙人",
      "primary_sales_id": 1,
      "primary_sales_name": "李经理",
      "current_service_sales_name": "李经理",
      "secondary_sales_name": "赵经理",
      "crm_customer_id": "CRM-HN-001"
    },
    "created_by": {
      "account_id": 1,
      "real_name": "张三",
      "phone": "138****8888"
    },
    "project_name": "万科样板间",
    "end_customer": "王先生",
    "delivery_method": 1,
    "delivery_method_text": "发送至门店",
    "receiver": {
      "name": "张三",
      "phone": "13888888888",
      "full_address": "湖南省长沙市岳麓区XXX路XXX号"
    },
    "items": [
      {
        "item_id": 3001,
        "item_no": "SS-20260817-HN001-0008-C01",
        "sequence": 1,
        "install_position": "客厅",
        "width": "180.0",
        "height": "300.0",
        "area": "5.4000",
        "track_color": "黑色",
        "track_amount": "396.00",
        "fabric_no": "SS-F-20260101",
        "fabric_name": "深灰遮光面料",
        "fabric_price": "40.00",
        "fabric_amount": "216.00",
        "accessory_amount": "210.00",
        "use_inventory": 1,
        "kit_amount": "0.00",
        "item_total": "822.00",
        "technical_status": 0,
        "technical_status_text": "待审核",
        "production_status": 0,
        "production_status_text": "待排产",
        "shipping_status": 0,
        "shipping_status_text": "待发货",
        "tracking_info": null,
        "production_trace": {
          "actual_supplier_name": null,
          "supplier_fabric_no": null,
          "supply_batch": null,
          "cut_size": null
        }
      }
    ],
    "summary": {
      "item_count": 3,
      "track_amount": "1116.00",
      "fabric_area_total": "18.7000",
      "fabric_amount": "605.10",
      "inventory_used_count": 2,
      "new_purchase_count": 1,
      "new_purchase_amount": "760.00",
      "accessory_amount": "290.00",
      "nonstandard_amount": "0.00",
      "discount_amount": "0.00",
      "total_amount": "2837.10",
      "paid_amount": "2837.10"
    },
    "payment": {
      "payment_no": "PAY20260817100001",
      "pay_channel": 1,
      "pay_channel_text": "微信支付",
      "pay_amount": "2837.10",
      "pay_status": 1,
      "transaction_id": "wx_xxx",
      "paid_at": "2026-08-17 11:05:00"
    },
    "expected_delivery_date": "2026-09-15",
    "remark": "请注意安装方向",
    "created_at": "2026-08-17 10:30:00",
    "updated_at": "2026-08-17 11:05:00"
  }
}
```

---

#### 技术审核
- 路径：POST /api/v1/admin/order/audit
- 说明：对订单进行技术审核，可按整单或按明细逐副审核。审核结果包括：通过、需门店确认、需补款、无法生产。
- 权限：后台-订单管理-技术审核
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| audit_result | int | 是 | 审核结果：1通过 2需门店确认 3需补款 4无法生产 |
| item_audits | array | 否 | 逐副审核明细（可选，不传则整单审核） |
| item_audits[].item_id | int | 是 | 窗帘明细ID |
| item_audits[].technical_status | int | 是 | 明细审核状态：1通过 2需确认 3需补款 4无法生产 |
| item_audits[].remark | string | 否 | 明细审核备注 |
| overall_remark | string | 否 | 整单审核备注 |
| supplement_amount | decimal | 否 | 需补款金额（audit_result=3时必填） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "order_id": 1008,
    "audit_status": 1,
    "audit_status_text": "审核通过",
    "new_order_status": 7,
    "new_order_status_text": "审核通过待排产"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1006 | 订单当前状态不允许审核（非已支付待审核） |

---

#### 更新生产状态
- 路径：POST /api/v1/admin/order/production
- 说明：更新订单或窗帘明细的生产状态
- 权限：后台-订单管理-生产管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| item_ids | array | 否 | 窗帘明细ID列表（不传则更新整单） |
| production_status | int | 是 | 目标生产状态：1生产中 2质检中 3已完成 |
| supplier_info | array | 否 | 生产追溯信息（分配套件、供应商时填写） |
| supplier_info[].item_id | int | 是 | 明细ID |
| supplier_info[].actual_supplier_id | int | 否 | 实际供应商ID |
| supplier_info[].supplier_fabric_no | string | 否 | 供应商原始面料编号 |
| supplier_info[].supply_batch | string | 否 | 供货批次 |
| supplier_info[].cut_size | string | 否 | 裁剪尺寸 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 发货管理
- 路径：POST /api/v1/admin/order/ship
- 说明：对订单执行发货操作，支持部分发货
- 权限：后台-订单管理-发货
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| item_ids | array | 是 | 本次发货的窗帘明细ID列表 |
| carrier | string | 是 | 承运商 |
| tracking_no | string | 是 | 物流单号 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "shipped_items": [3001, 3002],
    "new_order_status": 11,
    "new_order_status_text": "部分发货"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1006 | 订单当前状态不允许发货 |
| 1004 | 明细ID不属于该订单 |

---

#### 取消订单（管理员）
- 路径：POST /api/v1/admin/order/cancel
- 说明：管理员权限取消订单（含生产中订单）。必须填写取消原因，记录生产进度、成本和退款/套件库存处理方式。
- 权限：后台-订单管理-取消（高级权限）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| cancel_reason | string | 是 | 取消原因 |
| production_progress | string | 否 | 当前生产进度描述 |
| material_cost | decimal | 否 | 已发生材料成本 |
| refund_amount | decimal | 否 | 退款金额 |
| kit_return | int | 否 | 套件库存是否退回：0否 1是 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 改价
- 路径：POST /api/v1/admin/order/adjust-price
- 说明：修改订单金额（优惠、补款等），需审批。改价后重新计算订单总额。
- 权限：后台-订单管理-改价（需审批）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| item_id | int | 否 | 窗帘明细ID（不传则改整单） |
| adjust_field | string | 是 | 调整字段：discount_amount/nonstandard_amount/item_total |
| adjust_value | decimal | 是 | 调整后的值 |
| reason | string | 是 | 改价原因 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "old_total": "2837.10",
    "new_total": "2737.10",
    "adjust_log_id": 100
  }
}
```

> **⚠️ 重要说明**：改价操作后端重新计算订单总额，记录变更前后数据和审批信息。

---

### 3.6 库存管理（/api/v1/admin/inventory/*）

---

#### 查看门店库存
- 路径：GET /api/v1/admin/inventory/store
- 说明：查看指定门店或全部门店的套件库存
- 权限：后台-库存管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 否 | 门店ID（不传查全部） |
| kit_sku | string | 否 | 套件SKU筛选 |
| keyword | string | 否 | 门店关键词搜索 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "store_id": 1,
        "store_no": "HN001",
        "store_name": "长沙旗舰店",
        "kit_sku": "KIT-STD-V1",
        "kit_name": "标准智能套件",
        "total_purchased": 25,
        "available": 18,
        "locked": 3,
        "consumed": 2,
        "frozen": 1,
        "return_pending": 0,
        "adjusted": 0
      }
    ],
    "total": 128,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 人工调整库存
- 路径：POST /api/v1/admin/inventory/adjust
- 说明：人工调整门店库存，需填写原因，记录操作日志。高风险操作需要审批。
- 权限：后台-库存管理-调整（需审批）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 是 | 门店ID |
| kit_sku | string | 是 | 套件SKU |
| adjust_quantity | int | 是 | 调整数量（正数增加，负数减少） |
| reason | string | 是 | 调整原因 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "before_available": 18,
    "after_available": 20,
    "log_id": 600
  }
}
```

> **⚠️ 并发安全说明**：库存调整操作使用数据库事务 + Redis 分布式锁保证原子性，避免并发冲突。

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 5004 | 库存操作冲突，请重试 |

---

#### 库存流水查询（后台）
- 路径：GET /api/v1/admin/inventory/log
- 说明：全局库存流水查询
- 权限：后台-库存管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 否 | 门店ID筛选 |
| kit_sku | string | 否 | 套件SKU筛选 |
| log_type | int | 否 | 变化类型筛选 |
| order_id | int | 否 | 关联订单ID筛选 |
| operator_name | string | 否 | 操作人筛选 |
| start_date | string | 否 | 起始日期 |
| end_date | string | 否 | 截止日期 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "log_id": 501,
        "store_id": 1,
        "store_no": "HN001",
        "store_name": "长沙旗舰店",
        "kit_sku": "KIT-STD-V1",
        "log_type": 1,
        "log_type_text": "采购入账",
        "quantity": 25,
        "before_quantity": 0,
        "after_quantity": 25,
        "order_no": null,
        "operator_name": "系统",
        "reason": "首批套件采购入库",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 350,
    "page": 1,
    "page_size": 20
  }
}
```

---

### 3.7 财务管理（/api/v1/admin/finance/*）

---

#### 支付记录查询
- 路径：GET /api/v1/admin/finance/payment/list
- 说明：全局支付记录查询
- 权限：后台-财务管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索（支付单号/订单号/交易流水号） |
| pay_channel | int | 否 | 支付渠道：1微信 2支付宝 |
| pay_status | int | 否 | 支付状态：0待支付 1成功 2失败 3已退款 |
| store_id | int | 否 | 门店ID筛选 |
| start_date | string | 否 | 起始日期 |
| end_date | string | 否 | 截止日期 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "payment_id": 1,
        "payment_no": "PAY20260817100001",
        "order_no": "SS-20260817-HN001-0008",
        "store_name": "长沙旗舰店",
        "pay_channel": 1,
        "pay_channel_text": "微信支付",
        "pay_method": "JSAPI",
        "pay_amount": "2837.10",
        "transaction_id": "wx_xxx",
        "pay_status": 1,
        "pay_status_text": "支付成功",
        "paid_at": "2026-08-17 11:05:00",
        "refund_amount": null,
        "refunded_at": null
      }
    ],
    "total": 450,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 退款处理
- 路径：POST /api/v1/admin/finance/refund
- 说明：发起退款操作。支持全额退款和部分退款。退款需填写原因，调用支付渠道退款接口。
- 权限：后台-财务管理-退款（需审批）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| payment_id | int | 是 | 支付记录ID |
| refund_amount | decimal | 是 | 退款金额（不超过原支付金额） |
| refund_reason | string | 是 | 退款原因 |
| kit_return | int | 否 | 套件是否退回库存：0否 1是 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "refund_id": 1,
    "refund_amount": "2837.10",
    "refund_status": "processing"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1001 | 退款金额超过可退金额 |
| 1006 | 支付记录状态不允许退款 |
| 5003 | 退款接口调用失败 |

---

#### 对账导出
- 路径：GET /api/v1/admin/finance/reconciliation/export
- 说明：导出对账数据（Excel格式）
- 权限：后台-财务管理-导出
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| start_date | string | 是 | 起始日期 |
| end_date | string | 是 | 截止日期 |
| pay_channel | int | 否 | 支付渠道筛选 |
| store_id | int | 否 | 门店筛选 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "file_url": "https://oss.xxx.com/export/reconciliation_20260817.xlsx",
    "expire_at": "2026-08-17 18:00:00",
    "total_records": 120,
    "total_amount": "358600.00"
  }
}
```

---

#### 发票审核
- 路径：POST /api/v1/admin/finance/invoice/review
- 说明：审核发票申请（通过/驳回）
- 权限：后台-财务管理-发票
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| request_id | int | 是 | 发票申请ID |
| action | int | 是 | 操作：2审核通过 4驳回 |
| invoice_no | string | 否 | 发票号码（审核通过时填写） |
| invoice_code | string | 否 | 发票代码（审核通过时填写） |
| reject_reason | string | 否 | 驳回原因（驳回时必填） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

### 3.8 售后管理（/api/v1/admin/after-sale/*）

---

#### 售后列表（后台）
- 路径：GET /api/v1/admin/after-sale/list
- 说明：全局售后申请列表
- 权限：后台-售后管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索（售后单号/订单号） |
| status | int | 否 | 状态筛选 |
| problem_type | int | 否 | 问题类型筛选 |
| store_id | int | 否 | 门店筛选 |
| start_date | string | 否 | 起始日期 |
| end_date | string | 否 | 截止日期 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "after_sale_id": 1,
        "after_sale_no": "AS20260817001",
        "order_no": "SS-20260817-HN001-0008",
        "store_name": "长沙旗舰店",
        "problem_type": 1,
        "problem_type_text": "电机",
        "status": 1,
        "status_text": "待处理",
        "handler_name": null,
        "created_at": "2026-08-17 15:00:00"
      }
    ],
    "total": 25,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 售后详情（后台）
- 路径：GET /api/v1/admin/after-sale/detail
- 说明：获取售后申请完整详情
- 权限：后台-售后管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| after_sale_id | int | 是 | 售后单ID |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "after_sale_id": 1,
    "after_sale_no": "AS20260817001",
    "order_no": "SS-20260817-HN001-0008",
    "store_name": "长沙旗舰店",
    "store_no": "HN001",
    "item_no": "SS-20260817-HN001-0008-C01",
    "problem_type": 1,
    "problem_type_text": "电机",
    "problem_desc": "电机运转异响",
    "images": ["https://oss.xxx.com/img1.jpg"],
    "videos": ["https://oss.xxx.com/video1.mp4"],
    "install_date": "2026-08-10",
    "affect_usage": 1,
    "contact_name": "张三",
    "contact_phone": "13888888888",
    "expected_solution": "希望更换电机",
    "status": 1,
    "status_text": "待处理",
    "diagnosis": null,
    "responsibility": null,
    "solution": null,
    "accessory_cost": "0.00",
    "labor_cost": "0.00",
    "logistics_cost": "0.00",
    "handler_name": null,
    "created_by_name": "张三",
    "created_at": "2026-08-17 15:00:00"
  }
}
```

---

#### 处理售后
- 路径：POST /api/v1/admin/after-sale/process
- 说明：处理售后申请，填写诊断结果、责任判断和处理方案
- 权限：后台-售后管理-处理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| after_sale_id | int | 是 | 售后单ID |
| status | int | 是 | 目标状态：2处理中 3已完成 |
| diagnosis | string | 否 | 诊断结果 |
| responsibility | int | 否 | 责任判断：1世尚 2门店 3物流 4其他 |
| solution | string | 否 | 处理方案 |
| accessory_cost | decimal | 否 | 配件费用 |
| labor_cost | decimal | 否 | 人工费用 |
| logistics_cost | decimal | 否 | 物流费用 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 关闭售后
- 路径：POST /api/v1/admin/after-sale/close
- 说明：关闭售后申请
- 权限：后台-售后管理-处理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| after_sale_id | int | 是 | 售后单ID |
| close_reason | string | 是 | 关闭原因 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

### 3.9 系统管理（/api/v1/admin/system/*）

---

#### 管理员列表
- 路径：GET /api/v1/admin/system/admin/list
- 说明：获取后台管理员列表
- 权限：后台-系统管理-管理员
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索（用户名/姓名/手机号） |
| role_id | int | 否 | 角色筛选 |
| status | int | 否 | 状态筛选 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "admin_id": 1,
        "username": "admin",
        "real_name": "系统管理员",
        "phone": "13800000000",
        "email": "admin@shishang.com",
        "role_id": 1,
        "role_name": "超级管理员",
        "status": 1,
        "status_text": "正常",
        "last_login_at": "2026-08-17 09:00:00",
        "login_count": 156
      }
    ],
    "total": 15,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 管理员新增/编辑
- 路径：POST /api/v1/admin/system/admin/save
- 说明：新增或编辑管理员
- 权限：后台-系统管理-管理员
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| admin_id | int | 否 | 管理员ID（编辑时必填） |
| username | string | 是 | 登录用户名 |
| password | string | 否 | 密码（新增时必填） |
| real_name | string | 是 | 真实姓名 |
| phone | string | 否 | 手机号 |
| email | string | 否 | 邮箱 |
| avatar | string | 否 | 头像URL |
| role_id | int | 是 | 角色ID |
| status | int | 否 | 状态：1正常 0停用 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": { "admin_id": 2 }
}
```

---

#### 管理员删除
- 路径：DELETE /api/v1/admin/system/admin/delete
- 说明：删除管理员（软删除）
- 权限：后台-系统管理-管理员
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| admin_id | int | 是 | 管理员ID |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 角色列表
- 路径：GET /api/v1/admin/system/role/list
- 说明：获取角色列表
- 权限：后台-系统管理-角色
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "role_id": 1,
        "role_name": "超级管理员",
        "role_code": "super_admin",
        "description": "拥有所有权限",
        "admin_count": 1,
        "status": 1
      }
    ],
    "total": 8,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 角色新增/编辑
- 路径：POST /api/v1/admin/system/role/save
- 说明：新增或编辑角色，同时配置角色权限
- 权限：后台-系统管理-角色
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| role_id | int | 否 | 角色ID（编辑时必填） |
| role_name | string | 是 | 角色名称 |
| role_code | string | 是 | 角色编码 |
| description | string | 否 | 角色描述 |
| sort_order | int | 否 | 排序 |
| permission_ids | array | 是 | 权限ID列表 |
| status | int | 否 | 状态 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": { "role_id": 2 }
}
```

---

#### 角色删除
- 路径：DELETE /api/v1/admin/system/role/delete
- 说明：删除角色（如有管理员使用该角色则不允许删除）
- 权限：后台-系统管理-角色
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| role_id | int | 是 | 角色ID |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1006 | 该角色下仍有管理员使用，无法删除 |

---

#### 权限树
- 路径：GET /api/v1/admin/system/permission/tree
- 说明：获取权限菜单树形结构
- 权限：后台-系统管理-角色
- 请求参数：无

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "tree": [
      {
        "permission_id": 1,
        "parent_id": 0,
        "permission_name": "订单管理",
        "permission_code": "order",
        "permission_type": 1,
        "path": "/order",
        "icon": "order",
        "children": [
          {
            "permission_id": 2,
            "parent_id": 1,
            "permission_name": "查看订单",
            "permission_code": "order:view",
            "permission_type": 2,
            "children": []
          },
          {
            "permission_id": 3,
            "parent_id": 1,
            "permission_name": "审核订单",
            "permission_code": "order:audit",
            "permission_type": 2,
            "children": []
          }
        ]
      }
    ]
  }
}
```

---

#### 操作日志查询
- 路径：GET /api/v1/admin/system/operation-log
- 说明：查询系统操作日志
- 权限：后台-系统管理-日志
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| module | string | 否 | 模块筛选：order/payment/inventory/price/store/system |
| action | string | 否 | 操作筛选：create/update/delete/approve/reject/cancel等 |
| target_type | string | 否 | 目标类型筛选 |
| target_id | int | 否 | 目标ID筛选 |
| operator_name | string | 否 | 操作人筛选 |
| start_date | string | 否 | 起始日期 |
| end_date | string | 否 | 截止日期 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "log_id": 1001,
        "module": "order",
        "action": "audit",
        "target_type": "order",
        "target_id": 1008,
        "target_no": "SS-20260817-HN001-0008",
        "before_data": {"audit_status": 0},
        "after_data": {"audit_status": 1},
        "operator_id": 1,
        "operator_name": "系统管理员",
        "operator_role": "技术审核",
        "ip_address": "120.xxx.xxx.xxx",
        "remark": "尺寸核实通过",
        "created_at": "2026-08-17 14:00:00"
      }
    ],
    "total": 2800,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 客户归属变更
- 路径：POST /api/v1/admin/system/attribution/change
- 说明：变更门店或合伙人的归属关系（渠道模式、合伙人、主归属销售）。变更时自动写入历史表，级联更新下属门店的销售归属。
- 权限：后台-系统管理-归属变更（需审批）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| customer_type | int | 是 | 客户主体类型：1门店 2城市合伙人 |
| customer_id | int | 是 | 客户主体ID |
| channel_mode | int | 否 | 新渠道模式 |
| partner_id | int | 否 | 新合伙人ID（渠道模式=1时） |
| primary_sales_id | int | 是 | 新主归属销售ID |
| secondary_sales_id | int | 否 | 新协同销售ID |
| attribution_source | int | 是 | 归属来源：1开发 2分配 3继承 4转移 5系统迁移 |
| effective_time | string | 是 | 生效时间 |
| change_reason | string | 是 | 变更原因 |
| cascade_stores | int | 否 | 是否级联更新下属门店：0否 1是 |
| applicant | string | 否 | 申请人 |
| approver | string | 否 | 审批人 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "attribution_id": 50,
    "cascaded_store_count": 15
  }
}
```

---

#### 销售转交
- 路径：POST /api/v1/admin/system/sales/transfer
- 说明：销售离职/调岗时批量转交客户。以合伙人为单位转交，级联更新下属门店。
- 权限：后台-系统管理-销售转交（需审批）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| from_sales_id | int | 是 | 原销售ID |
| to_sales_id | int | 是 | 接任销售ID |
| partner_ids | array | 是 | 要转交的城市合伙人ID列表 |
| effective_time | string | 是 | 生效时间 |
| change_reason | string | 是 | 转交原因 |
| applicant | string | 否 | 申请人 |
| approver | string | 否 | 审批人 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "transferred_partner_count": 5,
    "transferred_store_count": 68,
    "task_id": 100
  }
}
```

---

### 3.10 统计（/api/v1/admin/stats/*）

---

#### 订单统计
- 路径：GET /api/v1/admin/stats/order
- 说明：获取订单统计数据
- 权限：后台-统计
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| start_date | string | 是 | 起始日期 |
| end_date | string | 是 | 截止日期 |
| store_id | int | 否 | 门店筛选 |
| partner_id | int | 否 | 合伙人筛选 |
| granularity | string | 否 | 统计粒度：day/week/month，默认day |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "summary": {
      "total_orders": 156,
      "total_amount": "458600.00",
      "avg_order_amount": "2939.74",
      "avg_item_count": "3.2",
      "completed_orders": 120,
      "cancelled_orders": 8,
      "after_sale_orders": 5
    },
    "trend": [
      {
        "date": "2026-08-01",
        "order_count": 12,
        "order_amount": "35680.00",
        "item_count": 38
      }
    ]
  }
}
```

---

#### 销售统计
- 路径：GET /api/v1/admin/stats/sales
- 说明：按销售维度统计订单和金额
- 权限：后台-统计
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| start_date | string | 是 | 起始日期 |
| end_date | string | 是 | 截止日期 |
| sales_id | int | 否 | 销售人员筛选 |
| granularity | string | 否 | 统计粒度 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "sales_id": 1,
        "sales_name": "李经理",
        "order_count": 80,
        "order_amount": "235600.00",
        "store_count": 30,
        "avg_order_amount": "2945.00"
      }
    ]
  }
}
```

---

#### 面料销量统计
- 路径：GET /api/v1/admin/stats/fabric
- 说明：按面料维度统计销量和金额
- 权限：后台-统计
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| start_date | string | 是 | 起始日期 |
| end_date | string | 是 | 截止日期 |
| series | string | 否 | 系列筛选 |
| top | int | 否 | 返回Top N，默认20 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "fabric_no": "SS-F-20260101",
        "fabric_name": "深灰遮光面料",
        "series": "雅致系列",
        "item_count": 45,
        "total_area": "245.6000",
        "total_amount": "9824.00",
        "supplier_distribution": [
          {"supplier_name": "浙江XXX纺织", "count": 40},
          {"supplier_name": "广东XXX纺织", "count": 5}
        ]
      }
    ]
  }
}
```

---

#### 库存统计
- 路径：GET /api/v1/admin/stats/inventory
- 说明：获取库存概览统计
- 权限：后台-统计
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 否 | 门店筛选，不传查全部 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "total_purchased": 2500,
    "total_available": 1800,
    "total_locked": 150,
    "total_consumed": 400,
    "total_frozen": 20,
    "turnover_rate": "16.00%",
    "low_stock_stores": [
      {"store_id": 5, "store_name": "株洲店", "available": 2}
    ]
  }
}
```

---

#### 售后统计
- 路径：GET /api/v1/admin/stats/after-sale
- 说明：获取售后统计数据
- 权限：后台-统计
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| start_date | string | 是 | 起始日期 |
| end_date | string | 是 | 截止日期 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "summary": {
      "total_after_sale": 25,
      "after_sale_rate": "2.50%",
      "avg_resolve_days": 3.5
    },
    "by_problem_type": [
      {"problem_type": 1, "problem_type_text": "电机", "count": 8, "rate": "32.00%"},
      {"problem_type": 5, "problem_type_text": "轨道", "count": 5, "rate": "20.00%"},
      {"problem_type": 6, "problem_type_text": "面料", "count": 4, "rate": "16.00%"}
    ],
    "by_responsibility": [
      {"responsibility": 1, "responsibility_text": "世尚", "count": 15},
      {"responsibility": 3, "responsibility_text": "物流", "count": 5},
      {"responsibility": 2, "responsibility_text": "门店", "count": 3},
      {"responsibility": 4, "responsibility_text": "其他", "count": 2}
    ]
  }
}
```

---

## 四、订单状态机

### 4.1 状态流转图

```
草稿(1) ──提交──→ 待支付(2) ──发起支付──→ 支付处理中(3)
  │                   │                       │
  │ 取消              │ 取消                   │ 支付成功
  ↓                   ↓                       ↓
已取消(16)         已取消(16)             已支付待审核(4)
  │                                           │
  │ 删除                                       ├─审核通过──→ 审核通过待排产(7) ──排产──→ 生产中(8)
  ↓                                           │                                              │
（软删除）                                      ├─需确认──→ 需门店确认(5)                        │
                                                │                    │                          │
                                                │                    └─确认──→ 审核通过待排产(7)  │
                                                │                                                 │
                                                └─需补款──→ 待补款(6) ──补款──→ 审核通过待排产(7)  │
                                                                                                   │
                                                                                                   ↓
已完成(14) ←──确认── 已签收(13) ←──签收── 已发货(12) ←──全部发货── 待发货(10) ←──完成生产── 质检中(9)
  │                                                                                                   │
  │                                                                                             生产中(8)
  └──售后──→ 售后处理中(15) ──完成──→ 已完成(14)
                │
                └──退款──→ 退款中(17) ──完成──→ 已退款(18)
```

### 4.2 门店端可执行操作

| 当前状态 | 可执行操作 |
|---------|-----------|
| 草稿(1) | 编辑、添加/删除明细、提交、取消、删除 |
| 待支付(2) | 支付、取消 |
| 需门店确认(5) | 确认、补充信息 |
| 待补款(6) | 补款 |
| 其他状态 | 仅查看 |

### 4.3 后台可执行操作

| 当前状态 | 可执行操作 |
|---------|-----------|
| 已支付待审核(4) | 技术审核（通过/需确认/需补款/无法生产） |
| 审核通过待排产(7) | 排产→生产中 |
| 生产中(8) | 更新生产状态、质检 |
| 质检中(9) | 更新质检状态 |
| 待发货(10) | 发货 |
| 部分发货(11) | 继续发货 |
| 任意状态（管理员权限） | 取消（需记录原因和成本） |

---

## 五、错误码速查表

| 错误码 | 说明 | 常见场景 |
|--------|------|---------|
| 0 | 成功 | - |
| 1001 | 参数错误 | 必填项缺失、格式不对 |
| 1002 | 手机号格式错误 | 非11位中国大陆手机号 |
| 1003 | 验证码错误/过期 | 登录、绑定微信 |
| 1004 | 数据不存在 | 订单/面料/地址等ID无效 |
| 1005 | 数据重复 | 编号已存在 |
| 1006 | 状态不允许操作 | 订单状态机校验失败 |
| 1007 | 尺寸超出范围 | 宽<90或>350，高<50或>600 |
| 1008 | 面料不可用 | 已下架或禁止订货 |
| 2001 | 未登录 | Token缺失 |
| 2002 | Token过期 | Token超过有效期 |
| 2003 | 账号已停用 | 门店账号/管理员被停用 |
| 2004 | 验证码频率过高 | 60秒内重复发送 |
| 2005 | 登录凭据错误 | 用户名密码错误 |
| 3001 | 无资源权限 | 无权访问该门店/订单 |
| 3002 | 无操作权限 | 角色无该操作权限 |
| 3003 | 跨门店访问 | 访问其他门店数据 |
| 5001 | 服务器内部错误 | 未捕获异常 |
| 5002 | 数据库错误 | SQL执行失败 |
| 5003 | 第三方服务错误 | 微信/支付宝接口失败 |
| 5004 | 库存并发冲突 | 分布式锁冲突 |

---

## 六、附录

### 6.1 编号生成规则

| 编号类型 | 格式 | 示例 |
|---------|------|------|
| 订单号 | SS-{日期}-{门店编号}-{4位流水号} | SS-20260817-HN001-0008 |
| 窗帘编号 | {订单号}-C{2位序号} | SS-20260817-HN001-0008-C03 |
| 支付单号 | PAY{日期}{6位流水号} | PAY20260817100001 |
| 售后单号 | AS{日期}{3位流水号} | AS20260817001 |
| 发票申请号 | INV{日期}{3位流水号} | INV20260817001 |

### 6.2 金额计算汇总

> **⚠️ 所有金额后端重新计算，不信任前端数据**

```
横轨费用 = 宽度(米) × 120
竖轨费用 = 高度(米) × 2 × 30
轨道费用 = 横轨费用 + 竖轨费用

面料费用 = 宽度(米) × 高度(米) × 面料单价

选装费用 = 电源加价 + 遥控器加价 + 墙面控制单价 × 数量

套件费用 = use_inventory ? 0 : 客户等级价格

非标费用 = 后台审核填写

单副合计 = 轨道费用 + 面料费用 + 选装费用 + 套件费用 + 非标费用

订单总额 = Σ(单副合计) + 非标费用 - 优惠金额
```

### 6.3 并发安全措施

1. **库存操作**：Redis 分布式锁（key: `lock:inventory:{store_id}:{kit_sku}`） + 数据库事务
2. **支付创建**：数据库唯一索引 `uk_payment_order_id` + Redis 幂等锁
3. **支付回调**：通过 `payment_no + transaction_id` 去重，状态机校验防止重复处理
4. **订单提交**：乐观锁（version 字段或状态前置校验）
5. **价格锁定**：提交时写入 `price_locked_at` 和 `price_locked_until`，审核和生产前校验锁定有效性

### 6.4 Redis Key 规划

| Key 模式 | 用途 | TTL |
|---------|------|-----|
| `token:store:{account_id}` | 门店端 Token | 2h |
| `token:admin:{admin_id}` | 后台 Token | 8h |
| `verify_code:{phone}:{scene}` | 验证码 | 5min |
| `lock:inventory:{store_id}:{sku}` | 库存操作锁 | 10s |
| `lock:payment:{order_id}` | 支付创建锁 | 30s |
| `lock:order:submit:{order_id}` | 订单提交锁 | 10s |
| `cache:fabric:list` | 面料列表缓存 | 5min |
| `cache:track:list` | 轨道列表缓存 | 10min |
| `cache:accessory:list` | 配件列表缓存 | 10min |
| `cache:kit:price:{level}` | 套件等级价格缓存 | 30min |

---

📋 交付物：API 接口文档  
📁 文件路径：`shishang-order-system/docs/api.md`  
📝 覆盖范围：门店端 10 个模块 + 后台 10 个模块，共计约 100+ 个接口  
🔗 依赖说明：数据库设计文档 v1.1、PRD v3.0  
⚠️ 注意事项：
1. 所有金额计算后端必须重新计算，不信任前端数据
2. 订单操作必须校验状态机流转规则
3. 支付和库存操作必须保证幂等性和并发安全
4. 价格锁定和支付超时均为 30 天
5. 管理员取消生产中订单需记录完整审计信息
'''

with open('/Coze/Drive/链极网络工作台/shishang-order-system/docs/api_part3.md', 'w') as f:
    f.write(content)

print("Part 3 written successfully")
