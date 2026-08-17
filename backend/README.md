# 世尚门店订货系统 - 后端

> 链极网络 · 世尚项目  
> 技术栈：PHP 8.1+ / ThinkPHP 8 / MySQL 8.0+ / Redis

## 目录结构

```
backend/
├── app/
│   ├── api/                    # API 应用
│   │   ├── controller/         # 控制器
│   │   │   ├── BaseController.php    # 基础控制器
│   │   │   ├── AuthController.php    # 认证
│   │   │   └── OrderController.php   # 订单（门店端）
│   │   ├── validate/           # 参数验证器
│   │   │   └── OrderValidate.php
│   │   └── route/              # 路由定义
│   │       └── app.php
│   ├── common/                 # 公共模块
│   │   ├── ApiResponse.php     # 统一响应 Trait
│   │   ├── enum/               # 枚举类
│   │   │   ├── OrderStatus.php
│   │   │   └── AccountRole.php
│   │   ├── middleware/         # 中间件
│   │   │   ├── CrossDomain.php
│   │   │   ├── AuthMiddleware.php
│   │   │   └── AdminAuthMiddleware.php
│   │   ├── model/              # 数据模型
│   │   │   ├── BaseModel.php
│   │   │   ├── Account.php
│   │   │   ├── Store.php
│   │   │   ├── Order.php
│   │   │   └── Fabric.php
│   │   └── service/            # 业务逻辑层
│   │       ├── BaseService.php
│   │       ├── AuthService.php
│   │       └── OrderService.php
│   └── ...
├── config/                     # 配置
│   ├── app.php
│   ├── database.php
│   ├── cache.php
│   └── middleware.php
├── public/
│   └── index.php               # 入口文件
├── .env                        # 环境变量
└── composer.json
```

## 快速开始

### 1. 启动环境

```bash
cd docker
docker-compose up -d
```

### 2. 安装依赖

```bash
# 进入 PHP 容器
docker exec -it shishang-php bash

# 安装 Composer 依赖
composer install
```

### 3. 数据库初始化

MySQL 启动时自动执行 `docker/mysql/init.sql`，创建 21 张核心表。

### 4. 访问接口

- 本地开发：`http://localhost`
- API 前缀：`/api/`

## API 规范

### 统一返回格式
```json
{
  "code": 0,
  "message": "success",
  "data": {}
}
```

### 错误码
| 范围 | 含义 |
|------|------|
| 0    | 成功 |
| 1xxx | 参数错误 |
| 2xxx | 认证错误 |
| 3xxx | 权限错误 |
| 5xxx | 服务器错误 |

### 认证方式
JWT Bearer Token，放在 Header：`Authorization: Bearer {token}`

### 分页参数
- 请求：`page`（页码）+ `page_size`（每页条数）
- 返回：`list` + `total` + `page` + `page_size`

## 待开发
- [ ] 完整 Model（剩余 17 张表）
- [ ] 完整 Controller + Validate
- [ ] 微信支付对接
- [ ] 微信登录对接
- [ ] OSS 上传
- [ ] 后台管理端 Controller
