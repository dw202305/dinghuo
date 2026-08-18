<?php

declare(strict_types=1);

namespace app\common\support;

use PDOException;
use think\facade\Db;
use Throwable;

/**
 * 业务幂等助手（批次4：并发与幂等加固）
 *
 * 幂等判定统一模式："先插、捕 1062/Duplicate entry 再回查原结果返回"，
 * 以 DB 唯一键（uk_payment_idempotent / uk_inventory_log_idempotent /
 * uk_recharge_idempotent / uk_balance_txn_idempotent）为最终裁决者，
 * 替换旧版"先查后插"的竞态窗口。
 *
 * 幂等键一律业务确定化（禁止 time()/mt_rand()/uniqid() 构造默认键）：
 * - 创建支付  order_pay:{order_no}:{channel}
 * - 支付回调  notify:{channel}:{payment_no}（Redis 并发护栏）
 * - 库存锁定  lock:{order_no}:{item_id}
 * - 库存释放  release:{order_no}:{item_id}
 * - 支付核销  consume:{order_no}:{item_id}
 * - 储值充值  recharge:{recharge_no}
 * - 人工调整  adjust:{业务键}
 */
final class Idempotency
{
    /**
     * 先插、捕唯一键冲突再回查原记录返回
     *
     * 插入成功返回新记录（含 id）；命中 1062/Duplicate entry 时按
     * 幂等键回查并返回原有记录。其他异常原样上抛。
     *
     * @param string $table 逻辑表名（Db::name 风格，如 payment / inventory_log）
     * @param array $data 待插入完整行数据（必须包含 $keyColumn 字段）
     * @param string $keyColumn 幂等键列名（该列须有唯一索引）
     * @return array|null 新记录或原有记录（回查不到时返回 null）
     */
    public static function insertOrFetch(string $table, array $data, string $keyColumn = 'idempotent_key'): ?array
    {
        try {
            $id = Db::name($table)->insertGetId($data);

            $row = Db::name($table)->where('id', $id)->find();

            return $row !== null && $row !== false ? (array) $row : $data;
        } catch (Throwable $e) {
            if (!self::isDuplicateKey($e)) {
                throw $e;
            }

            // 唯一键冲突：并发下另一请求已先行落库，回查原结果返回
            $keyValue = $data[$keyColumn] ?? null;
            if ($keyValue === null || $keyValue === '') {
                throw $e;
            }

            $existing = Db::name($table)->where($keyColumn, $keyValue)->find();

            return $existing !== null && $existing !== false ? (array) $existing : null;
        }
    }

    /**
     * 判断异常是否为唯一键冲突（MySQL 1062 / SQLSTATE 23000）
     *
     * @param Throwable $e
     * @return bool
     */
    public static function isDuplicateKey(Throwable $e): bool
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
