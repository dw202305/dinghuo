content = r'''# 世尚门店订货系统 - API 接口文档

> 版本：v1.0  
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
| 金额单位 | 元，精度 2 位小数（DECIMAL） |
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
| pay_channel | int | 是 | 支付渠道：1微信 2支付宝 |
| pay_method | string | 是 | 支付方式：JSAPI(小程序/公众号) / H5 / NATIVE |

- 响应示例（微信支付）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "payment_no": "PAY20260817100001",
    "pay_amount": "2837.10",
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
    "pay_amount": "2837.10",
    "pay_channel": 2,
    "pay_channel_text": "支付宝",
    "alipay_params": {
      "order_string": "alipay_sdk=xxx&..."
    },
    "expire_seconds": 1800
  }
}
```

> **⚠️ 幂等性说明**：同一 order_id 在未支付完成时重复调用创建支付，返回已有的 payment_no 和新的支付参数（因 prepay_id 等时效参数需刷新）。

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1004 | 订单不存在 |
| 1006 | 订单状态不允许支付（非待支付状态） |
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
    "paid_amount": "2837.10",
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
'''

with open('/Coze/Drive/链极网络工作台/shishang-order-system/docs/api_part1.md', 'w') as f:
    f.write(content)

print("Part 1 written successfully")
