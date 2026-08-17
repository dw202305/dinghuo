<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 面料参数验证器
 */
class FabricValidate extends Validate
{
    protected $rule = [
        'fabric_no' => 'require|max:50',
        'action'    => 'require|in:0,1',
    ];

    protected $message = [
        'fabric_no.require' => '面料编号不能为空',
        'fabric_no.max'     => '面料编号最长50字符',
        'action.require'    => '操作类型不能为空',
        'action.in'         => '操作类型无效（1收藏/0取消收藏）',
    ];

    protected $scene = [
        'favorite' => ['fabric_no', 'action'],
        'detail'   => ['fabric_no'],
    ];
}
