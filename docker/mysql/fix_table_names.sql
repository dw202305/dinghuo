-- 修复表名对齐问题
-- 我们的代码(init.sql + Models)使用复数命名，但部署的数据库用了单数

-- 1. 重命名储值账户表（单数→复数）
RENAME TABLE lj_customer_balance_account TO lj_customer_balance_accounts;
RENAME TABLE lj_customer_balance_transaction TO lj_customer_balance_transactions;
RENAME TABLE lj_recharge_order TO lj_recharge_orders;

-- 2. 重命名归属历史表（attribution→ownership）
RENAME TABLE lj_customer_attribution_history TO lj_customer_ownership_history;

-- 3. 创建缺失的价格版本表
CREATE TABLE IF NOT EXISTS lj_price_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version_no VARCHAR(50) NOT NULL COMMENT '版本号如V2026.08.17.001',
    effective_at DATETIME NOT NULL COMMENT '生效时间',
    status TINYINT NOT NULL DEFAULT 0 COMMENT '0草稿 1已发布 2已废弃',
    created_by BIGINT UNSIGNED NOT NULL COMMENT '创建人admin_id',
    remark VARCHAR(500) DEFAULT NULL COMMENT '备注',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_version_no (version_no),
    KEY idx_status (status),
    KEY idx_effective_at (effective_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='价格版本表';

-- 验证
SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = 'shishang_order';
