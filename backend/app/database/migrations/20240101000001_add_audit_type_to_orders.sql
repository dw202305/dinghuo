-- 添加审核类型字段，区分预审/后审流程（PRD v3.2 §5.9）
ALTER TABLE `lj_orders`
    ADD COLUMN `audit_type` VARCHAR(20) NOT NULL DEFAULT 'post_audit'
    COMMENT '审核类型：post_audit后审(默认), pre_audit预审'
    AFTER `audit_status`;
