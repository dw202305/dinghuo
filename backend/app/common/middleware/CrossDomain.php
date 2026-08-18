<?php
declare(strict_types=1);

namespace app\common\middleware;

use think\Request;
use think\Response;

/**
 * 跨域请求中间件（CORS 白名单模式，规范 §16）
 *
 * 批次5改造要点：
 * - 禁用原生 header()，统一经 think\Response 对象设置响应头；
 * - Access-Control-Allow-Origin 仅当请求 Origin 命中白名单时才回显该 Origin，
 *   未命中不输出 ACAO 头（浏览器侧即拦截）；
 * - 白名单来源：env CORS_ALLOWED_ORIGINS（[CORS] ALLOWED_ORIGINS，逗号分隔），
 *   缺省为三生产域名 + 本地开发域名。
 */
class CrossDomain
{
    /**
     * 缺省白名单（env 未配置时生效）：
     * 三个生产域名 + 本地 Vite 开发端口（localhost / 127.0.0.1）
     */
    private const DEFAULT_ALLOWED_ORIGINS = 'https://admin.shengshikunyuan.com,https://api.shengshikunyuan.com,https://shop.shengshikunyuan.com,http://localhost:5173,http://localhost:5174,http://127.0.0.1:5173';

    /**
     * 处理跨域请求
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $headers = [
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, X-Request-Id, Idempotent-Key',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Max-Age'       => '3600',
        ];

        // Origin 命中白名单才回显（携带凭证场景必须精确回显，不能用 *）
        $origin = (string) $request->header('Origin', '');
        if ($origin !== '' && in_array($origin, $this->allowedOrigins(), true)) {
            $headers['Access-Control-Allow-Origin']      = $origin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
            $headers['Vary']                             = 'Origin';
        }

        // 预检请求直接短路返回 204
        if ($request->isOptions()) {
            return response('', 204)->header($headers);
        }

        $response = $next($request);
        if ($response instanceof Response) {
            $response->header($headers);
        }

        return $response;
    }

    /**
     * 解析 CORS 白名单列表
     *
     * env 配置形如（backend/.env）：
     *   [CORS]
     *   ALLOWED_ORIGINS = https://admin.shengshikunyuan.com,...
     *
     * @return string[]
     */
    private function allowedOrigins(): array
    {
        $raw = (string) env('cors.allowed_origins', self::DEFAULT_ALLOWED_ORIGINS);

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($o) => $o !== ''));
    }
}
