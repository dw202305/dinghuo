<?php
declare(strict_types=1);

namespace app\common\exception;

use app\common\enum\ErrorCode;

/**
 * 统一业务异常
 *
 * 所有业务逻辑中可预期的错误，均应抛出此异常（或其子类）。
 * 由 ExceptionHandler 统一捕获并转换为标准 JSON 响应（规范 §14.1-14.3）。
 *
 * 使用示例：
 *   throw new BusinessException(ErrorCode::INVENTORY_INSUFFICIENT, '套件库存不足');
 *   throw new BusinessException(ErrorCode::BALANCE_INSUFFICIENT, '可用余额不足以支付本订单');
 */
class BusinessException extends \RuntimeException
{
    /**
     * 业务错误码（ErrorCode 常量）
     */
    protected int $errorCode;

    /**
     * 用户可读的错误描述
     */
    protected string $errorMessage;

    /**
     * @param int             $errorCode    业务错误码，使用 ErrorCode 常量
     * @param string          $errorMessage 错误描述（为空时自动取 ErrorCode 默认文案）
     * @param \Throwable|null $previous     前置异常
     */
    public function __construct(int $errorCode, string $errorMessage = '', \Throwable $previous = null)
    {
        $this->errorCode    = $errorCode;
        $this->errorMessage = $errorMessage;

        parent::__construct($errorMessage, $errorCode, $previous);
    }

    /**
     * 获取业务错误码
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * 获取错误描述
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * 获取对应的 HTTP 状态码
     */
    public function getHttpStatus(): int
    {
        return ErrorCode::toHttpStatus($this->errorCode);
    }
}
