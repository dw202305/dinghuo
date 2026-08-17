# 世尚门店订货系统 - API 接口文档（后台管理端）

> 版本：v1.1  
> 更新日期：2026-08-17  
> 技术栈：PHP 8.1+ / ThinkPHP 8 / MySQL 8.0+ / Redis  
> 后台前端：Vue 3 + Element Plus  
> 全局规范（响应格式、错误码、金额单位等）详见 `api_part2.md` 第一章

---

## 三、后台管理端接口

### 3.1 认证模块（/api/v1/admin/auth/*）

---

#### 管理员登录
- 路径：`POST /api/v1/admin/auth/login`
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
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 2001 | 用户名或密码错误 |
| 2003 | 账号已停用 |

---

#### 退出登录
- 路径：`POST /api/v1/admin/auth/logout`
- 说明：管理员退出登录
- 权限：后台已登录管理员

---

#### 获取当前管理员信息
- 路径：`GET /api/v1/admin/auth/profile`
- 说明：获取当前登录管理员的完整信息和权限列表
- 权限：后台已登录管理员

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
    "role_id": 1,
    "role_name": "超级管理员",
    "permissions": ["order:view", "order:audit", "..."],
    "last_login_at": "2026-08-17 09:00:00",
    "last_login_ip": "120.xxx.xxx.xxx"
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 修改密码
- 路径：`PUT /api/v1/admin/auth/password`
- 说明：修改当前管理员密码
- 权限：后台已登录管理员
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| old_password | string | 是 | 原密码 |
| new_password | string | 是 | 新密码，8-20位，含大小写字母和数字 |
| confirm_password | string | 是 | 确认新密码 |

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 2001 | 原密码错误 |
| 1001 | 两次密码输入不一致 / 密码强度不足 |

---

### 3.2 门店管理（/api/v1/admin/stores）

---

#### 门店列表
- 路径：`GET /api/v1/admin/stores`
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
        "customer_level": 1,
        "customer_level_text": "认证合作门店",
        "channel_mode": 1,
        "partner_name": "湖南城市合伙人",
        "primary_sales_name": "李经理",
        "province": "湖南省",
        "city": "长沙市",
        "contact_phone": "0731-88888888",
        "kit_available": 18,
        "status": 1,
        "status_text": "正常",
        "created_at": "2025-01-01 00:00:00"
      }
    ],
    "total": 128,
    "page": 1,
    "page_size": 20
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 门店详情
- 路径：`GET /api/v1/admin/stores/:id`
- 说明：获取门店完整信息，包含联系人、账号、归属关系等
- 权限：后台-门店管理

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
    "customer_level": 1,
    "channel_mode": 1,
    "province": "湖南省",
    "city": "长沙市",
    "status": 1,
    "contacts": [
      {
        "contact_id": 1,
        "contact_name": "张三",
        "phone": "13888888888",
        "contact_type": 1,
        "is_primary": 1
      }
    ],
    "accounts": [
      {
        "account_id": 1,
        "phone": "138****8888",
        "real_name": "张三",
        "account_role": 1,
        "account_role_text": "门店管理员"
      }
    ]
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 新增门店
- 路径：`POST /api/v1/admin/stores`
- 说明：新增门店，同时创建门店主体和默认联系人
- 权限：后台-门店管理-新增
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_no | string | 是 | 门店编号 |
| store_name | string | 是 | 门店名称 |
| business_entity | string | 否 | 经营主体名称 |
| credit_code | string | 否 | 统一社会信用代码 |
| store_type | int | 是 | 门店类型：1-4 |
| customer_level | int | 是 | 客户等级：1-5 |
| channel_mode | int | 是 | 渠道模式：1合伙人 2直营 |
| partner_id | int | 否 | 所属合伙人ID（渠道模式=1时必填） |
| primary_sales_id | int | 是 | 主归属销售ID |
| secondary_sales_id | int | 否 | 协同销售ID |
| province | string | 否 | 省 |
| city | string | 否 | 市 |
| district | string | 否 | 区 |
| address | string | 否 | 详细地址 |
| contact_phone | string | 否 | 门店联系电话 |
| primary_contact_name | string | 是 | 主联系人姓名 |
| primary_contact_phone | string | 是 | 主联系人手机号 |

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1001 | 门店编号已存在 |
| 3002 | 城市合伙人不存在 / 销售人员不存在 |

---

#### 更新门店
- 路径：`PUT /api/v1/admin/stores/:id`
- 说明：更新门店信息
- 权限：后台-门店管理-编辑
- 请求参数：同新增门店（均可选，store_no 不可修改）

---

#### 停用/启用门店
- 路径：`PUT /api/v1/admin/stores/:id/status`
- 说明：停用或启用门店。停用时门店下所有账号无法登录。
- 权限：后台-门店管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | int | 是 | 目标状态：1正常 2停用 |

---

#### 管理门店联系人
- 路径：`POST /api/v1/admin/stores/:id/contacts`
- 说明：新增或更新门店联系人
- 权限：后台-门店管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| contact_id | int | 否 | 联系人ID（更新时必填） |
| contact_name | string | 是 | 姓名 |
| phone | string | 是 | 手机号 |
| wechat | string | 否 | 微信号 |
| position | string | 否 | 职务 |
| contact_type | int | 是 | 联系人类型：1负责人 2采购 3下单 4财务 5安装 6售后 7收货人 |
| is_primary | int | 否 | 是否主联系人 |
| receive_order_notify | int | 否 | 是否接收订单通知 |
| receive_finance_notify | int | 否 | 是否接收财务通知 |

---

#### 管理门店账号
- 路径：`POST /api/v1/admin/stores/:id/accounts`
- 说明：为门店创建或更新登录账号
- 权限：后台-门店管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| account_id | int | 否 | 账号ID（更新时必填） |
| phone | string | 是 | 登录手机号 |
| real_name | string | 否 | 姓名 |
| contact_id | int | 否 | 关联联系人ID |
| account_role | int | 是 | 账号角色：1管理员 2下单员 3财务 4安装售后 5只读 |
| password | string | 否 | 初始密码（创建时可选） |

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1001 | 该手机号已被注册 |

---

### 3.3 城市合伙人管理（/api/v1/admin/partners）

---

#### 合伙人列表
- 路径：`GET /api/v1/admin/partners`
- 说明：获取城市合伙人列表
- 权限：后台-合伙人管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索关键词 |
| primary_sales_id | int | 否 | 归属销售筛选 |
| status | int | 否 | 状态筛选 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

---

#### 合伙人详情
- 路径：`GET /api/v1/admin/partners/:id`
- 说明：获取合伙人完整信息
- 权限：后台-合伙人管理

---

#### 新增/更新合伙人
- 路径：`POST /api/v1/admin/partners`
- 说明：新增或更新城市合伙人信息
- 权限：后台-合伙人管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| partner_id | int | 否 | 合伙人ID（更新时必填） |
| partner_no | string | 是 | 合伙人编号 |
| business_entity | string | 是 | 企业名称 |
| credit_code | string | 否 | 统一社会信用代码 |
| primary_contact_name | string | 否 | 主联系人姓名 |
| primary_contact_phone | string | 否 | 主联系人手机号 |
| authorized_city | string | 否 | 授权城市 |
| authorized_region | string | 否 | 授权区域 |
| cooperation_stage | int | 否 | 合作阶段 |
| partner_level | int | 否 | 合伙人等级 |
| primary_sales_id | int | 是 | 主归属销售ID |
| secondary_sales_id | int | 否 | 协同销售ID |

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 1001 | 合伙人编号已存在 |

---

#### 查看下属门店
- 路径：`GET /api/v1/admin/partners/:id/stores`
- 说明：获取合伙人下属门店列表
- 权限：后台-合伙人管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
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
        "order_count_30d": 12,
        "order_amount_30d_cent": 3568000
      }
    ],
    "total": 15,
    "page": 1,
    "page_size": 20
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

### 3.4 商品管理（/api/v1/admin/products）

---

#### 面料列表
- 路径：`GET /api/v1/admin/products/fabrics`
- 说明：获取面料列表，后台可查看所有面料（含已下架）
- 权限：后台-商品管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索（编号/名称/色号/系列） |
| series | string | 否 | 系列筛选 |
| listing_status | int | 否 | 上架状态 |
| orderable | int | 否 | 允许订货 |
| stock_status | int | 否 | 库存状态 |
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
        "price_per_sqm_cent": 4000,
        "main_image": "https://oss.xxx.com/fabric/SS-F-20260101.jpg",
        "stock_status": 1,
        "listing_status": 1,
        "orderable": 1
      }
    ],
    "total": 320,
    "page": 1,
    "page_size": 20
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 面料详情
- 路径：`GET /api/v1/admin/products/fabrics/:id`
- 说明：获取面料详情，含供应商映射信息
- 权限：后台-商品管理

---

#### 面料新增/编辑
- 路径：`POST /api/v1/admin/products/fabrics`
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
| price_per_sqm_cent | int | 是 | 单价/㎡（分） |
| main_image | string | 否 | 主图URL |
| listing_status | int | 否 | 上架状态 |
| orderable | int | 否 | 允许订货 |

---

#### 面料批量导入
- 路径：`POST /api/v1/admin/products/fabrics/import`
- 说明：批量导入面料数据（Excel文件）
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
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 面料批量调价
- 路径：`POST /api/v1/admin/products/fabrics/batch-price`
- 说明：批量调整面料价格，生成新价格版本
- 权限：后台-商品管理-调价
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| fabric_ids | array | 是 | 面料ID列表 |
| adjust_type | string | 是 | 调价方式：fixed / percent |
| adjust_value | decimal | 是 | 调价值 |
| effective_date | string | 是 | 新价格生效日期 |
| reason | string | 是 | 调价原因 |

---

#### 面料批量上下架
- 路径：`POST /api/v1/admin/products/fabrics/batch-status`
- 说明：批量上架或下架面料
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| fabric_ids | array | 是 | 面料ID列表 |
| listing_status | int | 是 | 目标状态：1上架 0下架 |
| orderable | int | 否 | 允许订货 |

---

#### 轨道列表
- 路径：`GET /api/v1/admin/products/tracks`
- 说明：获取轨道列表
- 权限：后台-商品管理

---

#### 轨道新增/编辑
- 路径：`POST /api/v1/admin/products/tracks`
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
| price_per_meter_cent | int | 是 | 门店单价/米（分） |
| partner_price_cent | int | 否 | 合伙人价格（分） |
| enabled | int | 否 | 是否启用 |

---

#### 配件列表
- 路径：`GET /api/v1/admin/products/accessories`
- 说明：获取选装配件列表
- 权限：后台-商品管理

---

#### 配件新增/编辑
- 路径：`POST /api/v1/admin/products/accessories`
- 说明：新增或编辑选装配件
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 否 | 配件ID（编辑时必填） |
| sku | string | 是 | 配件SKU |
| name | string | 是 | 配件名称 |
| config_group | string | 是 | 配置组：power/remote/wall_control |
| option_type | int | 是 | 类型：1标准 2升级 3新增 |
| surcharge_cent | int | 是 | 门店加价（分） |
| partner_surcharge_cent | int | 否 | 合伙人加价（分） |
| required | int | 否 | 是否必选 |
| enabled | int | 否 | 是否启用 |

---

#### 套件列表
- 路径：`GET /api/v1/admin/products/kits`
- 说明：获取套件列表
- 权限：后台-商品管理

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
        "prices": { "1": 76000, "2": 66000 },
        "enabled": 1
      }
    ],
    "total": 1,
    "page": 1,
    "page_size": 20
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

> 注：`prices` 中的值单位为**分**，如 `76000` 表示 ¥760.00

---

#### 套件新增/编辑
- 路径：`POST /api/v1/admin/products/kits`
- 说明：新增或编辑套件
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 否 | 套件ID（编辑时必填） |
| sku | string | 是 | 套件SKU |
| name | string | 是 | 套件名称 |
| prices | object | 是 | 各等级价格（分），key为customer_level |
| enabled | int | 否 | 是否启用 |

---

#### 供应商列表
- 路径：`GET /api/v1/admin/products/suppliers`
- 说明：获取面料供应商列表
- 权限：后台-商品管理

---

#### 供应商新增/编辑
- 路径：`POST /api/v1/admin/products/suppliers`
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

---

#### 供应商面料映射管理
- 路径：`POST /api/v1/admin/products/suppliers/mapping`
- 说明：新增或编辑供应商面料编号映射
- 权限：后台-商品管理-编辑
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 否 | 映射ID（编辑时必填） |
| fabric_no | string | 是 | 世尚面料编号 |
| supplier_id | int | 是 | 供应商ID |
| supplier_fabric_no | string | 是 | 供应商原始面料编号 |
| supplier_color_desc | string | 否 | 供应商内部颜色描述 |
| purchase_price_cent | int | 否 | 供应商采购价格（分） |
| delivery_days | int | 否 | 交期（天） |
| is_default_supplier | int | 否 | 是否默认供应商 |
| is_backup_supplier | int | 否 | 是否备选供应商 |

---

### 3.5 订单管理（/api/v1/admin/orders）

---

#### 订单列表
- 路径：`GET /api/v1/admin/orders`
- 说明：获取全平台订单列表，支持多维度筛选
- 权限：后台-订单管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索（订单号/项目名称/终端客户） |
| order_status | int | 否 | 订单状态筛选 |
| payment_status | int | 否 | 支付状态筛选 |
| audit_status | int | 否 | 审核状态筛选 |
| transaction_type | int | 否 | 下单类型：1门店 2合伙人 |
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
        "store_name": "长沙旗舰店",
        "store_no": "HN001",
        "partner_name": "湖南城市合伙人",
        "primary_sales_name": "李经理",
        "project_name": "万科样板间",
        "item_count": 3,
        "total_amount_cent": 283710,
        "paid_amount_cent": 283710,
        "payment_status": 2,
        "payment_status_text": "已支付",
        "audit_status": 0,
        "audit_status_text": "未审核",
        "created_at": "2026-08-17 10:30:00",
        "paid_at": "2026-08-17 11:05:00"
      }
    ],
    "total": 200,
    "page": 1,
    "page_size": 20
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 订单详情
- 路径：`GET /api/v1/admin/orders/:id`
- 说明：获取订单完整信息，包含所有窗帘明细
- 权限：后台-订单管理

- 响应示例（金额以分为单位）：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1008,
    "order_no": "SS-20260817-HN001-0008",
    "order_status": 4,
    "order_status_text": "已支付待审核",
    "store_name": "长沙旗舰店",
    "store_no": "HN001",
    "total_amount_cent": 283710,
    "items": [
      {
        "id": 3001,
        "item_no": "SS-20260817-HN001-0008-C01",
        "install_position": "客厅",
        "width": "180.0",
        "height": "300.0",
        "track_amount_cent": 39600,
        "fabric_amount_cent": 21600,
        "accessory_amount_cent": 21000,
        "item_total_cent": 82200,
        "technical_status": 0,
        "technical_status_text": "待审核"
      }
    ]
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 3002 | 订单不存在 |

---

#### 技术审核
- 路径：`POST /api/v1/admin/orders/:id/audit`
- 说明：对已支付订单进行技术审核。审核结果影响后续状态流转。
- 权限：后台-订单管理-审核
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| audit_result | int | 是 | 审核结果：1通过 2需门店确认 3需补款 4无法生产 |
| overall_remark | string | 否 | 整单审核备注 |
| supplement_amount_cent | int | 否 | 需补款金额（分），audit_result=3时必填 |
| item_audits | array | 否 | 逐副审核结果 |
| item_audits[].item_id | int | 是 | 窗帘明细ID |
| item_audits[].technical_status | int | 是 | 技术状态 |
| item_audits[].remark | string | 否 | 单副审核备注 |

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
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 3002 | 订单不存在 |
| 4003 | 订单当前状态不允许审核 |

---

#### 更新生产状态
- 路径：`POST /api/v1/admin/orders/:id/production`
- 说明：更新订单或指定明细的生产状态
- 权限：后台-订单管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| production_status | int | 是 | 生产状态：1生产中 2质检中 3待发货 |
| item_ids | array | 否 | 指定更新的明细ID列表，不传则整单更新 |

---

#### 发货
- 路径：`POST /api/v1/admin/orders/:id/ship`
- 说明：订单发货，填写物流信息
- 权限：后台-订单管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| item_ids | array | 是 | 本次发货的明细ID列表 |
| carrier | string | 是 | 承运商名称 |
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
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 4003 | 订单当前状态不允许发货 |

---

#### 取消订单（管理员）
- 路径：`POST /api/v1/admin/orders/:id/cancel`
- 说明：管理员取消订单，需记录生产进度和退款信息
- 权限：后台-订单管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| cancel_reason | string | 是 | 取消原因 |
| production_progress | string | 否 | 当前生产进度描述 |
| material_cost_cent | int | 否 | 已产生材料费（分） |
| refund_amount_cent | int | 否 | 退款金额（分） |
| kit_return | int | 否 | 套件是否退回：0否 1是 |

---

#### 改价
- 路径：`POST /api/v1/admin/orders/:id/adjust-price`
- 说明：管理员对订单进行改价操作
- 权限：后台-订单管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| adjust_field | string | 是 | 调整字段：discount_amount / nonstandard_amount / item_total |
| adjust_value | decimal | 是 | 调整值 |
| reason | string | 是 | 改价原因 |
| item_id | int | 否 | 窗帘明细ID（adjust_field=item_total时必填） |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "old_total": "2837.10",
    "new_total": "2637.10"
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

### 3.6 预审管理（/api/v1/admin/orders，正在同步开发中）

---

#### 切换为预审模式
- 路径：`POST /api/v1/admin/orders/:id/audit/switch-to-pre`
- 说明：将订单从直接审核切换为预审模式
- 权限：后台-订单管理

---

#### 提交审核结果
- 路径：`POST /api/v1/admin/orders/:id/audit/result`
- 说明：提交预审的审核结果
- 权限：后台-订单管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| audit_result | int | 是 | 审核结果：1通过 2需门店确认 3需补款 4无法生产 |
| remark | string | 否 | 审核备注 |

---

#### 查看审核信息
- 路径：`GET /api/v1/admin/orders/:id/audit`
- 说明：查看订单的审核信息和历史记录
- 权限：后台-订单管理

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "order_id": 1008,
    "audit_status": 1,
    "audit_status_text": "审核通过",
    "audited_at": "2026-08-17 14:00:00",
    "auditor_name": "技术主管",
    "remark": "尺寸确认无误"
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 审核超时检查
- 路径：`GET /api/v1/admin/orders/:id/audit/timeout-check`
- 说明：检查订单是否超过审核时限
- 权限：后台-订单管理

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "order_id": 1008,
    "is_timeout": false,
    "submitted_at": "2026-08-17 11:05:00",
    "deadline_at": "2026-08-19 11:05:00",
    "remaining_hours": 42
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

### 3.7 库存管理（/api/v1/admin/inventories）

---

#### 门店库存查询
- 路径：`GET /api/v1/admin/inventories/stores`
- 说明：查询各门店套件库存
- 权限：后台-库存管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 否 | 门店ID筛选 |
| kit_sku | string | 否 | 套件SKU筛选 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

---

#### 库存调整
- 路径：`POST /api/v1/admin/inventories/adjust`
- 说明：人工调整门店库存
- 权限：后台-库存管理-调整
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 是 | 门店ID |
| kit_sku | string | 是 | 套件SKU |
| adjust_quantity | int | 是 | 调整数量（正数增加，负数减少） |
| reason | string | 是 | 调整原因 |

---

#### 库存流水
- 路径：`GET /api/v1/admin/inventories/logs`
- 说明：获取库存变化流水记录
- 权限：后台-库存管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| store_id | int | 否 | 门店ID筛选 |
| kit_sku | string | 否 | 套件SKU筛选 |
| log_type | int | 否 | 变化类型筛选 |
| start_date | string | 否 | 起始日期 |
| end_date | string | 否 | 截止日期 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

---

### 3.8 财务管理（/api/v1/admin/finance）

---

#### 支付记录查询
- 路径：`GET /api/v1/admin/finance/payments`
- 说明：查询支付记录列表
- 权限：后台-财务管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 否 | 搜索（支付单号/订单号/交易号） |
| pay_channel | int | 否 | 支付渠道：1微信 2支付宝 |
| pay_status | int | 否 | 支付状态 |
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
        "pay_amount_cent": 283710,
        "pay_status": 1,
        "pay_status_text": "支付成功",
        "paid_at": "2026-08-17 11:05:00"
      }
    ],
    "total": 156,
    "page": 1,
    "page_size": 20
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 退款处理
- 路径：`POST /api/v1/admin/finance/refunds`
- 说明：发起退款操作
- 权限：后台-财务管理-退款
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| payment_id | int | 是 | 支付记录ID |
| refund_amount_cent | int | 是 | 退款金额（分） |
| refund_reason | string | 是 | 退款原因 |
| kit_return | int | 否 | 套件是否退回：0否 1是 |

- 响应示例：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "refund_id": 1,
    "refund_amount_cent": 283710,
    "refund_status": "processing"
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

- 错误码：

| 错误码 | 说明 |
|--------|------|
| 4003 | 支付记录状态不允许退款 |
| 1001 | 退款金额超过可退金额 |

---

#### 对账导出
- 路径：`GET /api/v1/admin/finance/reconciliation/export`
- 说明：导出对账数据
- 权限：后台-财务管理
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
    "file_url": "https://oss.xxx.com/export/reconciliation_20260817.xlsx",
    "expire_at": "2026-08-17 18:00:00",
    "total_records": 156,
    "total_amount_cent": 58900000
  },
  "request_id": "req_6a7b8c9d0e1f"
}
```

---

#### 发票审核
- 路径：`POST /api/v1/admin/finance/invoices/review`
- 说明：审核门店提交的发票申请
- 权限：后台-财务管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| request_id | int | 是 | 发票申请ID |
| action | int | 是 | 操作：1通过 2驳回 |
| invoice_no | string | 否 | 发票号码（通过时必填） |
| invoice_code | string | 否 | 发票代码（通过时必填） |
| reject_reason | string | 否 | 驳回原因（驳回时必填） |

---

### 3.9 发票管理（/api/v1/admin/invoices）

---

#### 发票列表
- 路径：`GET /api/v1/admin/invoices`
- 说明：获取全平台发票申请列表
- 权限：后台-发票管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | int | 否 | 状态筛选：1待审核 2已审核待开票 3已开票 4已驳回 |
| store_id | int | 否 | 门店ID筛选 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

---

#### 发票详情
- 路径：`GET /api/v1/admin/invoices/:id`
- 说明：获取发票申请详情
- 权限：后台-发票管理

---

#### 发票审核
- 路径：`POST /api/v1/admin/invoices/:id/review`
- 说明：审核发票申请
- 权限：后台-发票管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| action | int | 是 | 操作：1通过 2驳回 |
| invoice_no | string | 否 | 发票号码 |
| invoice_code | string | 否 | 发票代码 |
| reject_reason | string | 否 | 驳回原因 |

---

#### 开具发票
- 路径：`POST /api/v1/admin/invoices/:id/issue`
- 说明：对已审核通过的发票申请开具发票
- 权限：后台-发票管理

---

### 3.10 售后管理（/api/v1/admin/after-sales）

---

#### 售后列表
- 路径：`GET /api/v1/admin/after-sales`
- 说明：获取全平台售后申请列表
- 权限：后台-售后管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | int | 否 | 状态筛选：1待处理 2处理中 3已完成 4已关闭 |
| store_id | int | 否 | 门店ID筛选 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

---

#### 售后详情
- 路径：`GET /api/v1/admin/after-sales/:id`
- 说明：获取售后申请详情
- 权限：后台-售后管理

---

#### 处理售后
- 路径：`POST /api/v1/admin/after-sales/:id/process`
- 说明：处理售后申请，填写诊断结果和处理方案
- 权限：后台-售后管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| diagnosis | string | 是 | 诊断结果 |
| responsibility | int | 是 | 责任归属：1产品 2物流 3安装 4人为 |
| solution | string | 是 | 处理方案 |

---

#### 关闭售后
- 路径：`POST /api/v1/admin/after-sales/:id/close`
- 说明：关闭售后申请
- 权限：后台-售后管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| close_reason | string | 是 | 关闭原因 |

---

### 3.11 系统管理（/api/v1/admin/system）

---

#### 管理员列表
- 路径：`GET /api/v1/admin/system/admins`
- 说明：获取系统管理员列表
- 权限：后台-系统管理

---

#### 管理员新增/编辑
- 路径：`POST /api/v1/admin/system/admins`
- 说明：新增或编辑管理员
- 权限：后台-系统管理

---

#### 管理员删除
- 路径：`DELETE /api/v1/admin/system/admins/:id`
- 说明：删除管理员（软删除）
- 权限：后台-系统管理

---

#### 角色列表
- 路径：`GET /api/v1/admin/system/roles`
- 说明：获取角色列表
- 权限：后台-系统管理

---

#### 角色新增/编辑
- 路径：`POST /api/v1/admin/system/roles`
- 说明：新增或编辑角色
- 权限：后台-系统管理

---

#### 角色删除
- 路径：`DELETE /api/v1/admin/system/roles/:id`
- 说明：删除角色
- 权限：后台-系统管理

---

#### 权限树
- 路径：`GET /api/v1/admin/system/permissions/tree`
- 说明：获取完整权限树结构
- 权限：后台-系统管理

---

#### 操作日志
- 路径：`GET /api/v1/admin/system/operation-logs`
- 说明：获取系统操作日志
- 权限：后台-系统管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| admin_id | int | 否 | 管理员ID筛选 |
| module | string | 否 | 模块筛选 |
| start_date | string | 否 | 起始日期 |
| end_date | string | 否 | 截止日期 |
| page | int | 否 | 页码 |
| page_size | int | 否 | 每页数量 |

---

#### 归属变更
- 路径：`POST /api/v1/admin/system/attributions/change`
- 说明：变更门店/合伙人的归属关系
- 权限：后台-系统管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| target_type | string | 是 | 目标类型：store / partner |
| target_id | int | 是 | 目标ID |
| new_primary_sales_id | int | 是 | 新主归属销售ID |
| new_secondary_sales_id | int | 否 | 新协同销售ID |
| reason | string | 是 | 变更原因 |

---

#### 销售转交
- 路径：`POST /api/v1/admin/system/sales/transfers`
- 说明：将销售人员的客户/订单转交给其他销售
- 权限：后台-系统管理
- 请求参数：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| from_sales_id | int | 是 | 原销售ID |
| to_sales_id | int | 是 | 目标销售ID |
| transfer_type | string | 是 | 转交类型：all全部 store指定门店 order指定订单 |
| target_ids | array | 否 | 指定目标ID列表 |
| reason | string | 是 | 转交原因 |
