<?php
declare(strict_types=1);

namespace app\common\middleware;

use app\common\ApiResponse;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\Request;
use Response;
use think\facade\Cache;

/**
 * 门店/合伙人端认证中间件
 * 从 Authorization Header 解析 JWT Token
 *
 * 当前门店获取策略（对齐架构师方案）：
 * - Token 不含 store_id，只证明"你是谁"
 * - 当前活跃门店从 Redis 读取：key=current_store:{account_id}
 * - 登录时自动写入默认门店，切换门店时更新该 Key
 */
class AuthMiddleware
{
    use ApiResponse;

    /** Redis Key 前缀 */
    private const STORE_KEY_PREFIX = 'current_store:';
    /** 缓存 TTL（秒）：24 小时，兜底过期 */
    private const STORE_TTL = 86400;

    /**
     * 处理请求
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $token = $request->header('Authorization', '');

        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return $this->unauthorized('请先登录');
        }

        try {
            $secret = env('jwt.secret', 'lj_shishang_jwt_secret_2026');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            $accountId = $decoded->account_id ?? 0;
            if ($accountId <= 0) {
                return $this->unauthorized('登录已过期，请重新登录');
            }

            // 将用户基础信息注入 request
            $request->accountId   = $accountId;
            $request->accountRole = $decoded->role ?? 0;

            // 从 Redis 读取当前活跃门店
            $storeKey = self::STORE_KEY_PREFIX . $accountId;
            $storeId  = Cache::store('redis')->get($storeKey);

            if (!$storeId) {
                // 缓存未命中，从 DB 加载默认门店并写入 Redis
                $storeId = $this->loadDefaultStoreId($accountId);
                if ($storeId > 0) {
                    Cache::store('redis')->set($storeKey, $storeId, self::STORE_TTL);
                }
            }

            $request->storeId = (int) $storeId;

        } catch (\Exception $e) {
            return $this->unauthorized('登录已过期，请重新登录');
        }

        return $next($request);
    }

    /**
     * 从数据库加载账号的默认门店ID
     * @param int $accountId
     * @return int
     */
    private function loadDefaultStoreId(int $accountId): int
    {
        // 优先取 is_default_store=1 的门店
        $defaultStore = \app\common\model\AccountCustomer::where('account_id', $accountId)
            ->where('customer_type', 1)
            ->where('is_default_store', 1)
            ->where('status', 1)
            ->value('customer_id');

        if ($defaultStore) {
            return (int) $defaultStore;
        }

        // 没有默认门店时，取第一个关联门店
        $firstStore = \app\common\model\AccountCustomer::where('account_id', $accountId)
            ->where('customer_type', 1)
            ->where('status', 1)
            ->value('customer_id');

        return (int) ($firstStore ?: 0);
    }

    /**
     * 切换门店时更新 Redis 中的当前门店
     * 供 AuthController::switchStore() 调用
     * @param int $accountId
     * @param int $storeId
     * @return void
     */
    public static function updateCurrentStore(int $accountId, int $storeId): void
    {
        $storeKey = self::STORE_KEY_PREFIX . $accountId;
        Cache::store('redis')->set($storeKey, $storeId, self::STORE_TTL);
    }
}
