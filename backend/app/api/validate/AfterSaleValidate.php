<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 售后参数验证器
 */
class AfterSaleValidate extends Validate
{
    protected $rule = [
        'order_id'      => 'require|integer|gt:0',
        'problem_type'  => 'require|in:1,2,3,4,5,6,7,8,9,10,11',
        'problem_desc'  => 'require|max:2000',
        'contact_name'  => 'require|max:50',
        'contact_phone' => 'require|mobile',
        'images'        => 'array|max:9',
        'videos'        => 'array|max:3',
        'after_sale_id' => 'require|integer|gt:0',
    ];

    protected $message = [
        'order_id.require'      => '订单ID不能为空',
        'problem_type.require'  => '问题类型不能为空',
        'problem_type.in'       => '问题类型值无效',
        'problem_desc.require'  => '问题描述不能为空',
        'contact_name.require'  => '联系人不能为空',
        'contact_phone.require' => '联系电话不能为空',
        'contact_phone.mobile'  => '联系电话格式不正确',
        'images.array'          => '图片必须为数组',
        'images.max'            => '图片最多9张',
        'videos.array'          => '视频必须为数组',
        'videos.max'            => '视频最多3个',
        'after_sale_id.require' => '售后单ID不能为空',
    ];

    protected $scene = [
        'create'     => ['order_id', 'problem_type', 'problem_desc', 'contact_name', 'contact_phone'],
        'supplement' => ['after_sale_id'],
        'detail'     => ['after_sale_id'],
    ];
}
