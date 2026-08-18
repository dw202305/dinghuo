# 世尚门店订货系统 - 部署文档

## 服务器要求

| 项目 | 最低 | 当前配置 |
|---|---|---|
| CPU | 2 核 | 2 核 ✅ |
| 内存 | 4 GB | 4 GB ✅ |
| 硬盘 | 50 GB SSD | 70 GB ✅ |
| 带宽 | 3 Mbps | 5 Mbps ✅ |
| 系统 | Ubuntu 22.04 LTS | - |

## 部署架构

```
Nginx (64M) → PHP-FPM (512M) → ThinkPHP 8
         ↓              ↓
    MySQL 8.0 (1G)   Redis 7 (256M)
```

总内存分配约 1.8G，剩余 2.2G 留给系统 + Docker 运行时。

## 目录结构

```
shishang-order-system/
├── deploy/                    # 本目录
│   ├── docker-compose.yml     # 容器编排
│   ├── init-server.sh         # 服务器初始化脚本
│   ├── mysql/
│   │   ├── my.cnf            # MySQL 优化配置
│   │   └── init.sql          # 建表 SQL（25 张表）
│   ├── php/
│   │   ├── Dockerfile        # PHP 镜像构建
│   │   └── php.ini           # PHP 优化配置
│   ├── nginx/
│   │   └── default.conf      # Nginx 站点配置
│   └── redis/
│       └── redis.conf        # Redis 配置（200MB 限制）
├── backend/                   # ThinkPHP 8 后端
├── frontend/
│   ├── store/                # uni-app 门店端
│   └── admin/                # vue-pure-admin 后台端
└── docs/                      # 文档
    ├── database.md           # 数据库设计
    ├── api.md                # API 接口文档
    └── test-plan.md          # 测试计划
```

## 快速部署

### 1. 重装系统
- 选择 **Ubuntu 22.04 LTS**
- 安全组开放：22 / 80 / 443

### 2. 初始化服务器
```bash
# 上传 init-server.sh 到服务器后执行
chmod +x init-server.sh
./init-server.sh

# 重新登录 shell（让 docker 用户组生效）
newgrp docker
```

### 3. 部署应用
```bash
# 进入项目目录
cd /opt/shishang-order-system

# 复制后端 init.sql 到 deploy/mysql/
cp backend/docker/mysql/init.sql deploy/mysql/init.sql

# 启动所有服务
docker compose up -d

# 检查状态
docker compose ps
docker compose logs -f
```

### 4. 后端初始化
```bash
# 进入 PHP 容器
docker exec -it ss-php bash

# 安装依赖
composer install

# 生成 .env
cp .env.example .env
php think key:generate
```

## 域名配置（示例）

| 域名 | 用途 | 指向 |
|---|---|---|
| api.shishang.com | 后端 API | Nginx → PHP |
| admin.shishang.com | 后台管理 | Nginx → 静态文件 |
| m.shishang.com | 门店端 H5 | Nginx → 静态文件 |

## 凭证配置（批次0已轮换）

| 服务 | 用户名 | 凭证来源 |
|---|---|---|
| MySQL Root | root | 环境变量 `MYSQL_ROOT_PASSWORD`（compose 必需，无内置默认值） |
| MySQL App | shishang | `backend/.env` `[DATABASE] PASSWORD`（compose 默认值已同步新口令） |
| Redis | - | `backend/.env` `[REDIS] PASSWORD` 与 `deploy/redis/redis.conf`（已同步新口令） |

> 凭证已在批次0轮换，以 `backend/.env` 为准；请勿在文档/脚本中明文回显口令。

## 常用命令

```bash
# 查看服务状态
docker compose ps

# 查看日志
docker compose logs -f php
docker compose logs -f mysql

# 重启服务
docker compose restart php

# 进入 PHP 容器
docker exec -it ss-php bash

# 数据库备份
docker exec ss-mysql mysqldump -u shishang -p shishang_order > backup.sql

# 数据库恢复
docker exec -i ss-mysql mysql -u shishang -p shishang_order < backup.sql
```

## 注意事项

1. **内存紧张时**：优先保证 MySQL，可以停掉不用的服务
2. **前端编译**：在本地电脑编译好再上传 dist，服务器不编译前端
3. **HTTPS**：上线前用 Let's Encrypt 配置 SSL 证书
4. **安全**：MySQL 和 Redis 已绑定 127.0.0.1，不对外暴露
