<?php

declare(strict_types=1);

namespace app\common\support;

use think\facade\Cache;
use think\facade\Log;
use Throwable;

/**
 * Redis 分布式锁（安全版）
 *
 * 批次4（并发与幂等加固）：替换旧版 "get 判存在 + set 覆盖 + 无差别 delete"
 * 的非原子锁模式：
 * - 加锁：SET key token NX EX ttl（单条命令原子完成，持锁人写入随机 token）；
 * - 释放：Lua 脚本先比对 token 再删除（compare-and-delete），
 *   杜绝误删他人锁；eval 不可用时降级为 get+hash_equals+del 尽力而为；
 * - Redis 故障时降级放行（返回 true 并告警），一致性由数据库行锁/
 *   唯一键兜底（规范 9.2：Redis 锁不能代替数据库一致性）。
 *
 * 用法：
 *   $token = RedisLock::token();
 *   if (!RedisLock::acquire($key, 10, $token)) { throw 冲突异常; }
 *   try { ... } finally { RedisLock::release($key, $token); }
 */
final class RedisLock
{
    /** 释放锁 Lua 脚本：token 匹配才删除（原子 compare-and-delete） */
    private const RELEASE_LUA = <<<'LUA'
if redis.call('get', KEYS[1]) == ARGV[1] then
    return redis.call('del', KEYS[1])
else
    return 0
end
LUA;

    /**
     * 生成随机持锁 token
     *
     * @return string 16 位十六进制
     */
    public static function token(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * 原子加锁：SET key token NX EX ttl
     *
     * @param string $key 锁键
     * @param int $ttlSeconds 过期秒数（防死锁）
     * @param string $token 持锁人 token（释放时校验）
     * @return bool true=加锁成功（或 Redis 故障降级放行），false=锁被占用
     */
    public static function acquire(string $key, int $ttlSeconds, string $token): bool
    {
        try {
            /** @var \Redis $redis */
            $redis = Cache::store('redis')->handler();

            return (bool) $redis->set($key, $token, ['nx', 'ex' => $ttlSeconds]);
        } catch (Throwable $e) {
            // Redis 故障降级放行：由 DB 行锁/唯一键保障一致性
            Log::warning('Redis加锁异常，降级放行', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * 安全释放：仅当 token 匹配（锁仍归本持锁人）时删除
     *
     * @param string $key 锁键
     * @param string $token 加锁时使用的 token
     * @return bool 是否实际删除
     */
    public static function release(string $key, string $token): bool
    {
        try {
            /** @var \Redis $redis */
            $redis = Cache::store('redis')->handler();

            try {
                $result = $redis->eval(self::RELEASE_LUA, [$key, $token], 1);

                return (int) $result === 1;
            } catch (Throwable $e) {
                // eval 不可用（如集群/精简版 Redis）时降级：
                // get + 常量时间比对 + del（非严格原子，尽力而为）
                $current = $redis->get($key);
                if (is_string($current) && hash_equals($current, $token)) {
                    return (int) $redis->del($key) > 0;
                }

                return false;
            }
        } catch (Throwable $e) {
            Log::warning('Redis释放锁异常（等待TTL自然过期）', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
