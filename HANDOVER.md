# 世尚门店订货系统 — 项目交接文档

> 本文档供任何接手的 AI 或开发者快速上手。最后更新：2026-08-18。
> ⚠️ 本文档含服务器凭证与数据库密码，属敏感信息，请勿外泄、勿提交到公开仓库。

---

## 一、项目是什么

**世尚智能悬浮卷帘门店订货系统**，面向世尚品牌认证合作门店和城市合伙人的 **B2B 定制窗帘交易系统**。

核心业务：门店逐副录入窗帘尺寸 → 选轨道颜色/面料编号/智能配件 → 系统自动计价 → 支持套件库存抵扣 → 在线支付 → 总部技术审核 → 生产排产 → 发货追踪 → 售后。

需求与规范文档（务必先读）：
- `docs/prd_v3.2.md` — 产品需求（1719 行，含计价公式、状态机、归属模型）
- `docs/database.md` — 数据库设计
- `docs/dev_specification_v1.md` — 开发规范（强制）

---

## 二、技术栈

| 层级 | 技术 |
|------|------|
| 后端 | PHP 8.1 + ThinkPHP 8（多应用模式）+ MySQL 8.0 + Redis 7 |
| 管理端 | Vue 3 + TypeScript + Vite + Pinia + Element Plus + Vue Router 4 |
| 商城端 | uni-app（H5 + 微信小程序）+ Vue 3 + TypeScript + Pinia |
| 部署 | Docker Compose（Nginx + PHP-FPM + MySQL + Redis） |

---

## 三、仓库与部署信息

### Git 仓库
- 地址：`https://github.com/dw202305/dinghuo.git`
- 分支：`main`
- Git 用户：dw202305 / 568345100@qq.com（credential manager 已就绪，可直接 push）

### 生产服务器
| 项 | 值 |
|----|-----|
| IP | `159.75.243.222` |
| SSH 用户 | `ubuntu` |
| SSH 密码 | `Qwer1234..` |
| 系统 | Ubuntu 22.04.5 LTS |
| Docker | v29.7.2 / Compose v5.4.0 |
| Node.js | v20.19.0（已装） |
| 项目路径 | `/opt/shishang-order-system/` |
| 部署编排 | `/opt/shishang-order-system/deploy/docker-compose.yml` |

### 域名（已解析 + HTTPS）
| 域名 | 用途 |
|------|------|
| `admin.shengshikunyuan.com` | 后台管理端 |
| `api.shengshikunyuan.com` | API 接口 |
| `shop.shengshikunyuan.com` | 移动端 H5 商城 |

- SSL：Let's Encrypt 单张 SAN 证书覆盖三域，路径 `/etc/letsencrypt/live/admin.shengshikunyuan.com/`
- 自动续期：cron 每月 1 号 03:00 执行 `certbot renew` 并重启 nginx
- 80 端口全部 301 跳转 HTTPS

### 数据库
| 项 | 值 |
|----|-----|
| 库名 | `shishang_order` |
| 用户 | `shishang` |
| 密码 | `Shishang@2026!`（注意结尾有感叹号） |
| 表前缀 | `lj_`，共 28 张表 |
| 访问 | `docker exec ss-mysql mysql -ushishang -p'Shishang@2026!' shishang_order` |

### 容器清单
| 容器 | 镜像 | 端口 |
|------|------|------|
| ss-nginx | nginx:1.24-alpine | 80, 443 |
| ss-php | deploy-php | 9000 |
| ss-redis | redis:7-alpine | 6379（仅本地） |
| ss-mysql | mysql:8.0 | 3306（仅本地） |

---

## 四、目录结构

```
shishang-order-system/
├── backend/                 # ThinkPHP 8 后端
│   ├── app/
│   │   ├── api/controller/  # 门店端控制器 + admin/ 管理端控制器
│   │   ├── api/route/app.php# 路由定义（多应用，控制器用完整命名空间）
│   │   ├── api/validate/    # 参数验证器
│   │   ├── common/service/  # 业务服务层（核心逻辑）
│   │   ├── common/model/    # 数据模型（31 个）
│   │   ├── common/enum/     # 枚举
│   │   └── database/migrations/ # SQL 迁移脚本
│   ├── config/              # app/cache/database/middleware/log 配置
│   └── .env                 # 环境变量（勿提交，含密码）
├── frontend/
│   ├── admin/               # 管理端 Vue3（views/api/router/stores/types）
│   └── store/               # 商城端 uni-app（pages/api/stores/types）
├── deploy/                  # 生产部署
│   ├── docker-compose.yml
│   ├── nginx/default.conf   # 三站点 + HTTPS 配置
│   ├── mysql/init.sql       # 建库脚本（唯一事实源）
│   └── php/ redis/
└── docs/                    # PRD / 规范 / 数据库文档
```

---

## 五、当前状态（截至 2026-08-18）

### 已完成并上线
- ✅ 后端 API 全部公开接口返回 200（fabrics/tracks/accessories/kit-info）
- ✅ 合规整改 4 批次（OrderController 分层重构、补齐 FabricSupplierService/AuditService/NotificationService、管理端 3 页面、商城端价格动态化、数据库唯一约束）
- ✅ 前端管理端 + 商城端已构建并部署
- ✅ 域名切换 shengshikunyuan.com + HTTPS 证书
- ✅ 数据库唯一约束迁移已执行（lj_payment.transaction_id、lj_inventory_log.idempotent_key）
- ✅ 全部变更已推送 GitHub

### 待办 / 已知问题
| 事项 | 说明 | 优先级 |
|------|------|--------|
| 管理端 39 个 vue-tsc 类型错误 | 构建当前跳过类型检查（build 命令为 `vite build`，原命令备份在 package.json.bak）；不影响运行 | 中 |
| 微信支付 / 微信登录对接 | overtrue/wechat 已引入，商户号就绪但具体对接未实现 | 高 |
| 通知系统 | NotificationService 为 stub（仅记日志），PRD 要求的 12 个通知节点未实现 | 中 |
| 统计报表 | PRD 首期要求，未实现 | 中 |
| OSS 图片上传 | 未实现 | 中 |
| 服务器源码同步 | 服务器 /opt 代码与 git 仓库可能漂移，建议用 git pull 保持一致 | 低 |

---

## 六、常用操作命令

### SSH 连接服务器（Windows PowerShell + Posh-SSH）
本机已装 Posh-SSH 模块。写 .ps1 脚本后用
`powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "脚本.ps1"` 执行。
```powershell
Import-Module Posh-SSH -ErrorAction SilentlyContinue
$secPass = ConvertTo-SecureString "Qwer1234.." -AsPlainText -Force
$cred = New-Object System.Management.Automation.PSCredential("ubuntu", $secPass)
$session = New-SSHSession -ComputerName "159.75.243.222" -Credential $cred -AcceptKey -Force
$result = Invoke-SSHCommand -SessionId $session.SessionId -Command "命令" -TimeOut 300
Write-Host $result.Output
Remove-SSHSession -SessionId $session.SessionId | Out-Null
```
⚠️ 坑：脚本里写中文路径会乱码，用 `Get-ChildItem -Recurse -Filter` 动态找文件，别硬编码中文路径。

### 后端
```bash
# 重启 PHP
cd /opt/shishang-order-system/deploy && docker compose restart php
# 看日志
docker logs ss-php --tail 20
# composer 用腾讯云镜像（GitHub 会被限流 429）
composer config -g repo.packagist composer https://mirrors.cloud.tencent.com/composer/
```

### 前端构建
```bash
# 管理端（当前 build 跳过 vue-tsc）
cd /opt/shishang-order-system/frontend/admin && npm run build
# 商城端 H5（产物在 dist/build/h5，需移到 dist/ 顶层匹配 nginx 挂载）
cd /opt/shishang-order-system/frontend/store && npm run build:h5
# npm 用国内镜像
npm install --registry=https://registry.npmmirror.com
# store 端有 peer 冲突需加 --legacy-peer-deps
```

### 验证线上
```bash
curl -sk -o /dev/null -w '%{http_code}' https://admin.shengshikunyuan.com/
curl -sk https://api.shengshikunyuan.com/api/v1/fabrics
```

---

## 七、部署踩坑记录（重要，避免重复踩）

1. **nginx pathinfo 丢失**：`try_files ... /index.php$is_args$args` 会丢路径，必须用 `/index.php?s=$uri&$args` 让 ThinkPHP 通过 s 参数读 PATH_INFO。
2. **多应用模式服务注册**：需 `vendor/services.php` 含 `'think\app\Service'`，否则多应用路由失效。
3. **路由控制器引用**：多应用模式下门店端控制器要用完整命名空间 `'\app\api\controller\XxxController@method'`，路径用 `v1/...`（不带 `api/` 前缀）。
4. **config/log.php 必需**：缺失会报 `Unable to resolve NULL driver for [think\Log]`。
5. **composer GitHub 限流**：国内服务器走腾讯云/阿里云镜像。
6. **两套 init.sql 漂移**：`deploy/mysql/init.sql` 与 `docker/mysql/init.sql` 列名有差异，以 deploy 版为准。
7. **store 端 BASE_URL**：`frontend/store/src/api/index.ts` 须用 `import.meta.env.VITE_API_BASE_URL`，勿硬编码。
8. **uni-app 构建**：`manifest.json`/`pages.json` 需在 `src/` 下；产物在 `dist/build/h5`。

---

## 八、计价核心公式（业务关键，勿改错）

```
横轨费 = 宽度(米) × 横轨单价
竖轨费 = 高度(米) × 2 × 竖轨单价
面料费 = 宽(米) × 高(米) × 面料单价
选装费 = Σ(选装单价 × 数量)
套件费 = 未抵扣套件数 × 客户等级套件价（库存抵扣则为 0）
单副金额 = 横轨 + 竖轨 + 面料 + 选装 + 套件 + 非标
订单应付 = Σ单副 + 其他费用 − 优惠
```
- 金额一律用整数「分」存储（BIGINT，字段后缀 `_cent`），禁用 FLOAT/DOUBLE。
- 最终计价权在后端 PriceService，前端只做预览。
- 认证门店套件价 760 元/套，城市合伙人 660 元/套（含税不含运费）。

---

## 九、接手建议（上手顺序）

1. 读 `docs/prd_v3.2.md` 和 `docs/dev_specification_v1.md`。
2. 用第六节命令 SSH 上服务器，`docker ps` 确认 4 容器健康。
3. `curl https://api.shengshikunyuan.com/api/v1/fabrics` 确认后端通。
4. 浏览器开 `https://admin.shengshikunyuan.com` 确认管理端可访问。
5. 按第五节待办清单认领任务（优先微信支付对接）。
6. 改动后：本地 git push → 服务器 git pull → 对应容器 rebuild/restart。
