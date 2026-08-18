<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 储值账户参数验证器（批次3新增）
 *
 * 对应 route/app.php 2.9 节 balance-accounts 四条路由。
 */
class BalanceValidate extends Validate
{
    protected $rule = [
        'amount_cent' => 'require|integer|gt:0',
        'pay_channel' => 'require|in:1,2',
        'order_no'    => 'require|max:50',
        'type'        => 'integer|between:1,9',
    ];

    protected $message = [
        'amount_cent.require' => '金额不能为空',
        'amount_cent.integer' => '金额必须为整数（单位：分）',
        'amount_cent.gt'      => '金额必须大于0',
        'pay_channel.require' => '支付渠道不能为空',
        'pay_channel.in'      => '支付渠道无效：1微信 2支付宝',
        'order_no.require'    => '订单号不能为空',
        'type.between'        => '流水类型无效',
    ];

    protected $scene = [
        'recharge'     => ['amount_cent', 'pay_channel'],
        'pay'          => ['order_no', 'amount_cent'],
        'transactions' => ['type'],
    ];
}
