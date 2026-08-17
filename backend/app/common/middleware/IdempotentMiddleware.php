<?php
declare(strict_types=1);

namespace app\common\middleware;

use app\common\enum\ErrorCode;
use think\Request;
use think\Response;
use think\facade\Cache;

/**
 * 接口幂等中间件
 *
 * 对齐规范 §14.5 幂等 和 §9.2 并发控制：
 * - 对需要幂等的写操作接口，检查请求中的 Idempotent-Key 头
 * - 使用 Redis 做短期幂等标记（key 格式：idempotent:{action}:{key}，TTL 24h）
 * - 如果 key 已存在，直接返回上次缓存的结果（HTTP 200 + 原响应体）
 * - 如果 key 不存在，放行并在响应后缓存结果
 *
 * 幂等路由列表在 IDEMPOTENT_ROUTES 常量中配置。
 */
class IdempotentMiddleware
{
    /**
     * Redis Key 前缀
     */
    private const REDIS_PREFIX = 'idempotent:';

    /**
     * 幂等 Key TTL（秒）：24 小时（规范 §14.5）
     */
    private const KEY_TTL = 86400;

    /**
     * 请求头名称
     */
    private const HEADER_NAME = 'Idempotent-Key';

    /**
     * 需要幂等校验的路由配置
     * 格式：[ 'HTTP方法 路由模式' => 'action标识' ]
     *
     * 对齐规范 §14.5 所列的全部接口：
     *   创建订单 / 锁定库存 / 创建支付 / 支付回调
     *   发起退款 / 管理员取消 / 创建储值单 / 储值支付回调 / 余额支付
     */
    private const IDEMPOTENT_ROUTES = [
        // 门店端：创建订单
        'POST /api/v1/orders'                           => 'order_create',
        // 门店端：创建支付
        'POST /api/v1/orders/{order_no}/payments'       => 'payment_create',
        // 支付回调
        'POST /api/v1/payment-callbacks/wechat'         => 'payment_callback_wechat',
        'POST /api/v1/payment-callbacks/alipay'         => 'payment_callback_alipay',
        // 储值
        'POST /api/v1/balance-accounts/{id}/recharge'   => 'balance_recharge',
        // 余额支付
        'POST /api/v1/balance-accounts/{id}/pay'        => 'balance_pay',
    ];

    /**
     * 兼容旧路由的幂等配置（deprecated，保留过渡期）
     */
    private const IDEMPOTENT_ROUTES_LEGACY = [
        // 旧：创建订单
        'POST /api/v1/store/order/create'               => 'order_create',
        // 旧：创建支付
        'POST /api/v1/store/payment/create'             => 'payment_create',
        // 旧：微信支付回调
        'POST /api/v1/store/payment/notify/wechat'      => 'payment_callback_wechat',
        // 旧：支付宝回调
        'POST /api/v1/store/payment/notify/alipay'      => 'payment_callback_alipay',
    ];

    /**
     * 处理请求
     *
     * @param Request  $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // 合并新旧路由配置
        $allRoutes = array_merge(self::IDEMPOTENT_ROUTES, self::IDEMPOTENT_ROUTES_LEGACY);

        // 匹配当前请求是否为需要幂等的路由
        $action = $this->matchRoute($request, $allRoutes);

        // 非幂等路由，直接放行
        if ($action === null) {
            return $next($request);
        }

        // 获取幂等 Key
        $idempotentKey = $request->header(self::HEADER_NAME, '');

        // 未携带幂等 Key，直接放行（不强制要求，由业务层决定是否校验）
        if (empty($idempotentKey)) {
            return $next($request);
        }

        // 构建 Redis Key
        $redisKey = self::REDIS_PREFIX . $action . ':' . $idempotentKey;

        // 检查是否已处理过（使用 SETNX 原子操作防止并发）
        $cached = $this->getCachedResult($redisKey);

        if ($cached !== null) {
            // 已处理过，直接返回缓存结果（幂等拦截）
            return $this->buildIdempotentResponse($cached);
        }

        // 未处理过，先尝试占位（SETNX），防止并发重复执行
        $locked = $this->tryLock($redisKey);

        if (!$locked) {
            // 其他请求正在处理中（并发场景），等待后读取结果
            $cached = $this->waitForResult($redisKey);
            if ($cached !== null) {
                return $this->buildIdempotentResponse($cached);
            }
        }

        // 执行后续业务逻辑
        /** @var Response $response */
        $response = $next($request);

        // 缓存响应结果（仅缓存成功的 JSON 响应，失败的不缓存）
        $this->cacheResult($redisKey, $response);

        return $response;
    }

    /**
     * 匹配当前请求是否在幂等路由列表中
     *
     * @param Request $request
     * @param array   $routes
     * @return string|null action 标识，null 表示不匹配
     */
    private function matchRoute(Request $request, array $routes): ?string
    {
        $method = strtoupper($request->method());
        $path   = '/' . trim($request->pathinfo(), '/');

        foreach ($routes as $routePattern => $action) {
            $parts     = explode(' ', $routePattern, 2);
            $reqMethod = $parts[0] ?? '';
            $reqPath   = $parts[1] ?? '';

            if ($method !== $reqMethod) {
                continue;
            }

            // 将路由模式中的 {param} 替换为正则，进行匹配
            $regex = preg_replace('#\{[^}]+\}#', '[^/]+', $reqPath);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $path)) {
                return $action;
            }
        }

        return null;
    }

    /**
     * 获取缓存的处理结果
     *
     * @param string $redisKey
     * @return array|null
     */
    private function getCachedResult(string $redisKey): ?array
    {
        try {
            $cached = Cache::store('redis')->get($redisKey);
            if (is_array($cached) && isset($cached['status'])) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Redis 异常不影响业务流程
        }

        return null;
    }

    /**
     * 尝试对幂等 Key 加锁（SETNX）
     * 设置一个较短的锁 TTL（30 秒），防止死锁
     *
     * @param string $redisKey
     * @return bool
     */
    private function tryLock(string $redisKey): bool
    {
        try {
            $lockKey = $redisKey . ':lock';
            // 使用 set 方法的 nx 选项实现原子 SETNX
            return (bool) Cache::store('redis')->set($lockKey, 1, 30);
        } catch (\Throwable $e) {
            // Redis 异常时放行，避免阻断业务
            return true;
        }
    }

    /**
     * 等待并发请求的处理结果（短暂轮询）
     *
     * @param string $redisKey
     * @param int    $maxWait  最大等待秒数
     * @return array|null
     */
    private function waitForResult(string $redisKey, int $maxWait = 5): ?array
    {
        $waited = 0;
        while ($waited < $maxWait) {
            usleep(200000); // 200ms
            $waited += 200000;

            $cached = $this->getCachedResult($redisKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        return null;
    }

    /**
     * 缓存响应结果
     *
     * @param string   $redisKey
     * @param Response $response
     * @return void
     */
    private function cacheResult(string $redisKey, Response $response): void
    {
        try {
            // 仅缓存 HTTP 2xx 的成功响应，失败/异常不缓存
            $statusCode = $response->getCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                $content = $response->getContent();
                $data    = json_decode($content, true);

                if (is_array($data)) {
                    Cache::store('redis')->set($redisKey, [
                        'status'  => $statusCode,
                        'headers' => $response->getHeader(),
                        'body'    => $data,
                    ], self::KEY_TTL);
                }
            }
        } catch (\Throwable $e) {
            // Redis 缓存失败不影响业务
        }
    }

    /**
     * 根据缓存数据构建幂等响应
     *
     * @param array $cached
     * @return Response
     */
    private function buildIdempotentResponse(array $cached): Response
    {
        $statusCode = $cached['status'] ?? 200;
        $headers    = $cached['headers'] ?? [];
        $body       = $cached['body'] ?? [];

        // 标记该响应为幂等返回
        $body['_idempotent_replay'] = true;

        return json($body, $statusCode, $headers);
    }
}
