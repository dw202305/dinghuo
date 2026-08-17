<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 支付参数验证器
 */
class PaymentValidate extends Validate
{
    protected $rule = [
        'order_id'    => 'require|integer|gt:0',
        'pay_channel' => 'require|in:1,2',
        'pay_method'  => 'require|in:JSAPI,H5,NATIVE',
    ];

    protected $message = [
        'order_id.require'    => '订单ID不能为空',
        'pay_channel.require' => '支付渠道不能为空',
        'pay_channel.in'      => '支付渠道：1微信 2支付宝',
        'pay_method.require'  => '支付方式不能为空',
        'pay_method.in'       => '支付方式：JSAPI / H5 / NATIVE',
    ];

    protected $scene = [
        'create' => ['order_id', 'pay_channel', 'pay_method'],
        'status' => ['order_id'],
    ];
}
