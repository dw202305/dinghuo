<?php
declare(strict_types=1);

namespace app\common\middleware;

use think\Request;
use think\Response;

/**
 * 请求唯一标识中间件
 *
 * 对齐规范 §14.1 统一响应格式 & §18.1 请求日志：
 * 1. 每个请求生成唯一 request_id（格式：req_ + 短 UUID）
 * 2. 若请求头已携带 X-Request-Id，则复用（链路追踪场景）
 * 3. 将 request_id 注入响应 JSON 的 request_id 字段
 * 4. 将 request_id 注册到 ThinkPHP 的 Log 上下文
 * 5. 注册到中间件管道（在 CrossDomain 之后）
 */
class RequestIdMiddleware
{
    /**
     * 请求头名称
     */
    private const HEADER_NAME = 'X-Request-Id';

    /**
     * request_id 在 ThinkPHP Request 属性上的键名
     */
    public const REQUEST_ATTR = 'request_id';

    /**
     * 处理请求
     *
     * @param Request  $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // 1. 尝试从请求头复用已有的 request_id
        $requestId = $request->header(self::HEADER_NAME, '');

        // 2. 若未携带，则生成新的 request_id
        if (empty($requestId)) {
            $requestId = 'req_' . $this->generateShortUuid();
        }

        // 3. 注入到 Request 对象，后续 Controller / Service 可通过 $request->requestId 获取
        $request->{self::REQUEST_ATTR} = $requestId;

        // 4. 注册到 ThinkPHP Log 上下文，所有日志自动附带 request_id
        if (class_exists(\think\facade\Log::class)) {
            \think\facade\Log::withContext([
                'request_id' => $requestId,
            ]);
        }

        // 5. 执行后续中间件 / 控制器
        /** @var Response $response */
        $response = $next($request);

        // 6. 向响应注入 request_id（仅对 JSON 响应生效）
        if ($response instanceof \think\response\Json) {
            $data = $response->getData();
            if (is_array($data)) {
                $data['request_id'] = $requestId;
                $response->data($data);
            }
        }

        // 7. 在响应头中也携带，方便前端 / 调试工具
        $response->header([
            self::HEADER_NAME => $requestId,
        ]);

        return $response;
    }

    /**
     * 生成短 UUID（22 位，去连字符的 UUID v4 前 22 位）
     * 格式示例：550e8400e29b41d4a7ab
     *
     * @return string
     */
    private function generateShortUuid(): string
    {
        // 优先使用 random_bytes 生成安全随机值
        $bytes = random_bytes(16);
        // 设置版本为 4（RFC 4122）
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        // 设置变体
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        // 转为十六进制字符串，取前 22 位
        $hex = bin2hex($bytes);
        return substr($hex, 0, 22);
    }
}
