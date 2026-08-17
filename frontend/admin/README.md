# 世尚智能悬浮卷帘门店订货系统 - 后台管理端项目脚手架

## 项目概述

基于 Vue 3 + TypeScript + Vite + Element Plus 搭建的后台管理系统，采用 vue-pure-admin 设计模式，完整支持 RBAC 权限模型。

## 技术栈

- **Vue 3.4+** - Composition API + `<script setup>`
- **TypeScript 5.x** - 严格模式（禁 any）
- **Vite 5.x** - 构建工具
- **Element Plus** - PC端 UI 组件库
- **Pinia** - 状态管理
- **Vue Router 4** - 路由管理
- **pnpm** - 包管理器
- **Axios** - HTTP 请求
- **@vueuse/core** - 实用工具集
- **@wangeditor/editor** - 富文本编辑器

## 项目结构

```
shishang-order-system/frontend/admin/
├── package.json                    # 依赖声明
├── vite.config.ts                  # Vite 配置（Element Plus 按需引入、路径别名、代理）
├── tsconfig.json                   # TypeScript 严格模式配置
├── tsconfig.node.json              # Node 环境 TS 配置
├── index.html                      # HTML 入口
├── .env.development                # 开发环境变量
├── .env.production                 # 生产环境变量
├── env.d.ts                        # 环境类型声明
├── public/
│   └── vite.svg                    # 网站图标
└── src/
    ├── main.ts                     # 应用入口
    ├── App.vue                     # 根组件
    │
    ├── api/                        # API 请求模块（12个文件）
    │   ├── index.ts                # Axios 实例 + 拦截器
    │   ├── auth.ts                 # 管理员登录/登出/权限
    │   ├── order.ts                # 订单管理 API
    │   ├── audit.ts                # 技术审核 API
    │   ├── fabric.ts               # 面料管理 API
    │   ├── stock.ts                # 库存管理 API
    │   ├── customer.ts             # 客户管理 API
    │   ├── production.ts           # 生产单 API
    │   ├── finance.ts              # 财务管理 API
    │   ├── afterSale.ts            # 售后管理 API
    │   ├── invoice.ts              # 发票管理 API
    │   └── system.ts               # 系统管理（RBAC）API
    │
    ├── assets/styles/              # 样式资源（3个文件）
    │   ├── variables.css           # CSS 变量（品牌色 Token）
    │   ├── element-override.css    # Element Plus 主题覆盖
    │   └── global.css              # 全局样式
    │
    ├── components/                 # 通用组件（5个文件）
    │   ├── Breadcrumb.vue          # 面包屑导航
    │   ├── SearchForm.vue          # 通用搜索表单
    │   ├── TablePagination.vue     # 通用分页组件
    │   ├── StatusTag.vue           # 状态标签组件
    │   └── ConfirmDialog.vue       # 确认对话框
    │
    ├── composables/                # 组合式函数（4个文件）
    │   ├── useAuth.ts              # 认证相关逻辑
    │   ├── useTable.ts             # 表格 CRUD 通用逻辑
    │   ├── usePermission.ts        # 按钮级权限判断
    │   └── usePagination.ts        # 分页逻辑
    │
    ├── layouts/                    # 布局组件（3个文件）
    │   ├── DefaultLayout.vue       # 默认布局（侧边栏+顶部栏+内容区）
    │   ├── Sidebar.vue             # 左侧可折叠菜单
    │   └── Header.vue              # 顶部栏（面包屑+用户信息）
    │
    ├── router/                     # 路由配置（10个文件）
    │   ├── index.ts                # 主路由 + 守卫
    │   └── modules/                # 路由模块
    │       ├── order.ts            # 订单管理路由
    │       ├── fabric.ts           # 面料管理路由
    │       ├── stock.ts            # 库存管理路由
    │       ├── customer.ts         # 客户管理路由
    │       ├── production.ts       # 生产单管理路由
    │       ├── finance.ts          # 财务管理路由
    │       ├── after-sale.ts       # 售后管理路由
    │       └── system.ts           # 系统管理路由
    │
    ├── stores/                     # Pinia 状态管理（3个文件）
    │   ├── auth.ts                 # 管理员登录态、权限列表
    │   ├── permission.ts           # 动态路由/菜单权限
    │   └── app.ts                  # 全局设置（侧边栏折叠等）
    │
    ├── types/                      # TypeScript 类型定义（8个文件）
    │   ├── api.ts                  # API 统一返回类型
    │   ├── common.ts               # 通用枚举（订单状态、支付状态等）
    │   ├── order.ts                # 订单相关类型
    │   ├── fabric.ts               # 面料相关类型
    │   ├── stock.ts                # 库存相关类型
    │   ├── customer.ts             # 客户相关类型
    │   ├── production.ts           # 生产单相关类型
    │   └── admin.ts                # 管理员/RBAC 类型
    │
    ├── utils/                      # 工具函数（4个文件）
    │   ├── storage.ts              # 本地存储封装
    │   ├── format.ts               # 格式化函数（日期、金额、手机号等）
    │   ├── validator.ts            # 表单验证规则
    │   └── permission.ts           # 权限判断工具函数
    │
    └── views/                      # 页面视图（20个文件）
        ├── login/
        │   └── LoginPage.vue       # 登录页
        ├── dashboard/
        │   └── DashboardPage.vue   # 工作台/仪表盘
        ├── order/
        │   ├── OrderList.vue       # 订单列表
        │   └── OrderDetail.vue     # 订单详情
        ├── audit/
        │   └── AuditWorkbench.vue  # 技术审核工作台
        ├── fabric/
        │   ├── FabricList.vue      # 面料列表
        │   ├── FabricForm.vue      # 面料新增/编辑
        │   └── FabricImport.vue    # 批量导入
        ├── stock/
        │   └── StockList.vue       # 库存管理
        ├── customer/
        │   ├── CustomerList.vue    # 客户列表
        │   └── CustomerLevel.vue   # 等级管理
        ├── production/
        │   └── ProductionList.vue  # 生产单管理
        ├── finance/
        │   └── FinanceList.vue     # 财务管理
        ├── after-sale/
        │   └── AfterSaleList.vue   # 售后管理
        ├── invoice/
        │   └── InvoiceList.vue     # 发票管理
        └── system/
            ├── AdminList.vue       # 管理员管理
            ├── RoleList.vue        # 角色管理
            └── PermissionList.vue  # 权限管理

总计：80 个文件
```

## 核心实现特性

### 1. Axios 封装 (src/api/index.ts)

- ✅ baseURL 从环境变量读取
- ✅ 请求拦截器：自动注入 `Authorization: Bearer {token}`
- ✅ 响应拦截器：
  - `code === 0` → 返回 data
  - `code === 2001` → 跳登录页
  - `code === 3001/3002` → 提示无权限
  - 其他非0 → `ElMessage.error`
- ✅ 统一返回类型 `ApiResponse<T>`
- ✅ 封装 GET/POST/PUT/DELETE 方法

### 2. 路由配置 (src/router/index.ts)

- ✅ 登录页无布局（独立页面）
- ✅ 其他页面使用 DefaultLayout（左侧菜单 + 顶部 Header）
- ✅ 路由守卫：未登录 → `/login`；已登录但无权限 → 403
- ✅ 动态路由模块：支持按权限过滤
- ✅ 8 个业务模块路由：订单、面料、库存、客户、生产单、财务、售后、系统

### 3. 布局系统 (src/layouts/)

- ✅ **DefaultLayout.vue**：左侧菜单 + 顶部 Header + 内容区
- ✅ **Sidebar.vue**：左侧可折叠侧边栏，菜单项含图标
- ✅ **Header.vue**：顶部栏显示面包屑 + 管理员信息 + 退出按钮
- ✅ 路由切换过渡动画（fade-transform）

### 4. CSS 变量系统 (src/assets/styles/variables.css)

品牌色 Token 完整定义：

```css
:root {
  /* 品牌主色 Slate Indigo */
  --color-primary-50: #f4f6fa;
  --color-primary-500: #56638f;  /* 主色 */
  --color-primary-900: #1a1f33;
  
  /* 辅助色 Warm Sand */
  --color-accent-50: #fdf9f3;
  --color-accent-500: #c49338;   /* 辅助主色 */
  
  /* 后台布局 */
  --sidebar-width: 220px;
  --sidebar-collapsed-width: 64px;
  --header-height: 56px;
}
```

### 5. Element Plus 主题覆盖 (src/assets/styles/element-override.css)

将 Element Plus 的 `--el-color-primary` 覆盖为品牌色 `#56638F`，统一视觉风格。

### 6. TypeScript 类型定义

对齐数据库 25 张表（含 4 张 RBAC 表），定义核心类型：

- ✅ **Admin, AdminRole, AdminPermission** - RBAC 权限模型
- ✅ **订单完整类型** - 含技术审核状态、生产状态、发货状态
- ✅ **面料管理类型** - 含供应商映射
- ✅ **通用枚举** - 订单状态（18种）、支付状态、审核状态等

### 7. 权限模型 (src/stores/permission.ts)

- ✅ 基于 RBAC 的动态路由
- ✅ `usePermission` composable 支持按钮级权限判断
- ✅ `hasPermission(permissions, code)` - 判断是否拥有指定权限
- ✅ `hasAnyPermission(permissions, codes)` - 判断是否拥有任意一个权限

### 8. 通用组件

- ✅ **SearchForm** - 通用搜索表单，支持插槽
- ✅ **TablePagination** - 通用分页组件，双向绑定
- ✅ **StatusTag** - 状态标签，根据状态自动映射颜色
- ✅ **ConfirmDialog** - 确认对话框，支持 warning/danger 类型
- ✅ **Breadcrumb** - 面包屑导航，自动根据路由生成

### 9. 组合式函数

- ✅ **useAuth** - 认证相关逻辑（登录、登出、权限获取）
- ✅ **useTable** - 表格 CRUD 通用逻辑（加载、搜索、重置、分页、删除确认）
- ✅ **usePermission** - 按钮级权限判断
- ✅ **usePagination** - 分页逻辑封装

### 10. 工具函数

- ✅ **storage.ts** - Token 管理、用户信息存储
- ✅ **format.ts** - 日期、金额、手机号、面积格式化
- ✅ **validator.ts** - 手机号、邮箱验证规则
- ✅ **permission.ts** - 权限判断工具

## 开发规范

### TypeScript

- ✅ 严格模式（`strict: true`）
- ✅ 禁止 `any`（`noImplicitAny: true`）
- ✅ 所有公共方法必须有 JSDoc 注释
- ✅ 类型定义完整，对齐数据库表结构

### Vue 组件

- ✅ 使用 `<script setup lang="ts">` 语法
- ✅ 组件命名 PascalCase（如 `OrderList.vue`）
- ✅ CSS 使用 BEM 或 kebab-case
- ✅ 文件命名 kebab-case（如 `use-table.ts`）

### 代码风格

- ✅ 使用 Composition API
- ✅ 响应式数据使用 `ref` / `reactive`
- ✅ 计算属性使用 `computed`
- ✅ 生命周期钩子使用 `onMounted` / `onUnmounted` 等

## 启动项目

```bash
# 进入项目目录
cd /Coze/Drive/链极网络工作台/shishang-order-system/frontend/admin

# 安装依赖（使用 pnpm）
pnpm install

# 启动开发服务器
pnpm dev

# 构建生产版本
pnpm build

# 预览生产构建
pnpm preview
```

## 环境变量

### 开发环境 (.env.development)

```env
VITE_API_BASE_URL=/api/v1
VITE_APP_TITLE=世尚智能悬浮卷帘 - 后台管理
```

### 生产环境 (.env.production)

```env
VITE_API_BASE_URL=https://api.shishang.com/api/v1
VITE_APP_TITLE=世尚智能悬浮卷帘 - 后台管理
```

## API 代理配置

Vite 开发服务器已配置代理：

```typescript
proxy: {
  '/api': {
    target: 'http://localhost:8000',
    changeOrigin: true
  }
}
```

所有 `/api` 开头的请求将自动代理到后端服务。

## 品牌设计规范

### 色彩系统

- **主色**：Slate Indigo（#56638F）- 低饱和度蓝灰色系
- **辅助色**：Warm Sand（#C49338）- 低饱和暖色
- **中性色**：完整的 10 级灰度系统
- **功能色**：成功（#059669）、警告（#D97706）、错误（#DC2626）、信息（#2563EB）

### 设计原则

- 国际范：瑞士国际主义排版风格，大量留白
- 简约克制：无多余装饰，让内容成为焦点
- 安静容器：UI 退后一步，以低饱和中性色"托举"产品内容

## 已实现功能模块

### ✅ 已完成

1. **项目脚手架** - 完整的 Vue 3 + TypeScript + Vite 项目结构
2. **路由系统** - 8 个业务模块路由 + 动态路由 + 路由守卫
3. **布局系统** - 左侧菜单 + 顶部栏 + 内容区
4. **认证系统** - 登录页 + Token 管理 + 权限控制
5. **API 封装** - Axios 实例 + 拦截器 + 统一错误处理
6. **类型定义** - 完整的 TypeScript 类型（对齐数据库 25 张表）
7. **通用组件** - 搜索表单、分页、状态标签、确认对话框、面包屑
8. **组合式函数** - useTable、useAuth、usePermission、usePagination
9. **样式系统** - CSS 变量 + Element Plus 主题覆盖
10. **工具函数** - 存储、格式化、验证、权限判断

### 📋 页面骨架（20个页面）

- ✅ 登录页
- ✅ 工作台/仪表盘
- ✅ 订单列表 + 订单详情
- ✅ 技术审核工作台
- ✅ 面料列表 + 面料编辑 + 批量导入
- ✅ 库存管理
- ✅ 客户列表 + 等级管理
- ✅ 生产单管理
- ✅ 财务管理
- ✅ 售后管理
- ✅ 发票管理
- ✅ 管理员管理 + 角色管理 + 权限管理

## 后续开发建议

1. **完善页面功能** - 当前页面为骨架，需补充完整的表单、弹窗、交互逻辑
2. **对接后端 API** - 确保 API 路径和返回格式与后端一致
3. **权限细化** - 根据实际 RBAC 权限配置，细化按钮级权限控制
4. **富文本编辑器** - 已引入 @wangeditor/editor，可在需要的页面中使用
5. **图表组件** - 如需数据可视化，建议引入 ECharts
6. **国际化** - 如需多语言支持，可引入 vue-i18n
7. **单元测试** - 建议为核心组件和工具函数编写单元测试

## 文件清单

总计创建 **80 个文件**，包括：

- 配置文件：8 个
- API 模块：12 个
- 样式文件：3 个
- 通用组件：5 个
- 组合式函数：4 个
- 布局组件：3 个
- 路由配置：10 个
- 状态管理：3 个
- 类型定义：8 个
- 工具函数：4 个
- 页面视图：20 个

---

**项目已就绪，可以开始开发！**
