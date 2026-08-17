<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 地址参数验证器
 */
class AddressValidate extends Validate
{
    protected $rule = [
        'address_id'     => 'require|integer|gt:0',
        'address_type'   => 'require|in:1,2,3',
        'receiver_name'  => 'require|max:50',
        'receiver_phone' => 'require|mobile',
        'province'       => 'require|max:20',
        'city'           => 'require|max:20',
        'district'       => 'require|max:20',
        'detail_address' => 'require|max:500',
    ];

    protected $message = [
        'address_id.require'     => '地址ID不能为空',
        'address_id.integer'     => '地址ID必须为整数',
        'address_type.require'   => '地址类型不能为空',
        'address_type.in'        => '地址类型：1门店地址 2仓库地址 3终端客户地址',
        'receiver_name.require'  => '收件人不能为空',
        'receiver_phone.require' => '手机号不能为空',
        'receiver_phone.mobile'  => '手机号格式不正确',
        'province.require'       => '省份不能为空',
        'city.require'           => '城市不能为空',
        'district.require'       => '区县不能为空',
        'detail_address.require' => '详细地址不能为空',
    ];

    protected $scene = [
        'create'  => ['address_type', 'receiver_name', 'receiver_phone', 'province', 'city', 'district', 'detail_address'],
        'update'  => ['address_id'],
        'delete'  => ['address_id'],
        'default' => ['address_id'],
    ];
}
