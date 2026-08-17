<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

/**
 * 审核相关参数验证器
 */
class AuditValidate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'result'     => 'require|in:approved,needs_confirm,needs_supplement,cannot_produce',
        'remark'     => 'max:500',
        'order_no'   => 'require|max:64',
    ];

    /**
     * 错误消息
     */
    protected $message = [
        'result.require' => '审核结果不能为空',
        'result.in'      => '审核结果只能是：approved, needs_confirm, needs_supplement, cannot_produce',
        'remark.max'     => '备注不能超过500字符',
        'order_no.require' => '订单号不能为空',
    ];

    /**
     * 场景：提交审核结果
     */
    public function sceneSubmit(): static
    {
        return $this->only(['result', 'remark']);
    }

    /**
     * 场景：申请预审
     */
    public function sceneRequest(): static
    {
        return $this->only(['order_no']);
    }
}
