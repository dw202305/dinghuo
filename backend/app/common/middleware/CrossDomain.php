<?php
declare(strict_types=1);

namespace app\common\middleware;

use think\Request;
use think\Response;

/**
 * 跨域请求中间件
 */
class CrossDomain
{
    /**
     * 处理跨域请求
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-Request-Id, Idempotent-Key');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 3600');

        if ($request->isOptions()) {
            return response('', 204);
        }

        return $next($request);
    }
}
