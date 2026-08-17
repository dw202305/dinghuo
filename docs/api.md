# 世尚门店订货系统 - API 接口文档

> 版本：v1.1  
> 更新日期：2026-08-17  
> 技术栈：PHP 8.1+ / ThinkPHP 8 / MySQL 8.0+ / Redis  
> 前端：Vue 3 + uni-app（小程序+H5）+ Element Plus（后台）  
> 依赖：数据库设计文档 v1.2、PRD v3.2、开发规范 v1  

> **⚠️ v1.1 变更说明**：  
> 1. 所有金额字段从 DECIMAL（元）改为 BIGINT（分），字段名后缀 `_cent`；  
> 2. 接口中所有金额相关的请求参数和响应字段单位统一为"分"；  
> 3. 新增储值/余额模块接口（门店钱包、余额支付、后台储值管理、储值回调）；  
> 4. 支付渠道新增 `balance`（余额支付），单订单支付方式互斥；  
> 5. 新增业务错误码 4103-4106。

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
| 金额单位 | 分（BIGINT），字段名后缀 `_cent`，如 `amount_cent` |
| 面积单位 | 平方米，精度 4 位小数 |
| 尺寸单位 | 厘米（输入），米（计算时转换） |

### 1.2 API 路径规范

- 前缀：`/api/v1/`
- 风格：RESTful kebab-case（如 `/api/v1/store/user-info`）
- 门店端接口：`/api/v1/store/*`
- 后台接口：`/api/v1/admin/*`

### 1.3 统一响应格式

**成功响应：**

```json
{
  "code": 0,
  "message": "success",
  "data": {}
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
  }
}
```

**错误响应：**

```json
{
  "code": 1001,
  "message": "参数错误：手机号格式不正确",
  "data": null
}
```

### 1.4 错误码定义

| 错误码范围 | 说明 |
|-----------|------|
| 0 | 成功 |
| 1000-1999 | 参数错误 |
| 2000-2999 | 认证错误 |
| 3000-3999 | 权限错误 |
| 4000-4999 | 业务冲突（库存、价格、支付、余额） |
| 5000-5999 | 服务器错误 |

**常用错误码：**

| 错误码 | 说明 |
|--------|------|
| 0 | 成功 |
| 1001 | 参数缺失或格式错误 |
| 1002 | 手机号格式错误 |
| 1003 | 验证码错误或已过期 |
| 1004 | 数据不存在 |
| 1005 | 数据已存在（重复） |
| 1006 | 状态不允许当前操作 |
| 1007 | 尺寸超出产品允许范围 |
| 1008 | 面料不可用（已下架或禁止订货） |
| 2001 | 未登录或 Token 无效 |
| 2002 | Token 已过期 |
| 2003 | 账号已停用 |
| 2004 | 验证码发送频率过高 |
| 2005 | 登录凭据错误 |
| 3001 | 无权限访问该资源 |
| 3002 | 无权限执行该操作 |
| 3003 | 跨权限访问其他门店数据 |
| 5001 | 服务器内部错误 |
| 5002 | 数据库操作失败 |
| 5003 | 支付服务调用失败 |
| 5004 | 库存操作失败（并发冲突） |

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

1. **金额计算**：所有金额计算类接口，后端必须重新计算，不信任前端传入的金额数据。
2. **订单状态机**：订单相关接口必须校验当前状态是否允许目标操作。
3. **支付幂等**：支付相关接口使用 `payment_no` + `X-Request-Id` 保证幂等性。
4. **库存并发**：库存操作使用数据库事务 + Redis 分布式锁保证一致性。
5. **价格锁定**：订单提交后锁定价格 30 天，不受后台调价影响。
6. **软删除**：支持软删除的表查询时自动过滤 `deleted_at IS NULL`。
7. **余额支付互斥**：单张订单只能选择余额、微信或支付宝其中一种支付方式，不支持混合支付。
8. **资金流水不可变**：所有余额变化必须通过不可变资金流水产生，禁止直接修改余额而不写流水；冲正通过生成反向流水完成。
9. **余额支付幂等**：余额支付接口使用 `idempotent_key` 保证幂等性，重复提交不重复扣减。
10. **储值账户归属**：储值账户归属于客户主体（门店/合伙人），不归属于手机号或操作账号。

---

## 二、门店端接口

### 2.1 认证模块（/api/v1/store/auth/*）

---

#### 发送验证码
- 路径：POST /api/v1/store/auth/send-code
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
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1002 | 手机号格式错误 |
| 2004 | 验证码发送频率过高，请60秒后重试 |
| 5001 | 短信服务异常 |

---

#### 手机号+验证码登录
- 路径：POST /api/v1/store/auth/login
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
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1002 | 手机号格式错误 |
| 1003 | 验证码错误或已过期 |
| 2003 | 账号已停用 |

---

#### 微信授权登录（小程序）
- 路径：POST /api/v1/store/auth/wechat-login
- 说明：微信小程序授权登录。通过 wx.login 获取 code，后端换取 openid/unionid，自动关联或创建账号。若手机号尚未绑定，返回 need_bindphone 标识，前端引导手机号授权。
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
    "account_role": 1,
    "stores": [
      {
        "store_id": 1,
        "store_no": "HN001",
        "store_name": "长沙旗舰店",
        "role_in_customer": 1,
        "is_default": true
      }
    ]
  }
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
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1001 | 微信授权 code 无效 |
| 5001 | 微信接口调用失败 |

---

#### 退出登录
- 路径：POST /api/v1/store/auth/logout
- 说明：注销当前登录会话，使 Token 失效
- 权限：门店端已登录用户
- 请求参数：无

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 获取当前账号信息
- 路径：GET /api/v1/store/auth/profile
- 说明：获取当前登录账号的完整信息，包含账号信息、关联门店列表、当前门店信息
- 权限：门店端已登录用户
- 请求参数：无

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
      "customer_level_text": "认证合作门店",
      "channel_mode": 1,
      "partner_name": null,
      "primary_sales_name": "李经理",
      "contact_phone": "0731-88888888",
      "province": "湖南省",
      "city": "长沙市",
      "district": "岳麓区",
      "address": "XXX路XXX号"
    },
    "stores": [
      {
        "store_id": 1,
        "store_no": "HN001",
        "store_name": "长沙旗舰店",
        "role_in_customer": 1,
        "is_default": true
      }
    ]
  }
}
```

---

### 2.2 门店首页（/api/v1/store/home）

---

#### 获取工作台数据
- 路径：GET /api/v1/store/home/dashboard
- 说明：获取门店工作台数据，包含门店基本信息、库存概览、各状态订单统计、待办事项
- 权限：门店端已登录用户
- 请求参数：无

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
      "customer_level_text": "认证合作门店",
      "kit_price": 760.00,
      "primary_contact": {
        "name": "张三",
        "phone": "13888888888"
      }
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
      {
        "type": "payment_reminder",
        "message": "您有2笔订单待支付，请及时处理",
        "link": "/pages/order/list?status=pending_payment"
      }
    ]
  }
}
```

---

### 2.3 订单模块（/api/v1/store/order/*）

---

#### 创建订单（草稿）
- 路径：POST /api/v1/store/order/create
- 说明：创建一张空白草稿订单，填写订单级信息。系统自动生成订单号（SS-日期-门店编号-流水号）。创建后可继续添加窗帘明细。
- 权限：门店端已登录用户（下单员及以上角色）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_name | string | 否 | 项目名称，最长100字符 |
| end_customer | string | 否 | 终端客户名称或代号，最长100字符 |
| delivery_method | int | 否 | 收货方式：1发送至门店（默认） 2发送至终端客户 |
| address_id | int | 否 | 收货地址ID，delivery_method=1时使用门店地址 |
| receiver_name | string | 否 | 收件人姓名（终端客户地址时必填） |
| receiver_phone | string | 否 | 收件人手机号（终端客户地址时必填） |
| receiver_province | string | 否 | 省（终端客户地址时必填） |
| receiver_city | string | 否 | 市（终端客户地址时必填） |
| receiver_district | string | 否 | 区（终端客户地址时必填） |
| receiver_detail | string | 否 | 详细地址（终端客户地址时必填） |
| expected_delivery_date | string | 否 | 期望交期，格式 YYYY-MM-DD |
| invoice_required | int | 否 | 是否需要发票：0否（默认） 1是 |
| remark | string | 否 | 整单备注 |
| attachments | array | 否 | 现场照片/图纸URL数组 |
| save_address | int | 否 | 是否保存终端客户地址到地址簿：0否 1是 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "order_id": 1008,
    "order_no": "SS-20260817-HN001-0008"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1002 | 手机号格式错误（收件人手机号校验） |
| 1004 | 收货地址不存在 |
| 3002 | 当前账号角色无下单权限 |

---

#### 获取订单列表
- 路径：GET /api/v1/store/order/list
- 说明：获取当前门店的订单列表，支持按状态筛选和分页
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_status | int | 否 | 订单状态筛选，不传返回全部 |
| keyword | string | 否 | 搜索关键词（订单号/项目名称/终端客户） |
| start_date | string | 否 | 创建日期起始，格式 YYYY-MM-DD |
| end_date | string | 否 | 创建日期截止，格式 YYYY-MM-DD |
| page | int | 否 | 页码，默认1 |
| page_size | int | 否 | 每页数量，默认20，最大100 |

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
        "order_status": 2,
        "order_status_text": "待支付",
        "project_name": "万科样板间",
        "end_customer": "王先生",
        "item_count": 3,
        "total_amount": "2837.10",
        "paid_amount": "0.00",
        "payment_status": 0,
        "payment_status_text": "未支付",
        "created_at": "2026-08-17 10:30:00",
        "expected_delivery_date": "2026-09-15"
      }
    ],
    "total": 56,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 获取订单详情
- 路径：GET /api/v1/store/order/detail
- 说明：获取订单完整信息，包含订单基本信息、所有窗帘明细、收货信息、支付信息
- 权限：门店端已登录用户（仅可查看本门店订单）
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
    "order_status": 2,
    "order_status_text": "待支付",
    "project_name": "万科样板间",
    "end_customer": "王先生",
    "delivery_method": 1,
    "delivery_method_text": "发送至门店",
    "receiver": {
      "name": "张三",
      "phone": "13888888888",
      "province": "湖南省",
      "city": "长沙市",
      "district": "岳麓区",
      "detail": "XXX路XXX号"
    },
    "expected_delivery_date": "2026-09-15",
    "invoice_required": 0,
    "remark": "请注意安装方向",
    "attachments": ["https://oss.xxx.com/img1.jpg"],
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
        "track_horizontal_length": "1.8",
        "track_vertical_length": "6.0",
        "track_amount": "396.00",
        "fabric_no": "SS-F-20260101",
        "fabric_name": "世尚系列A-深灰",
        "fabric_price": "40.00",
        "fabric_amount": "216.00",
        "power_type": 2,
        "power_type_text": "锂电池",
        "power_surcharge": "100.00",
        "remote_type": 2,
        "remote_type_text": "Pro",
        "remote_surcharge": "30.00",
        "wall_control_type": 2,
        "wall_control_type_text": "Pro",
        "wall_control_quantity": 1,
        "wall_control_price": "80.00",
        "wall_control_amount": "80.00",
        "accessory_amount": "210.00",
        "use_inventory": 1,
        "kit_price": "0.00",
        "kit_amount": "0.00",
        "nonstandard_amount": "0.00",
        "item_total": "822.00",
        "install_condition": "石膏板吊顶",
        "remark": "靠窗侧安装",
        "technical_status": 0,
        "technical_status_text": "待审核",
        "production_status": 0,
        "production_status_text": "待排产",
        "shipping_status": 0,
        "shipping_status_text": "待发货"
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
      "shipping_method": "到付",
      "nonstandard_amount": "0.00",
      "discount_amount": "0.00",
      "total_amount": "2837.10"
    },
    "payment": {
      "payment_status": 0,
      "payment_status_text": "未支付",
      "paid_amount": "0.00",
      "price_locked_until": "2026-09-16 10:30:00"
    },
    "created_at": "2026-08-17 10:30:00",
    "updated_at": "2026-08-17 10:30:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 订单不存在 |
| 3003 | 无权查看其他门店订单 |

---

#### 更新订单基本信息
- 路径：PUT /api/v1/store/order/update
- 说明：更新草稿或待支付状态的订单基本信息。已支付订单不可修改。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| project_name | string | 否 | 项目名称 |
| end_customer | string | 否 | 终端客户名称 |
| delivery_method | int | 否 | 收货方式 |
| address_id | int | 否 | 收货地址ID |
| receiver_name | string | 否 | 收件人 |
| receiver_phone | string | 否 | 收件人手机号 |
| receiver_province | string | 否 | 省 |
| receiver_city | string | 否 | 市 |
| receiver_district | string | 否 | 区 |
| receiver_detail | string | 否 | 详细地址 |
| expected_delivery_date | string | 否 | 期望交期 |
| invoice_required | int | 否 | 是否需要发票 |
| remark | string | 否 | 整单备注 |
| attachments | array | 否 | 附件URL数组 |

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
| 1004 | 订单不存在 |
| 1006 | 当前订单状态不允许修改（仅草稿/待支付可修改） |

---

#### 新增窗帘明细
- 路径：POST /api/v1/store/order/item/add
- 说明：向草稿订单新增一副窗帘明细。后端根据门店等级、面料价格、轨道价格、配件价格实时计算所有费用。宽度范围 90.0-350.0cm，高度范围 50.0-600.0cm，超出范围提示非标。
- 权限：门店端已登录用户（下单员及以上）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| install_position | string | 是 | 安装位置/房间，最长50字符 |
| width | decimal | 是 | 宽度（厘米），90.0-350.0，精度1位 |
| height | decimal | 是 | 高度（厘米），50.0-600.0，精度1位 |
| track_color | string | 是 | 轨道颜色：黑色/白色/灰色 |
| fabric_no | string | 是 | 世尚面料编号 |
| power_type | int | 否 | 电源类型：1标准（默认） 2锂电池 |
| remote_type | int | 否 | 遥控器类型：1标准（默认） 2Pro |
| wall_control_type | int | 否 | 墙面控制：0不配置（默认） 1标准 2Pro |
| wall_control_quantity | int | 否 | 墙面控制数量，默认0 |
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
    "track_amount": "396.00",
    "fabric_amount": "216.00",
    "accessory_amount": "210.00",
    "kit_amount": "0.00",
    "nonstandard_amount": "0.00",
    "item_total": "822.00",
    "is_nonstandard": false,
    "nonstandard_hint": null
  }
}
```

> **⚠️ 重要说明**：所有金额由后端根据门店等级、面料价格、轨道价格、配件价格实时计算，不信任前端传入的金额。

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 订单不存在 |
| 1006 | 当前订单状态不允许添加明细（仅草稿可添加） |
| 1007 | 尺寸超出产品允许范围（宽>350cm 或 高>600cm） |
| 1008 | 面料不存在或已下架 |
| 1001 | 轨道颜色不在可选范围 |
| 5004 | 库存套件不足（use_inventory=1 但可用库存不足） |

---

#### 更新窗帘明细
- 路径：PUT /api/v1/store/order/item/update
- 说明：更新草稿订单中某副窗帘明细。后端重新计算所有费用。若切换库存使用状态，同步调整库存锁定。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| item_id | int | 是 | 窗帘明细ID |
| install_position | string | 否 | 安装位置 |
| width | decimal | 否 | 宽度（厘米） |
| height | decimal | 否 | 高度（厘米） |
| track_color | string | 否 | 轨道颜色 |
| fabric_no | string | 否 | 面料编号 |
| power_type | int | 否 | 电源类型 |
| remote_type | int | 否 | 遥控器类型 |
| wall_control_type | int | 否 | 墙面控制类型 |
| wall_control_quantity | int | 否 | 墙面控制数量 |
| use_inventory | int | 否 | 是否使用库存套件 |
| install_condition | string | 否 | 安装条件 |
| remark | string | 否 | 备注 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "item_id": 3001,
    "track_amount": "396.00",
    "fabric_amount": "216.00",
    "accessory_amount": "210.00",
    "kit_amount": "0.00",
    "item_total": "822.00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 明细不存在 |
| 1006 | 当前状态不允许修改 |
| 1007 | 尺寸超出范围 |
| 1008 | 面料不可用 |
| 5004 | 库存不足 |

---

#### 删除窗帘明细
- 路径：DELETE /api/v1/store/order/item/delete
- 说明：从草稿订单中删除一副窗帘明细。若该明细使用了库存套件，释放对应库存。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| item_id | int | 是 | 窗帘明细ID |

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
| 1004 | 明细不存在 |
| 1006 | 当前状态不允许删除（仅草稿状态可删除明细） |

---

#### 复制窗帘明细
- 路径：POST /api/v1/store/order/item/copy
- 说明：复制订单中某副窗帘明细的配置（含尺寸、轨道颜色、面料、配件等），生成新的窗帘明细。支持复制尺寸+配置或仅复制配置。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| source_item_id | int | 是 | 源窗帘明细ID |
| copy_dimensions | int | 否 | 是否复制尺寸：0否 1是（默认） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "item_id": 3002,
    "item_no": "SS-20260817-HN001-0008-C02",
    "item_total": "822.00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 源明细不存在 |
| 1006 | 订单非草稿状态 |

---

#### 获取订单预览（汇总计算）
- 路径：GET /api/v1/store/order/preview
- 说明：获取订单预览数据，包含每副窗帘明细的费用明细和整单汇总。后端实时计算所有金额。
- 权限：门店端已登录用户
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
    "order_no": "SS-20260817-HN001-0008",
    "items": [
      {
        "item_no": "SS-20260817-HN001-0008-C01",
        "install_position": "客厅",
        "width": "180.0",
        "height": "300.0",
        "area": "5.4000",
        "track_amount": "396.00",
        "fabric_amount": "216.00",
        "accessory_amount": "210.00",
        "kit_amount": "0.00",
        "nonstandard_amount": "0.00",
        "item_total": "822.00"
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
      "shipping_method": "到付",
      "nonstandard_amount": "0.00",
      "discount_amount": "0.00",
      "total_amount": "2837.10"
    },
    "inventory_summary": {
      "kit_available": 20,
      "kit_locked_other": 3,
      "kit_use_in_order": 2,
      "kit_remaining_after_order": 15
    }
  }
}
```

> **⚠️ 重要说明**：所有金额由后端实时计算，前端仅做展示，不作为最终结算依据。

---

#### 提交订单（锁定价格和库存）
- 路径：POST /api/v1/store/order/submit
- 说明：将草稿订单提交为待支付状态。后端重新计算所有金额，锁定价格30天，锁定使用的套件库存。提交前需门店确认勾选定制产品须知。
- 权限：门店端已登录用户（下单员及以上）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| confirmed | int | 是 | 确认定制须知：必须为1 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "order_id": 1008,
    "order_no": "SS-20260817-HN001-0008",
    "order_status": 2,
    "order_status_text": "待支付",
    "total_amount": "2837.10",
    "price_locked_until": "2026-09-16 10:30:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 订单不存在 |
| 1006 | 订单非草稿状态 / 订单无窗帘明细 |
| 1001 | 未确认定制须知 |
| 5004 | 库存套件不足，请调整库存使用策略 |

---

#### 取消订单
- 路径：POST /api/v1/store/order/cancel
- 说明：取消草稿或待支付状态的订单。取消后释放已锁定的套件库存。已支付的订单门店不可自行取消，需联系后台管理员。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| cancel_reason | string | 是 | 取消原因，最长500字符 |

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
| 1004 | 订单不存在 |
| 1006 | 当前订单状态不允许取消（仅草稿/待支付可取消） |

---

#### 删除草稿订单
- 路径：DELETE /api/v1/store/order/delete
- 说明：删除草稿状态的订单（软删除）。仅草稿状态可删除。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |

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
| 1004 | 订单不存在 |
| 1006 | 当前订单状态不允许删除（仅草稿可删除） |

---

### 2.4 面料模块（/api/v1/store/fabric/*）

---

#### 面料列表
- 路径：GET /api/v1/store/fabric/list
- 说明：获取可下单面料列表，支持多维度搜索、筛选和分页。仅返回已上架且允许订货的面料。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索关键词（面料编号/名称/色号） |
| series | string | 否 | 系列筛选 |
| material | string | 否 | 材质筛选 |
| color_name | string | 否 | 颜色筛选 |
| function_tag | string | 否 | 功能标签筛选（如阻燃、防水） |
| price_min | decimal | 否 | 最低价格 |
| price_max | decimal | 否 | 最高价格 |
| stock_status | int | 否 | 库存状态：1充足 2紧张 3缺货 |
| sort | string | 否 | 排序：price_asc / price_desc / newest / hot（默认） |
| page | int | 否 | 页码，默认1 |
| page_size | int | 否 | 每页数量，默认20，最大100 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "fabric_id": 1,
        "fabric_no": "SS-F-20260101",
        "series": "雅致系列",
        "name": "深灰遮光面料",
        "material": "涤纶",
        "color_name": "深灰",
        "color_code": "#4A4A4A",
        "function_tags": ["遮光", "阻燃"],
        "price_per_sqm": "40.00",
        "main_image": "https://oss.xxx.com/fabric/SS-F-20260101.jpg",
        "stock_status": 1,
        "stock_status_text": "充足",
        "is_favorited": true
      }
    ],
    "total": 256,
    "page": 1,
    "page_size": 20,
    "filter_options": {
      "series_list": ["雅致系列", "轻奢系列", "自然系列"],
      "material_list": ["涤纶", "亚麻", "混纺"],
      "function_tag_list": ["遮光", "阻燃", "防水", "防霉"]
    }
  }
}
```

---

#### 面料详情
- 路径：GET /api/v1/store/fabric/detail
- 说明：获取单款面料的完整信息，包含图片、属性、价格等
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| fabric_no | string | 是 | 世尚面料编号 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "fabric_id": 1,
    "fabric_no": "SS-F-20260101",
    "series": "雅致系列",
    "name": "深灰遮光面料",
    "material": "涤纶",
    "color_name": "深灰",
    "color_code": "#4A4A4A",
    "texture_tags": ["细纹", "哑光"],
    "function_tags": ["遮光", "阻燃"],
    "price_per_sqm": "40.00",
    "main_image": "https://oss.xxx.com/fabric/SS-F-20260101.jpg",
    "detail_images": [
      "https://oss.xxx.com/fabric/SS-F-20260101_d1.jpg",
      "https://oss.xxx.com/fabric/SS-F-20260101_d2.jpg"
    ],
    "fabric_width": "2.80",
    "min_billing_area": "1.0000",
    "stock_status": 1,
    "stock_status_text": "充足",
    "is_favorited": true
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 面料不存在 |

---

#### 收藏面料
- 路径：POST /api/v1/store/fabric/favorite
- 说明：切换面料收藏状态（收藏/取消收藏）
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| fabric_no | string | 是 | 面料编号 |
| action | int | 是 | 操作：1收藏 0取消收藏 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 获取常用面料
- 路径：GET /api/v1/store/fabric/favorites
- 说明：获取当前门店收藏的常用面料列表
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
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
        "fabric_no": "SS-F-20260101",
        "name": "深灰遮光面料",
        "series": "雅致系列",
        "price_per_sqm": "40.00",
        "main_image": "https://oss.xxx.com/fabric/SS-F-20260101.jpg",
        "stock_status": 1
      }
    ],
    "total": 12,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 获取最近使用面料
- 路径：GET /api/v1/store/fabric/recent
- 说明：获取当前门店最近下单使用过的面料列表（按使用时间倒序）
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| limit | int | 否 | 返回数量，默认10，最大50 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "fabric_no": "SS-F-20260101",
        "name": "深灰遮光面料",
        "series": "雅致系列",
        "price_per_sqm": "40.00",
        "main_image": "https://oss.xxx.com/fabric/SS-F-20260101.jpg",
        "last_used_at": "2026-08-15 14:20:00",
        "stock_status": 1
      }
    ]
  }
}
```

---

### 2.5 商品模块（/api/v1/store/product/*）

---

#### 获取轨道列表
- 路径：GET /api/v1/store/product/track/list
- 说明：获取当前门店等级可用的轨道列表（含价格）。根据门店客户等级返回对应价格。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| track_type | int | 否 | 轨道类型：1横轨 2竖轨，不传返回全部 |
| color | string | 否 | 颜色筛选 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "sku": "TRACK-H-BLK",
        "track_type": 1,
        "track_type_text": "横轨",
        "color": "黑色",
        "standard_length": "6.00",
        "price_per_meter": "120.00",
        "enabled": 1
      },
      {
        "sku": "TRACK-H-WHT",
        "track_type": 1,
        "track_type_text": "横轨",
        "color": "白色",
        "standard_length": "6.00",
        "price_per_meter": "120.00",
        "enabled": 1
      }
    ]
  }
}
```

---

#### 获取选装配件列表
- 路径：GET /api/v1/store/product/accessory/list
- 说明：获取选装配件列表，按配置组分类返回，含门店等级对应价格
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| config_group | string | 否 | 配置组筛选：power / remote / wall_control |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "sku": "ACC-POWER-STD",
        "name": "标准电源适配器",
        "config_group": "power",
        "option_type": 1,
        "option_type_text": "标准",
        "surcharge": "0.00",
        "required": 1,
        "select_mode": 1,
        "image": "https://oss.xxx.com/acc/power-std.jpg"
      },
      {
        "sku": "ACC-POWER-LI",
        "name": "锂电池电源适配器",
        "config_group": "power",
        "option_type": 2,
        "option_type_text": "升级",
        "surcharge": "100.00",
        "required": 0,
        "select_mode": 1,
        "image": "https://oss.xxx.com/acc/power-li.jpg"
      },
      {
        "sku": "ACC-WC-PRO",
        "name": "墙面控制器Pro款",
        "config_group": "wall_control",
        "option_type": 3,
        "option_type_text": "新增",
        "surcharge": "80.00",
        "required": 0,
        "select_mode": 1,
        "allow_quantity": 1,
        "max_quantity": 5,
        "image": "https://oss.xxx.com/acc/wc-pro.jpg"
      }
    ]
  }
}
```

---

#### 获取套件信息
- 路径：GET /api/v1/store/product/kit/info
- 说明：获取当前门店等级的套件信息和价格
- 权限：门店端已登录用户
- 请求参数：无

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "kit_sku": "KIT-STD-V1",
    "kit_name": "标准智能套件",
    "kit_price": "760.00",
    "customer_level": 1,
    "customer_level_text": "认证合作门店",
    "includes": [
      "管状双传动电机",
      "管状电源适配器",
      "滑轮",
      "齿轮",
      "链条",
      "标准遥控器"
    ]
  }
}
```

---

### 2.6 支付模块（/api/v1/store/payment/*）

---

#### 创建支付
- 路径：POST /api/v1/store/payment/create
- 说明：为待支付订单创建支付。后端重新校验订单金额，生成唯一支付单号，调用微信/支付宝接口获取支付参数。支持幂等：同一订单未完成支付时重复调用返回相同支付单。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| pay_channel | int | 是 | 支付渠道：1微信 2支付宝 3余额 |
| pay_method | string | 条件 | 支付方式：JSAPI(小程序/公众号) / H5 / NATIVE（选择微信或支付宝时必填，选择余额时不需要） |
| idempotent_key | string | 否 | 幂等键（余额支付时必填，防止重复扣款） |

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
  }
}
```

- 响应示例（支付宝支付）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "payment_no": "PAY20260817100002",
    "pay_amount_cent": 283710,
    "pay_channel": 2,
    "pay_channel_text": "支付宝",
    "alipay_params": {
      "order_string": "alipay_sdk=xxx&..."
    },
    "expire_seconds": 1800
  }
}
```

> **⚠️ 幂等性说明**：同一 order_id 在未支付完成时重复调用创建支付，返回已有的 payment_no 和新的支付参数（因 prepay_id 等时效参数需刷新）。余额支付时使用 `idempotent_key` 保证幂等，重复提交不会重复扣减。
>
> **⚠️ 支付方式互斥**：单张订单只能选择余额、微信或支付宝其中一种支付方式，不支持任何形式的混合支付。

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 订单不存在 |
| 1006 | 订单状态不允许支付（非待支付状态） |
| 4103 | 余额不足 |
| 4104 | 不支持混合支付 |
| 4106 | 资金账户已冻结 |
| 5003 | 支付服务调用失败，请稍后重试 |

---

#### 查询支付状态
- 路径：GET /api/v1/store/payment/status
- 说明：查询订单的支付状态。若支付回调延迟，后端主动向微信/支付宝发起查单。
- 权限：门店端已登录用户
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
    "payment_status": 2,
    "payment_status_text": "已支付",
    "paid_amount_cent": 283710,
    "paid_at": "2026-08-17 11:05:00",
    "payment_no": "PAY20260817100001",
    "pay_channel": 1,
    "pay_channel_text": "微信支付",
    "order_status": 4,
    "order_status_text": "已支付待审核"
  }
}
```

---

#### 支付回调（微信）
- 路径：POST /api/v1/store/payment/notify/wechat
- 说明：微信支付异步回调通知（服务端对服务端）。验签、幂等处理、更新支付状态、更新订单状态、核销库存。
- 权限：微信支付系统回调，无需 Token
- 请求参数：微信官方回调参数（XML/JSON）
- 响应：微信要求返回格式

```json
{
  "code": "SUCCESS",
  "message": "成功"
}
```

> **⚠️ 幂等性说明**：同一笔支付可能收到多次回调通知，系统通过 payment_no + transaction_id 去重，不重复处理支付成功逻辑（不重复扣库存、不重复更新订单）。

---

#### 支付回调（支付宝）
- 路径：POST /api/v1/store/payment/notify/alipay
- 说明：支付宝支付异步回调通知（服务端对服务端）。验签、幂等处理。
- 权限：支付宝系统回调，无需 Token
- 请求参数：支付宝官方回调参数
- 响应：

```
success
```

---

### 2.7 库存模块（/api/v1/store/inventory/*）

---

#### 获取套件库存
- 路径：GET /api/v1/store/inventory/kit
- 说明：获取当前门店的套件库存概览
- 权限：门店端已登录用户
- 请求参数：无

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
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
    ]
  }
}
```

---

#### 获取库存流水
- 路径：GET /api/v1/store/inventory/log
- 说明：获取当前门店的库存变化流水记录
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| kit_sku | string | 否 | 套件SKU筛选 |
| log_type | int | 否 | 变化类型：1采购入账 2订单锁定 3支付核销 4取消释放 5退款退回 6售后更换 7人工调整 8门店调拨 |
| start_date | string | 否 | 起始日期 YYYY-MM-DD |
| end_date | string | 否 | 截止日期 YYYY-MM-DD |
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
        "kit_sku": "KIT-STD-V1",
        "log_type": 2,
        "log_type_text": "订单锁定",
        "quantity": -2,
        "before_quantity": 20,
        "after_quantity": 18,
        "order_no": "SS-20260817-HN001-0008",
        "operator_name": "系统",
        "reason": "订单提交锁定库存",
        "created_at": "2026-08-17 10:30:00"
      }
    ],
    "total": 35,
    "page": 1,
    "page_size": 20
  }
}
```

---

### 2.8 收货地址模块（/api/v1/store/address/*）

---

#### 获取地址列表
- 路径：GET /api/v1/store/address/list
- 说明：获取当前门店的收货地址列表
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| address_type | int | 否 | 地址类型：1门店地址 2仓库地址 3终端客户地址，不传返回全部 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "address_id": 1,
        "address_type": 1,
        "address_type_text": "门店地址",
        "address_label": "总部仓库",
        "receiver_name": "张三",
        "receiver_phone": "13888888888",
        "province": "湖南省",
        "city": "长沙市",
        "district": "岳麓区",
        "detail_address": "XXX路XXX号",
        "is_default": 1,
        "is_single_use": 0
      }
    ]
  }
}
```

---

#### 新增地址
- 路径：POST /api/v1/store/address/create
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
| is_single_use | int | 否 | 是否仅用于单次订单：0否（默认） 1是 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "address_id": 2
  }
}
```

---

#### 更新地址
- 路径：PUT /api/v1/store/address/update
- 说明：更新收货地址信息
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| address_id | int | 是 | 地址ID |
| address_type | int | 否 | 地址类型 |
| address_label | string | 否 | 地址标签 |
| receiver_name | string | 否 | 收件人 |
| receiver_phone | string | 否 | 手机号 |
| province | string | 否 | 省 |
| city | string | 否 | 市 |
| district | string | 否 | 区 |
| detail_address | string | 否 | 详细地址 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 删除地址
- 路径：DELETE /api/v1/store/address/delete
- 说明：删除收货地址（软删除，status 置为 0）
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| address_id | int | 是 | 地址ID |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 设置默认地址
- 路径：PUT /api/v1/store/address/set-default
- 说明：将指定地址设为门店默认收货地址，同时取消其他地址的默认状态
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| address_id | int | 是 | 地址ID |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

### 2.9 售后模块（/api/v1/store/after-sale/*）

---

#### 申请售后
- 路径：POST /api/v1/store/after-sale/create
- 说明：从具体订单/窗帘明细发起售后申请
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| item_id | int | 否 | 窗帘明细ID，整单售后可不填 |
| problem_type | int | 是 | 问题类型：1电机 2电源 3遥控器 4墙控 5轨道 6面料 7结构件 8安装 9初始化 10运输破损 11其他 |
| problem_desc | string | 是 | 问题描述 |
| images | array | 否 | 图片URL数组，最多9张 |
| videos | array | 否 | 视频URL数组，最多3个 |
| install_date | string | 否 | 安装日期 YYYY-MM-DD |
| affect_usage | int | 否 | 是否影响使用：0否（默认） 1是 |
| contact_name | string | 是 | 联系人 |
| contact_phone | string | 是 | 联系电话 |
| expected_solution | string | 否 | 期望处理方式 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "after_sale_id": 1,
    "after_sale_no": "AS20260817001"
  }
}
```

---

#### 获取售后列表
- 路径：GET /api/v1/store/after-sale/list
- 说明：获取当前门店的售后申请列表
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | int | 否 | 状态筛选：1待处理 2处理中 3已完成 4已关闭 |
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
        "item_no": "SS-20260817-HN001-0008-C01",
        "problem_type": 1,
        "problem_type_text": "电机",
        "problem_desc": "电机运转异响",
        "status": 1,
        "status_text": "待处理",
        "created_at": "2026-08-17 15:00:00"
      }
    ],
    "total": 3,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 获取售后详情
- 路径：GET /api/v1/store/after-sale/detail
- 说明：获取售后申请详情
- 权限：门店端已登录用户
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
    "item_no": "SS-20260817-HN001-0008-C01",
    "problem_type": 1,
    "problem_type_text": "电机",
    "problem_desc": "电机运转异响",
    "images": ["https://oss.xxx.com/img1.jpg"],
    "videos": [],
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
    "handler_name": null,
    "created_at": "2026-08-17 15:00:00"
  }
}
```

---

#### 补充售后信息
- 路径：PUT /api/v1/store/after-sale/supplement
- 说明：补充或修改售后申请信息（仅在待处理或处理中状态可操作）
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| after_sale_id | int | 是 | 售后单ID |
| problem_desc | string | 否 | 问题描述（补充） |
| images | array | 否 | 新增图片URL数组 |
| videos | array | 否 | 新增视频URL数组 |
| expected_solution | string | 否 | 期望处理方式 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

### 2.10 发票模块（/api/v1/store/invoice/*）

---

#### 申请发票
- 路径：POST /api/v1/store/invoice/create
- 说明：订单确认收货后申请开票。可开票金额不超过订单实付金额。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| invoice_type | int | 是 | 发票类型：1普票 2专票 |
| title | string | 是 | 发票抬头 |
| tax_no | string | 是 | 税号 |
| tax_rate | decimal | 是 | 税率（%），由后台维护可选值 |
| invoice_amount | decimal | 是 | 开票金额 |
| bank_name | string | 否 | 开户银行（专票必填） |
| bank_account | string | 否 | 银行账号（专票必填） |
| company_address | string | 否 | 公司地址（专票必填） |
| company_phone | string | 否 | 公司电话（专票必填） |
| delivery_method | int | 否 | 交付方式：1电子（默认） 2邮寄 |
| delivery_address | string | 否 | 邮寄地址（delivery_method=2时必填） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "request_id": 1,
    "request_no": "INV20260817001"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1006 | 订单未确认收货，不可申请发票 |
| 1001 | 开票金额超过可开票金额 |

---

#### 获取发票申请列表
- 路径：GET /api/v1/store/invoice/list
- 说明：获取当前门店的发票申请列表
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | int | 否 | 状态筛选：1待审核 2已审核待开票 3已开票 4已驳回 |
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
        "request_id": 1,
        "request_no": "INV20260817001",
        "order_no": "SS-20260817-HN001-0008",
        "invoice_type": 1,
        "invoice_type_text": "普票",
        "title": "长沙XXX公司",
        "tax_no": "91430xxx",
        "invoice_amount": "2837.10",
        "status": 1,
        "status_text": "待审核",
        "created_at": "2026-08-17 16:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 获取发票详情
- 路径：GET /api/v1/store/invoice/detail
- 说明：获取发票申请详情
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| request_id | int | 是 | 发票申请ID |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "request_id": 1,
    "request_no": "INV20260817001",
    "order_no": "SS-20260817-HN001-0008",
    "invoice_type": 2,
    "invoice_type_text": "专票",
    "title": "长沙XXX公司",
    "tax_no": "91430xxx",
    "tax_rate": "6.00",
    "invoice_amount": "2837.10",
    "bank_name": "中国银行长沙分行",
    "bank_account": "1234567890",
    "company_address": "长沙市岳麓区XXX",
    "company_phone": "0731-88888888",
    "delivery_method": 1,
    "delivery_method_text": "电子",
    "status": 3,
    "status_text": "已开票",
    "invoice_no": "01234567",
    "invoice_code": "0430xxx",
    "invoiced_at": "2026-08-18 10:00:00",
    "reject_reason": null,
    "created_at": "2026-08-17 16:00:00"
  }
}
```

---



### 2.11 我的钱包（/api/v1/store/balance/*）

---

#### 查询我的资金账户
- 路径：GET /api/v1/store/balance/account
- 说明：查询当前门店（客户主体）的资金账户信息，包括可用余额、冻结余额、累计储值、累计消费、累计退款和累计人工调整。所有金额单位为分。
- 权限：门店端已登录用户
- 请求参数：无

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "account_id": 1,
    "customer_type": 1,
    "customer_type_text": "门店",
    "customer_id": 100,
    "store_name": "长沙旗舰店",
    "currency": "CNY",
    "available_balance_cent": 5000000,
    "frozen_balance_cent": 0,
    "total_recharge_cent": 10000000,
    "total_consumed_cent": 4500000,
    "total_refund_cent": 500000,
    "total_adjustment_cent": 0,
    "account_status": 1,
    "account_status_text": "正常",
    "updated_at": "2026-08-17 10:00:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 资金账户不存在 |

---

#### 发起储值充值
- 路径：POST /api/v1/store/balance/recharge
- 说明：发起储值充值，支持微信和支付宝在线充值。后端创建储值订单并调用支付平台接口获取支付参数。充值成功后资金计入当前门店资金账户。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| amount_cent | int | 是 | 充值金额（分），必须为正整数 |
| recharge_method | int | 是 | 储值方式：1微信 2支付宝 |
| pay_method | string | 是 | 支付方式：JSAPI(小程序/公众号) / H5 / NATIVE |
| idempotent_key | string | 是 | 幂等键，防止重复创建储值单 |

- 响应示例（微信充值）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "recharge_no": "RCH20260817100001",
    "amount_cent": 1000000,
    "recharge_method": 1,
    "recharge_method_text": "微信",
    "status": 2,
    "status_text": "支付中",
    "wechat_params": {
      "timeStamp": "1724000000",
      "nonceStr": "xxx",
      "package": "prepay_id=xxx",
      "signType": "RSA",
      "paySign": "xxx"
    },
    "expire_seconds": 1800
  }
}
```

- 响应示例（支付宝充值）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "recharge_no": "RCH20260817100002",
    "amount_cent": 1000000,
    "recharge_method": 2,
    "recharge_method_text": "支付宝",
    "status": 2,
    "status_text": "支付中",
    "alipay_params": {
      "order_string": "alipay_sdk=xxx&..."
    },
    "expire_seconds": 1800
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1001 | 充值金额必须为正整数 |
| 4106 | 资金账户已冻结 |
| 5003 | 支付服务调用失败 |

---

#### 储值记录列表
- 路径：GET /api/v1/store/balance/recharge/list
- 说明：查询当前门店的储值记录列表，支持按状态和储值方式筛选，按创建时间倒序排列。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | int | 否 | 储值单状态：1待支付 2支付中 3待审核 4已入账 5已关闭 6已退款 |
| recharge_method | int | 否 | 储值方式：1微信 2支付宝 3线下 4测试 |
| start_date | string | 否 | 起始日期 YYYY-MM-DD |
| end_date | string | 否 | 截止日期 YYYY-MM-DD |
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
        "recharge_id": 1,
        "recharge_no": "RCH20260817100001",
        "amount_cent": 1000000,
        "recharge_method": 1,
        "recharge_method_text": "微信",
        "status": 4,
        "status_text": "已入账",
        "trade_no": "wx_xxx",
        "paid_at": "2026-08-17 10:05:00",
        "credited_at": "2026-08-17 10:05:01",
        "created_at": "2026-08-17 10:04:00"
      }
    ],
    "total": 25,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 储值记录详情
- 路径：GET /api/v1/store/balance/recharge/{id}
- 说明：查询单笔储值记录的完整信息，包含支付和审核详情。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 是 | 储值记录ID（路径参数） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "recharge_id": 1,
    "recharge_no": "RCH20260817100001",
    "account_id": 1,
    "amount_cent": 1000000,
    "recharge_method": 1,
    "recharge_method_text": "微信",
    "trade_no": "wx_xxx",
    "status": 4,
    "status_text": "已入账",
    "applicant_name": "张三",
    "reviewer_name": null,
    "paid_at": "2026-08-17 10:05:00",
    "reviewed_at": null,
    "credited_at": "2026-08-17 10:05:01",
    "remark": null,
    "created_at": "2026-08-17 10:04:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 储值记录不存在 |

---

#### 资金流水列表
- 路径：GET /api/v1/store/balance/transactions
- 说明：查询当前门店的资金流水列表，支持按流水类型和日期范围筛选，按创建时间倒序排列。资金流水不可修改、不可删除。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| transaction_type | int | 否 | 流水类型：1储值 2消费 3退款 4冻结 5解冻 6调入 7调出 8冲正 9人工调整 |
| start_date | string | 否 | 起始日期 YYYY-MM-DD |
| end_date | string | 否 | 截止日期 YYYY-MM-DD |
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
        "transaction_id": 1,
        "transaction_no": "TXN20260817100001",
        "transaction_type": 1,
        "transaction_type_text": "储值",
        "fund_type": 1,
        "fund_type_text": "真实资金",
        "direction": 1,
        "direction_text": "收入",
        "amount_cent": 1000000,
        "before_balance_cent": 4000000,
        "after_balance_cent": 5000000,
        "payment_channel": "wechat",
        "operator_name": "张三",
        "reason": "微信充值",
        "remark": null,
        "created_at": "2026-08-17 10:05:01"
      }
    ],
    "total": 88,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 流水详情
- 路径：GET /api/v1/store/balance/transactions/{id}
- 说明：查询单笔资金流水的完整信息，包含关联订单、支付单、储值单等引用信息。
- 权限：门店端已登录用户
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 是 | 流水ID（路径参数） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "transaction_id": 1,
    "transaction_no": "TXN20260817100001",
    "account_id": 1,
    "transaction_type": 1,
    "transaction_type_text": "储值",
    "fund_type": 1,
    "fund_type_text": "真实资金",
    "direction": 1,
    "direction_text": "收入",
    "amount_cent": 1000000,
    "before_balance_cent": 4000000,
    "after_balance_cent": 5000000,
    "ref_order_id": null,
    "ref_payment_id": null,
    "ref_recharge_id": 1,
    "payment_channel": "wechat",
    "operator_id": 1,
    "operator_name": "张三",
    "reviewer_id": null,
    "reviewer_name": null,
    "reason": "微信充值",
    "remark": null,
    "created_at": "2026-08-17 10:05:01"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 流水记录不存在 |

---

### 2.12 余额支付（/api/v1/store/payment/balance-*）

---

#### 余额支付
- 路径：POST /api/v1/store/payment/balance-pay
- 说明：使用余额支付整单订单。后端在同一MySQL事务内完成余额扣减、资金流水生成、支付记录创建、库存核销和订单支付状态更新。余额不足时整笔失败，不做部分扣减。单订单只能选择一种支付方式，余额与微信/支付宝互斥。
- 权限：门店端已登录用户（需有支付权限的账号角色）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_id | int | 是 | 订单ID |
| idempotent_key | string | 是 | 幂等键，防止重复扣减（建议使用 UUID 或订单号+时间戳组合） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "payment_no": "PAY20260817100003",
    "pay_amount_cent": 283710,
    "pay_channel": 3,
    "pay_channel_text": "余额支付",
    "balance_before_cent": 5000000,
    "balance_after_cent": 4716290,
    "transaction_no": "TXN20260817100005",
    "paid_at": "2026-08-17 11:00:00"
  }
}
```

> **⚠️ 幂等性说明**：同一 `idempotent_key` 重复提交返回相同结果，不会重复扣减余额。
>
> **⚠️ 支付方式互斥**：单张订单只能选择余额、微信或支付宝其中一种支付方式，不支持任何形式的混合支付。余额不足时整笔失败。

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 订单不存在 |
| 1006 | 订单状态不允许支付（非待支付状态） |
| 4103 | 余额不足 |
| 4104 | 不支持混合支付 |
| 4106 | 资金账户已冻结 |

---

#### 余额退款
- 路径：POST /api/v1/store/payment/balance-refund
- 说明：将余额支付的订单退款退回原客户主体余额。退款必须关联原余额支付流水，在同一事务内完成余额回补和反向资金流水生成。
- 权限：门店端已登录用户（退款需审批权限，或后台发起）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| payment_id | int | 是 | 原余额支付记录ID |
| refund_amount_cent | int | 否 | 退款金额（分），不传则全额退款；部分退款需审批 |
| refund_reason | string | 是 | 退款原因 |
| idempotent_key | string | 是 | 幂等键 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "refund_id": 1,
    "refund_amount_cent": 283710,
    "transaction_no": "TXN20260817100006",
    "balance_before_cent": 4716290,
    "balance_after_cent": 5000000,
    "refund_status": "success",
    "refunded_at": "2026-08-17 12:00:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 原支付记录不存在 |
| 1006 | 该支付记录非余额支付，无法使用余额退款 |
| 4106 | 资金账户已冻结 |


## 三、后台管理端接口

### 3.1 认证模块（/api/v1/admin/auth/*）

---

#### 管理员登录
- 路径：POST /api/v1/admin/auth/login
- 说明：后台管理员登录，返回 Token 和管理员信息
- 权限：公开接口
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | 是 | 登录用户名 |
| password | string | 是 | 密码 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "token": "eyJhbGciOi...",
    "expires_in": 28800,
    "admin_id": 1,
    "username": "admin",
    "real_name": "系统管理员",
    "role_id": 1,
    "role_name": "超级管理员",
    "permissions": [
      "order:view", "order:audit", "order:cancel",
      "product:view", "product:edit",
      "store:view", "store:edit",
      "inventory:view", "inventory:adjust",
      "finance:view", "finance:refund",
      "after-sale:view", "after-sale:process",
      "system:admin", "system:role", "system:log"
    ]
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 2005 | 用户名或密码错误 |
| 2003 | 账号已停用 |

---

#### 退出登录
- 路径：POST /api/v1/admin/auth/logout
- 说明：管理员退出登录，使 Token 失效
- 权限：后台已登录管理员
- 请求参数：无

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 获取当前管理员信息
- 路径：GET /api/v1/admin/auth/profile
- 说明：获取当前登录管理员的完整信息和权限列表
- 权限：后台已登录管理员
- 请求参数：无

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "admin_id": 1,
    "username": "admin",
    "real_name": "系统管理员",
    "phone": "13800000000",
    "email": "admin@shishang.com",
    "avatar": "https://oss.xxx.com/avatar/admin.jpg",
    "role_id": 1,
    "role_name": "超级管理员",
    "permissions": ["order:view", "order:audit", "..."],
    "last_login_at": "2026-08-17 09:00:00",
    "last_login_ip": "120.xxx.xxx.xxx",
    "login_count": 156
  }
}
```

---

#### 修改密码
- 路径：PUT /api/v1/admin/auth/password
- 说明：修改当前管理员密码
- 权限：后台已登录管理员
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| old_password | string | 是 | 原密码 |
| new_password | string | 是 | 新密码，8-20位，含大小写字母和数字 |
| confirm_password | string | 是 | 确认新密码 |

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
| 2005 | 原密码错误 |
| 1001 | 两次密码输入不一致 / 密码强度不足 |

---

### 3.2 门店管理（/api/v1/admin/store/*）

---

#### 门店列表
- 路径：GET /api/v1/admin/store/list
- 说明：获取门店列表，支持多维度筛选和分页
- 权限：后台-门店管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索关键词（门店编号/名称/联系人） |
| store_type | int | 否 | 门店类型筛选 |
| customer_level | int | 否 | 客户等级筛选 |
| channel_mode | int | 否 | 渠道模式：1合伙人渠道 2公司直营 |
| partner_id | int | 否 | 所属合伙人ID筛选 |
| primary_sales_id | int | 否 | 归属销售ID筛选 |
| status | int | 否 | 状态：1正常 2停用 3待审核 |
| province | string | 否 | 省筛选 |
| city | string | 否 | 市筛选 |
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
        "business_entity": "长沙XXX建材有限公司",
        "customer_level": 1,
        "customer_level_text": "认证合作门店",
        "channel_mode": 1,
        "channel_mode_text": "城市合伙人渠道",
        "partner_name": "湖南城市合伙人",
        "primary_sales_name": "李经理",
        "province": "湖南省",
        "city": "长沙市",
        "contact_phone": "0731-88888888",
        "primary_contact_name": "张三",
        "kit_available": 18,
        "status": 1,
        "status_text": "正常",
        "cooperation_start_date": "2025-01-01",
        "created_at": "2025-01-01 00:00:00"
      }
    ],
    "total": 128,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 门店详情
- 路径：GET /api/v1/admin/store/detail
- 说明：获取门店完整信息，包含基本信息、联系人、账号、归属关系等
- 权限：后台-门店管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 是 | 门店ID |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "store_id": 1,
    "store_no": "HN001",
    "store_name": "长沙旗舰店",
    "business_entity": "长沙XXX建材有限公司",
    "credit_code": "91430xxx",
    "store_type": 1,
    "store_type_text": "认证合作门店",
    "customer_level": 1,
    "customer_level_text": "认证合作门店",
    "channel_mode": 1,
    "channel_mode_text": "城市合伙人渠道",
    "partner": {
      "partner_id": 1,
      "partner_no": "P-HN-001",
      "business_entity": "湖南XXX建材有限公司"
    },
    "primary_sales": {
      "sales_id": 1,
      "name": "李经理",
      "employee_no": "S001"
    },
    "province": "湖南省",
    "city": "长沙市",
    "district": "岳麓区",
    "address": "XXX路XXX号",
    "contact_phone": "0731-88888888",
    "wechat": "shishang_cs",
    "showroom_photos": ["https://oss.xxx.com/showroom/1.jpg"],
    "invoice_title": "长沙XXX建材有限公司",
    "tax_no": "91430xxx",
    "cooperation_start_date": "2025-01-01",
    "contract_price_version": 1,
    "status": 1,
    "status_text": "正常",
    "contacts": [
      {
        "contact_id": 1,
        "contact_name": "张三",
        "phone": "13888888888",
        "wechat": "zhangsan",
        "position": "总经理",
        "contact_type": 1,
        "contact_type_text": "负责人",
        "is_primary": 1,
        "receive_order_notify": 1,
        "receive_finance_notify": 0,
        "status": 1
      }
    ],
    "accounts": [
      {
        "account_id": 1,
        "phone": "138****8888",
        "real_name": "张三",
        "account_role": 1,
        "account_role_text": "门店管理员",
        "verify_status": 1,
        "status": 1,
        "last_login_at": "2026-08-17 10:00:00"
      }
    ]
  }
}
```

---

#### 新增门店
- 路径：POST /api/v1/admin/store/create
- 说明：新增门店，同时创建门店主体和默认联系人
- 权限：后台-门店管理-新增
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_no | string | 是 | 门店编号，如 HN001 |
| store_name | string | 是 | 门店名称 |
| business_entity | string | 否 | 经营主体名称 |
| credit_code | string | 否 | 统一社会信用代码 |
| store_type | int | 是 | 门店类型：1-4 |
| customer_level | int | 是 | 客户等级：1-5 |
| channel_mode | int | 是 | 渠道模式：1合伙人渠道 2公司直营 |
| partner_id | int | 否 | 所属城市合伙人ID（渠道模式=1时必填） |
| primary_sales_id | int | 是 | 主归属销售ID |
| secondary_sales_id | int | 否 | 协同销售ID |
| province | string | 否 | 省 |
| city | string | 否 | 市 |
| district | string | 否 | 区 |
| address | string | 否 | 详细地址 |
| contact_phone | string | 否 | 门店联系电话 |
| wechat | string | 否 | 门店微信 |
| showroom_photos | array | 否 | 展厅照片URL数组 |
| invoice_title | string | 否 | 开票名称 |
| tax_no | string | 否 | 税号 |
| cooperation_start_date | string | 否 | 合作开始日期 |
| primary_contact_name | string | 是 | 主联系人姓名 |
| primary_contact_phone | string | 是 | 主联系人手机号 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "store_id": 129,
    "store_no": "HN002"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1005 | 门店编号已存在 |
| 1004 | 城市合伙人不存在 |
| 1004 | 销售人员不存在 |

---

#### 更新门店
- 路径：PUT /api/v1/admin/store/update
- 说明：更新门店信息
- 权限：后台-门店管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 是 | 门店ID |
| store_name | string | 否 | 门店名称 |
| business_entity | string | 否 | 经营主体名称 |
| credit_code | string | 否 | 统一社会信用代码 |
| customer_level | int | 否 | 客户等级 |
| channel_mode | int | 否 | 渠道模式 |
| partner_id | int | 否 | 所属合伙人ID |
| primary_sales_id | int | 否 | 主归属销售ID |
| secondary_sales_id | int | 否 | 协同销售ID |
| province | string | 否 | 省 |
| city | string | 否 | 市 |
| district | string | 否 | 区 |
| address | string | 否 | 详细地址 |
| contact_phone | string | 否 | 联系电话 |
| wechat | string | 否 | 微信 |
| showroom_photos | array | 否 | 展厅照片 |
| invoice_title | string | 否 | 开票名称 |
| tax_no | string | 否 | 税号 |
| invoice_info | object | 否 | 开票资料JSON |
| cooperation_start_date | string | 否 | 合作开始日期 |
| contract_price_version | int | 否 | 合同价格版本 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 停用/启用门店
- 路径：PUT /api/v1/admin/store/status
- 说明：停用或启用门店。停用时门店下所有账号无法登录。
- 权限：后台-门店管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 是 | 门店ID |
| status | int | 是 | 目标状态：1正常 2停用 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": null
}
```

---

#### 管理门店联系人
- 路径：POST /api/v1/admin/store/contact/save
- 说明：新增或更新门店联系人
- 权限：后台-门店管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| contact_id | int | 否 | 联系人ID（更新时必填，新增时不填） |
| store_id | int | 是 | 所属门店ID |
| contact_name | string | 是 | 姓名 |
| phone | string | 是 | 手机号 |
| wechat | string | 否 | 微信号 |
| position | string | 否 | 职务 |
| contact_type | int | 是 | 联系人类型：1负责人 2采购 3下单 4财务 5安装 6售后 7收货人 |
| is_primary | int | 否 | 是否主联系人：0否（默认） 1是 |
| receive_order_notify | int | 否 | 是否接收订单通知：0否 1是（默认） |
| receive_finance_notify | int | 否 | 是否接收财务通知：0否（默认） 1是 |
| remark | string | 否 | 备注 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "contact_id": 2
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1005 | 该手机号已是该门店联系人 |
| 1006 | 不能取消所有主联系人 |

---

#### 管理门店账号
- 路径：POST /api/v1/admin/store/account/save
- 说明：为门店创建或更新登录账号
- 权限：后台-门店管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| account_id | int | 否 | 账号ID（更新时必填） |
| store_id | int | 是 | 所属门店ID |
| phone | string | 是 | 登录手机号 |
| real_name | string | 否 | 姓名 |
| contact_id | int | 否 | 关联联系人ID |
| account_role | int | 是 | 账号角色：1门店管理员 2下单员 3财务 4安装售后 5只读 |
| password | string | 否 | 初始密码（创建时可选） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "account_id": 5
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1005 | 该手机号已被注册 |

---

### 3.3 城市合伙人管理（/api/v1/admin/partner/*）

---

#### 合伙人列表
- 路径：GET /api/v1/admin/partner/list
- 说明：获取城市合伙人列表
- 权限：后台-合伙人管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索关键词（编号/企业名称） |
| primary_sales_id | int | 否 | 归属销售筛选 |
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
        "partner_id": 1,
        "partner_no": "P-HN-001",
        "business_entity": "湖南XXX建材有限公司",
        "authorized_city": "长沙市",
        "cooperation_stage": 1,
        "partner_level": 1,
        "primary_sales_name": "李经理",
        "store_count": 15,
        "status": 1,
        "status_text": "正常",
        "cooperation_start_date": "2025-03-01",
        "cooperation_end_date": "2027-02-28"
      }
    ],
    "total": 8,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 合伙人详情
- 路径：GET /api/v1/admin/partner/detail
- 说明：获取合伙人完整信息
- 权限：后台-合伙人管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| partner_id | int | 是 | 合伙人ID |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "partner_id": 1,
    "partner_no": "P-HN-001",
    "business_entity": "湖南XXX建材有限公司",
    "credit_code": "91430xxx",
    "primary_contact": {
      "contact_id": 10,
      "contact_name": "王总",
      "phone": "13900000000"
    },
    "authorized_city": "长沙市",
    "authorized_region": "岳麓区、天心区、芙蓉区",
    "cooperation_stage": 1,
    "partner_level": 1,
    "primary_sales": {
      "sales_id": 1,
      "name": "李经理"
    },
    "secondary_sales": {
      "sales_id": 3,
      "name": "赵经理"
    },
    "crm_customer_id": "CRM-HN-001",
    "cooperation_start_date": "2025-03-01",
    "cooperation_end_date": "2027-02-28",
    "status": 1,
    "status_text": "正常",
    "store_count": 15
  }
}
```

---

#### 新增/更新合伙人
- 路径：POST /api/v1/admin/partner/save
- 说明：新增或更新城市合伙人信息。新增时自动创建归属关系历史记录。
- 权限：后台-合伙人管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| partner_id | int | 否 | 合伙人ID（更新时必填） |
| partner_no | string | 是 | 合伙人编号（新增时必填） |
| business_entity | string | 是 | 企业名称 |
| credit_code | string | 否 | 统一社会信用代码 |
| primary_contact_name | string | 否 | 主联系人姓名（新增时必填） |
| primary_contact_phone | string | 否 | 主联系人手机号（新增时必填） |
| authorized_city | string | 否 | 授权城市 |
| authorized_region | string | 否 | 授权区域 |
| cooperation_stage | int | 否 | 合作阶段 |
| partner_level | int | 否 | 合伙人等级 |
| primary_sales_id | int | 是 | 主归属销售ID |
| secondary_sales_id | int | 否 | 协同销售ID |
| crm_customer_id | string | 否 | CRM客户ID |
| cooperation_start_date | string | 否 | 合作开始日期 |
| cooperation_end_date | string | 否 | 合作结束日期 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "partner_id": 2
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1005 | 合伙人编号已存在 |
| 1004 | 销售人员不存在 |

---

#### 查看下属门店
- 路径：GET /api/v1/admin/partner/stores
- 说明：获取合伙人下属的门店列表
- 权限：后台-合伙人管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| partner_id | int | 是 | 合伙人ID |
| status | int | 否 | 门店状态筛选 |
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
        "customer_level": 1,
        "status": 1,
        "status_text": "正常",
        "order_count_30d": 12,
        "order_amount_30d": "35680.00"
      }
    ],
    "total": 15,
    "page": 1,
    "page_size": 20
  }
}
```

---

### 3.4 商品管理（/api/v1/admin/product/*）

---

#### 面料列表（后台）
- 路径：GET /api/v1/admin/product/fabric/list
- 说明：获取面料列表，支持多维度搜索筛选，后台可查看所有面料（含已下架）
- 权限：后台-商品管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索（编号/名称/色号/系列） |
| series | string | 否 | 系列筛选 |
| listing_status | int | 否 | 上架状态：1已上架 0已下架 |
| orderable | int | 否 | 允许订货：1是 0否 |
| stock_status | int | 否 | 库存状态：1充足 2紧张 3缺货 |
| sort | string | 否 | 排序字段 |
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
        "fabric_id": 1,
        "fabric_no": "SS-F-20260101",
        "series": "雅致系列",
        "name": "深灰遮光面料",
        "material": "涤纶",
        "color_name": "深灰",
        "color_code": "#4A4A4A",
        "function_tags": ["遮光", "阻燃"],
        "price_per_sqm": "40.00",
        "main_image": "https://oss.xxx.com/fabric/SS-F-20260101.jpg",
        "stock_status": 1,
        "stock_status_text": "充足",
        "listing_status": 1,
        "orderable": 1,
        "sort_weight": 100,
        "effective_date": "2026-01-01",
        "price_version": 1,
        "supplier_count": 2
      }
    ],
    "total": 320,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 面料详情（后台）
- 路径：GET /api/v1/admin/product/fabric/detail
- 说明：获取面料详情，包含供应商映射信息
- 权限：后台-商品管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| fabric_id | int | 是 | 面料ID |

- 响应示例（含供应商映射）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "fabric_id": 1,
    "fabric_no": "SS-F-20260101",
    "series": "雅致系列",
    "name": "深灰遮光面料",
    "material": "涤纶",
    "color_name": "深灰",
    "color_code": "#4A4A4A",
    "texture_tags": ["细纹", "哑光"],
    "function_tags": ["遮光", "阻燃"],
    "price_per_sqm": "40.00",
    "main_image": "https://oss.xxx.com/fabric/SS-F-20260101.jpg",
    "detail_images": ["https://oss.xxx.com/fabric/d1.jpg"],
    "fabric_width": "2.80",
    "min_billing_area": "1.0000",
    "loss_coefficient": "1.0000",
    "stock_status": 1,
    "listing_status": 1,
    "orderable": 1,
    "sort_weight": 100,
    "effective_date": "2026-01-01",
    "price_version": 1,
    "suppliers": [
      {
        "mapping_id": 1,
        "supplier_id": 1,
        "supplier_name": "浙江XXX纺织",
        "supplier_fabric_no": "ZJ-F-001",
        "supplier_color_desc": "深灰A",
        "purchase_price": "22.00",
        "delivery_days": 7,
        "is_default_supplier": 1,
        "is_backup_supplier": 0,
        "effective_date": "2026-01-01",
        "expire_date": null,
        "status": 1
      }
    ]
  }
}
```

---

#### 面料新增/编辑
- 路径：POST /api/v1/admin/product/fabric/save
- 说明：新增或编辑面料信息
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| fabric_id | int | 否 | 面料ID（编辑时必填） |
| fabric_no | string | 是 | 世尚面料编号 |
| series | string | 否 | 系列 |
| name | string | 是 | 名称 |
| material | string | 否 | 材质 |
| color_name | string | 否 | 颜色名称 |
| color_code | string | 否 | 色号 |
| texture_tags | array | 否 | 纹理风格标签 |
| function_tags | array | 否 | 功能标签 |
| price_per_sqm | decimal | 是 | 单价/㎡ |
| main_image | string | 否 | 主图URL |
| detail_images | array | 否 | 详情图URL数组 |
| fabric_width | decimal | 否 | 面料幅宽（米） |
| min_billing_area | decimal | 否 | 最小计费面积 |
| loss_coefficient | decimal | 否 | 损耗系数，默认1.0000 |
| stock_status | int | 否 | 库存状态 |
| listing_status | int | 否 | 上架状态 |
| orderable | int | 否 | 允许订货 |
| sort_weight | int | 否 | 排序权重 |
| effective_date | string | 否 | 生效日期 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "fabric_id": 1
  }
}
```

---

#### 面料批量导入
- 路径：POST /api/v1/admin/product/fabric/import
- 说明：批量导入面料数据（Excel文件）。支持新增和更新。
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| file | file | 是 | Excel文件（.xlsx），不超过10MB |
| mode | string | 否 | 导入模式：append仅新增（默认） upsert新增或更新 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "total_rows": 100,
    "success_count": 95,
    "fail_count": 5,
    "fail_details": [
      {"row": 12, "fabric_no": "SS-F-XXXX", "error": "面料编号已存在"}
    ]
  }
}
```

---

#### 面料批量调价
- 路径：POST /api/v1/admin/product/fabric/batch-price
- 说明：批量调整面料价格，生成新价格版本。需审批。
- 权限：后台-商品管理-调价（需审批）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| fabric_ids | array | 是 | 面料ID列表 |
| adjust_type | string | 是 | 调价方式：fixed固定值 percent百分比 |
| adjust_value | decimal | 是 | 调价值（固定金额或百分比，如 +5 或 -10%） |
| effective_date | string | 是 | 新价格生效日期 |
| reason | string | 是 | 调价原因 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "affected_count": 50,
    "new_price_version": 2
  }
}
```

---

#### 面料批量上下架
- 路径：POST /api/v1/admin/product/fabric/batch-status
- 说明：批量上架或下架面料
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| fabric_ids | array | 是 | 面料ID列表 |
| listing_status | int | 是 | 目标状态：1上架 0下架 |
| orderable | int | 否 | 允许订货：1是 0否 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "affected_count": 50
  }
}
```

---

#### 轨道列表（后台）
- 路径：GET /api/v1/admin/product/track/list
- 说明：获取轨道列表
- 权限：后台-商品管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| track_type | int | 否 | 轨道类型筛选 |
| color | string | 否 | 颜色筛选 |
| enabled | int | 否 | 启用状态筛选 |
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
        "sku": "TRACK-H-BLK",
        "track_type": 1,
        "track_type_text": "横轨",
        "color": "黑色",
        "standard_length": "6.00",
        "price_per_meter": "120.00",
        "partner_price": "100.00",
        "enabled": 1,
        "effective_date": "2026-01-01",
        "price_version": 1
      }
    ],
    "total": 6,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 轨道新增/编辑
- 路径：POST /api/v1/admin/product/track/save
- 说明：新增或编辑轨道
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 否 | 轨道ID（编辑时必填） |
| sku | string | 是 | 轨道SKU |
| track_type | int | 是 | 类型：1横轨 2竖轨 |
| color | string | 是 | 颜色 |
| standard_length | decimal | 是 | 标准原料长度（米） |
| price_per_meter | decimal | 是 | 门店单价/米 |
| partner_price | decimal | 否 | 合伙人价格 |
| enabled | int | 否 | 是否启用 |
| effective_date | string | 否 | 生效日期 |
| remark | string | 否 | 备注 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": { "id": 1 }
}
```

---

#### 配件列表（后台）
- 路径：GET /api/v1/admin/product/accessory/list
- 说明：获取选装配件列表
- 权限：后台-商品管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| config_group | string | 否 | 配置组筛选 |
| enabled | int | 否 | 启用状态筛选 |
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
        "sku": "ACC-POWER-STD",
        "name": "标准电源适配器",
        "image": "https://oss.xxx.com/acc/power-std.jpg",
        "config_group": "power",
        "option_type": 1,
        "option_type_text": "标准",
        "surcharge": "0.00",
        "partner_surcharge": "0.00",
        "required": 1,
        "select_mode": 1,
        "allow_quantity": 0,
        "enabled": 1,
        "effective_date": "2026-01-01",
        "price_version": 1
      }
    ],
    "total": 6,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 配件新增/编辑
- 路径：POST /api/v1/admin/product/accessory/save
- 说明：新增或编辑选装配件
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 否 | 配件ID（编辑时必填） |
| sku | string | 是 | 配件SKU |
| name | string | 是 | 配件名称 |
| image | string | 否 | 图片URL |
| config_group | string | 是 | 配置组：power/remote/wall_control |
| option_type | int | 是 | 类型：1标准 2升级 3新增 |
| surcharge | decimal | 是 | 门店加价 |
| partner_surcharge | decimal | 否 | 合伙人加价 |
| required | int | 否 | 是否必选 |
| select_mode | int | 否 | 选择模式：1单选 2多选 |
| allow_quantity | int | 否 | 是否允许数量 |
| max_quantity | int | 否 | 最大数量 |
| applicable_products | array | 否 | 适用产品JSON |
| compatibility_rules | object | 否 | 兼容排斥规则JSON |
| enabled | int | 否 | 是否启用 |
| effective_date | string | 否 | 生效日期 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": { "id": 1 }
}
```

---

#### 套件管理（列表）
- 路径：GET /api/v1/admin/product/kit/list
- 说明：获取套件列表
- 权限：后台-商品管理
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
        "id": 1,
        "sku": "KIT-STD-V1",
        "name": "标准智能套件",
        "prices": {
          "1": "760.00",
          "2": "660.00"
        },
        "enabled": 1,
        "price_version": 1
      }
    ],
    "total": 1,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 套件新增/编辑
- 路径：POST /api/v1/admin/product/kit/save
- 说明：新增或编辑套件
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 否 | 套件ID（编辑时必填） |
| sku | string | 是 | 套件SKU |
| name | string | 是 | 套件名称 |
| prices | object | 是 | 各等级价格，key为customer_level，value为价格 |
| enabled | int | 否 | 是否启用 |
| effective_date | string | 否 | 生效日期 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": { "id": 1 }
}
```

---

#### 供应商列表
- 路径：GET /api/v1/admin/product/supplier/list
- 说明：获取面料供应商列表
- 权限：后台-商品管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索关键词 |
| business_status | int | 否 | 经营状态：1正常 2停用 |
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
        "supplier_name": "浙江XXX纺织",
        "contact_person": "王经理",
        "contact_phone": "13700000000",
        "business_status": 1,
        "business_status_text": "正常",
        "cooperation_start_date": "2025-01-01",
        "cooperation_end_date": "2027-12-31",
        "fabric_count": 50,
        "purchase_remark": "品质稳定"
      }
    ],
    "total": 12,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 供应商新增/编辑
- 路径：POST /api/v1/admin/product/supplier/save
- 说明：新增或编辑供应商
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 否 | 供应商ID（编辑时必填） |
| supplier_name | string | 是 | 供应商名称 |
| contact_person | string | 否 | 联系人 |
| contact_phone | string | 否 | 联系电话 |
| business_status | int | 否 | 经营状态 |
| cooperation_start_date | string | 否 | 合作开始日期 |
| cooperation_end_date | string | 否 | 合作结束日期 |
| purchase_remark | string | 否 | 采购备注 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": { "id": 1 }
}
```

---

#### 供应商面料映射管理
- 路径：POST /api/v1/admin/product/supplier/mapping/save
- 说明：新增或编辑供应商面料编号映射。新增映射不得覆盖已有历史映射记录。
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 否 | 映射ID（编辑时必填） |
| fabric_no | string | 是 | 世尚面料编号 |
| supplier_id | int | 是 | 供应商ID |
| supplier_fabric_no | string | 是 | 供应商原始面料编号 |
| supplier_color_desc | string | 否 | 供应商内部颜色/批次描述 |
| purchase_price | decimal | 否 | 供应商采购价格 |
| purchase_unit | string | 否 | 计价单位 |
| min_order_quantity | int | 否 | 最小起订量 |
| delivery_days | int | 否 | 交期（天） |
| effective_date | string | 否 | 映射生效日期 |
| expire_date | string | 否 | 映射失效日期 |
| is_default_supplier | int | 否 | 是否默认供应商 |
| is_backup_supplier | int | 否 | 是否备选供应商 |
| quality_remark | string | 否 | 质量/色差备注 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": { "id": 1 }
}
```

---


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
        "paid_amount_cent": 283710,
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
| pay_channel | int | 否 | 支付渠道：1微信 2支付宝 3余额 |
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
    "refund_amount_cent": 283710,
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
    "total_amount_cent": 35860000
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


### 3.11 储值管理（/api/v1/admin/balance/*）

---

#### 资金账户列表（后台）
- 路径：GET /api/v1/admin/balance/accounts
- 说明：查询所有客户资金账户列表，支持按客户类型、状态和关键词搜索，按更新时间倒序排列。
- 权限：后台-财务管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| customer_type | int | 否 | 客户主体类型：1门店 2城市合伙人 |
| account_status | int | 否 | 账户状态：1正常 2冻结 3注销 |
| keyword | string | 否 | 搜索（门店名称/编号/合伙人编号） |
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
        "account_id": 1,
        "customer_type": 1,
        "customer_type_text": "门店",
        "customer_id": 100,
        "customer_name": "长沙旗舰店",
        "customer_no": "HN001",
        "currency": "CNY",
        "available_balance_cent": 5000000,
        "frozen_balance_cent": 0,
        "total_recharge_cent": 10000000,
        "total_consumed_cent": 4500000,
        "account_status": 1,
        "account_status_text": "正常",
        "updated_at": "2026-08-17 10:00:00"
      }
    ],
    "total": 35,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 账户详情（后台）
- 路径：GET /api/v1/admin/balance/accounts/{id}
- 说明：查询单个资金账户的完整信息，包含所有统计字段和最近交易摘要。
- 权限：后台-财务管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 是 | 资金账户ID（路径参数） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "account_id": 1,
    "customer_type": 1,
    "customer_type_text": "门店",
    "customer_id": 100,
    "customer_name": "长沙旗舰店",
    "customer_no": "HN001",
    "currency": "CNY",
    "available_balance_cent": 5000000,
    "frozen_balance_cent": 0,
    "total_recharge_cent": 10000000,
    "total_consumed_cent": 4500000,
    "total_refund_cent": 500000,
    "total_adjustment_cent": 0,
    "account_status": 1,
    "account_status_text": "正常",
    "created_at": "2026-08-01 09:00:00",
    "updated_at": "2026-08-17 10:00:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 资金账户不存在 |

---

#### 冻结账户
- 路径：POST /api/v1/admin/balance/accounts/{id}/freeze
- 说明：冻结指定资金账户。冻结后该账户不可发起余额支付，但不影响查看余额和流水。需记录操作原因。
- 权限：后台-财务管理（需审批权限）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 是 | 资金账户ID（路径参数） |
| reason | string | 是 | 冻结原因 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "account_id": 1,
    "account_status": 2,
    "account_status_text": "冻结",
    "frozen_at": "2026-08-17 14:00:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 资金账户不存在 |
| 1006 | 账户已处于冻结状态 |

---

#### 解冻账户
- 路径：POST /api/v1/admin/balance/accounts/{id}/unfreeze
- 说明：解冻已冻结的资金账户，恢复正常使用。需记录操作原因。
- 权限：后台-财务管理（需审批权限）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 是 | 资金账户ID（路径参数） |
| reason | string | 是 | 解冻原因 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "account_id": 1,
    "account_status": 1,
    "account_status_text": "正常",
    "unfrozen_at": "2026-08-17 15:00:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 资金账户不存在 |
| 1006 | 账户未处于冻结状态 |

---

#### 线下储值入账
- 路径：POST /api/v1/admin/balance/offline-recharge
- 说明：为客户录入线下储值（如银行转账、现金等），必须关联付款凭证。提交后进入待审核状态，审核通过后资金到账并生成资金流水。
- 权限：后台-财务管理-储值
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| account_id | int | 是 | 资金账户ID |
| amount_cent | int | 是 | 储值金额（分） |
| offline_voucher | string | 是 | 付款凭证信息（凭证照片URL或凭证编号） |
| payer_name | string | 是 | 付款人姓名 |
| payee_account | string | 是 | 收款账户信息 |
| paid_at | string | 是 | 付款到账时间 YYYY-MM-DD HH:mm:ss |
| remark | string | 否 | 备注 |
| idempotent_key | string | 是 | 幂等键 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "recharge_id": 10,
    "recharge_no": "RCH20260817100010",
    "amount_cent": 5000000,
    "recharge_method": 3,
    "recharge_method_text": "线下转账",
    "status": 3,
    "status_text": "待审核",
    "applicant_name": "财务小王",
    "created_at": "2026-08-17 14:30:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1001 | 参数缺失或格式错误 |
| 1004 | 资金账户不存在 |
| 4106 | 资金账户已冻结 |

---

#### 审核储值单
- 路径：POST /api/v1/admin/balance/recharge/{id}/review
- 说明：审核线下储值单（通过或驳回）。通过后资金到账并生成资金流水；驳回后储值单关闭。
- 权限：后台-财务管理-储值审核
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 是 | 储值单ID（路径参数） |
| action | int | 是 | 操作：1审核通过 2驳回 |
| remark | string | 条件 | 驳回时必填驳回原因 |

- 响应示例（审核通过）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "recharge_id": 10,
    "recharge_no": "RCH20260817100010",
    "status": 4,
    "status_text": "已入账",
    "reviewer_name": "财务主管",
    "reviewed_at": "2026-08-17 15:00:00",
    "credited_at": "2026-08-17 15:00:01"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 储值单不存在 |
| 1006 | 储值单状态不允许审核 |

---

#### 资金流水列表（后台）
- 路径：GET /api/v1/admin/balance/transactions
- 说明：查询全量资金流水，支持按客户主体、流水类型、资金方向、日期范围等多维度筛选。
- 权限：后台-财务管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| account_id | int | 否 | 资金账户ID |
| customer_type | int | 否 | 客户主体类型：1门店 2城市合伙人 |
| customer_id | int | 否 | 客户主体ID |
| transaction_type | int | 否 | 流水类型：1储值 2消费 3退款 4冻结 5解冻 6调入 7调出 8冲正 9人工调整 |
| direction | int | 否 | 资金方向：1收入 2支出 |
| fund_type | int | 否 | 资金属性：1真实资金 2测试资金 |
| keyword | string | 否 | 搜索（流水号/客户名称） |
| start_date | string | 否 | 起始日期 YYYY-MM-DD |
| end_date | string | 否 | 截止日期 YYYY-MM-DD |
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
        "transaction_id": 1,
        "transaction_no": "TXN20260817100001",
        "account_id": 1,
        "customer_name": "长沙旗舰店",
        "customer_no": "HN001",
        "transaction_type": 1,
        "transaction_type_text": "储值",
        "fund_type": 1,
        "fund_type_text": "真实资金",
        "direction": 1,
        "direction_text": "收入",
        "amount_cent": 1000000,
        "before_balance_cent": 4000000,
        "after_balance_cent": 5000000,
        "payment_channel": "wechat",
        "operator_name": "张三",
        "reason": "微信充值",
        "created_at": "2026-08-17 10:05:01"
      }
    ],
    "total": 350,
    "page": 1,
    "page_size": 20
  }
}
```

---

#### 人工余额调整
- 路径：POST /api/v1/admin/balance/adjust
- 说明：对指定资金账户进行人工余额调整（增加或扣减），必须填写调整原因，需经过财务审批。调整后自动生成对应的人工调整类型资金流水。生产环境禁止无审批的人工加款。
- 权限：后台-财务管理（需审批权限）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| account_id | int | 是 | 资金账户ID |
| amount_cent | int | 是 | 调整金额（分），正数为增加，负数为扣减 |
| reason | string | 是 | 调整原因（必填，审计用途） |
| remark | string | 否 | 备注 |
| idempotent_key | string | 是 | 幂等键 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "transaction_no": "TXN20260817100010",
    "transaction_type": 9,
    "transaction_type_text": "人工调整",
    "amount_cent": 500000,
    "before_balance_cent": 5000000,
    "after_balance_cent": 5500000,
    "operator_name": "财务主管",
    "created_at": "2026-08-17 16:00:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1001 | 调整金额不能为0 |
| 1004 | 资金账户不存在 |
| 4103 | 余额不足（扣减时） |
| 4106 | 资金账户已冻结 |

---

#### 冲正流水
- 路径：POST /api/v1/admin/balance/transactions/{id}/reverse
- 说明：对指定资金流水进行冲正，生成一笔金额相反的新流水（原流水不做任何修改）。冲正后自动更新账户余额。
- 权限：后台-财务管理（需审批权限）
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 是 | 原流水ID（路径参数） |
| reason | string | 是 | 冲正原因 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "original_transaction_no": "TXN20260817100005",
    "reverse_transaction_no": "TXN20260817100011",
    "transaction_type": 8,
    "transaction_type_text": "冲正",
    "amount_cent": 1000000,
    "direction": 2,
    "direction_text": "支出",
    "before_balance_cent": 5500000,
    "after_balance_cent": 4500000,
    "operator_name": "财务主管",
    "created_at": "2026-08-17 17:00:00"
  }
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 原流水不存在 |
| 1006 | 该流水已被冲正或不允许冲正 |



## 四、储值回调接口

---

#### 储值微信支付回调
- 路径：POST /api/v1/callback/recharge/wechat
- 说明：储值充值微信支付异步回调通知（服务端对服务端）。验签、幂等处理、更新储值单状态、生成资金流水、更新账户余额。
- 权限：微信支付系统回调，无需 Token
- 请求参数：微信官方回调参数（XML/JSON）
- 响应：微信要求返回格式

```json
{
  "code": "SUCCESS",
  "message": "成功"
}
```

> **⚠️ 幂等性说明**：同一笔储值充值可能收到多次回调通知，系统通过 `recharge_no` + `transaction_id` 去重，不重复处理到账逻辑（不重复入账、不重复生成流水）。

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 4105 | 储值回调已处理（幂等返回） |

---

#### 储值支付宝回调
- 路径：POST /api/v1/callback/recharge/alipay
- 说明：储值充值支付宝异步回调通知（服务端对服务端）。验签、幂等处理。
- 权限：支付宝系统回调，无需 Token
- 请求参数：支付宝官方回调参数
- 响应：

```
success
```

> **⚠️ 幂等性说明**：同一笔储值充值可能收到多次回调通知，系统通过 `recharge_no` + `trade_no` 去重，不重复处理。

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 4105 | 储值回调已处理（幂等返回） |

---

## 五、订单状态机

### 5.1 状态流转图

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

### 5.2 门店端可执行操作

| 当前状态 | 可执行操作 |
|---------|-----------|
| 草稿(1) | 编辑、添加/删除明细、提交、取消、删除 |
| 待支付(2) | 支付、取消 |
| 需门店确认(5) | 确认、补充信息 |
| 待补款(6) | 补款 |
| 其他状态 | 仅查看 |

### 5.3 后台可执行操作

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

## 六、错误码速查表

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
| 4103 | 余额不足 | 余额支付时可用余额不够 |
| 4104 | 不支持混合支付 | 订单尝试混合支付方式 |
| 4105 | 储值回调已处理 | 储值支付回调重复通知 |
| 4106 | 资金账户已冻结 | 账户冻结后发起余额支付 |
| 5004 | 库存并发冲突 | 分布式锁冲突 |

---

## 七、附录

### 7.1 编号生成规则

| 编号类型 | 格式 | 示例 |
|---------|------|------|
| 订单号 | SS-{日期}-{门店编号}-{4位流水号} | SS-20260817-HN001-0008 |
| 窗帘编号 | {订单号}-C{2位序号} | SS-20260817-HN001-0008-C03 |
| 支付单号 | PAY{日期}{6位流水号} | PAY20260817100001 |
| 售后单号 | AS{日期}{3位流水号} | AS20260817001 |
| 发票申请号 | INV{日期}{3位流水号} | INV20260817001 |
| 储值单号 | RCH{日期}{6位流水号} | RCH20260817100001 |
| 资金流水号 | TXN{日期}{6位流水号} | TXN20260817100001 |

### 7.2 金额计算汇总

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

### 7.3 并发安全措施

1. **库存操作**：Redis 分布式锁（key: `lock:inventory:{store_id}:{kit_sku}`） + 数据库事务
2. **支付创建**：数据库唯一索引 `uk_payment_order_id` + Redis 幂等锁
3. **支付回调**：通过 `payment_no + transaction_id` 去重，状态机校验防止重复处理
4. **订单提交**：乐观锁（version 字段或状态前置校验）
5. **价格锁定**：提交时写入 `price_locked_at` 和 `price_locked_until`，审核和生产前校验锁定有效性

### 7.4 Redis Key 规划

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
| `lock:balance:pay:{account_id}` | 余额支付扣款锁 | 30s |
| `lock:recharge:{recharge_no}` | 储值回调处理锁 | 30s |
| `cache:balance:{account_id}` | 账户余额缓存 | 5min |

---

📋 交付物：API 接口文档  
📁 文件路径：`shishang-order-system/docs/api.md`  
📝 覆盖范围：门店端 12 个模块 + 后台 11 个模块 + 回调模块，共计约 120+ 个接口  
🔗 依赖说明：数据库设计文档 v1.2、PRD v3.2、开发规范 v1  
⚠️ 注意事项：
1. 所有金额字段单位为"分"（BIGINT），后端必须重新计算，不信任前端数据
2. 订单操作必须校验状态机流转规则
3. 支付和库存操作必须保证幂等性和并发安全
4. 价格锁定和支付超时均为 30 天
5. 管理员取消生产中订单需记录完整审计信息
6. 余额支付、储值回调和退款必须幂等
7. 资金流水不可修改、不可物理删除，冲正通过反向流水完成
8. 储值账户归属于客户主体（门店/合伙人），不归属于手机号
