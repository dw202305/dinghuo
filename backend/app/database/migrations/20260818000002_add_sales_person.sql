-- ============================================================
-- 迁移：v1.3.1 增量 —— 新增销售人员表 lj_sales_person
-- 日期：2026-08-18
-- 变更：管理端 AdminOrderController / AdminStoreController / AdminPartnerController
--       以 leftJoin('sales_person sp', 'sp.id = xx.primary_sales_id') 引用该表，
--       但 v1.3 及此前迁移均未建表，导致管理端订单/门店/合伙人列表 500。
--       字段依据：代码读取 sp.id / sp.name；按 PRD v3.2 §4.0 三层归属模型
--       （销售隶属总部或城市合伙人渠道、支持 §4.0.3.1 销售转交）补充
--       角色、合伙人归属、状态等列。
-- 兼容：若库中已存在 v1.3 的 lj_sales（公司销售人员表），自动将其数据
--       回灌至 lj_sales_person（按主键 INSERT IGNORE，幂等可重复执行），
--       保证 store/partner.primary_sales_id 既有引用不丢失。
-- 关联规范：PRD v3.2 §4.0、deploy/mysql/init.sql v1.3.1（§2.8.1）
-- ============================================================

-- 1. 销售人员表 lj_sales_person
CREATE TABLE IF NOT EXISTS `lj_sales_person` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_no` VARCHAR(20) DEFAULT NULL COMMENT '员工编号',
  `name` VARCHAR(50) NOT NULL COMMENT '姓名',
  `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
  `role` TINYINT NOT NULL DEFAULT 1 COMMENT '销售角色：1总部销售 2城市合伙人渠道销售',
  `partner_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '所属城市合伙人ID，总部销售为空',
  `superior_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '上级销售或主管ID',
  `responsible_region` VARCHAR(200) DEFAULT NULL COMMENT '负责区域',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1在职 2离职 3调岗停用',
  `hire_date` DATE DEFAULT NULL COMMENT '入职日期',
  `leave_date` DATE DEFAULT NULL COMMENT '离职日期',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sales_person_employee_no` (`employee_no`),
  KEY `idx_sales_person_partner_id` (`partner_id`),
  KEY `idx_sales_person_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销售人员表（PRD 4.0 销售归属模型）';

-- 2. 旧表 lj_sales 数据回灌（仅当 lj_sales 存在时执行；INSERT IGNORE 按主键去重，可重复执行）
SET @has_legacy_sales = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lj_sales'
);
SET @copy_sql = IF(@has_legacy_sales > 0,
  'INSERT IGNORE INTO `lj_sales_person` (`id`, `employee_no`, `name`, `phone`, `superior_id`, `responsible_region`, `status`, `hire_date`, `leave_date`, `created_at`, `updated_at`) SELECT `id`, `employee_no`, `name`, `phone`, `superior_id`, `responsible_region`, `employment_status`, `hire_date`, `leave_date`, `created_at`, `updated_at` FROM `lj_sales`',
  'SELECT 1'
);
PREPARE stmt_copy_sales FROM @copy_sql;
EXECUTE stmt_copy_sales;
DEALLOCATE PREPARE stmt_copy_sales;

-- ============================================================
-- 回滚脚本
-- ============================================================
-- 注意：回滚会删除 lj_sales_person 及其数据（含回灌数据），
--       管理端列表将恢复 500，执行前请确认无业务依赖
-- DROP TABLE IF EXISTS `lj_sales_person`;
