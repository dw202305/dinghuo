<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 认证参数验证器
 */
class AuthValidate extends Validate
{
    protected $rule = [
        'phone'       => 'require|mobile',
        'scene'       => 'require|in:login,bind-wechat,change-phone',
        'verify_code' => 'require|length:6|number',
        'code'        => 'require',
    ];

    protected $message = [
        'phone.require'       => '手机号不能为空',
        'phone.mobile'        => '手机号格式不正确',
        'scene.require'       => '使用场景不能为空',
        'scene.in'            => '使用场景值无效',
        'verify_code.require' => '验证码不能为空',
        'verify_code.length'  => '验证码为6位数字',
        'verify_code.number'  => '验证码只能包含数字',
        'code.require'        => '微信授权code不能为空',
    ];

    protected $scene = [
        'send_code' => ['phone', 'scene'],
        'login'     => ['phone', 'verify_code'],
        'wechat'    => ['code'],
    ];
}
