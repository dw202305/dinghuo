#!/bin/bash
# ============================================
# 世尚门店订货系统 - 服务器初始化脚本
# 适用于：Ubuntu 22.04 LTS / 2C4G / 70G SSD
# ============================================

set -e

echo "🚀 开始初始化世尚订货系统服务器..."

# 1. Swap 设置（2G，防止内存溢出）
echo "📦 配置 Swap..."
if [ ! -f /swapfile ]; then
    sudo fallocate -l 2G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
    echo "✅ Swap 已配置"
else
    echo "⏭️ Swap 已存在，跳过"
fi

# 2. 系统更新
echo "📦 更新系统..."
sudo apt update && sudo apt upgrade -y

# 3. 安装 Docker
echo "🐳 安装 Docker..."
if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com | sh
    sudo usermod -aG docker $USER
    echo "✅ Docker 已安装，请重新登录 shell 或执行: newgrp docker"
else
    echo "⏭️ Docker 已安装"
fi

# 4. 安装 Docker Compose 插件
echo "🐳 安装 Docker Compose..."
sudo apt install docker-compose-plugin -y

# 5. Docker 镜像加速（腾讯云）
echo "⚡ 配置 Docker 镜像加速..."
sudo mkdir -p /etc/docker
sudo tee /etc/docker/daemon.json <<EOF
{
  "registry-mirrors": [
    "https://mirror.ccs.tencentyun.com",
    "https://docker.m.daocloud.io"
  ],
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
EOF
sudo systemctl restart docker
echo "✅ Docker 镜像加速已配置"

# 6. 时区
echo "🕐 设置时区..."
sudo timedatectl set-timezone Asia/Shanghai

# 7. 创建项目目录
echo "📁 创建项目目录..."
PROJECT_DIR="/opt/shishang-order-system"
sudo mkdir -p $PROJECT_DIR
sudo chown $USER:$USER $PROJECT_DIR

# 8. 防火墙基础配置
echo "🔒 配置防火墙..."
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
echo "✅ 防火墙已开启（22/80/443）"

echo ""
echo "============================================"
echo "✅ 服务器初始化完成！"
echo "============================================"
echo ""
echo "下一步操作："
echo "1. 重新登录 shell（让 docker 用户组生效）"
echo "2. 复制项目代码到 $PROJECT_DIR"
echo "3. 复制 deploy/ 目录下的配置文件"
echo "4. 执行: cd $PROJECT_DIR && docker compose up -d"
echo ""
echo "凭证说明（批次0已轮换，旧口令已废弃）："
echo "  MySQL Root: 通过环境变量 MYSQL_ROOT_PASSWORD 提供（compose 必需，无内置默认值）"
echo "  MySQL App:  见 backend/.env [DATABASE] PASSWORD（已轮换）"
echo "  Redis:      见 backend/.env [REDIS] PASSWORD 与 deploy/redis/redis.conf（已轮换）"
echo ""
echo "⚠️  安全提醒："
echo "  - 凭证以 backend/.env 为准，勿在脚本/文档中明文回显口令"
echo "  - 配置 HTTPS 证书（Let's Encrypt）"
echo "  - MySQL/Redis 仅监听 127.0.0.1"
echo "============================================"
