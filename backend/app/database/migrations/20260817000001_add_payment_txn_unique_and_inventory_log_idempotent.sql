-- ============================================================
-- 迁移：补齐支付平台交易号唯一约束 + 库存流水幂等键
-- 日期：2026-08-17
-- 关联规范：开发规范 v1 第7.3节（唯一约束）
-- ============================================================

-- 前置查重（执行前人工核对，确认无重复数据后再执行 ALTER）
-- SELECT transaction_id, COUNT(*) FROM lj_payment WHERE transaction_id IS NOT NULL GROUP BY transaction_id HAVING COUNT(*) > 1;

-- 1. 支付平台交易号唯一约束（MySQL UNIQUE KEY 对 NULL 不去重，存量 NULL 安全）
ALTER TABLE lj_payment ADD UNIQUE KEY uk_payment_transaction_id (transaction_id);

-- 2. 库存流水表补幂等键列（deploy 环境缺失此列）
ALTER TABLE lj_inventory_log ADD COLUMN idempotent_key VARCHAR(128) DEFAULT NULL COMMENT '幂等键';
ALTER TABLE lj_inventory_log ADD UNIQUE KEY uk_inventory_log_idempotent (idempotent_key);

-- ============================================================
-- 回滚脚本
-- ============================================================
-- ALTER TABLE lj_payment DROP INDEX uk_payment_transaction_id;
-- ALTER TABLE lj_inventory_log DROP INDEX uk_inventory_log_idempotent;
-- ALTER TABLE lj_inventory_log DROP COLUMN idempotent_key;
