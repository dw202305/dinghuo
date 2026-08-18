-- ============================================================
-- 迁移：v1.3.2 增量 —— lj_after_sale 表列对齐
-- 日期：2026-08-19
-- 背景：生产 GET /api/v1/admin/after-sales 返回 500，
--       日志异常栈：SQLSTATE[42S22] 1054 Unknown column 'a.store_id' in 'on clause'
--       AfterSaleService::getAdminAfterSaleList 以 leftJoin('store s','s.id=a.store_id')
--       引用 store_id，但 v1.3 init.sql 的 lj_after_sale 无此列；
--       另代码 process/close 使用的 close_reason、handled_at 两列同样缺失，
--       费用列实际为 accessory_cost_cent / labor_cost_cent / logistics_cost_cent（代码已对齐）。
-- 变更：1) 补建 store_id / close_reason / handled_at 三列（幂等，已存在则跳过）
--       2) 补建 idx_after_sale_store_id 索引（幂等）
--       3) 从 lj_order.transaction_id 回填存量 store_id（仅回填 NULL 行，可重复执行）
-- 关联规范：deploy/mysql/init.sql v1.3.2（§2.23）、docs/database.md §2.23
-- ============================================================

-- 1. 补列 store_id（门店ID，售后单归属门店；管理端列表 join lj_store 用）
SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lj_after_sale' AND COLUMN_NAME = 'store_id');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lj_after_sale` ADD COLUMN `store_id` BIGINT UNSIGNED DEFAULT NULL COMMENT ''门店ID'' AFTER `order_no`',
  'SELECT 1');
PREPARE stmt_as_col1 FROM @ddl;
EXECUTE stmt_as_col1;
DEALLOCATE PREPARE stmt_as_col1;

-- 2. 补列 close_reason（关闭原因，closeAfterSale 写入）
SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lj_after_sale' AND COLUMN_NAME = 'close_reason');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lj_after_sale` ADD COLUMN `close_reason` VARCHAR(500) DEFAULT NULL COMMENT ''关闭原因'' AFTER `solution`',
  'SELECT 1');
PREPARE stmt_as_col2 FROM @ddl;
EXECUTE stmt_as_col2;
DEALLOCATE PREPARE stmt_as_col2;

-- 3. 补列 handled_at（处理时间，processAfterSale/closeAfterSale 写入）
SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lj_after_sale' AND COLUMN_NAME = 'handled_at');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lj_after_sale` ADD COLUMN `handled_at` DATETIME DEFAULT NULL COMMENT ''处理时间'' AFTER `handler_name`',
  'SELECT 1');
PREPARE stmt_as_col3 FROM @ddl;
EXECUTE stmt_as_col3;
DEALLOCATE PREPARE stmt_as_col3;

-- 4. 补索引 idx_after_sale_store_id
SET @has_idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lj_after_sale' AND INDEX_NAME = 'idx_after_sale_store_id');
SET @ddl = IF(@has_idx = 0,
  'ALTER TABLE `lj_after_sale` ADD INDEX `idx_after_sale_store_id` (`store_id`)',
  'SELECT 1');
PREPARE stmt_as_idx FROM @ddl;
EXECUTE stmt_as_idx;
DEALLOCATE PREPARE stmt_as_idx;

-- 5. 存量数据回填 store_id（订单交易对象即门店：lj_order.transaction_id；
--    仅回填 NULL 行，重复执行无副作用）
UPDATE `lj_after_sale` `a`
  JOIN `lj_order` `o` ON `o`.`id` = `a`.`order_id`
SET `a`.`store_id` = `o`.`transaction_id`
WHERE `a`.`store_id` IS NULL;

-- ============================================================
-- 回滚脚本
-- ============================================================
-- 注意：回滚后管理端/门店端售后列表将恢复 500，执行前请确认无业务依赖
-- ALTER TABLE `lj_after_sale` DROP INDEX `idx_after_sale_store_id`;
-- ALTER TABLE `lj_after_sale` DROP COLUMN `handled_at`;
-- ALTER TABLE `lj_after_sale` DROP COLUMN `close_reason`;
-- ALTER TABLE `lj_after_sale` DROP COLUMN `store_id`;
