-- ============================================================
-- 迁移：v1.3 增量 —— 新增套件主数据表 / 业务单号序列表 / 订单状态历史表
-- 日期：2026-08-18
-- 变更：从 deploy/mysql/init.sql v1.3（§2.25 / §2.26 / §2.27）摘取三张新表 DDL，
--       并随表写入 lj_kit 等级套件价两条种子数据（PRD v3.2 §4.4）。
-- 关联规范：PRD v3.2 §4.4（等级套件价）、database.md v1.2、开发规范 v1 第7节
-- ============================================================

-- 前置查重（information_schema 判存在则跳过；执行前可人工核对）
-- SELECT TABLE_NAME FROM information_schema.TABLES
--  WHERE TABLE_SCHEMA = 'shishang_order'
--    AND TABLE_NAME IN ('lj_kit', 'lj_sequence', 'lj_order_status_history');
-- 说明：下方均使用 CREATE TABLE IF NOT EXISTS / INSERT IGNORE，
--       三表已存在（如 v1.3 全新初始化库）时自动跳过，可安全重复执行。

-- 1. 套件主数据表 lj_kit（等级套件价，PRD v3.2 §4.4）
CREATE TABLE IF NOT EXISTS `lj_kit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kit_sku` VARCHAR(32) NOT NULL COMMENT '套件SKU，如 KIT-STD-STORE',
  `kit_name` VARCHAR(100) NOT NULL COMMENT '套件名称',
  `customer_level` TINYINT NOT NULL COMMENT '适用客户等级：1认证合作门店 2城市合伙人',
  `kit_price_cent` BIGINT NOT NULL COMMENT '等级套件价（分），含税不含运费',
  `effective_from` DATE DEFAULT NULL COMMENT '价格生效日期',
  `effective_to` DATE DEFAULT NULL COMMENT '价格失效日期',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1启用 0停用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kit_sku` (`kit_sku`),
  KEY `idx_kit_customer_level` (`customer_level`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='套件主数据表（等级套件价，PRD 4.4）';

-- 2. 业务单号序列表 lj_sequence（Redis 取号的 MySQL 降级通道）
CREATE TABLE IF NOT EXISTS `lj_sequence` (
  `seq_type` VARCHAR(32) NOT NULL COMMENT '序列类型，如 order/payment/recharge/balance_txn',
  `seq_date` DATE NOT NULL COMMENT '序列日期',
  `seq_value` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前序列值',
  PRIMARY KEY (`seq_type`, `seq_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='业务单号序列表（Redis取号的MySQL降级通道）';

-- 3. 订单状态历史表 lj_order_status_history
CREATE TABLE IF NOT EXISTS `lj_order_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
  `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
  `from_status` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '变更前状态（创建时为空串）',
  `to_status` VARCHAR(32) NOT NULL COMMENT '变更后状态',
  `action` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '触发动作',
  `role` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '操作角色：store/admin/system等',
  `reason` VARCHAR(255) DEFAULT NULL COMMENT '原因',
  `operator_id` BIGINT DEFAULT NULL COMMENT '操作人ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单状态历史表';

-- 4. 等级套件价初始数据（PRD v3.2 §4.4：认证门店760元/套、城市合伙人660元/套，含税不含运费）
--    INSERT IGNORE 依赖 uk_kit_sku 唯一约束去重，重复执行不报错、不覆盖已有数据
INSERT IGNORE INTO `lj_kit` (`kit_sku`, `kit_name`, `customer_level`, `kit_price_cent`, `status`) VALUES
('KIT-STD-STORE', '标准套件（认证合作门店）', 1, 76000, 1),
('KIT-STD-PARTNER', '标准套件（城市合伙人）', 2, 66000, 1);

-- ============================================================
-- 回滚脚本
-- ============================================================
-- 注意：回滚会删除三张表及其数据，执行前请确认无业务依赖
-- DELETE FROM `lj_kit` WHERE `kit_sku` IN ('KIT-STD-STORE', 'KIT-STD-PARTNER');
-- DROP TABLE IF EXISTS `lj_order_status_history`;
-- DROP TABLE IF EXISTS `lj_sequence`;
-- DROP TABLE IF EXISTS `lj_kit`;
