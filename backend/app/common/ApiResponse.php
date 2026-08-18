<?php
declare(strict_types=1);

namespace app\common;

use app\common\enum\ErrorCode;
use app\common\middleware\RequestIdMiddleware;
use think\Response;

/**
 * API 统一响应 Trait
 *
 * 所有 Controller 引入此 Trait，统一返回格式。
 * 对齐规范 §14.1：所有响应包含 code / message / data / request_id。
 */
trait ApiResponse
{
    /**
     * 获取当前请求的 request_id
     *
     * @return string
     */
    protected function getRequestId(): string
    {
        $request = app('request');
        return $request->{RequestIdMiddleware::REQUEST_ATTR}
            ?? $request->header('X-Request-Id', '')
            ?? '';
    }

    /**
     * 成功响应
     * @param mixed  $data    返回数据
     * @param string $message 提示信息
     * @param int    $code    业务码
     * @return Response
     */
    protected function success(mixed $data = null, string $message = 'success', int $code = 0): Response
    {
        // 成功响应固定 HTTP 200（规范 §14.2）
        return json([
            'code'       => $code,
            'message'    => $message,
            'data'       => $data,
            'request_id' => $this->getRequestId(),
        ], 200);
    }

    /**
     * 失败响应
     *
     * HTTP 状态码按业务错误码段映射（规范 §14.2，批次5）：
     * 1xxx→400、2xxx→401、3xxx→403(3002→404)、4xxx→409/422、5xxx→500，
     * 精确映射见 ErrorCode::HTTP_STATUS_MAP，未登记的按段推断。
     *
     * 注意：支付/储值回调接口的应答（微信 {"code":"SUCCESS"}、支付宝 success/failure）
     * 由 Controller 独立返回，不经过本方法，不受此映射影响。
     *
     * @param string $message 错误信息
     * @param int    $code    错误码
     * @param mixed  $data    附加数据
     * @return Response
     */
    protected function error(string $message = 'error', int $code = 5000, mixed $data = null): Response
    {
        return json([
            'code'       => $code,
            'message'    => $message,
            'data'       => $data,
            'request_id' => $this->getRequestId(),
        ], ErrorCode::toHttpStatus($code));
    }

    /**
     * 参数错误
     */
    protected function paramError(string $message = '参数错误'): Response
    {
        return $this->error($message, 1001);
    }

    /**
     * 认证失败
     */
    protected function unauthorized(string $message = '请先登录'): Response
    {
        return $this->error($message, 2001);
    }

    /**
     * 权限不足
     */
    protected function forbidden(string $message = '权限不足'): Response
    {
        return $this->error($message, 3001);
    }

    /**
     * 分页响应
     * @param \think\Paginator $paginator 分页对象
     * @param string           $message   提示信息
     * @return Response
     */
    protected function paginate(\think\Paginator $paginator, string $message = 'success'): Response
    {
        return json([
            'code'    => 0,
            'message' => $message,
            'data'    => [
                'list'      => $paginator->items(),
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'page_size' => $paginator->listRows(),
            ],
            'request_id' => $this->getRequestId(),
        ]);
    }
}
