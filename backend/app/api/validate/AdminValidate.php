<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 后台管理员参数验证器
 */
class AdminValidate extends Validate
{
    protected $rule = [
        // 登录
        'username'        => 'require|max:50',
        'password'        => 'require|max:50',
        // 改密码
        'old_password'    => 'require|max:50',
        'new_password'    => 'require|length:8,20|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        'confirm_password' => 'require|confirm:new_password',
        // 门店
        'store_id'        => 'require|integer|gt:0',
        'store_no'        => 'require|max:20',
        'store_name'      => 'require|max:100',
        'store_type'      => 'require|in:1,2,3,4',
        'customer_level'  => 'require|in:1,2,3,4,5',
        'channel_mode'    => 'require|in:1,2',
        'primary_sales_id' => 'require|integer|gt:0',
        'primary_contact_name'  => 'require|max:50',
        'primary_contact_phone' => 'require|mobile',
        // 合伙人
        'partner_id'      => 'require|integer|gt:0',
        'partner_no'      => 'require|max:20',
        'business_entity' => 'require|max:200',
        // 面料
        'fabric_id'       => 'require|integer|gt:0',
        'fabric_no'       => 'require|max:50',
        'name'            => 'require|max:100',
        'price_per_sqm'   => 'require|number|gt:0',
        // 轨道
        'sku'             => 'require|max:50',
        'track_type'      => 'require|in:1,2',
        'color'           => 'require|max:20',
        'standard_length' => 'require|number|gt:0',
        // 库存调整
        'kit_sku'         => 'require|max:50',
        'adjust_quantity' => 'require|integer|neq:0',
        'reason'          => 'require|max:500',
        // 售后处理
        'after_sale_id'   => 'require|integer|gt:0',
        // 发票审核
        'request_id'      => 'require|integer|gt:0',
        'action'          => 'require|in:2,4',
        // 订单审核
        'order_id'        => 'require|integer|gt:0',
        'audit_result'    => 'require|in:1,2,3,4',
        // 管理员
        'admin_id'        => 'require|integer|gt:0',
        'role_id'         => 'require|integer|gt:0',
        'real_name'       => 'require|max:50',
        // 角色
        'role_name'       => 'require|max:50',
        'role_code'       => 'require|max:50',
    ];

    protected $message = [
        'username.require'          => '用户名不能为空',
        'password.require'          => '密码不能为空',
        'old_password.require'      => '原密码不能为空',
        'new_password.require'      => '新密码不能为空',
        'new_password.length'       => '新密码长度8-20位',
        'new_password.regex'        => '新密码需包含大小写字母和数字',
        'confirm_password.require'  => '确认密码不能为空',
        'confirm_password.confirm'  => '两次密码输入不一致',
        'store_id.require'          => '门店ID不能为空',
        'store_no.require'          => '门店编号不能为空',
        'store_name.require'        => '门店名称不能为空',
        'store_type.require'        => '门店类型不能为空',
        'customer_level.require'    => '客户等级不能为空',
        'channel_mode.require'      => '渠道模式不能为空',
        'primary_sales_id.require'  => '主归属销售不能为空',
        'primary_contact_name.require'  => '主联系人姓名不能为空',
        'primary_contact_phone.require' => '主联系人手机号不能为空',
        'primary_contact_phone.mobile'  => '主联系人手机号格式不正确',
        'partner_id.require'        => '合伙人ID不能为空',
        'partner_no.require'        => '合伙人编号不能为空',
        'business_entity.require'   => '企业名称不能为空',
        'fabric_id.require'         => '面料ID不能为空',
        'fabric_no.require'         => '面料编号不能为空',
        'name.require'              => '名称不能为空',
        'price_per_sqm.require'     => '单价不能为空',
        'price_per_sqm.gt'          => '单价必须大于0',
        'sku.require'               => 'SKU不能为空',
        'track_type.require'        => '轨道类型不能为空',
        'track_type.in'             => '轨道类型：1横轨 2竖轨',
        'color.require'             => '颜色不能为空',
        'standard_length.require'   => '标准长度不能为空',
        'kit_sku.require'           => '套件SKU不能为空',
        'adjust_quantity.require'   => '调整数量不能为空',
        'adjust_quantity.neq'       => '调整数量不能为0',
        'reason.require'            => '原因不能为空',
        'after_sale_id.require'     => '售后单ID不能为空',
        'request_id.require'        => '发票申请ID不能为空',
        'action.require'            => '操作不能为空',
        'action.in'                 => '操作值：2审核通过 4驳回',
        'order_id.require'          => '订单ID不能为空',
        'audit_result.require'      => '审核结果不能为空',
        'audit_result.in'           => '审核结果：1通过 2需门店确认 3需补款 4无法生产',
        'admin_id.require'          => '管理员ID不能为空',
        'role_id.require'           => '角色ID不能为空',
        'real_name.require'         => '姓名不能为空',
        'role_name.require'         => '角色名称不能为空',
        'role_code.require'         => '角色编码不能为空',
    ];

    protected $scene = [
        'login'           => ['username', 'password'],
        'change_password' => ['old_password', 'new_password', 'confirm_password'],
        'store_create'    => ['store_no', 'store_name', 'store_type', 'customer_level', 'channel_mode', 'primary_sales_id', 'primary_contact_name', 'primary_contact_phone'],
        'store_update'    => ['store_id'],
        'partner_save'    => ['partner_no', 'business_entity'],
        'fabric_save'     => ['fabric_no', 'name', 'price_per_sqm'],
        'track_save'      => ['sku', 'track_type', 'color', 'standard_length'],
        'inventory_adjust' => ['store_id', 'kit_sku', 'adjust_quantity', 'reason'],
        'order_audit'     => ['order_id', 'audit_result'],
        'after_sale_process' => ['after_sale_id'],
        'invoice_review'  => ['request_id', 'action'],
        'admin_save'      => ['username', 'real_name', 'role_id'],
        'role_save'       => ['role_name', 'role_code'],
    ];
}
