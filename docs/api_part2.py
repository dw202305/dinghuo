content = r'''

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
'''

with open('/Coze/Drive/链极网络工作台/shishang-order-system/docs/api_part2.md', 'w') as f:
    f.write(content)

print("Part 2 written successfully")
