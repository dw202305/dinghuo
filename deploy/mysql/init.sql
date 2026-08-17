-- 世尚门店订货系统 数据库初始化脚本
-- 版本：v1.2（基于 database.md v1.2，28张表）
-- 生成日期：2026-08-17
-- 金额单位：分（BIGINT），尺寸单位：厘米/米，面积单位：平方米

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `shishang_order` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `shishang_order`;

-- ========================================
-- 2.1 门店主体表 lj_store
-- ========================================
CREATE TABLE `lj_store` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_no` VARCHAR(20) NOT NULL COMMENT '门店编号，如 HN001',
  `store_name` VARCHAR(100) NOT NULL COMMENT '门店名称',
  `business_entity` VARCHAR(200) DEFAULT NULL COMMENT '经营主体名称',
  `credit_code` VARCHAR(50) DEFAULT NULL COMMENT '统一社会信用代码',
  `store_type` TINYINT NOT NULL DEFAULT 1 COMMENT '门店类型：1认证合作门店 2城市合伙人自营 3产品体验客户 4特殊合同客户',
  `customer_level` TINYINT NOT NULL DEFAULT 1 COMMENT '客户等级：1认证合作门店 2城市合伙人 3产品体验客户 4特殊合同客户 5大B客户',
  `channel_mode` TINYINT NOT NULL DEFAULT 1 COMMENT '渠道模式：1城市合伙人渠道 2公司直营',
  `partner_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '所属城市合伙人ID，直营为空',
  `primary_sales_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '当前主归属销售ID',
  `crm_customer_id` VARCHAR(50) DEFAULT NULL COMMENT 'CRM客户ID',
  `province` VARCHAR(50) DEFAULT NULL COMMENT '省',
  `city` VARCHAR(50) DEFAULT NULL COMMENT '市',
  `district` VARCHAR(50) DEFAULT NULL COMMENT '区',
  `address` VARCHAR(500) DEFAULT NULL COMMENT '门店详细地址',
  `contact_phone` VARCHAR(20) DEFAULT NULL COMMENT '门店联系电话',
  `wechat` VARCHAR(50) DEFAULT NULL COMMENT '门店微信或企业微信',
  `showroom_photos` JSON DEFAULT NULL COMMENT '展厅照片URL数组',
  `default_address_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '默认收货地址ID',
  `invoice_title` VARCHAR(200) DEFAULT NULL COMMENT '开票名称',
  `tax_no` VARCHAR(50) DEFAULT NULL COMMENT '税号',
  `invoice_info` JSON DEFAULT NULL COMMENT '开票资料JSON',
  `cooperation_start_date` DATE DEFAULT NULL COMMENT '合作开始日期',
  `primary_contact_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '主联系人ID',
  `admin_account_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '门店管理员账号ID',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 2停用 3待审核',
  `contract_price_version` INT DEFAULT NULL COMMENT '合同价格版本',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_store_store_no` (`store_no`),
  KEY `idx_store_partner_id` (`partner_id`),
  KEY `idx_store_primary_sales_id` (`primary_sales_id`),
  KEY `idx_store_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='门店主体表';

-- ========================================
-- 2.2 城市合伙人主体表 lj_partner
-- ========================================
CREATE TABLE `lj_partner` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partner_no` VARCHAR(20) NOT NULL COMMENT '合伙人编号',
  `business_entity` VARCHAR(200) NOT NULL COMMENT '企业或经营主体名称',
  `credit_code` VARCHAR(50) DEFAULT NULL COMMENT '统一社会信用代码',
  `primary_contact_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '主联系人ID',
  `authorized_city` VARCHAR(100) DEFAULT NULL COMMENT '授权城市',
  `authorized_region` VARCHAR(200) DEFAULT NULL COMMENT '授权区域',
  `cooperation_stage` TINYINT DEFAULT 1 COMMENT '合作阶段',
  `partner_level` TINYINT DEFAULT 1 COMMENT '合伙人等级',
  `primary_sales_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '当前主归属销售ID',
  `secondary_sales_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '协同销售ID',
  `crm_customer_id` VARCHAR(50) DEFAULT NULL COMMENT 'CRM客户ID',
  `cooperation_start_date` DATE DEFAULT NULL COMMENT '合作开始日期',
  `cooperation_end_date` DATE DEFAULT NULL COMMENT '合作结束日期',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 2停用 3待审核',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_partner_partner_no` (`partner_no`),
  KEY `idx_partner_primary_sales_id` (`primary_sales_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='城市合伙人主体表';

-- ========================================
-- 2.3 联系人表 lj_store_contact
-- ========================================
CREATE TABLE `lj_store_contact` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` BIGINT UNSIGNED NOT NULL COMMENT '所属门店ID',
  `contact_name` VARCHAR(50) NOT NULL COMMENT '姓名',
  `phone` VARCHAR(20) NOT NULL COMMENT '手机号',
  `wechat` VARCHAR(50) DEFAULT NULL COMMENT '微信号',
  `position` VARCHAR(50) DEFAULT NULL COMMENT '职务或岗位',
  `contact_type` TINYINT NOT NULL DEFAULT 1 COMMENT '联系人类型：1负责人 2采购 3下单 4财务 5安装售后 6售后 7收货人',
  `is_primary` TINYINT NOT NULL DEFAULT 0 COMMENT '是否主联系人：0否 1是',
  `receive_order_notify` TINYINT NOT NULL DEFAULT 1 COMMENT '是否接收订单通知',
  `receive_finance_notify` TINYINT NOT NULL DEFAULT 0 COMMENT '是否接收财务通知',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 0停用',
  `remark` VARCHAR(500) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`),
  KEY `idx_contact_store_id` (`store_id`),
  KEY `idx_contact_phone` (`phone`),
  KEY `idx_contact_is_primary` (`store_id`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='门店联系人表';

-- ========================================
-- 2.4 登录账号表 lj_account
-- ========================================
CREATE TABLE `lj_account` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone` VARCHAR(20) NOT NULL COMMENT '登录手机号',
  `password_hash` VARCHAR(255) DEFAULT NULL COMMENT '密码哈希（门店账号可选）',
  `contact_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联联系人ID',
  `real_name` VARCHAR(50) DEFAULT NULL COMMENT '姓名',
  `verify_status` TINYINT NOT NULL DEFAULT 0 COMMENT '身份验证状态：0未验证 1已验证',
  `wechat_openid` VARCHAR(100) DEFAULT NULL COMMENT '微信OpenID',
  `wechat_unionid` VARCHAR(100) DEFAULT NULL COMMENT '微信UnionID',
  `account_role` TINYINT NOT NULL DEFAULT 2 COMMENT '账号角色：1门店管理员 2下单员 3财务 4安装售后 5只读',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 0停用',
  `last_login_at` DATETIME DEFAULT NULL COMMENT '最近登录时间',
  `verify_code_sent_at` DATETIME DEFAULT NULL COMMENT '验证码发送时间',
  `password_set_at` DATETIME DEFAULT NULL COMMENT '密码设置时间',
  `disable_reason` VARCHAR(500) DEFAULT NULL COMMENT '停用原因',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_account_phone` (`phone`),
  KEY `idx_account_contact_id` (`contact_id`),
  KEY `idx_account_wechat_openid` (`wechat_openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录账号表（门店端）';

-- ========================================
-- 2.4.1 后台管理员表 lj_admin
-- ========================================
CREATE TABLE `lj_admin` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL COMMENT '登录用户名',
  `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
  `real_name` VARCHAR(50) NOT NULL COMMENT '真实姓名',
  `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
  `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
  `avatar` VARCHAR(500) DEFAULT NULL COMMENT '头像URL',
  `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色ID',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 0停用',
  `last_login_at` DATETIME DEFAULT NULL COMMENT '最近登录时间',
  `last_login_ip` VARCHAR(50) DEFAULT NULL COMMENT '最近登录IP',
  `login_count` INT NOT NULL DEFAULT 0 COMMENT '登录次数',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_username` (`username`),
  KEY `idx_admin_role_id` (`role_id`),
  KEY `idx_admin_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台管理员表';

-- ========================================
-- 2.4.2 后台角色表 lj_admin_role
-- ========================================
CREATE TABLE `lj_admin_role` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL COMMENT '角色名称',
  `role_code` VARCHAR(50) NOT NULL COMMENT '角色编码',
  `description` VARCHAR(500) DEFAULT NULL COMMENT '角色描述',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 0停用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_code` (`role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台角色表';

-- ========================================
-- 2.4.3 后台权限表 lj_admin_permission
-- ========================================
CREATE TABLE `lj_admin_permission` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED DEFAULT 0 COMMENT '父级ID',
  `permission_name` VARCHAR(50) NOT NULL COMMENT '权限名称',
  `permission_code` VARCHAR(100) NOT NULL COMMENT '权限编码',
  `permission_type` TINYINT NOT NULL DEFAULT 1 COMMENT '类型：1菜单 2按钮 3接口',
  `path` VARCHAR(200) DEFAULT NULL COMMENT '路由路径',
  `icon` VARCHAR(50) DEFAULT NULL COMMENT '图标',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 0停用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permission_code` (`permission_code`),
  KEY `idx_permission_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台权限表';

-- ========================================
-- 2.4.4 角色权限关联表 lj_admin_role_permission
-- ========================================
CREATE TABLE `lj_admin_role_permission` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色ID',
  `permission_id` BIGINT UNSIGNED NOT NULL COMMENT '权限ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
  KEY `idx_role_permission_permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色权限关联表';

-- ========================================
-- 2.5 账号与客户主体关联表 lj_account_customer
-- ========================================
CREATE TABLE `lj_account_customer` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id` BIGINT UNSIGNED NOT NULL COMMENT '账号ID',
  `customer_type` TINYINT NOT NULL COMMENT '客户主体类型：1门店 2城市合伙人',
  `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户主体ID',
  `role_in_customer` TINYINT NOT NULL DEFAULT 1 COMMENT '在该客户下的角色',
  `permission_scope` JSON DEFAULT NULL COMMENT '权限范围JSON',
  `is_default_store` TINYINT NOT NULL DEFAULT 0 COMMENT '是否默认登录门店：0否 1是',
  `auth_start_date` DATE DEFAULT NULL COMMENT '授权开始时间',
  `auth_end_date` DATE DEFAULT NULL COMMENT '授权结束时间',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 0停用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_account_customer` (`account_id`, `customer_type`, `customer_id`, `role_in_customer`),
  KEY `idx_account_customer_customer` (`customer_type`, `customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='账号与客户主体关联表';

-- ========================================
-- 2.6 收货地址表 lj_store_address
-- ========================================
CREATE TABLE `lj_store_address` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` BIGINT UNSIGNED NOT NULL COMMENT '所属门店ID',
  `address_type` TINYINT NOT NULL DEFAULT 1 COMMENT '地址类型：1门店地址 2仓库地址 3终端客户地址',
  `address_label` VARCHAR(50) DEFAULT NULL COMMENT '地址标签，如"总部仓库"',
  `receiver_name` VARCHAR(50) NOT NULL COMMENT '收件人',
  `receiver_phone` VARCHAR(20) NOT NULL COMMENT '手机号',
  `province` VARCHAR(50) NOT NULL COMMENT '省',
  `city` VARCHAR(50) NOT NULL COMMENT '市',
  `district` VARCHAR(50) NOT NULL COMMENT '区',
  `detail_address` VARCHAR(500) NOT NULL COMMENT '详细地址',
  `is_default` TINYINT NOT NULL DEFAULT 0 COMMENT '是否门店默认收货地址：0否 1是',
  `is_single_use` TINYINT NOT NULL DEFAULT 0 COMMENT '是否仅用于单次订单：0否 1是',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 0删除',
  `created_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '创建账号ID',
  PRIMARY KEY (`id`),
  KEY `idx_address_store_id` (`store_id`),
  KEY `idx_address_is_default` (`store_id`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收货地址表';

-- ========================================
-- 2.7 客户归属关系历史表 lj_customer_attribution_history
-- ========================================
CREATE TABLE `lj_customer_attribution_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_type` TINYINT NOT NULL COMMENT '客户主体类型：1门店 2城市合伙人',
  `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户主体ID',
  `channel_mode` TINYINT NOT NULL COMMENT '渠道模式：1城市合伙人渠道 2公司直营',
  `partner_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '城市合伙人ID',
  `primary_sales_id` BIGINT UNSIGNED NOT NULL COMMENT '主归属销售ID',
  `secondary_sales_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '协同销售ID',
  `attribution_source` TINYINT NOT NULL COMMENT '归属来源：1开发 2分配 3继承 4转移 5系统迁移',
  `effective_time` DATETIME NOT NULL COMMENT '生效时间',
  `expire_time` DATETIME DEFAULT NULL COMMENT '失效时间',
  `is_current` TINYINT NOT NULL DEFAULT 1 COMMENT '是否当前有效：0否 1是',
  `change_reason` VARCHAR(500) DEFAULT NULL COMMENT '变更原因',
  `cascade_from_partner` TINYINT NOT NULL DEFAULT 0 COMMENT '是否由合伙人销售归属变更自动级联：0否 1是',
  `parent_partner_relation_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '上级城市合伙人关系ID',
  `applicant` VARCHAR(50) DEFAULT NULL COMMENT '申请人',
  `approver` VARCHAR(50) DEFAULT NULL COMMENT '审批人',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attribution_customer` (`customer_type`, `customer_id`, `is_current`),
  KEY `idx_attribution_sales` (`primary_sales_id`, `is_current`),
  KEY `idx_attribution_partner` (`partner_id`, `is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户归属关系历史表';

-- ========================================
-- 2.8 公司销售人员表 lj_sales
-- ========================================
CREATE TABLE `lj_sales` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_no` VARCHAR(20) NOT NULL COMMENT '员工编号',
  `name` VARCHAR(50) NOT NULL COMMENT '姓名',
  `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
  `department` VARCHAR(100) DEFAULT NULL COMMENT '所属部门或团队',
  `responsible_region` VARCHAR(200) DEFAULT NULL COMMENT '负责区域',
  `superior_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '上级销售或主管ID',
  `crm_user_id` VARCHAR(50) DEFAULT NULL COMMENT 'CRM用户ID',
  `employment_status` TINYINT NOT NULL DEFAULT 1 COMMENT '在职状态：1在职 2离职 3调岗',
  `hire_date` DATE DEFAULT NULL COMMENT '入职日期',
  `leave_date` DATE DEFAULT NULL COMMENT '离职日期',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sales_employee_no` (`employee_no`),
  KEY `idx_sales_employment_status` (`employment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公司销售人员表';

-- ========================================
-- 2.9 订单表 lj_order
-- ========================================
CREATE TABLE `lj_order` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(50) NOT NULL COMMENT '订单号，如 SS-20260816-HN001-0008',
  `transaction_type` TINYINT NOT NULL COMMENT '交易主体类型：1门店 2城市合伙人',
  `transaction_id` BIGINT UNSIGNED NOT NULL COMMENT '交易主体ID',
  `service_store_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '实际服务门店ID，合伙人自营为空',
  `partner_snapshot_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '城市合伙人归属快照ID',
  `primary_sales_snapshot_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '公司主归属销售快照ID',
  `current_service_sales_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '当前服务销售ID',
  `secondary_sales_snapshot_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '协同销售快照ID',
  `crm_customer_snapshot_id` VARCHAR(50) DEFAULT NULL COMMENT 'CRM客户ID快照',
  `crm_opportunity_id` VARCHAR(50) DEFAULT NULL COMMENT 'CRM商机ID',
  `created_by` BIGINT UNSIGNED NOT NULL COMMENT '创建账号ID',
  `delivery_method` TINYINT NOT NULL DEFAULT 1 COMMENT '收货方式：1发送至门店 2发送至终端客户',
  `address_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '地址ID',
  `receiver_name` VARCHAR(50) NOT NULL COMMENT '收件人快照',
  `receiver_phone` VARCHAR(20) NOT NULL COMMENT '收件人手机号快照',
  `receiver_province` VARCHAR(50) NOT NULL COMMENT '省快照',
  `receiver_city` VARCHAR(50) NOT NULL COMMENT '市快照',
  `receiver_district` VARCHAR(50) NOT NULL COMMENT '区快照',
  `receiver_detail` VARCHAR(500) NOT NULL COMMENT '详细地址快照',
  `project_name` VARCHAR(100) DEFAULT NULL COMMENT '项目名称',
  `end_customer` VARCHAR(100) DEFAULT NULL COMMENT '终端客户名称或代号',
  `order_status` TINYINT NOT NULL DEFAULT 1 COMMENT '订单状态：1草稿 2待支付 3支付处理中 4已支付待审核 5需门店确认 6待补款 7审核通过待排产 8生产中 9质检中 10待发货 11部分发货 12已发货 13已签收 14已完成 15售后处理中 16已取消 17退款中 18已退款',
  `item_count` INT NOT NULL DEFAULT 0 COMMENT '窗帘副数',
  `track_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '轨道费用合计（分）',
  `fabric_area_total` DECIMAL(12,4) NOT NULL DEFAULT 0 COMMENT '面料总面积（平方米）',
  `fabric_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '面料费用合计（分）',
  `inventory_used_count` INT NOT NULL DEFAULT 0 COMMENT '库存套件使用数量',
  `new_purchase_count` INT NOT NULL DEFAULT 0 COMMENT '新购套件数量',
  `new_purchase_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '新购套件费用（分）',
  `accessory_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '选装配件费用（分）',
  `shipping_method` VARCHAR(50) NOT NULL DEFAULT '到付' COMMENT '运费方式',
  `nonstandard_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '非标费用（分）',
  `discount_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '优惠金额（分）',
  `total_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '应付总额（分）',
  `paid_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '实付金额（分）',
  `payment_status` TINYINT NOT NULL DEFAULT 0 COMMENT '支付状态：0未支付 1部分支付 2已支付',
  `audit_status` TINYINT NOT NULL DEFAULT 0 COMMENT '审核状态：0未审核 1审核通过 2需确认 3待补款 4无法生产',
  `expected_delivery_date` DATE DEFAULT NULL COMMENT '期望交期',
  `invoice_required` TINYINT NOT NULL DEFAULT 0 COMMENT '是否需要发票：0否 1是',
  `remark` TEXT COMMENT '整单备注',
  `attachments` JSON DEFAULT NULL COMMENT '现场照片或图纸附件URL数组',
  `price_locked_at` DATETIME DEFAULT NULL COMMENT '价格锁定时间',
  `price_locked_until` DATETIME DEFAULT NULL COMMENT '价格锁定截止时间',
  `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
  `produced_at` DATETIME DEFAULT NULL COMMENT '开始生产时间',
  `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
  `cancelled_at` DATETIME DEFAULT NULL COMMENT '取消时间',
  `cancel_reason` VARCHAR(500) DEFAULT NULL COMMENT '取消原因',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_order_no` (`order_no`),
  KEY `idx_order_transaction` (`transaction_type`, `transaction_id`),
  KEY `idx_order_service_store` (`service_store_id`),
  KEY `idx_order_created_by` (`created_by`),
  KEY `idx_order_status` (`order_status`),
  KEY `idx_order_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表';

-- ========================================
-- 2.10 窗帘明细表 lj_order_item
-- ========================================
CREATE TABLE `lj_order_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_no` VARCHAR(60) NOT NULL COMMENT '窗帘编号，如 SS-20260816-HN001-0008-C03',
  `order_id` BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
  `sequence` INT NOT NULL COMMENT '序号',
  `install_position` VARCHAR(50) NOT NULL COMMENT '安装位置/房间',
  `width_cm` DECIMAL(6,1) NOT NULL COMMENT '宽度（厘米）',
  `height_cm` DECIMAL(6,1) NOT NULL COMMENT '高度（厘米）',
  `area_m2` DECIMAL(12,4) NOT NULL COMMENT '面积（平方米）',
  `track_color` VARCHAR(20) NOT NULL COMMENT '轨道颜色',
  `track_horizontal_length_m` DECIMAL(6,2) NOT NULL COMMENT '横轨长度（米）',
  `track_vertical_length_m` DECIMAL(6,2) NOT NULL COMMENT '竖轨总长度（米）',
  `track_amount_cent` BIGINT NOT NULL COMMENT '轨道费用（分）',
  `fabric_no` VARCHAR(50) NOT NULL COMMENT '世尚面料编号',
  `fabric_price_cent` BIGINT NOT NULL COMMENT '面料单价（分，下单时快照）',
  `fabric_amount_cent` BIGINT NOT NULL COMMENT '面料费用（分）',
  `power_type` TINYINT NOT NULL DEFAULT 1 COMMENT '电源类型：1标准 2锂电池',
  `power_surcharge_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '电源加价（分）',
  `remote_type` TINYINT NOT NULL DEFAULT 1 COMMENT '遥控器类型：1标准 2Pro',
  `remote_surcharge_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '遥控器加价（分）',
  `wall_control_type` TINYINT NOT NULL DEFAULT 0 COMMENT '墙面控制类型：0不配置 1标准 2Pro',
  `wall_control_quantity` INT NOT NULL DEFAULT 0 COMMENT '墙面控制数量',
  `wall_control_price_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '墙面控制单价（分）',
  `wall_control_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '墙面控制费用（分）',
  `accessory_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '选装费用合计（分）',
  `use_inventory` TINYINT NOT NULL DEFAULT 0 COMMENT '是否使用库存套件：0否 1是',
  `kit_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '套件编号ID',
  `kit_price_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '套件单价（分，快照）',
  `kit_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '套件应付金额（分）',
  `nonstandard_amount_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '非标费用（分）',
  `item_total_cent` BIGINT NOT NULL COMMENT '单副合计（分）',
  `install_condition` VARCHAR(500) DEFAULT NULL COMMENT '安装方式或现场条件',
  `remark` VARCHAR(500) DEFAULT NULL COMMENT '单副备注',
  `technical_status` TINYINT NOT NULL DEFAULT 0 COMMENT '技术状态：0待审核 1通过 2需确认 3需补款 4无法生产',
  `production_status` TINYINT NOT NULL DEFAULT 0 COMMENT '生产状态：0待排产 1生产中 2质检中 3已完成',
  `qc_status` TINYINT NOT NULL DEFAULT 0 COMMENT '质检状态：0待质检 1合格 2不合格',
  `shipping_status` TINYINT NOT NULL DEFAULT 0 COMMENT '发货状态：0待发货 1已发货 2已签收',
  `tracking_no` VARCHAR(100) DEFAULT NULL COMMENT '物流单号',
  `carrier` VARCHAR(50) DEFAULT NULL COMMENT '承运商',
  `shipped_at` DATETIME DEFAULT NULL COMMENT '发货时间',
  `received_at` DATETIME DEFAULT NULL COMMENT '签收时间',
  `actual_supplier_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '实际供应商ID',
  `supplier_fabric_no` VARCHAR(50) DEFAULT NULL COMMENT '供应商原始面料编号',
  `supply_batch` VARCHAR(50) DEFAULT NULL COMMENT '供货批次',
  `cut_size` VARCHAR(100) DEFAULT NULL COMMENT '裁剪尺寸',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_item_item_no` (`item_no`),
  KEY `idx_item_order_id` (`order_id`),
  KEY `idx_item_fabric_no` (`fabric_no`),
  KEY `idx_item_technical_status` (`technical_status`),
  KEY `idx_item_production_status` (`production_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='窗帘明细表';

-- ========================================
-- 2.11 门店套件库存表 lj_store_inventory
-- ========================================
CREATE TABLE `lj_store_inventory` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` BIGINT UNSIGNED NOT NULL COMMENT '门店ID',
  `kit_sku` VARCHAR(50) NOT NULL COMMENT '套件SKU',
  `total_purchased` INT NOT NULL DEFAULT 0 COMMENT '已采购总数',
  `available` INT NOT NULL DEFAULT 0 COMMENT '可用数量',
  `locked` INT NOT NULL DEFAULT 0 COMMENT '已锁定数量',
  `consumed` INT NOT NULL DEFAULT 0 COMMENT '已核销数量',
  `frozen` INT NOT NULL DEFAULT 0 COMMENT '售后冻结数量',
  `return_pending` INT NOT NULL DEFAULT 0 COMMENT '退回待检数量',
  `adjusted` INT NOT NULL DEFAULT 0 COMMENT '调整数量',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inventory_store_sku` (`store_id`, `kit_sku`),
  KEY `idx_inventory_store_id` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='门店套件库存表';

-- ========================================
-- 2.12 库存流水表 lj_inventory_log
-- ========================================
CREATE TABLE `lj_inventory_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` BIGINT UNSIGNED NOT NULL COMMENT '门店ID',
  `inventory_id` BIGINT UNSIGNED NOT NULL COMMENT '库存记录ID',
  `log_type` TINYINT NOT NULL COMMENT '变化类型：1采购入账 2订单锁定 3支付核销 4取消释放 5退款退回 6售后更换 7人工调整 8门店调拨',
  `quantity` INT NOT NULL COMMENT '变化数量（正负）',
  `before_quantity` INT NOT NULL COMMENT '变化前数量',
  `after_quantity` INT NOT NULL COMMENT '变化后数量',
  `order_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联订单ID',
  `operator_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '操作人ID',
  `operator_name` VARCHAR(50) DEFAULT NULL COMMENT '操作人姓名',
  `reason` VARCHAR(500) DEFAULT NULL COMMENT '原因',
  `idempotent_key` VARCHAR(128) DEFAULT NULL COMMENT '幂等键',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inventory_log_idempotent` (`idempotent_key`),
  KEY `idx_log_store_id` (`store_id`),
  KEY `idx_log_inventory_id` (`inventory_id`),
  KEY `idx_log_order_id` (`order_id`),
  KEY `idx_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='库存流水表';

-- ========================================
-- 2.13 面料表 lj_fabric
-- ========================================
CREATE TABLE `lj_fabric` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fabric_no` VARCHAR(50) NOT NULL COMMENT '世尚面料编号，唯一',
  `series` VARCHAR(50) DEFAULT NULL COMMENT '系列',
  `name` VARCHAR(100) NOT NULL COMMENT '名称',
  `material` VARCHAR(50) DEFAULT NULL COMMENT '材质',
  `color_name` VARCHAR(50) DEFAULT NULL COMMENT '颜色名称',
  `color_code` VARCHAR(20) DEFAULT NULL COMMENT '色号',
  `texture_tags` JSON DEFAULT NULL COMMENT '纹理和风格标签',
  `function_tags` JSON DEFAULT NULL COMMENT '功能标签，如阻燃、防水',
  `price_per_sqm_cent` BIGINT NOT NULL COMMENT '单价/㎡（分）',
  `main_image` VARCHAR(500) DEFAULT NULL COMMENT '主图URL',
  `detail_images` JSON DEFAULT NULL COMMENT '详情图URL数组',
  `fabric_width` DECIMAL(8,2) DEFAULT NULL COMMENT '面料幅宽（米）',
  `min_billing_area` DECIMAL(12,4) DEFAULT NULL COMMENT '最小计费面积（平方米）',
  `loss_coefficient` DECIMAL(5,4) DEFAULT 1.0000 COMMENT '损耗系数',
  `stock_status` TINYINT NOT NULL DEFAULT 1 COMMENT '库存状态：1充足 2紧张 3缺货',
  `listing_status` TINYINT NOT NULL DEFAULT 1 COMMENT '上架状态：1已上架 0已下架',
  `orderable` TINYINT NOT NULL DEFAULT 1 COMMENT '允许订货：1是 0否',
  `sort_weight` INT NOT NULL DEFAULT 0 COMMENT '排序权重',
  `effective_date` DATE DEFAULT NULL COMMENT '生效日期',
  `price_version` INT NOT NULL DEFAULT 1 COMMENT '价格版本',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fabric_fabric_no` (`fabric_no`),
  KEY `idx_fabric_series` (`series`),
  KEY `idx_fabric_listing_status` (`listing_status`, `orderable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='面料表';

-- ========================================
-- 2.14 面料供应商表 lj_fabric_supplier
-- ========================================
CREATE TABLE `lj_fabric_supplier` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_name` VARCHAR(200) NOT NULL COMMENT '供应商名称',
  `contact_person` VARCHAR(50) DEFAULT NULL COMMENT '联系人',
  `contact_phone` VARCHAR(20) DEFAULT NULL COMMENT '联系电话',
  `business_status` TINYINT NOT NULL DEFAULT 1 COMMENT '经营状态：1正常 2停用',
  `cooperation_start_date` DATE DEFAULT NULL COMMENT '合作开始日期',
  `cooperation_end_date` DATE DEFAULT NULL COMMENT '合作结束日期',
  `purchase_remark` VARCHAR(500) DEFAULT NULL COMMENT '采购备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='面料供应商表';

-- ========================================
-- 2.15 面料供应商映射表 lj_fabric_supplier_mapping
-- ========================================
CREATE TABLE `lj_fabric_supplier_mapping` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fabric_id` BIGINT UNSIGNED NOT NULL COMMENT '面料ID',
  `fabric_no` VARCHAR(50) NOT NULL COMMENT '世尚面料编号',
  `supplier_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
  `supplier_fabric_no` VARCHAR(50) NOT NULL COMMENT '供应商原始面料编号',
  `supplier_color_desc` VARCHAR(100) DEFAULT NULL COMMENT '供应商内部颜色或批次描述',
  `purchase_price_cent` BIGINT DEFAULT NULL COMMENT '供应商采购价格（分）',
  `purchase_unit` VARCHAR(20) DEFAULT NULL COMMENT '计价单位',
  `min_order_quantity` INT DEFAULT NULL COMMENT '最小起订量',
  `delivery_days` INT DEFAULT NULL COMMENT '交期（天）',
  `effective_date` DATE DEFAULT NULL COMMENT '映射生效日期',
  `expire_date` DATE DEFAULT NULL COMMENT '映射失效日期',
  `is_default_supplier` TINYINT NOT NULL DEFAULT 0 COMMENT '是否默认供应商：0否 1是',
  `is_backup_supplier` TINYINT NOT NULL DEFAULT 0 COMMENT '是否备选供应商：0否 1是',
  `quality_remark` VARCHAR(500) DEFAULT NULL COMMENT '质量或色差备注',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1有效 0失效',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mapping_fabric_no` (`fabric_no`),
  KEY `idx_mapping_supplier_id` (`supplier_id`),
  KEY `idx_mapping_effective` (`effective_date`, `expire_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='面料供应商映射表';

-- ========================================
-- 2.16 轨道表 lj_track
-- ========================================
CREATE TABLE `lj_track` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(50) NOT NULL COMMENT '轨道SKU',
  `track_type` TINYINT NOT NULL COMMENT '类型：1横轨 2竖轨',
  `color` VARCHAR(20) NOT NULL COMMENT '颜色',
  `standard_length` DECIMAL(8,2) NOT NULL COMMENT '标准原料长度（米）',
  `price_per_meter_cent` BIGINT NOT NULL COMMENT '门店单价/米（分）',
  `partner_price_cent` BIGINT DEFAULT NULL COMMENT '合伙人价格（分）',
  `enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用：1是 0否',
  `effective_date` DATE DEFAULT NULL COMMENT '生效日期',
  `price_version` INT NOT NULL DEFAULT 1 COMMENT '价格版本',
  `remark` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_track_sku_color` (`sku`, `color`),
  KEY `idx_track_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轨道表';

-- ========================================
-- 2.17 选装配件表 lj_accessory
-- ========================================
CREATE TABLE `lj_accessory` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(50) NOT NULL COMMENT '配件SKU',
  `name` VARCHAR(100) NOT NULL COMMENT '配件名称',
  `image` VARCHAR(500) DEFAULT NULL COMMENT '图片URL',
  `config_group` VARCHAR(50) NOT NULL COMMENT '配置组：power/remote/wall_control',
  `option_type` TINYINT NOT NULL COMMENT '类型：1标准 2升级 3新增',
  `surcharge_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '加价或补差价（分）',
  `partner_surcharge_cent` BIGINT DEFAULT NULL COMMENT '合伙人加价（分）',
  `required` TINYINT NOT NULL DEFAULT 0 COMMENT '是否必选：0否 1是',
  `select_mode` TINYINT NOT NULL DEFAULT 1 COMMENT '选择模式：1单选 2多选',
  `allow_quantity` TINYINT NOT NULL DEFAULT 0 COMMENT '是否允许数量：0否 1是',
  `max_quantity` INT DEFAULT NULL COMMENT '最大数量',
  `applicable_products` JSON DEFAULT NULL COMMENT '适用产品JSON',
  `compatibility_rules` JSON DEFAULT NULL COMMENT '兼容和排斥规则JSON',
  `stock_status` TINYINT NOT NULL DEFAULT 1 COMMENT '库存状态',
  `enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用',
  `effective_date` DATE DEFAULT NULL COMMENT '生效日期',
  `price_version` INT NOT NULL DEFAULT 1 COMMENT '价格版本',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_accessory_sku` (`sku`),
  KEY `idx_accessory_config_group` (`config_group`),
  KEY `idx_accessory_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='选装配件表';

-- ========================================
-- 2.18 支付记录表 lj_payment
-- ========================================
CREATE TABLE `lj_payment` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_no` VARCHAR(50) NOT NULL COMMENT '支付单号',
  `order_id` BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
  `order_no` VARCHAR(50) NOT NULL COMMENT '订单号',
  `payment_channel` VARCHAR(20) NOT NULL COMMENT '支付渠道：balance/wechat/alipay',
  `pay_method` VARCHAR(20) NOT NULL COMMENT '支付方式：JSAPI/H5/NATIVE等',
  `pay_amount_cent` BIGINT NOT NULL COMMENT '支付金额（分）',
  `transaction_id` VARCHAR(100) DEFAULT NULL COMMENT '第三方支付流水号',
  `pay_status` TINYINT NOT NULL DEFAULT 0 COMMENT '支付状态：0待支付 1支付成功 2支付失败 3已退款',
  `paid_at` DATETIME DEFAULT NULL COMMENT '支付成功时间',
  `notify_content` TEXT COMMENT '支付回调原始内容',
  `refund_amount_cent` BIGINT DEFAULT NULL COMMENT '退款金额（分）',
  `refunded_at` DATETIME DEFAULT NULL COMMENT '退款时间',
  `refund_reason` VARCHAR(500) DEFAULT NULL COMMENT '退款原因',
  `balance_transaction_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '余额资金流水ID，余额支付时关联',
  `idempotent_key` VARCHAR(100) DEFAULT NULL COMMENT '幂等键',
  `transaction_subject_type` TINYINT DEFAULT NULL COMMENT '交易主体类型：1门店 2城市合伙人',
  `transaction_subject_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '交易主体ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_payment_no` (`payment_no`),
  UNIQUE KEY `uk_payment_idempotent` (`idempotent_key`),
  KEY `idx_payment_order_id` (`order_id`),
  UNIQUE KEY `uk_payment_transaction_id` (`transaction_id`),
  KEY `idx_payment_status` (`pay_status`),
  KEY `idx_payment_balance_txn` (`balance_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付记录表';

-- ========================================
-- 2.19 客户资金账户表 lj_customer_balance_account
-- ========================================
CREATE TABLE `lj_customer_balance_account` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_type` TINYINT NOT NULL COMMENT '客户主体类型：1门店 2城市合伙人',
  `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户主体ID',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'CNY' COMMENT '币种',
  `available_balance_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '可用余额（分）',
  `frozen_balance_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '冻结余额（分）',
  `total_recharge_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '累计储值（分）',
  `total_consumed_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '累计消费（分）',
  `total_refund_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '累计退款（分）',
  `total_adjustment_cent` BIGINT NOT NULL DEFAULT 0 COMMENT '累计人工调整（分）',
  `account_status` TINYINT NOT NULL DEFAULT 1 COMMENT '账户状态：1正常 2冻结 3注销',
  `version` INT NOT NULL DEFAULT 0 COMMENT '乐观锁版本号',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_balance_account_customer` (`customer_type`, `customer_id`, `currency`),
  KEY `idx_balance_account_status` (`account_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户资金账户表';

-- ========================================
-- 2.20 客户资金流水表 lj_customer_balance_transaction
-- ========================================
CREATE TABLE `lj_customer_balance_transaction` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_no` VARCHAR(50) NOT NULL COMMENT '流水号',
  `account_id` BIGINT UNSIGNED NOT NULL COMMENT '资金账户ID',
  `customer_type` TINYINT NOT NULL COMMENT '客户主体类型快照：1门店 2城市合伙人',
  `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户主体ID快照',
  `transaction_type` TINYINT NOT NULL COMMENT '流水类型：1储值 2消费 3退款 4冻结 5解冻 6调入 7调出 8冲正 9人工调整',
  `fund_type` TINYINT NOT NULL DEFAULT 1 COMMENT '资金属性：1真实资金 2测试资金',
  `direction` TINYINT NOT NULL COMMENT '资金方向：1收入 2支出',
  `amount_cent` BIGINT NOT NULL COMMENT '变动金额（分）',
  `before_balance_cent` BIGINT NOT NULL COMMENT '变动前余额（分）',
  `after_balance_cent` BIGINT NOT NULL COMMENT '变动后余额（分）',
  `ref_order_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联订单ID',
  `ref_payment_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联支付单ID',
  `ref_recharge_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联储值单ID',
  `refund_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联退款单ID',
  `idempotent_key` VARCHAR(100) NOT NULL COMMENT '唯一业务幂等键',
  `payment_channel` VARCHAR(20) DEFAULT NULL COMMENT '支付渠道：wechat/alipay/offline/test',
  `operator_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '操作人ID',
  `operator_name` VARCHAR(50) DEFAULT NULL COMMENT '操作人姓名',
  `reviewer_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '审核人ID',
  `reviewer_name` VARCHAR(50) DEFAULT NULL COMMENT '审核人姓名',
  `reason` VARCHAR(500) DEFAULT NULL COMMENT '原因',
  `remark` VARCHAR(500) DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_balance_txn_idempotent` (`idempotent_key`),
  UNIQUE KEY `uk_balance_txn_no` (`transaction_no`),
  KEY `idx_balance_txn_account` (`account_id`),
  KEY `idx_balance_txn_customer` (`customer_type`, `customer_id`),
  KEY `idx_balance_txn_type` (`transaction_type`),
  KEY `idx_balance_txn_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户资金流水表';

-- ========================================
-- 2.21 储值订单表 lj_recharge_order
-- ========================================
CREATE TABLE `lj_recharge_order` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `recharge_no` VARCHAR(50) NOT NULL COMMENT '储值单号',
  `account_id` BIGINT UNSIGNED NOT NULL COMMENT '资金账户ID',
  `customer_type` TINYINT NOT NULL COMMENT '客户主体类型：1门店 2城市合伙人',
  `customer_id` BIGINT UNSIGNED NOT NULL COMMENT '客户主体ID',
  `amount_cent` BIGINT NOT NULL COMMENT '储值金额（分）',
  `recharge_method` TINYINT NOT NULL COMMENT '储值方式：1微信 2支付宝 3线下 4测试',
  `trade_no` VARCHAR(100) DEFAULT NULL COMMENT '支付平台交易号',
  `offline_voucher` VARCHAR(500) DEFAULT NULL COMMENT '线下凭证信息',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1待支付 2支付中 3待审核 4已入账 5已关闭 6已退款',
  `applicant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '申请人ID',
  `applicant_name` VARCHAR(50) DEFAULT NULL COMMENT '申请人姓名',
  `reviewer_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '审核人ID',
  `reviewer_name` VARCHAR(50) DEFAULT NULL COMMENT '审核人姓名',
  `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
  `reviewed_at` DATETIME DEFAULT NULL COMMENT '审核时间',
  `credited_at` DATETIME DEFAULT NULL COMMENT '入账时间',
  `idempotent_key` VARCHAR(100) DEFAULT NULL COMMENT '幂等键',
  `remark` VARCHAR(500) DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_recharge_no` (`recharge_no`),
  UNIQUE KEY `uk_recharge_idempotent` (`idempotent_key`),
  KEY `idx_recharge_account` (`account_id`),
  KEY `idx_recharge_customer` (`customer_type`, `customer_id`),
  KEY `idx_recharge_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='储值订单表';

-- ========================================
-- 2.22 操作日志表 lj_operation_log
-- ========================================
CREATE TABLE `lj_operation_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(50) NOT NULL COMMENT '模块：order/payment/inventory/price等',
  `action` VARCHAR(50) NOT NULL COMMENT '操作：create/update/delete/approve等',
  `target_type` VARCHAR(50) NOT NULL COMMENT '目标类型：order/payment等',
  `target_id` BIGINT UNSIGNED NOT NULL COMMENT '目标ID',
  `target_no` VARCHAR(50) DEFAULT NULL COMMENT '目标编号',
  `before_data` JSON DEFAULT NULL COMMENT '变更前数据',
  `after_data` JSON DEFAULT NULL COMMENT '变更后数据',
  `operator_id` BIGINT UNSIGNED NOT NULL COMMENT '操作人ID',
  `operator_name` VARCHAR(50) NOT NULL COMMENT '操作人姓名',
  `operator_role` VARCHAR(50) DEFAULT NULL COMMENT '操作人角色',
  `ip_address` VARCHAR(50) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'User Agent',
  `remark` VARCHAR(500) DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_module_action` (`module`, `action`),
  KEY `idx_log_target` (`target_type`, `target_id`),
  KEY `idx_log_operator_id` (`operator_id`),
  KEY `idx_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

-- ========================================
-- 2.23 售后申请表 lj_after_sale
-- ========================================
CREATE TABLE `lj_after_sale` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `after_sale_no` VARCHAR(50) NOT NULL COMMENT '售后单号',
  `order_id` BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
  `order_no` VARCHAR(50) NOT NULL COMMENT '订单号',
  `item_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '窗帘明细ID',
  `item_no` VARCHAR(60) DEFAULT NULL COMMENT '窗帘编号',
  `kit_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '套件编号ID',
  `problem_type` TINYINT NOT NULL COMMENT '问题类型：1电机 2电源 3遥控器 4墙控 5轨道 6面料 7结构件 8安装 9初始化 10运输破损 11其他',
  `problem_desc` TEXT NOT NULL COMMENT '问题描述',
  `images` JSON DEFAULT NULL COMMENT '图片URL数组',
  `videos` JSON DEFAULT NULL COMMENT '视频URL数组',
  `install_date` DATE DEFAULT NULL COMMENT '安装日期',
  `affect_usage` TINYINT NOT NULL DEFAULT 0 COMMENT '是否影响使用：0否 1是',
  `contact_name` VARCHAR(50) NOT NULL COMMENT '联系人',
  `contact_phone` VARCHAR(20) NOT NULL COMMENT '联系电话',
  `expected_solution` VARCHAR(500) DEFAULT NULL COMMENT '期望处理方式',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1待处理 2处理中 3已完成 4已关闭',
  `diagnosis` TEXT COMMENT '诊断结果',
  `responsibility` TINYINT DEFAULT NULL COMMENT '责任判断：1世尚 2门店 3物流 4其他',
  `solution` VARCHAR(500) DEFAULT NULL COMMENT '处理方案',
  `accessory_cost_cent` BIGINT DEFAULT 0 COMMENT '配件费用（分）',
  `labor_cost_cent` BIGINT DEFAULT 0 COMMENT '人工费用（分）',
  `logistics_cost_cent` BIGINT DEFAULT 0 COMMENT '物流费用（分）',
  `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
  `handler_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '处理人ID',
  `handler_name` VARCHAR(50) DEFAULT NULL COMMENT '处理人姓名',
  `created_by` BIGINT UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_after_sale_no` (`after_sale_no`),
  KEY `idx_after_sale_order_id` (`order_id`),
  KEY `idx_after_sale_status` (`status`),
  KEY `idx_after_sale_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='售后申请表';

-- ========================================
-- 2.24 发票申请表 lj_invoice_request
-- ========================================
CREATE TABLE `lj_invoice_request` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_no` VARCHAR(50) NOT NULL COMMENT '申请编号',
  `order_id` BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
  `order_no` VARCHAR(50) NOT NULL COMMENT '订单号',
  `store_id` BIGINT UNSIGNED NOT NULL COMMENT '门店ID',
  `invoice_type` TINYINT NOT NULL COMMENT '发票类型：1普票 2专票',
  `title` VARCHAR(200) NOT NULL COMMENT '发票抬头',
  `tax_no` VARCHAR(50) NOT NULL COMMENT '税号',
  `tax_rate` DECIMAL(5,2) NOT NULL COMMENT '税率（%）',
  `invoice_amount_cent` BIGINT NOT NULL COMMENT '开票金额（分）',
  `bank_name` VARCHAR(100) DEFAULT NULL COMMENT '开户银行（专票）',
  `bank_account` VARCHAR(50) DEFAULT NULL COMMENT '银行账号（专票）',
  `company_address` VARCHAR(200) DEFAULT NULL COMMENT '公司地址（专票）',
  `company_phone` VARCHAR(20) DEFAULT NULL COMMENT '公司电话（专票）',
  `delivery_method` TINYINT DEFAULT 1 COMMENT '交付方式：1电子 2邮寄',
  `delivery_address` VARCHAR(500) DEFAULT NULL COMMENT '邮寄地址',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1待审核 2已审核待开票 3已开票 4已驳回',
  `invoice_no` VARCHAR(50) DEFAULT NULL COMMENT '发票号码',
  `invoice_code` VARCHAR(50) DEFAULT NULL COMMENT '发票代码',
  `invoiced_at` DATETIME DEFAULT NULL COMMENT '开票时间',
  `reject_reason` VARCHAR(500) DEFAULT NULL COMMENT '驳回原因',
  `reviewer_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '审核人ID',
  `reviewer_name` VARCHAR(50) DEFAULT NULL COMMENT '审核人姓名',
  `reviewed_at` DATETIME DEFAULT NULL COMMENT '审核时间',
  `created_by` BIGINT UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoice_request_no` (`request_no`),
  KEY `idx_invoice_order_id` (`order_id`),
  KEY `idx_invoice_store_id` (`store_id`),
  KEY `idx_invoice_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='发票申请表';

-- ========================================
-- 初始化数据
-- ========================================

-- 超级管理员角色
INSERT INTO `lj_admin_role` (`id`, `role_name`, `role_code`, `description`, `sort_order`, `status`) VALUES
(1, '超级管理员', 'super_admin', '系统最高权限角色', 0, 1);

-- 超级管理员账号（密码：Admin@2026!）
INSERT INTO `lj_admin` (`id`, `username`, `password_hash`, `real_name`, `phone`, `role_id`, `status`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uPZQxNCGy6', '系统管理员', '13800000000', 1, 1);

SET FOREIGN_KEY_CHECKS = 1;
