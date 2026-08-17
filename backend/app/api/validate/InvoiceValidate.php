<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 发票参数验证器
 */
class InvoiceValidate extends Validate
{
    protected $rule = [
        'order_id'        => 'require|integer|gt:0',
        'invoice_type'    => 'require|in:1,2',
        'title'           => 'require|max:200',
        'tax_no'          => 'require|max:30',
        'tax_rate'        => 'require|number|gt:0',
        'invoice_amount'  => 'require|number|gt:0',
        'request_id'      => 'require|integer|gt:0',
    ];

    protected $message = [
        'order_id.require'       => '订单ID不能为空',
        'invoice_type.require'   => '发票类型不能为空',
        'invoice_type.in'        => '发票类型：1普票 2专票',
        'title.require'          => '发票抬头不能为空',
        'tax_no.require'         => '税号不能为空',
        'tax_rate.require'       => '税率不能为空',
        'invoice_amount.require' => '开票金额不能为空',
        'invoice_amount.gt'      => '开票金额必须大于0',
        'request_id.require'     => '发票申请ID不能为空',
    ];

    protected $scene = [
        'create' => ['order_id', 'invoice_type', 'title', 'tax_no', 'tax_rate', 'invoice_amount'],
        'detail' => ['request_id'],
    ];
}
