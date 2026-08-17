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
    ];

    protected $scene = [
        'create' => ['receiver_name', 'receiver_phone', 'receiver_province',
                     'receiver_city', 'receiver_district', 'receiver_detail', 'items'],
    ];
}
