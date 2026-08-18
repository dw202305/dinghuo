<?php
declare(strict_types=1);

namespace app\common\exception;

use think\exception\ValidateException;

/**
 * 携带业务错误码的校验异常
 *
 * 背景：think-validate 的 ValidateException 构造签名为 ($error, $key)，
 * 第二参数是"校验字段名"而非错误码，直接传业务码会导致 getCode() 恒为 0
 * （批次5 遗留问题，批次6 资金安全测试中触发并确认）。
 *
 * 本类在保持 ValidateException 捕获兼容性的前提下携带 ErrorCode 业务码，
 * 供控制器按 $e->getCode() 返回标准错误码（规范 §14.3）。
 *
 * 使用示例：
 *   throw new CodedValidateException('库存套件不足，请调整库存使用策略', ErrorCode::INVENTORY_INSUFFICIENT);
 */
class CodedValidateException extends ValidateException
{
    public function __construct(string $message, int $errorCode = 0)
    {
        parent::__construct($message);
        $this->code = $errorCode;
    }
}
