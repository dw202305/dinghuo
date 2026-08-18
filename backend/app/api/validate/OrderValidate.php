<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 订单参数验证器
 */
class OrderValidate extends Validate
{
    protected $rule = [
        'receiver_name'     => 'require|max:50',
        'receiver_phone'    => 'require|mobile',
        'receiver_province' => 'require',
        'receiver_city'     => 'require',
        'receiver_district' => 'require',
        'receiver_detail'   => 'require|max:500',
        'items'             => 'require|array|min:1',
        'items.*.install_position' => 'require|max:50',
        'items.*.width'     => 'require|number|gt:0',
        'items.*.height'    => 'require|number|gt:0',
        'items.*.area'      => 'require|number|gt:0',
        'items.*.track_color' => 'require',
        'items.*.fabric_no' => 'require|max:50',

        // ── 批次3新增：明细增删改/提交/取消/复制场景 ──
        // （控制器已将前端 width/height 归一化为 width_cm/height_cm）
        'install_position'  => 'require|max:50',
        'width_cm'          => 'require|number|gt:0',
        'height_cm'         => 'require|number|gt:0',
        'track_color'       => 'require|max:20',
        'fabric_no'         => 'require|max:50',
        'source_item_id'    => 'require|integer|gt:0',
        'copy_dimensions'   => 'in:0,1',
        'confirmed'         => 'require|in:1',
        'reason'            => 'require|max:500',
        'project_name'      => 'max:100',
        'end_customer'      => 'max:100',
        'expected_delivery_date' => 'date',
        'invoice_required'  => 'in:0,1',
        'remark'            => 'max:500',
    ];

    protected $message = [
        'receiver_name.require'     => '收件人不能为空',
        'receiver_phone.require'    => '收件人手机号不能为空',
        'receiver_phone.mobile'     => '手机号格式不正确',
        'receiver_province.require' => '省份不能为空',
        'receiver_city.require'     => '城市不能为空',
        'receiver_district.require' => '区县不能为空',
        'receiver_detail.require'   => '详细地址不能为空',
        'items.require'             => '至少包含一副窗帘',
        'items.*.install_position.require' => '安装位置不能为空',
        'items.*.width.require'     => '宽度不能为空',
        'items.*.height.require'    => '高度不能为空',
        'items.*.area.require'      => '面积不能为空',
        'items.*.fabric_no.require' => '面料编号不能为空',
        'install_position.require'  => '安装位置不能为空',
        'width_cm.require'          => '宽度不能为空',
        'width_cm.gt'               => '宽度必须大于0',
        'height_cm.require'         => '高度不能为空',
        'height_cm.gt'              => '高度必须大于0',
        'track_color.require'       => '轨道颜色不能为空',
        'fabric_no.require'         => '面料编号不能为空',
        'source_item_id.require'    => '源窗帘明细ID不能为空',
        'confirmed.require'         => '请确认定制须知',
        'confirmed.in'              => '必须确认定制须知（confirmed=1）',
        'reason.require'            => '取消原因不能为空',
    ];

    protected $scene = [
        'create' => ['receiver_name', 'receiver_phone', 'receiver_province',
                     'receiver_city', 'receiver_district', 'receiver_detail', 'items'],
        // 批次3：更新订单基本信息（全部可选，仅校验传入字段）
        'update' => [
            'receiver_name' => 'max:50',
            'receiver_phone' => 'mobile',
            'receiver_detail' => 'max:500',
            'project_name', 'end_customer', 'expected_delivery_date',
            'invoice_required', 'remark',
        ],
        // 批次3：新增窗帘明细（必填字段齐全）
        'addItem' => ['install_position', 'width_cm', 'height_cm', 'track_color', 'fabric_no'],
        // 批次3：更新窗帘明细（均可选，仅校验传入字段）
        'updateItem' => [
            'install_position' => 'max:50',
            'width_cm'  => 'number|gt:0',
            'height_cm' => 'number|gt:0',
            'track_color' => 'max:20',
            'fabric_no' => 'max:50',
            'remark',
        ],
        // 批次3：复制窗帘明细
        'copyItem' => ['source_item_id', 'copy_dimensions'],
        // 批次3：提交订单（必须确认定制须知）
        'submit' => ['confirmed'],
        // 批次3：取消订单
        'cancel' => ['reason'],
    ];
}
