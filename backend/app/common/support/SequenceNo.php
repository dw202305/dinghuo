<?php

declare(strict_types=1);

namespace app\common\support;

use PDOException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use Throwable;

/**
 * 业务单号序列生成器
 *
 * 统一取号机制，替换各服务基于 "like 前缀查最大值+1" 的旧生成器
 * （并发下会重号）：
 * - 主路径：Redis INCR seq:{type}:{Ymd}，首次自增到 1 时设置 2 天 TTL；
 * - 降级路径：Redis 异常时走 MySQL lj_sequence 表
 *   （INSERT ... ON DUPLICATE KEY UPDATE seq_value=LAST_INSERT_ID(seq_value+1)）；
 * - 标准格式：{prefix}{Ymd}{6位序号}；需要其他序号位宽的业务（订单号4位、
 *   资金流水号8位）使用 next() 取号后自行格式化，保持原单号格式不变。
 *
 * Redis 与 MySQL 双通道切换极端情况下可能出现重号，调用方落库时依赖
 * 唯一索引兜底，并用 withRetry() 捕获唯一键冲突重试（≤3 次）。
 *
 * @see deploy/mysql/init.sql lj_sequence
 */
final class SequenceNo
{
    /** Redis 序号键 TTL（秒）：保留 2 天，跨日自然切换 */
    private const REDIS_TTL = 172800;

    /** 默认序号位宽 */
    private const SEQ_WIDTH = 6;

    /**
     * 生成业务单号：{prefix}{Ymd}{6位序号}
     *
     * @param string $type 序列类型（order/payment/recharge/balance_txn 等）
     * @param string $prefix 单号前缀（如 PAY、RC、BAL、REF）
     * @return string
     */
    public static function generate(string $type, string $prefix): string
    {
        $seq = self::next($type);

        return $prefix . date('Ymd') . str_pad((string) $seq, self::SEQ_WIDTH, '0', STR_PAD_LEFT);
    }

    /**
     * 获取指定序列的下一个序号值（主路径 Redis，异常降级 MySQL）
     *
     * @param string $type 序列类型
     * @return int 序号（从1开始）
     */
    public static function next(string $type): int
    {
        if ($type === '') {
            throw new \InvalidArgumentException('序列类型不能为空');
        }

        $date = date('Ymd');

        try {
            return self::nextByRedis($type, $date);
        } catch (Throwable $e) {
            Log::warning('Redis取号失败，降级MySQL序列表', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);

            return self::nextByMysql($type, $date);
        }
    }

    /**
     * 重试包装：调用方捕获唯一键冲突后重试（≤3 次）
     *
     * 回调内部应包含"取号 + 落库"完整动作，冲突重试时会重新取号。
     *
     * @template T
     * @param callable():T $callback 业务回调
     * @param int $maxAttempts 最大尝试次数（默认3）
     * @return T
     */
    public static function withRetry(callable $callback, int $maxAttempts = 3): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                return $callback();
            } catch (Throwable $e) {
                if ($attempt >= $maxAttempts || !self::isDuplicateKey($e)) {
                    throw $e;
                }

                Log::warning('单号唯一键冲突，重新取号重试', [
                    'attempt' => $attempt,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Redis 取号：INCR 原子自增，首次（自增结果为1）设置 TTL
     *
     * @param string $type 序列类型
     * @param string $date Ymd
     * @return int
     */
    private static function nextByRedis(string $type, string $date): int
    {
        /** @var \Redis $redis */
        $redis = Cache::store('redis')->handler();
        $key = "seq:{$type}:{$date}";

        $seq = (int) $redis->incr($key);
        if ($seq <= 0) {
            throw new \RuntimeException("Redis取号结果非法：{$seq}");
        }

        if ($seq === 1) {
            $redis->expire($key, self::REDIS_TTL);
        }

        return $seq;
    }

    /**
     * MySQL 降级取号：ON DUPLICATE KEY UPDATE + LAST_INSERT_ID 技巧
     *
     * 首次插入（affected=1）序号为1；命中更新（affected=2）时
     * LAST_INSERT_ID(seq_value+1) 将新值写入会话，SELECT LAST_INSERT_ID() 读回。
     *
     * @param string $type 序列类型
     * @param string $date Ymd
     * @return int
     */
    private static function nextByMysql(string $type, string $date): int
    {
        $affected = Db::execute(
            'INSERT INTO lj_sequence (seq_type, seq_date, seq_value) VALUES (?, ?, 1) '
            . 'ON DUPLICATE KEY UPDATE seq_value = LAST_INSERT_ID(seq_value + 1)',
            [$type, $date]
        );

        if ((int) $affected === 1) {
            // 当日首次取号：直接插入成功
            return 1;
        }

        $row = Db::query('SELECT LAST_INSERT_ID() AS seq');
        $seq = (int) ($row[0]['seq'] ?? 0);

        if ($seq <= 0) {
            throw new \RuntimeException("MySQL降级取号结果非法：{$seq}");
        }

        return $seq;
    }

    /**
     * 判断异常是否为唯一键冲突（MySQL 1062 / SQLSTATE 23000）
     *
     * @param Throwable $e
     * @return bool
     */
    private static function isDuplicateKey(Throwable $e): bool
    {
        if ($e instanceof PDOException) {
            if ($e->getCode() === '23000' || (int) $e->getCode() === 23000) {
                return true;
            }
        }

        $message = $e->getMessage();

        return str_contains($message, 'Duplicate entry')
            || str_contains($message, '1062')
            || str_contains($message, '23000');
    }
}
