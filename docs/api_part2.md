# 世尚门店订货系统 - API 接口文档（门店端 + 全局规范）

> 版本：v1.1  
> 更新日期：2026-08-17  
> 技术栈：PHP 8.1+ / ThinkPHP 8 / MySQL 8.0+ / Redis  
> 前端：Vue 3 + uni-app（小程序+H5）+ Element Plus（后台）  
> 依赖：数据库设计文档 v1.1、PRD v3.0

---

## 一、全局规范

### 1.1 通信协议

| 项目 | 说明 |
|------|------|
| 协议 | HTTPS |
| 数据格式 | JSON |
| 字符编码 | UTF-8 |
| 认证方式 | Bearer Token（Header: `Authorization: Bearer {token}`） |
| 时间格式 | `YYYY-MM-DD HH:mm:ss` |
| 日期格式 | `YYYY-MM-DD` |
| **金额单位** | **分（整数）**，如 `10000` 表示 100.00 元 |
| 面积单位 | 平方米，精度 4 位小数 |
| 尺寸单位 | 厘米（输入），米（计算时转换） |

> **💡 金额说明**：自 v1.1 起，所有金额字段统一使用**分**（整数）传输，避免浮点精度问题。字段名以 `_cent` 后缀标识。  
> 示例：`total_amount_cent: 283710` 表示 ¥2,837.10

### 1.2 API 路径规范

- 前缀：`/api/v1/`
- 风格：RESTful **复数资源名** + kebab-case
- 门店端接口：`/api/v1/orders`、`/api/v1/fabrics`、`/api/v1/auth/*` 等
- 后台接口：`/api/v1/admin/stores`、`/api/v1/admin/orders` 等
- 路径参数使用 `{resource_name}` 标识，如 `orders/:order_no`

### 1.3 统一响应格式

**成功响应：**

```json
{
  "code": 0,
  "message": "success",
  "data": {},
  "request_id": "req_6a7b8c9d0e1f"
}
```

**分页响应：**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [],
    "total": 100,
    "page": 1,
    "page_size": 20
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

**错误响应：**

```json
{
  "code": 1001,
  "message": "参数错误：手机号格式不正确",
  "data": null,
  "request_id": "req_6a7b8c9d0e1f"
}
```

> 所有响应均包含 `request_id` 字段，用于请求追踪与问题排查。

### 1.4 错误码定义

#### 1.4.1 错误码总览

| 错误码范围 | 说明 | HTTP 状态码 |
|-----------|------|------------|
| 0 | 成功 | 200 |
| 1001-1003 | 参数错误 | 400 |
| 2001-2003 | 认证错误 | 401 |
| 3001-3002 | 权限与数据访问错误 | 403/404 |
| 4001-4202 | 业务冲突 | 409/422 |
| 5000-5002 | 系统错误 | 500/502 |

#### 1.4.2 完整错误码表

**1xxx 参数和格式**

| 错误码 | 常量名 | 说明 | HTTP |
|--------|--------|------|------|
| 1001 | PARAM_INVALID | 参数无效（格式、范围、类型） | 400 |
| 1002 | PARAM_MISSING | 必填参数缺失 | 400 |
| 1003 | FORMAT_ERROR | 请求体格式错误（非合法 JSON 等） | 400 |

**2xxx 认证和令牌**

| 错误码 | 常量名 | 说明 | HTTP |
|--------|--------|------|------|
| 2001 | UNAUTHENTICATED | 未认证（未携带 Token 或 Token 无效） | 401 |
| 2002 | TOKEN_EXPIRED | Token 已过期 | 401 |
| 2003 | TOKEN_INVALID | Token 无效（签名错误、payload 不合法） | 401 |

**3xxx 权限和数据访问**

| 错误码 | 常量名 | 说明 | HTTP |
|--------|--------|------|------|
| 3001 | FORBIDDEN | 无权限（角色不足 / 数据越权） | 403 |
| 3002 | DATA_NOT_FOUND | 资源不存在 | 404 |

**4xxx 业务冲突**

| 错误码 | 常量名 | 说明 | HTTP |
|--------|--------|------|------|
| 4001 | INVENTORY_INSUFFICIENT | 套件库存不足 | 409 |
| 4002 | PRICE_EXPIRED | 订单价格已失效（价格锁定 30 天） | 422 |
| 4003 | ILLEGAL_STATUS_TRANSITION | 非法订单状态转换 | 409 |
| 4004 | ORDER_IN_PRODUCTION | 订单已进入生产，不可取消/修改 | 409 |
| 4101 | PAYMENT_CALLBACK_PROCESSED | 支付回调已处理（幂等拦截） | 409 |
| 4102 | PAYMENT_AMOUNT_MISMATCH | 支付金额与订单金额不一致 | 422 |
| 4103 | BALANCE_INSUFFICIENT | 余额不足 | 422 |
| 4104 | MIXED_PAYMENT_NOT_SUPPORTED | 不支持混合支付 | 422 |
| 4105 | BALANCE_CALLBACK_PROCESSED | 储值回调已处理（幂等拦截） | 409 |
| 4106 | ACCOUNT_FROZEN | 资金账户已冻结 | 422 |
| 4201 | FABRIC_OFF_SHELF | 面料已下架 | 422 |
| 4202 | SIZE_OUT_OF_RANGE | 尺寸超出标准范围（宽 90~350cm / 高 50~600cm） | 422 |

**5xxx 系统**

| 错误码 | 常量名 | 说明 | HTTP |
|--------|--------|------|------|
| 5000 | SYSTEM_ERROR | 服务器内部错误 | 500 |
| 5001 | DATABASE_ERROR | 数据库异常 | 500 |
| 5002 | THIRD_PARTY_ERROR | 第三方服务调用失败（微信/支付宝/短信/CRM 等） | 502 |

### 1.5 分页参数规范

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| page | int | 否 | 1 | 页码，从1开始 |
| page_size | int | 否 | 20 | 每页数量，最大100 |

### 1.6 通用请求头

| Header | 必填 | 说明 |
|--------|------|------|
| Authorization | 是（登录接口除外） | `Bearer {token}` |
| Content-Type | 是 | `application/json` |
| X-Store-Id | 否 | 多门店账号切换时指定当前操作门店 |
| X-Request-Id | 否 | 请求追踪ID（幂等性控制） |

### 1.7 关键业务约束

1. **金额计算**：所有金额以分（整数）传输和存储。后端必须重新计算，不信任前端传入的金额数据。
2. **订单状态机**：订单相关接口必须校验当前状态是否允许目标操作。
3. **支付幂等**：支付相关接口使用 `payment_no` + `X-Request-Id` 保证幂等性。
4. **库存并发**：库存操作使用数据库事务 + Redis 分布式锁保证一致性。
5. **价格锁定**：订单提交后锁定价格 30 天，不受后台调价影响。
6. **软删除**：支持软删除的表查询时自动过滤 `deleted_at IS NULL`。

---

## 二、门店端接口

### 2.1 认证模块（/api/v1/auth/*）

---

#### 发送验证码
- 路径：`POST /api/v1/auth/send-code`
- 说明：向指定手机号发送登录验证码（6位数字，有效期5分钟，60秒内不可重复发送）
- 权限：公开接口
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| phone | string | 是 | 手机号，11位中国大陆手机号 |
| scene | string | 是 | 使用场景：login / bind-wechat / change-phone |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "expire_seconds": 300
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 手机号+验证码登录
- 路径：`POST /api/v1/auth/login`
- 说明：手机号+验证码登录，首次登录自动创建会话。若账号关联多个门店，返回门店列表供选择。
- 权限：公开接口
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| phone | string | 是 | 手机号 |
| verify_code | string | 是 | 验证码 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "token": "eyJhbGciOi...",
    "expires_in": 7200,
    "account_id": 1,
    "real_name": "张三",
    "account_role": 1,
    "verify_status": 1,
    "stores": [
      {
        "store_id": 1,
        "store_no": "HN001",
        "store_name": "长沙旗舰店",
        "role_in_customer": 1,
        "is_default": true
      }
    ]
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 微信授权登录（小程序）
- 路径：`POST /api/v1/auth/wechat-login`
- 说明：微信小程序授权登录。通过 wx.login 获取 code，后端换取 openid/unionid，自动关联或创建账号。
- 权限：公开接口
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| code | string | 是 | 微信 wx.login 返回的 code |
| encrypted_data | string | 否 | 加密数据（获取手机号时使用） |
| iv | string | 否 | 加密向量（获取手机号时使用） |

- 响应示例（已绑定手机号）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "token": "eyJhbGciOi...",
    "expires_in": 7200,
    "account_id": 1,
    "real_name": "张三",
    "stores": [{ "store_id": 1, "store_no": "HN001", "store_name": "长沙旗舰店" }]
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 响应示例（需绑定手机号）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "need_bindphone": true,
    "temp_token": "temp_xxx",
    "wechat_openid": "oXXXX"
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 退出登录
- 路径：`POST /api/v1/auth/logout`
- 说明：注销当前登录会话，使 Token 失效
- 权限：门店端已登录用户

- 响应示例：

```json
{ "code": 0, "message": "success", "data": null, "request_id": "req_6a7b8c9d0e1f" }
```

---

#### 获取当前账号信息
- 路径：`GET /api/v1/auth/profile`
- 说明：获取当前登录账号的完整信息
- 权限：门店端已登录用户

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "account_id": 1,
    "phone": "138****8888",
    "real_name": "张三",
    "account_role": 1,
    "account_role_text": "门店管理员",
    "verify_status": 1,
    "wechat_bound": true,
    "current_store": {
      "store_id": 1,
      "store_no": "HN001",
      "store_name": "长沙旗舰店",
      "customer_level": 1,
      "province": "湖南省",
      "city": "长沙市"
    },
    "stores": [
      { "store_id": 1, "store_no": "HN001", "store_name": "长沙旗舰店", "is_default": true }
    ]
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 获取当前用户概要
- 路径：`GET /api/v1/auth/me`
- 说明：获取当前用户概要信息（轻量版）
- 权限：门店端已登录用户

---

#### 切换门店
- 路径：`POST /api/v1/auth/switch-store`
- 说明：多门店账号切换当前操作门店
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 是 | 目标门店ID |

---

### 2.2 门店首页（/api/v1/dashboard）

---

#### 获取工作台数据
- 路径：`GET /api/v1/dashboard`
- 说明：获取门店工作台数据，包含门店基本信息、库存概览、各状态订单统计、待办事项
- 权限：门店端已登录用户

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "store_info": {
      "store_id": 1,
      "store_no": "HN001",
      "store_name": "长沙旗舰店",
      "customer_level": 1,
      "kit_price_cent": 76000
    },
    "inventory": {
      "kit_available": 20,
      "kit_locked": 3
    },
    "order_stats": {
      "pending_payment": 2,
      "pending_confirm": 1,
      "in_production": 5,
      "pending_receive": 3,
      "completed": 48,
      "after_sale": 1
    },
    "notices": [
      { "type": "payment_reminder", "message": "您有2笔订单待支付" }
    ]
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

### 2.3 订单模块（/api/v1/orders）

---

#### 创建订单（草稿）
- 路径：`POST /api/v1/orders`
- 说明：创建一张空白草稿订单。系统自动生成订单号。
- 权限：门店端已登录用户（下单员及以上角色）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_name | string | 否 | 项目名称，最长100字符 |
| end_customer | string | 否 | 终端客户名称或代号 |
| delivery_method | int | 否 | 收货方式：1发送至门店（默认） 2发送至终端客户 |
| address_id | int | 否 | 收货地址ID |
| receiver_name | string | 否 | 收件人姓名 |
| receiver_phone | string | 否 | 收件人手机号 |
| receiver_province | string | 否 | 省 |
| receiver_city | string | 否 | 市 |
| receiver_district | string | 否 | 区 |
| receiver_detail | string | 否 | 详细地址 |
| expected_delivery_date | string | 否 | 期望交期 YYYY-MM-DD |
| invoice_required | int | 否 | 是否需要发票：0否（默认） 1是 |
| remark | string | 否 | 整单备注 |
| attachments | array | 否 | 现场照片/图纸URL数组 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1008,
    "order_no": "SS-20260817-HN001-0008"
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 获取订单列表
- 路径：`GET /api/v1/orders`
- 说明：获取当前门店的订单列表，支持按状态筛选和分页
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | int | 否 | 订单状态筛选 |
| page | int | 否 | 页码，默认1 |
| page_size | int | 否 | 每页数量，默认20 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": 1008,
        "order_no": "SS-20260817-HN001-0008",
        "order_status": 2,
        "order_status_text": "待支付",
        "project_name": "万科样板间",
        "item_count": 3,
        "total_amount_cent": 283710,
        "paid_amount_cent": 0,
        "payment_status": 0,
        "created_at": "2026-08-17 10:30:00"
      }
    ],
    "total": 56,
    "page": 1,
    "page_size": 20
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 获取订单详情
- 路径：`GET /api/v1/orders/:order_no`
- 说明：获取订单完整信息
- 权限：门店端已登录用户（仅可查看本门店订单）

- 响应示例（金额以分为单位）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1008,
    "order_no": "SS-20260817-HN001-0008",
    "order_status": 2,
    "order_status_text": "待支付",
    "project_name": "万科样板间",
    "items": [
      {
        "item_id": 3001,
        "item_no": "SS-20260817-HN001-0008-C01",
        "install_position": "客厅",
        "width": "180.0",
        "height": "300.0",
        "area": "5.4000",
        "track_amount_cent": 39600,
        "fabric_amount_cent": 21600,
        "accessory_amount_cent": 21000,
        "item_total_cent": 82200
      }
    ],
    "summary": {
      "total_amount_cent": 283710,
      "discount_amount_cent": 0
    }
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 3002 | 订单不存在 |

---

#### 更新订单基本信息
- 路径：`PUT /api/v1/orders/:order_no`
- 说明：更新草稿或待支付状态的订单基本信息
- 权限：门店端已登录用户
- 请求参数：同创建订单
- 错误码：

| 错误码 | 说明 |
|--------|------|
| 3002 | 订单不存在 |
| 4003 | 当前订单状态不允许修改 |

---

#### 新增窗帘明细
- 路径：`POST /api/v1/orders/:order_no/items`
- 说明：向草稿订单新增一副窗帘明细。后端实时计算所有费用。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| install_position | string | 是 | 安装位置，最长50字符 |
| width | decimal | 是 | 宽度（厘米），90.0-350.0 |
| height | decimal | 是 | 高度（厘米），50.0-600.0 |
| track_color | string | 是 | 轨道颜色：黑色/白色/灰色 |
| fabric_no | string | 是 | 世尚面料编号 |
| power_type | int | 否 | 电源类型：1标准（默认） 2锂电池 |
| remote_type | int | 否 | 遥控器类型：1标准（默认） 2Pro |
| wall_control_type | int | 否 | 墙面控制：0不配置（默认） 1标准 2Pro |
| wall_control_quantity | int | 否 | 墙面控制数量 |
| use_inventory | int | 否 | 是否使用库存套件：0否（默认） 1是 |
| install_condition | string | 否 | 安装方式或现场条件 |
| remark | string | 否 | 单副备注 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "item_id": 3001,
    "item_no": "SS-20260817-HN001-0008-C01",
    "track_amount_cent": 39600,
    "fabric_amount_cent": 21600,
    "accessory_amount_cent": 21000,
    "kit_amount_cent": 0,
    "item_total_cent": 82200
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 3002 | 订单不存在 |
| 4003 | 当前订单状态不允许添加明细 |
| 4202 | 尺寸超出产品允许范围 |
| 4201 | 面料不存在或已下架 |
| 4001 | 库存套件不足 |

---

#### 更新窗帘明细
- 路径：`PUT /api/v1/orders/:order_no/items/:item_id`
- 说明：更新草稿订单中某副窗帘明细
- 权限：门店端已登录用户
- 请求参数：同新增窗帘明细（均可选）

---

#### 删除窗帘明细
- 路径：`DELETE /api/v1/orders/:order_no/items/:item_id`
- 说明：从草稿订单中删除一副窗帘明细，释放对应库存
- 权限：门店端已登录用户

---

#### 复制窗帘明细
- 路径：`POST /api/v1/orders/:order_no/items/copy`
- 说明：复制某副窗帘明细的配置
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| source_item_id | int | 是 | 源窗帘明细ID |
| copy_dimensions | int | 否 | 是否复制尺寸：0否 1是（默认） |

---

#### 获取订单预览
- 路径：`GET /api/v1/orders/:order_no/preview`
- 说明：获取订单预览数据，含每副窗帘费用明细和整单汇总
- 权限：门店端已登录用户

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "order_no": "SS-20260817-HN001-0008",
    "items": [
      {
        "item_no": "SS-20260817-HN001-0008-C01",
        "install_position": "客厅",
        "track_amount_cent": 39600,
        "fabric_amount_cent": 21600,
        "accessory_amount_cent": 21000,
        "item_total_cent": 82200
      }
    ],
    "summary": {
      "total_amount_cent": 283710
    }
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 提交订单（锁定价格和库存）
- 路径：`POST /api/v1/orders/:order_no/submit`
- 说明：将草稿订单提交为待支付状态。锁定价格30天，锁定套件库存。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| confirmed | int | 是 | 确认定制须知：必须为1 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1008,
    "order_no": "SS-20260817-HN001-0008",
    "order_status": 2,
    "total_amount_cent": 283710,
    "price_locked_until": "2026-09-16 10:30:00"
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 3002 | 订单不存在 |
| 4003 | 订单非草稿状态 / 订单无窗帘明细 |
| 1002 | 未确认定制须知 |
| 4001 | 库存套件不足 |

---

#### 取消订单
- 路径：`PUT /api/v1/orders/:order_no/cancel`
- 说明：取消草稿或待支付状态的订单，释放已锁定库存
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reason | string | 是 | 取消原因，最长500字符 |

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 3002 | 订单不存在 |
| 4003 | 当前订单状态不允许取消 |

---

#### 删除草稿订单
- 路径：`DELETE /api/v1/orders/:order_no`
- 说明：删除草稿状态的订单（软删除）
- 权限：门店端已登录用户

---

#### 价格预览
- 路径：`POST /api/v1/orders/:order_no/price-preview`
- 说明：下单前查看后端计算的价格结果（规范 §8 计价）
- 权限：门店端已登录用户

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "items": [
      {
        "item_id": 3001,
        "track_amount_cent": 39600,
        "fabric_amount_cent": 21600,
        "accessory_amount_cent": 21000,
        "kit_amount_cent": 0,
        "item_total_cent": 82200
      }
    ],
    "total_amount_cent": 283710
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

### 2.4 支付模块（/api/v1/orders/:order_no/payments）

---

#### 创建支付
- 路径：`POST /api/v1/orders/:order_no/payments`
- 说明：为待支付订单创建支付。生成唯一支付单号，调用微信/支付宝接口。支持幂等。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| pay_channel | int | 是 | 支付渠道：1微信 2支付宝 |
| pay_method | string | 是 | 支付方式：JSAPI / H5 / NATIVE |

- 响应示例（微信支付）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "payment_no": "PAY20260817100001",
    "pay_amount_cent": 283710,
    "pay_channel": 1,
    "pay_channel_text": "微信支付",
    "wechat_params": {
      "timeStamp": "1724000000",
      "nonceStr": "xxx",
      "package": "prepay_id=xxx",
      "signType": "RSA",
      "paySign": "xxx"
    },
    "expire_seconds": 1800
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 3002 | 订单不存在 |
| 4003 | 订单状态不允许支付 |
| 5002 | 支付服务调用失败 |

---

#### 查询支付状态
- 路径：`GET /api/v1/orders/:order_no/payments/status`
- 说明：查询订单的支付状态
- 权限：门店端已登录用户

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "payment_status": 2,
    "payment_status_text": "已支付",
    "paid_amount_cent": 283710,
    "paid_at": "2026-08-17 11:05:00",
    "payment_no": "PAY20260817100001",
    "pay_channel": 1,
    "order_status": 4,
    "order_status_text": "已支付待审核"
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 支付回调（微信）
- 路径：`POST /api/v1/payment-callbacks/wechat`
- 说明：微信支付异步回调通知（服务端对服务端）。验签、幂等处理。
- 权限：微信支付系统回调，无需 Token

```json
{ "code": "SUCCESS", "message": "成功" }
```

---

#### 支付回调（支付宝）
- 路径：`POST /api/v1/payment-callbacks/alipay`
- 说明：支付宝支付异步回调通知。验签、幂等处理。
- 权限：支付宝系统回调，无需 Token

---

### 2.5 储值账户与余额（/api/v1/balance-accounts）

> 注：BalanceAccountController 正在同步开发中，路由已预注册。

---

#### 储值账户详情
- 路径：`GET /api/v1/balance-accounts/:id`
- 说明：获取储值账户余额和基本信息
- 权限：门店端已登录用户

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1,
    "balance_cent": 5000000,
    "frozen_cent": 0,
    "status": 1,
    "status_text": "正常"
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 储值充值
- 路径：`POST /api/v1/balance-accounts/:id/recharge`
- 说明：发起储值账户充值
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| amount_cent | int | 是 | 充值金额（分） |
| pay_channel | int | 是 | 支付渠道：1微信 2支付宝 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "recharge_no": "RCH20260817001",
    "amount_cent": 1000000,
    "pay_channel": 1
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 余额支付
- 路径：`POST /api/v1/balance-accounts/:id/pay`
- 说明：使用储值余额支付订单
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_no | string | 是 | 订单号 |
| amount_cent | int | 是 | 支付金额（分） |

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 4103 | 余额不足 |
| 4106 | 资金账户已冻结 |

---

#### 交易流水
- 路径：`GET /api/v1/balance-accounts/:id/transactions`
- 说明：获取储值账户的交易流水
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| type | int | 否 | 交易类型：1充值 2支付 3退款 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

---

### 2.6 面料模块（/api/v1/fabrics）

---

#### 面料列表（公开）
- 路径：`GET /api/v1/fabrics`
- 说明：获取可下单面料列表，仅返回已上架且允许订货的面料
- 权限：公开接口
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索关键词 |
| series | string | 否 | 系列筛选 |
| stock_status | int | 否 | 库存状态：1充足 2紧张 3缺货 |
| sort | string | 否 | 排序：price_asc / price_desc / newest / hot |
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
        "id": 1,
        "fabric_no": "SS-F-20260101",
        "series": "雅致系列",
        "name": "深灰遮光面料",
        "price_per_sqm_cent": 4000,
        "main_image": "https://oss.xxx.com/fabric/SS-F-20260101.jpg",
        "stock_status": 1,
        "is_favorited": true
      }
    ],
    "total": 256,
    "page": 1,
    "page_size": 20
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 面料详情（公开）
- 路径：`GET /api/v1/fabrics/:id`
- 说明：获取单款面料完整信息
- 权限：公开接口

---

#### 收藏面料
- 路径：`POST /api/v1/fabrics/:id/favorite`
- 说明：切换面料收藏状态
- 权限：门店端已登录用户

---

#### 获取常用面料
- 路径：`GET /api/v1/fabrics/favorites`
- 说明：获取当前门店收藏的面料列表
- 权限：门店端已登录用户

---

#### 获取最近使用面料
- 路径：`GET /api/v1/fabrics/recent`
- 说明：获取最近下单使用过的面料列表
- 权限：门店端已登录用户

---

### 2.7 商品模块（公开浏览）

---

#### 获取轨道列表
- 路径：`GET /api/v1/tracks`
- 说明：获取当前门店等级可用的轨道列表（含价格）
- 权限：公开接口

---

#### 获取选装配件列表
- 路径：`GET /api/v1/accessories`
- 说明：获取选装配件列表，按配置组分类返回
- 权限：公开接口

---

#### 获取套件信息
- 路径：`GET /api/v1/kit-info`
- 说明：获取当前门店等级的套件信息和价格
- 权限：公开接口

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "kit_sku": "KIT-STD-V1",
    "kit_name": "标准智能套件",
    "kit_price_cent": 76000,
    "includes": ["管状双传动电机", "管状电源适配器", "滑轮", "齿轮", "链条", "标准遥控器"]
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

### 2.8 库存模块（/api/v1/inventory）

---

#### 获取套件库存
- 路径：`GET /api/v1/inventory/kit`
- 说明：获取当前门店的套件库存概览
- 权限：门店端已登录用户

---

#### 获取库存流水
- 路径：`GET /api/v1/inventory/logs`
- 说明：获取当前门店的库存变化流水记录
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| kit_sku | string | 否 | 套件SKU筛选 |
| log_type | int | 否 | 变化类型筛选 |
| start_date | string | 否 | 起始日期 |
| end_date | string | 否 | 截止日期 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

---

### 2.9 收货地址模块（/api/v1/addresses）

---

#### 获取地址列表
- 路径：`GET /api/v1/addresses`
- 说明：获取当前门店的收货地址列表
- 权限：门店端已登录用户

---

#### 新增地址
- 路径：`POST /api/v1/addresses`
- 说明：新增收货地址
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| address_type | int | 是 | 地址类型：1门店地址 2仓库地址 3终端客户地址 |
| address_label | string | 否 | 地址标签 |
| receiver_name | string | 是 | 收件人 |
| receiver_phone | string | 是 | 手机号 |
| province | string | 是 | 省 |
| city | string | 是 | 市 |
| district | string | 是 | 区 |
| detail_address | string | 是 | 详细地址 |
| is_default | int | 否 | 是否默认：0否（默认） 1是 |

---

#### 更新地址
- 路径：`PUT /api/v1/addresses/:id`
- 说明：更新收货地址信息
- 权限：门店端已登录用户

---

#### 删除地址
- 路径：`DELETE /api/v1/addresses/:id`
- 说明：删除收货地址（软删除）
- 权限：门店端已登录用户

---

#### 设置默认地址
- 路径：`PUT /api/v1/addresses/:id/set-default`
- 说明：将指定地址设为默认收货地址
- 权限：门店端已登录用户

---

### 2.10 售后模块（/api/v1/after-sales）

---

#### 申请售后
- 路径：`POST /api/v1/after-sales`
- 说明：从具体订单/窗帘明细发起售后申请
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| item_id | int | 否 | 窗帘明细ID，整单售后可不填 |
| problem_type | int | 是 | 问题类型：1-11 |
| problem_desc | string | 是 | 问题描述 |
| images | array | 否 | 图片URL数组，最多9张 |
| videos | array | 否 | 视频URL数组，最多3个 |
| contact_name | string | 是 | 联系人 |
| contact_phone | string | 是 | 联系电话 |
| expected_solution | string | 否 | 期望处理方式 |

---

#### 获取售后列表
- 路径：`GET /api/v1/after-sales`
- 说明：获取当前门店的售后申请列表
- 权限：门店端已登录用户

---

#### 获取售后详情
- 路径：`GET /api/v1/after-sales/:id`
- 说明：获取售后申请详情
- 权限：门店端已登录用户

---

#### 补充售后信息
- 路径：`PUT /api/v1/after-sales/:id/supplement`
- 说明：补充或修改售后申请信息（仅待处理/处理中状态可操作）
- 权限：门店端已登录用户

---

### 2.11 发票模块（/api/v1/invoices）

---

#### 申请发票
- 路径：`POST /api/v1/invoices`
- 说明：订单确认收货后申请开票
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| invoice_type | int | 是 | 发票类型：1普票 2专票 |
| title | string | 是 | 发票抬头 |
| tax_no | string | 是 | 税号 |
| invoice_amount_cent | int | 是 | 开票金额（分） |
| delivery_method | int | 否 | 交付方式：1电子（默认） 2邮寄 |

---

#### 获取发票列表
- 路径：`GET /api/v1/invoices`
- 说明：获取当前门店的发票申请列表
- 权限：门店端已登录用户

---

#### 获取发票详情
- 路径：`GET /api/v1/invoices/:id`
- 说明：获取发票申请详情
- 权限：门店端已登录用户

---

### 2.12 预审申请（/api/v1/orders/:order_no/pre-audit）

> 注：正在同步开发中

---

#### 申请预审
- 路径：`POST /api/v1/orders/:order_no/pre-audit/request`
- 说明：门店对已支付订单申请预审
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| request_type | string | 是 | 预审类型 |
| remark | string | 否 | 申请说明 |

