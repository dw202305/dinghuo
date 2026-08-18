<?php
declare(strict_types=1);

namespace app\common\exception;

use app\common\enum\ErrorCode;
use app\common\middleware\RequestIdMiddleware;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\Request;
use think\Response;
use Throwable;

/**
 * 统一异常处理器
 *
 * 对齐规范 §14.1-14.3：
 * - BusinessException     → 返回对应业务错误码（4xxx 为主）
 * - ValidateException     → 返回 1xxx 参数错误
 * - AuthenticationException → 返回 2xxx 认证错误
 * - PermissionException   → 返回 3xxx 权限错误
 * - HttpException (404)   → 返回 3002 数据不存在
 * - 其他 Exception        → 返回 5000 系统错误（不暴露堆栈和内部信息）
 * - 所有响应包含 request_id
 *
 * 注册方式：在 app/ExceptionHandle.php 中继承此类，或在 config/app.php 中配置。
 */
class ExceptionHandler extends Handle
{
    /**
     * 不需要记录日志的异常类列表
     * （这些是可预期的业务异常，无需写入 error log）
     */
    protected $ignoreReport = [
        BusinessException::class,
        ValidateException::class,
        HttpException::class,
    ];

    /**
     * 渲染异常为 HTTP 响应
     *
     * @param \think\Response|\think\exception\Handle $this
     * @param Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // 获取 request_id（可能由 RequestIdMiddleware 注入）
        $requestId = $request->{RequestIdMiddleware::REQUEST_ATTR}
            ?? $request->header('X-Request-Id', '')
            ?? 'req_unknown';

        // API 请求统一返回 JSON
        if ($this->isApiRequest($request)) {
            return $this->renderApiException($request, $e, $requestId);
        }

        // 非 API 请求走默认渲染（页面等）
        return parent::render($request, $e);
    }

    /**
     * 渲染 API 异常为统一 JSON 格式
     *
     * @param Request   $request
     * @param Throwable $e
     * @param string    $requestId
     * @return Response
     */
    protected function renderApiException(Request $request, Throwable $e, string $requestId): Response
    {
        // ── 1. BusinessException：业务异常 ──
        if ($e instanceof BusinessException) {
            return $this->buildJsonResponse(
                $e->getErrorCode(),
                $e->getErrorMessage(),
                null,
                $requestId,
                $e->getHttpStatus()
            );
        }

        // ── 2. ValidateException：参数校验异常（ThinkPHP 内置） ──
        if ($e instanceof ValidateException) {
            return $this->buildJsonResponse(
                ErrorCode::PARAM_INVALID,
                $e->getMessage() ?: '参数校验失败',
                null,
                $requestId,
                400
            );
        }

        // ── 3. HttpException：HTTP 状态异常 ──
        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            return match ($statusCode) {
                401 => $this->buildJsonResponse(
                    ErrorCode::UNAUTHENTICATED,
                    $e->getMessage() ?: '请先登录',
                    null,
                    $requestId,
                    401
                ),
                403 => $this->buildJsonResponse(
                    ErrorCode::FORBIDDEN,
                    $e->getMessage() ?: '无权访问',
                    null,
                    $requestId,
                    403
                ),
                404 => $this->buildJsonResponse(
                    ErrorCode::DATA_NOT_FOUND,
                    $e->getMessage() ?: '资源不存在',
                    null,
                    $requestId,
                    404
                ),
                405 => $this->buildJsonResponse(
                    ErrorCode::PARAM_INVALID,
                    '请求方法不允许',
                    null,
                    $requestId,
                    405
                ),
                429 => $this->buildJsonResponse(
                    ErrorCode::RATE_LIMITED,
                    '请求过于频繁，请稍后重试',
                    null,
                    $requestId,
                    429
                ),
                default => $this->buildJsonResponse(
                    ErrorCode::SYSTEM_ERROR,
                    $e->getMessage() ?: '请求错误',
                    null,
                    $requestId,
                    $statusCode
                ),
            };
        }

        // ── 4. PDOException / QueryException：数据库异常 ──
        if ($this->isDatabaseException($e)) {
            $this->report($e);
            return $this->buildJsonResponse(
                ErrorCode::DATABASE_ERROR,
                '数据库操作异常',
                null,
                $requestId,
                500
            );
        }

        // ── 5. 其他未预期异常：返回通用错误，不暴露堆栈 ──
        $this->report($e);

        $message = '服务器内部错误';
        // 调试模式下返回简要异常信息（仅限开发/测试环境）
        if ($this->app->isDebug()) {
            $message = $e->getMessage() ?: '服务器内部错误';
        }

        return $this->buildJsonResponse(
            ErrorCode::SYSTEM_ERROR,
            $message,
            null,
            $requestId,
            500
        );
    }

    /**
     * 构建统一 JSON 响应
     *
     * @param int    $code       业务错误码
     * @param string $message    错误描述
     * @param mixed  $data       附加数据
     * @param string $requestId  请求唯一标识
     * @param int    $httpStatus HTTP 状态码
     * @return Response
     */
    protected function buildJsonResponse(
        int    $code,
        string $message,
        mixed  $data,
        string $requestId,
        int    $httpStatus = 200
    ): Response {
        return json([
            'code'       => $code,
            'message'    => $message,
            'data'       => $data,
            'request_id' => $requestId,
        ], $httpStatus);
    }

    /**
     * 判断当前请求是否为 API 请求
     *
     * @param Request $request
     * @return bool
     */
    protected function isApiRequest(Request $request): bool
    {
        // 1. URL 路径以 /api/ 开头
        if (str_starts_with($request->pathinfo(), 'api/')) {
            return true;
        }

        // 2. Accept 头包含 application/json
        $accept = $request->header('Accept', '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // 3. X-Requested-With 头为 XMLHttpRequest
        if ($request->header('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }

        return false;
    }

    /**
     * 判断异常是否为数据库相关异常
     *
     * @param Throwable $e
     * @return bool
     */
    protected function isDatabaseException(Throwable $e): bool
    {
        $dbExceptionClasses = [
            \PDOException::class,
        ];

        // ThinkPHP 可能有自己的数据库异常类
        if (class_exists(\think\exception\PDOException::class)) {
            $dbExceptionClasses[] = \think\exception\PDOException::class;
        }

        foreach ($dbExceptionClasses as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
