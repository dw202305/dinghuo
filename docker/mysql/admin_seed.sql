-- ============================================================
-- 初始管理员种子数据
-- 版本：v1.1
-- 日期：2026-08-17
-- 变更：新增储值管理、余额流水、归属管理、价格版本等权限
-- 执行方式：mysql -u root -p shishang_order < admin_seed.sql
-- ============================================================

-- 1. 插入默认角色
INSERT INTO `lj_admin_role` (`id`, `role_name`, `role_code`, `description`, `sort_order`, `status`)
VALUES
(1, '超级管理员', 'super_admin', '拥有系统全部权限', 1, 1),
(2, '运营管理员', 'operator', '日常运营管理', 2, 1),
(3, '财务人员', 'finance', '财务对账、发票管理', 3, 1),
(4, '客服', 'customer_service', '售后处理、门店沟通', 4, 1);

-- 2. 插入默认权限（菜单 + 按钮 + 接口三层结构）
INSERT INTO `lj_admin_permission` (`id`, `parent_id`, `permission_name`, `permission_code`, `permission_type`, `path`, `icon`, `sort_order`, `status`)
VALUES
-- 一级菜单
(1,  0, '门店管理',   'store',             1, '/store',             'shop',       1,  1),
(2,  0, '合伙人管理', 'partner',           1, '/partner',           'team',       2,  1),
(3,  0, '商品管理',   'product',           1, '/product',           'goods',      3,  1),
(4,  0, '订单管理',   'order',             1, '/order',             'document',   4,  1),
(5,  0, '库存管理',   'inventory',         1, '/inventory',         'warehouse',  5,  1),
(6,  0, '财务管理',   'finance',           1, '/finance',           'money',      6,  1),
(7,  0, '售后管理',   'after_sale',        1, '/after-sale',        'service',    7,  1),
(8,  0, '发票管理',   'invoice',           1, '/invoice',           'bill',       8,  1),
(9,  0, '系统管理',   'system',            1, '/system',            'setting',    9,  1),

-- 门店管理 - 按钮/接口
(10, 1, '查看门店列表', 'store.list',       2, NULL, NULL, 1, 1),
(11, 1, '门店详情',     'store.detail',     2, NULL, NULL, 2, 1),
(12, 1, '新建门店',     'store.create',     2, NULL, NULL, 3, 1),
(13, 1, '编辑门店',     'store.update',     2, NULL, NULL, 4, 1),
(14, 1, '修改状态',     'store.status',     2, NULL, NULL, 5, 1),

-- 合伙人管理
(15, 2, '查看列表',     'partner.list',     2, NULL, NULL, 1, 1),
(16, 2, '合伙人详情',   'partner.detail',   2, NULL, NULL, 2, 1),
(17, 2, '新建/编辑',    'partner.save',     2, NULL, NULL, 3, 1),

-- 商品管理
(18, 3, '面料列表',     'product.fabric.list',       2, NULL, NULL, 1, 1),
(19, 3, '面料新增编辑', 'product.fabric.save',       2, NULL, NULL, 2, 1),
(20, 3, '面料批量导入', 'product.fabric.import',     2, NULL, NULL, 3, 1),
(21, 3, '面料批量调价', 'product.fabric.batch_price',2, NULL, NULL, 4, 1),
(22, 3, '轨道管理',     'product.track',             2, NULL, NULL, 5, 1),
(23, 3, '配件管理',     'product.accessory',         2, NULL, NULL, 6, 1),
(24, 3, '套件管理',     'product.kit',               2, NULL, NULL, 7, 1),
(25, 3, '供应商管理',   'product.supplier',          2, NULL, NULL, 8, 1),

-- 订单管理
(26, 4, '订单列表',     'order.list',       2, NULL, NULL, 1, 1),
(27, 4, '订单详情',     'order.detail',     2, NULL, NULL, 2, 1),
(28, 4, '技术审核',     'order.audit',      2, NULL, NULL, 3, 1),
(29, 4, '排产',         'order.production', 2, NULL, NULL, 4, 1),
(30, 4, '发货',         'order.ship',       2, NULL, NULL, 5, 1),
(31, 4, '取消订单',     'order.cancel',     2, NULL, NULL, 6, 1),
(32, 4, '调价',         'order.adjust',     2, NULL, NULL, 7, 1),

-- 库存管理
(33, 5, '库存查询',     'inventory.list',   2, NULL, NULL, 1, 1),
(34, 5, '库存调整',     'inventory.adjust', 2, NULL, NULL, 2, 1),
(35, 5, '库存日志',     'inventory.log',    2, NULL, NULL, 3, 1),

-- 财务管理
(36, 6, '支付记录',     'finance.payment_list',       2, NULL, NULL, 1, 1),
(37, 6, '退款操作',     'finance.refund',             2, NULL, NULL, 2, 1),
(38, 6, '对账导出',     'finance.reconciliation',     2, NULL, NULL, 3, 1),

-- 售后管理
(39, 7, '售后列表',     'after_sale.list',    2, NULL, NULL, 1, 1),
(40, 7, '售后详情',     'after_sale.detail',  2, NULL, NULL, 2, 1),
(41, 7, '处理售后',     'after_sale.process', 2, NULL, NULL, 3, 1),
(42, 7, '关闭售后',     'after_sale.close',   2, NULL, NULL, 4, 1),

-- 发票管理
(43, 8, '发票列表',     'invoice.list',    2, NULL, NULL, 1, 1),
(44, 8, '发票审核',     'invoice.review',  2, NULL, NULL, 2, 1),
(45, 8, '开票',         'invoice.issue',   2, NULL, NULL, 3, 1),

-- 系统管理
(46, 9, '管理员管理',   'system.admin',         2, NULL, NULL, 1, 1),
(47, 9, '角色管理',     'system.role',          2, NULL, NULL, 2, 1),
(48, 9, '权限树',       'system.permission',    2, NULL, NULL, 3, 1),
(49, 9, '操作日志',     'system.operation_log', 2, NULL, NULL, 4, 1),

-- 接口级别（type=3）
(50, 4, '订单审核接口', 'api:order.audit',       3, '/api/v1/admin/order/audit',       NULL, 1, 1),
(51, 9, '管理员增删',   'api:system.admin.write', 3, '/api/v1/admin/system/admin/save', NULL, 2, 1),
(52, 6, '退款接口',     'api:finance.refund',     3, '/api/v1/admin/finance/refund',    NULL, 3, 1),

-- ============================================================
-- 新增：储值管理模块
-- ============================================================
(53, 6, '储值管理',     'finance.balance',      1, '/finance/balance',   'wallet',    10, 1),
(54, 53, '客户资金账户列表', 'finance.balance.account_list',   2, NULL, NULL, 1, 1),
(55, 53, '资金账户详情',     'finance.balance.account_detail',   2, NULL, NULL, 2, 1),
(56, 53, '余额流水查询',     'finance.balance.transaction_list', 2, NULL, NULL, 3, 1),
(57, 53, '储值订单管理',     'finance.balance.recharge_list',   2, NULL, NULL, 4, 1),
(58, 53, '储值审核',         'finance.balance.recharge_review', 2, NULL, NULL, 5, 1),
(59, 53, '人工余额调整',     'finance.balance.manual_adjust',  2, NULL, NULL, 6, 1),
(60, 53, '余额退款',         'finance.balance.refund',          2, NULL, NULL, 7, 1),
(61, 53, '资金对账',         'finance.balance.reconciliation',  2, NULL, NULL, 8, 1),
(62, 53, '测试余额发放',     'finance.balance.test_recharge',   2, NULL, NULL, 9, 1),
(63, 6, '储值账户接口', 'api:finance.balance.account',    3, '/api/v1/admin/finance/balance/account',    NULL, 10, 1),
(64, 6, '储值审核接口', 'api:finance.balance.recharge',   3, '/api/v1/admin/finance/balance/recharge',   NULL, 11, 1),
(65, 6, '余额调整接口', 'api:finance.balance.adjust',     3, '/api/v1/admin/finance/balance/adjust',     NULL, 12, 1),

-- ============================================================
-- 新增：归属管理模块
-- ============================================================
(66, 0, '归属管理',   'ownership',           1, '/ownership',         'link',      10, 1),
(67, 66, '归属关系列表',   'ownership.list',       2, NULL, NULL, 1, 1),
(68, 66, '归属变更',       'ownership.change',     2, NULL, NULL, 2, 1),
(69, 66, '销售转交',       'ownership.transfer',   2, NULL, NULL, 3, 1),
(70, 66, '归属历史',       'ownership.history',    2, NULL, NULL, 4, 1),
(71, 66, '归属变更接口',   'api:ownership.change', 3, '/api/v1/admin/ownership/change',   NULL, 5, 1),

-- ============================================================
-- 新增：价格版本管理
-- ============================================================
(72, 3, '价格版本管理',   'product.price_version',        2, NULL, NULL, 9, 1),
(73, 3, '价格版本列表',   'product.price_version.list',   2, NULL, NULL, 10, 1),
(74, 3, '新建价格版本',   'product.price_version.create', 2, NULL, NULL, 11, 1),
(75, 3, '价格版本详情',   'product.price_version.detail', 2, NULL, NULL, 12, 1),

-- ============================================================
-- 新增：销售人员管理
-- ============================================================
(76, 66, '销售人员列表',   'ownership.sales_list',   2, NULL, NULL, 5, 1),
(77, 66, '销售人员管理',   'ownership.sales_manage', 2, NULL, NULL, 6, 1);

-- 3. 为超级管理员角色绑定全部权限
INSERT INTO `lj_admin_role_permission` (`role_id`, `permission_id`)
SELECT 1, id FROM `lj_admin_permission`;

-- 4. 为运营管理员绑定常用权限（除系统管理和归属管理中的销售转交外）
INSERT INTO `lj_admin_role_permission` (`role_id`, `permission_id`)
SELECT 2, id FROM `lj_admin_permission` WHERE id NOT IN (46, 47, 48, 49, 51, 69, 59, 62);

-- 5. 为财务人员绑定财务+发票+订单查看+储值管理权限
INSERT INTO `lj_admin_role_permission` (`role_id`, `permission_id`)
SELECT 3, id FROM `lj_admin_permission`
WHERE id IN (
    4, 26, 27,                                          -- 订单查看
    36, 37, 38,                                          -- 财务基础
    43, 44, 45,                                          -- 发票管理
    53, 54, 55, 56, 57, 58, 59, 60, 61, 63, 64, 65,    -- 储值管理全权限
    66, 67, 70, 76                                       -- 归属查看
);

-- 6. 为客服绑定售后+订单查看权限
INSERT INTO `lj_admin_role_permission` (`role_id`, `permission_id`)
SELECT 4, id FROM `lj_admin_permission`
WHERE id IN (4, 26, 27, 39, 40, 41, 42);

-- 7. 插入默认超管账号
-- 密码：admin123 （bcrypt hash）
-- 生成方式：php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
-- 注：lj_admin 无 is_super_admin 列，超管身份由 role_id=1（超级管理员角色）表达
INSERT INTO `lj_admin` (`id`, `username`, `password_hash`, `real_name`, `phone`, `email`, `role_id`, `status`)
VALUES
(1, 'admin', '$2y$10$xwRAV9JBVBFyq9ttDWEZIegJkcv4YF1rGwOlM9Vj60B6jjMfezKDG', '超级管理员', '13800000000', 'admin@shishang.com', 1, 1);
