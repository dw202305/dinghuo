-- ============================================================
-- 迁移：订单表补审核类型字段（audit_type）
-- 日期：2026-08-18
-- 变更：修正原文件表名错误 lj_orders → lj_order（deploy 实际表名为 lj_order），
--       并按迁移格式惯例补充前置查重与回滚段
-- 关联规范：PRD v3.2 §5.9（先付后审/先审后付）、开发规范 v1 第7节
-- ============================================================

-- 前置查重（执行前人工核对：确认 lj_order 尚无 audit_type 列后再执行 ALTER）
-- SELECT COLUMN_NAME FROM information_schema.COLUMNS
--  WHERE TABLE_SCHEMA = 'shishang_order' AND TABLE_NAME = 'lj_order' AND COLUMN_NAME = 'audit_type';

-- 1. 订单表补审核类型字段，区分先付后审/先审后付流程（PRD v3.2 §5.9）
ALTER TABLE `lj_order`
    ADD COLUMN `audit_type` VARCHAR(20) NOT NULL DEFAULT 'post_audit'
    COMMENT '审核类型：post_audit先付后审 pre_audit先审后付'
    AFTER `audit_status`;

-- ============================================================
-- 回滚脚本
-- ============================================================
-- ALTER TABLE `lj_order` DROP COLUMN `audit_type`;
