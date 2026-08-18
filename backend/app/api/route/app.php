<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | API 路由定义 - 世尚门店订货系统
// | 规范：RESTful 复数资源名 + kebab-case（dev_specification §5.2）
// |
// | 新版门店端前缀：/api/v1/orders、/api/v1/fabrics、/api/v1/payments 等
// | 新版后台端前缀：/api/v1/admin/stores、/api/v1/admin/orders 等
// | 旧版路由（/api/v1/store/*）标记为 deprecated，保留兼容过渡期
// +----------------------------------------------------------------------

use think\facade\Route;

// ============================================================
// 一、门店端公开接口（无需认证） — 新版 RESTful 路由
// ============================================================

// 面料（公开浏览）
Route::get('v1/fabrics', '\app\api\controller\FabricController@list');
Route::get('v1/fabrics/:id', '\app\api\controller\FabricController@detail');

// 轨道（公开浏览）
Route::get('v1/tracks', '\app\api\controller\ProductController@trackList');

// 选装配件（公开浏览）
Route::get('v1/accessories', '\app\api\controller\ProductController@accessoryList');

// 套件信息（公开浏览）
Route::get('v1/kit-info', '\app\api\controller\ProductController@kitInfo');

// 支付回调（无需 Token，支付平台回调）
Route::post('v1/payment-callbacks/wechat', '\app\api\controller\PaymentController@wechatNotify');
Route::post('v1/payment-callbacks/alipay', '\app\api\controller\PaymentController@alipayNotify');

// 门店端公开认证端点（登录前无 Token，必须置于 auth 鉴权组之外）
Route::post('v1/auth/send-code', '\app\api\controller\AuthController@sendCode');
Route::post('v1/auth/login', '\app\api\controller\AuthController@login');
Route::post('v1/auth/wechat-login', '\app\api\controller\AuthController@wechatLogin');

// 后台管理端公开认证端点（登录前无 Token，必须置于 admin_auth 鉴权组之外）
Route::post('v1/admin/auth/login', '\app\api\controller\admin\AdminAuthController@login');

// ============================================================
// 二、门店端需认证接口 — 新版 RESTful 路由
// ============================================================
Route::group('v1', function () {

    // ---- 2.1 认证模块（公开端点 send-code/login/wechat-login 已移出本鉴权组，见第一节）----
    Route::post('auth/logout', '\app\api\controller\AuthController@logout');
    Route::get('auth/profile', '\app\api\controller\AuthController@profile');
    Route::get('auth/me', '\app\api\controller\AuthController@me');
    Route::post('auth/switch-store', '\app\api\controller\AuthController@switchStore');

    // ---- 2.2 门店首页 ----
    Route::get('dashboard', '\app\api\controller\HomeController@dashboard');

    // ---- 2.3 订单模块（复数资源名 orders）----
    Route::post('orders', '\app\api\controller\OrderController@save');
    Route::get('orders', '\app\api\controller\OrderController@index');
    Route::get('orders/:order_no', '\app\api\controller\OrderController@read');
    Route::put('orders/:order_no', '\app\api\controller\OrderController@update');
    Route::post('orders/:order_no/items', '\app\api\controller\OrderController@addItem');
    Route::put('orders/:order_no/items/:item_id', '\app\api\controller\OrderController@updateItem');
    Route::delete('orders/:order_no/items/:item_id', '\app\api\controller\OrderController@deleteItem');
    Route::post('orders/:order_no/items/copy', '\app\api\controller\OrderController@copyItem');
    Route::get('orders/:order_no/preview', '\app\api\controller\OrderController@preview');
    Route::post('orders/:order_no/submit', '\app\api\controller\OrderController@submit');
    Route::put('orders/:order_no/cancel', '\app\api\controller\OrderController@cancel');
    // 预审申请（门店端）
    Route::post('orders/:order_no/pre-audit/request', '\app\api\controller\OrderController@requestPreAudit');
    Route::delete('orders/:order_no', '\app\api\controller\OrderController@delete');
    // 新增：价格预览（规范 §8 计价，下单前查看后端计算结果）
    Route::post('orders/:order_no/price-preview', '\app\api\controller\OrderController@pricePreview');
    // 新增：创建支付（嵌套在订单资源下）
    Route::post('orders/:order_no/payments', '\app\api\controller\PaymentController@create');
    // 查询支付状态
    Route::get('orders/:order_no/payments/status', '\app\api\controller\PaymentController@status');

    // ---- 2.4 面料模块（复数资源名 fabrics）----
    Route::post('fabrics/:id/favorite', '\app\api\controller\FabricController@favorite');
    Route::get('fabrics/favorites', '\app\api\controller\FabricController@favorites');
    Route::get('fabrics/recent', '\app\api\controller\FabricController@recent');

    // ---- 2.5 库存模块 ----
    Route::get('inventory/kit', '\app\api\controller\InventoryController@kit');
    Route::get('inventory/logs', '\app\api\controller\InventoryController@log');

    // ---- 2.6 收货地址模块（复数资源名 addresses）----
    Route::get('addresses', '\app\api\controller\AddressController@list');
    Route::post('addresses', '\app\api\controller\AddressController@create');
    Route::put('addresses/:id', '\app\api\controller\AddressController@update');
    Route::delete('addresses/:id', '\app\api\controller\AddressController@delete');
    Route::put('addresses/:id/set-default', '\app\api\controller\AddressController@setDefault');

    // ---- 2.7 售后模块（复数资源名 after-sales）----
    Route::post('after-sales', '\app\api\controller\AfterSaleController@create');
    Route::get('after-sales', '\app\api\controller\AfterSaleController@list');
    Route::get('after-sales/:id', '\app\api\controller\AfterSaleController@detail');
    Route::put('after-sales/:id/supplement', '\app\api\controller\AfterSaleController@supplement');

    // ---- 2.8 发票模块（复数资源名 invoices）----
    Route::post('invoices', '\app\api\controller\InvoiceController@create');
    Route::get('invoices', '\app\api\controller\InvoiceController@list');
    Route::get('invoices/:id', '\app\api\controller\InvoiceController@detail');

    // ---- 2.9 储值账户与余额（新增，规范 §4.9 & §14.5）----
    // 注：BalanceAccountController 已实现（recharge/pay/transactions/detail
    // 均委托 BalanceAccountService，见 app/api/controller/BalanceAccountController.php）
    Route::post('balance-accounts/:id/recharge', '\app\api\controller\BalanceAccountController@recharge');
    Route::post('balance-accounts/:id/pay', '\app\api\controller\BalanceAccountController@pay');
    Route::get('balance-accounts/:id/transactions', '\app\api\controller\BalanceAccountController@transactions');
    Route::get('balance-accounts/:id', '\app\api\controller\BalanceAccountController@detail');


    // ---- 2.10 面料系列与筛选选项 ----
    Route::get('fabrics/series', '\app\api\controller\FabricController@series');
    Route::get('fabrics/filter-options', '\app\api\controller\FabricController@filterOptions');

    // ---- 2.11 订单余额支付和确认收货 ----
    Route::post('orders/:order_no/pay-balance', '\app\api\controller\OrderController@payBalance');
    Route::post('orders/:order_no/confirm-receive', '\app\api\controller\OrderController@confirmReceive');

    // ---- 2.12 地址详情 ----
    Route::get('addresses/:id', '\app\api\controller\AddressController@detail');

    // ---- 2.13 面料库存 ----
    Route::get('inventory/fabric-stock', '\app\api\controller\InventoryController@fabricStock');

    // ---- 2.14 墙面控制器 ----
    Route::get('products/wall-controller', '\app\api\controller\ProductController@wallControllerList');

})->middleware('auth');

// ============================================================
// 三、后台管理端接口 — 新版 RESTful 路由（复数资源名）
// ============================================================
Route::group('v1/admin', function () {

    // ---- 3.1 认证模块（公开端点 login 已移出本鉴权组，见第一节）----
    Route::post('auth/logout', 'admin/AdminAuthController@logout');
    Route::get('auth/profile', 'admin/AdminAuthController@profile');
    Route::put('auth/password', 'admin/AdminAuthController@changePassword');

    // ---- 3.2 门店管理（复数：stores）----
    Route::get('stores', 'admin/AdminStoreController@list');
    Route::get('stores/:id', 'admin/AdminStoreController@detail');
    Route::post('stores', 'admin/AdminStoreController@create');
    Route::put('stores/:id', 'admin/AdminStoreController@update');
    Route::put('stores/:id/status', 'admin/AdminStoreController@status');
    Route::post('stores/:id/contacts', 'admin/AdminStoreController@contactSave');
    Route::post('stores/:id/accounts', 'admin/AdminStoreController@accountSave');

    // ---- 3.3 城市合伙人管理（复数：partners）----
    Route::get('partners', 'admin/AdminPartnerController@list');
    Route::get('partners/:id', 'admin/AdminPartnerController@detail');
    Route::post('partners', 'admin/AdminPartnerController@save');
    Route::get('partners/:id/stores', 'admin/AdminPartnerController@stores');

    // ---- 3.4 商品管理 ----
    // 面料
    Route::get('products/fabrics', 'admin/AdminProductController@fabricList');
    Route::get('products/fabrics/:id', 'admin/AdminProductController@fabricDetail');
    Route::post('products/fabrics', 'admin/AdminProductController@fabricSave');
    Route::post('products/fabrics/import', 'admin/AdminProductController@fabricImport');
    Route::post('products/fabrics/batch-price', 'admin/AdminProductController@fabricBatchPrice');
    Route::post('products/fabrics/batch-status', 'admin/AdminProductController@fabricBatchStatus');
    // 轨道
    Route::get('products/tracks', 'admin/AdminProductController@trackList');
    Route::post('products/tracks', 'admin/AdminProductController@trackSave');
    // 配件
    Route::get('products/accessories', 'admin/AdminProductController@accessoryList');
    Route::post('products/accessories', 'admin/AdminProductController@accessorySave');
    // 套件
    Route::get('products/kits', 'admin/AdminProductController@kitList');
    Route::post('products/kits', 'admin/AdminProductController@kitSave');
    // 供应商
    Route::get('products/suppliers', 'admin/AdminProductController@supplierList');
    Route::post('products/suppliers', 'admin/AdminProductController@supplierSave');
    Route::post('products/suppliers/mapping', 'admin/AdminProductController@supplierMappingSave');

    // ---- 3.5 订单管理（复数：orders）----
    Route::get('orders', 'admin/AdminOrderController@list');
    Route::get('orders/:id', 'admin/AdminOrderController@detail');
    Route::post('orders/:id/audit', 'admin/AdminOrderController@audit');
    Route::post('orders/:id/production', 'admin/AdminOrderController@production');
    Route::post('orders/:id/ship', 'admin/AdminOrderController@ship');
    Route::post('orders/:id/cancel', 'admin/AdminOrderController@cancel');
    Route::post('orders/:id/adjust-price', 'admin/AdminOrderController@adjustPrice');

    // ---- 3.11 技术审核 ----
    Route::post('orders/:id/audit/switch-to-pre', 'admin/AdminTechnicalAuditController@switchToPreAudit');
    Route::post('orders/:id/audit/result', 'admin/AdminTechnicalAuditController@submitResult');
    Route::get('orders/:id/audit', 'admin/AdminTechnicalAuditController@getAuditDetail');
    Route::get('orders/:id/audit/timeout-check', 'admin/AdminTechnicalAuditController@checkTimeout');

    // ---- 3.6 库存管理（复数：inventories）----
    Route::get('inventories/stores', 'admin/AdminInventoryController@store');
    Route::post('inventories/adjust', 'admin/AdminInventoryController@adjust');
    Route::get('inventories/logs', 'admin/AdminInventoryController@log');

    // ---- 3.7 财务管理（复数：finance）----
    Route::get('finance/payments', 'admin/AdminFinanceController@paymentList');
    Route::post('finance/refunds', 'admin/AdminFinanceController@refund');
    Route::get('finance/reconciliation/export', 'admin/AdminFinanceController@reconciliationExport');
    Route::post('finance/invoices/review', 'admin/AdminFinanceController@invoiceReview');

    // ---- 3.8 发票管理（复数：invoices）----
    Route::get('invoices', 'admin/AdminInvoiceController@list');
    Route::get('invoices/:id', 'admin/AdminInvoiceController@detail');
    Route::post('invoices/:id/review', 'admin/AdminInvoiceController@review');
    Route::post('invoices/:id/issue', 'admin/AdminInvoiceController@issue');

    // ---- 3.9 售后管理（复数：after-sales）----
    Route::get('after-sales', 'admin/AdminAfterSaleController@list');
    Route::get('after-sales/:id', 'admin/AdminAfterSaleController@detail');
    Route::post('after-sales/:id/process', 'admin/AdminAfterSaleController@process');
    Route::post('after-sales/:id/close', 'admin/AdminAfterSaleController@close');

    // ---- 3.10 系统管理 ----
    // 管理员
    Route::get('system/admins', 'admin/AdminSystemController@adminList');
    Route::post('system/admins', 'admin/AdminSystemController@adminSave');
    Route::delete('system/admins/:id', 'admin/AdminSystemController@adminDelete');
    // 角色
    Route::get('system/roles', 'admin/AdminSystemController@roleList');
    Route::post('system/roles', 'admin/AdminSystemController@roleSave');
    Route::delete('system/roles/:id', 'admin/AdminSystemController@roleDelete');
    // 权限树
    Route::get('system/permissions/tree', 'admin/AdminSystemController@permissionTree');
    // 操作日志
    Route::get('system/operation-logs', 'admin/AdminSystemController@operationLog');
    // 归属变更
    Route::post('system/attributions/change', 'admin/AdminSystemController@attributionChange');
    // 销售转交
    Route::post('system/sales/transfers', 'admin/AdminSystemController@salesTransfer');


    // ---- 3.12 客户等级管理 ----
    Route::get('customer-levels', 'admin/AdminStoreController@customerLevelList');
    Route::put('customer-levels/:id', 'admin/AdminStoreController@customerLevelUpdate');

    // ---- 3.13 发货管理 ----
    Route::get('logistics', 'admin/AdminOrderController@logisticsList');
    Route::post('logistics/:id/ship', 'admin/AdminOrderController@logisticsShip');

    // ---- 3.14 生产单管理 ----
    Route::get('production', 'admin/AdminOrderController@productionList');
    Route::post('production/:id/confirm', 'admin/AdminOrderController@productionConfirm');

    // ---- 3.15 客户资金账户/储值审核/资金对账 ----
    Route::get('finance/customer-accounts', 'admin/AdminFinanceController@customerAccounts');
    Route::get('finance/recharge-audit', 'admin/AdminFinanceController@rechargeAudit');
    Route::post('finance/recharge-audit/:id', 'admin/AdminFinanceController@rechargeAuditProcess');
    Route::get('finance/reconciliation', 'admin/AdminFinanceController@financeReconciliation');

    // ---- 3.16 仪表盘统计 ----
    Route::get('dashboard/stats', 'admin/AdminDashboardController@stats');

})->middleware('admin_auth');


// ============================================================
// 四、旧版门店端路由（deprecated — 兼容过渡期，前端迁移后移除）
// ============================================================
// @deprecated 请使用新版 /api/v1/orders、/api/v1/fabrics 等 RESTful 路由
Route::group('store', function () {

    // 面料列表/详情（公开）
    Route::get('fabrics', '\app\api\controller\FabricController@list');
    Route::get('fabrics/:id', '\app\api\controller\FabricController@detail');
    // 轨道列表
    Route::get('tracks', '\app\api\controller\ProductController@trackList');
    // 选装配件列表
    Route::get('accessories', '\app\api\controller\ProductController@accessoryList');
    // 支付回调（无需Token）
    Route::post('payment/notify/wechat', '\app\api\controller\PaymentController@wechatNotify');
    Route::post('payment/notify/alipay', '\app\api\controller\PaymentController@alipayNotify');

})->prefix('v1/store');

// @deprecated 请使用新版认证路由 /api/v1/auth/*
// 旧版门店端公开认证端点（登录前无 Token，必须置于 auth 鉴权组之外）
Route::group('store', function () {

    Route::post('auth/send-code', '\app\api\controller\AuthController@sendCode');
    Route::post('auth/login', '\app\api\controller\AuthController@login');
    Route::post('auth/wechat-login', '\app\api\controller\AuthController@wechatLogin');

})->prefix('v1/store');

// @deprecated 请使用新版认证路由 /api/v1/auth/*
Route::group('store', function () {

    Route::post('auth/logout', '\app\api\controller\AuthController@logout');
    Route::get('auth/profile', '\app\api\controller\AuthController@profile');
    Route::get('auth/me', '\app\api\controller\AuthController@me');
    Route::post('auth/switch-store', '\app\api\controller\AuthController@switchStore');

    Route::get('home/dashboard', '\app\api\controller\HomeController@dashboard');

    // 订单（旧：动词式路径）
    Route::post('order/create', '\app\api\controller\OrderController@save');
    Route::get('order/list', '\app\api\controller\OrderController@index');
    Route::get('order/detail', '\app\api\controller\OrderController@read');
    Route::put('order/update', '\app\api\controller\OrderController@update');
    Route::post('order/item/add', '\app\api\controller\OrderController@addItem');
    Route::put('order/item/update', '\app\api\controller\OrderController@updateItem');
    Route::delete('order/item/delete', '\app\api\controller\OrderController@deleteItem');
    Route::post('order/item/copy', '\app\api\controller\OrderController@copyItem');
    Route::get('order/preview', '\app\api\controller\OrderController@preview');
    Route::post('order/submit', '\app\api\controller\OrderController@submit');
    Route::put('order/cancel', '\app\api\controller\OrderController@cancel');
    Route::delete('order/delete', '\app\api\controller\OrderController@delete');

    // 面料（旧）
    Route::get('fabric/list', '\app\api\controller\FabricController@list');
    Route::get('fabric/detail', '\app\api\controller\FabricController@detail');
    Route::post('fabric/favorite', '\app\api\controller\FabricController@favorite');
    Route::get('fabric/favorites', '\app\api\controller\FabricController@favorites');
    Route::get('fabric/recent', '\app\api\controller\FabricController@recent');

    // 商品（旧）
    Route::get('product/track/list', '\app\api\controller\ProductController@trackList');
    Route::get('product/accessory/list', '\app\api\controller\ProductController@accessoryList');
    Route::get('product/kit/info', '\app\api\controller\ProductController@kitInfo');

    // 支付（旧）
    Route::post('payment/create', '\app\api\controller\PaymentController@create');
    Route::get('payment/status', '\app\api\controller\PaymentController@status');

    // 库存（旧）
    Route::get('inventory/kit', '\app\api\controller\InventoryController@kit');
    Route::get('inventory/log', '\app\api\controller\InventoryController@log');

    // 收货地址（旧）
    Route::get('address/list', '\app\api\controller\AddressController@list');
    Route::post('address/create', '\app\api\controller\AddressController@create');
    Route::put('address/update', '\app\api\controller\AddressController@update');
    Route::delete('address/delete', '\app\api\controller\AddressController@delete');
    Route::put('address/set-default', '\app\api\controller\AddressController@setDefault');

    // 售后（旧）
    Route::post('after-sale/create', '\app\api\controller\AfterSaleController@create');
    Route::get('after-sale/list', '\app\api\controller\AfterSaleController@list');
    Route::get('after-sale/detail', '\app\api\controller\AfterSaleController@detail');
    Route::put('after-sale/supplement', '\app\api\controller\AfterSaleController@supplement');

    // 发票（旧）
    Route::post('invoice/create', '\app\api\controller\InvoiceController@create');
    Route::get('invoice/list', '\app\api\controller\InvoiceController@list');
    Route::get('invoice/detail', '\app\api\controller\InvoiceController@detail');


    // 面料系列和筛选（旧）
    Route::get('fabric/series', '\app\api\controller\FabricController@series');
    Route::get('fabric/filter-options', '\app\api\controller\FabricController@filterOptions');
    // 订单余额支付和确认收货（旧）
    Route::post('order/pay-balance', '\app\api\controller\OrderController@payBalance');
    Route::post('order/confirm-receive', '\app\api\controller\OrderController@confirmReceive');
    // 地址详情（旧）
    Route::get('address/detail', '\app\api\controller\AddressController@detail');
    // 面料库存（旧）
    Route::get('inventory/fabric-stock', '\app\api\controller\InventoryController@fabricStock');
    // 墙面控制器（旧）
    Route::get('product/wall-controller/list', '\app\api\controller\ProductController@wallControllerList');

})->middleware('auth')->prefix('v1/store');


// ============================================================
// 五、旧版后台管理端路由（deprecated — 兼容过渡期）
// ============================================================
// @deprecated 请使用新版 /api/v1/admin/stores、/api/v1/admin/orders 等 RESTful 路由
// 旧版后台管理端公开认证端点（登录前无 Token，必须置于 admin_auth 鉴权组之外）
Route::group('admin', function () {

    Route::post('auth/login', '\app\api\controller\admin\AdminAuthController@login');

})->prefix('v1/admin');

// @deprecated 请使用新版 /api/v1/admin/stores、/api/v1/admin/orders 等 RESTful 路由
Route::group('admin', function () {

    Route::post('auth/logout', 'admin/AdminAuthController@logout');
    Route::get('auth/profile', 'admin/AdminAuthController@profile');
    Route::put('auth/password', 'admin/AdminAuthController@changePassword');

    // 门店管理（旧）
    Route::get('store/list', 'admin/AdminStoreController@list');
    Route::get('store/detail', 'admin/AdminStoreController@detail');
    Route::post('store/create', 'admin/AdminStoreController@create');
    Route::put('store/update', 'admin/AdminStoreController@update');
    Route::put('store/status', 'admin/AdminStoreController@status');
    Route::post('store/contact/save', 'admin/AdminStoreController@contactSave');
    Route::post('store/account/save', 'admin/AdminStoreController@accountSave');

    // 城市合伙人（旧）
    Route::get('partner/list', 'admin/AdminPartnerController@list');
    Route::get('partner/detail', 'admin/AdminPartnerController@detail');
    Route::post('partner/save', 'admin/AdminPartnerController@save');
    Route::get('partner/stores', 'admin/AdminPartnerController@stores');

    // 商品管理（旧）
    Route::get('product/fabric/list', 'admin/AdminProductController@fabricList');
    Route::get('product/fabric/detail', 'admin/AdminProductController@fabricDetail');
    Route::post('product/fabric/save', 'admin/AdminProductController@fabricSave');
    Route::post('product/fabric/import', 'admin/AdminProductController@fabricImport');
    Route::post('product/fabric/batch-price', 'admin/AdminProductController@fabricBatchPrice');
    Route::post('product/fabric/batch-status', 'admin/AdminProductController@fabricBatchStatus');
    Route::get('product/track/list', 'admin/AdminProductController@trackList');
    Route::post('product/track/save', 'admin/AdminProductController@trackSave');
    Route::get('product/accessory/list', 'admin/AdminProductController@accessoryList');
    Route::post('product/accessory/save', 'admin/AdminProductController@accessorySave');
    Route::get('product/kit/list', 'admin/AdminProductController@kitList');
    Route::post('product/kit/save', 'admin/AdminProductController@kitSave');
    Route::get('product/supplier/list', 'admin/AdminProductController@supplierList');
    Route::post('product/supplier/save', 'admin/AdminProductController@supplierSave');
    Route::post('product/supplier/mapping/save', 'admin/AdminProductController@supplierMappingSave');

    // 订单管理（旧）
    Route::get('order/list', 'admin/AdminOrderController@list');
    Route::get('order/detail', 'admin/AdminOrderController@detail');
    Route::post('order/audit', 'admin/AdminOrderController@audit');
    Route::post('order/production', 'admin/AdminOrderController@production');
    Route::post('order/ship', 'admin/AdminOrderController@ship');
    Route::post('order/cancel', 'admin/AdminOrderController@cancel');
    Route::post('order/adjust-price', 'admin/AdminOrderController@adjustPrice');

    // 库存管理（旧）
    Route::get('inventory/store', 'admin/AdminInventoryController@store');
    Route::post('inventory/adjust', 'admin/AdminInventoryController@adjust');
    Route::get('inventory/log', 'admin/AdminInventoryController@log');

    // 财务管理（旧）
    Route::get('finance/payment/list', 'admin/AdminFinanceController@paymentList');
    Route::post('finance/refund', 'admin/AdminFinanceController@refund');
    Route::get('finance/reconciliation/export', 'admin/AdminFinanceController@reconciliationExport');
    Route::post('finance/invoice/review', 'admin/AdminFinanceController@invoiceReview');

    // 发票管理（旧）
    Route::get('invoice/list', 'admin/AdminInvoiceController@list');
    Route::get('invoice/detail', 'admin/AdminInvoiceController@detail');
    Route::post('invoice/review', 'admin/AdminInvoiceController@review');
    Route::post('invoice/issue', 'admin/AdminInvoiceController@issue');

    // 售后管理（旧）
    Route::get('after-sale/list', 'admin/AdminAfterSaleController@list');
    Route::get('after-sale/detail', 'admin/AdminAfterSaleController@detail');
    Route::post('after-sale/process', 'admin/AdminAfterSaleController@process');
    Route::post('after-sale/close', 'admin/AdminAfterSaleController@close');

    // 系统管理（旧）
    Route::get('system/admin/list', 'admin/AdminSystemController@adminList');
    Route::post('system/admin/save', 'admin/AdminSystemController@adminSave');
    Route::delete('system/admin/delete', 'admin/AdminSystemController@adminDelete');
    Route::get('system/role/list', 'admin/AdminSystemController@roleList');
    Route::post('system/role/save', 'admin/AdminSystemController@roleSave');
    Route::delete('system/role/delete', 'admin/AdminSystemController@roleDelete');
    Route::get('system/permission/tree', 'admin/AdminSystemController@permissionTree');
    Route::get('system/operation-log', 'admin/AdminSystemController@operationLog');
    Route::post('system/attribution/change', 'admin/AdminSystemController@attributionChange');
    Route::post('system/sales/transfer', 'admin/AdminSystemController@salesTransfer');


    // 客户等级管理（旧）
    Route::get('customer-level/list', 'admin/AdminStoreController@customerLevelList');
    Route::post('customer-level/update', 'admin/AdminStoreController@customerLevelUpdate');
    // 发货管理（旧）
    Route::get('logistics/list', 'admin/AdminOrderController@logisticsList');
    Route::post('logistics/ship', 'admin/AdminOrderController@logisticsShip');
    // 生产单管理（旧）
    Route::get('production/list', 'admin/AdminOrderController@productionList');
    Route::post('production/confirm', 'admin/AdminOrderController@productionConfirm');
    // 客户资金账户/储值审核/资金对账（旧）
    Route::get('finance/customer-accounts', 'admin/AdminFinanceController@customerAccounts');
    Route::get('finance/recharge-audit/list', 'admin/AdminFinanceController@rechargeAudit');
    Route::post('finance/recharge-audit/process', 'admin/AdminFinanceController@rechargeAuditProcess');
    Route::get('finance/reconciliation', 'admin/AdminFinanceController@financeReconciliation');

})->middleware('admin_auth')->prefix('v1/admin');
