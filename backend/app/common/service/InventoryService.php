<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\InventoryLogType;
use app\common\exception\CodedValidateException;
use app\common\support\Idempotency;
use app\common\support\RedisLock;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use Throwable;

/**
 * 库存服务（重构版）
 *
 * 管理套件库存的查询、锁定、核销、释放和调整。
 * 功能要点：
 * - 所有库存操作支持幂等键（规范 9.2 & 14.5）
 * - 同一幂等键重复请求返回原结果，不重复执行
 * - 确保所有库存变化生成流水
 * - 库存操作在事务内完成，使用行锁防并发（规范 9.2）
 *
 * 批次2c：流水表列对齐 deploy/mysql/init.sql lj_inventory_log——
 * 必写 inventory_id（NOT NULL）；deploy 无 kit_sku/order_no 列，
 * kit_sku 经 store_inventory JOIN 获取、order_no 经 order JOIN 获取；
 * lj_kit 列名为 kit_sku/kit_name（非 sku/name）。
 *
 * 批次4（并发与幂等加固）：
 * - 幂等键业务确定化：lock:{order_no}:{item_id} / release:{order_no}:{item_id} /
 *   consume:{order_no}:{item_id} / adjust:{业务键}，禁用 time()/mt_rand；
 * - 幂等判定改"先插、捕 1062 再回查原结果"（uk_inventory_log_idempotent 裁决）；
 * - Redis 辅助锁改 SET NX EX + 随机 token，finally 校验 token 后删除（RedisLock）。
 *
 * @see docs/dev_specification_v1.0.md 第九节
 * @see docs/prd_v3.2.md 九
 */
class InventoryService extends BaseService
{
    /**
     * 获取门店套件库存概览
     *
     * @param int $storeId 门店ID
     * @return array
     */
    public function getStoreKitInventory(int $storeId): array
    {
        $list = Db::name('store_inventory')
            ->alias('si')
            // deploy lj_kit 列名 kit_sku/kit_name
            ->leftJoin('kit k', 'k.kit_sku = si.kit_sku')
            ->where('si.store_id', $storeId)
            ->field([
                'si.id as inventory_id',
                'si.kit_sku',
                'k.kit_name as kit_name',
                'si.total_purchased',
                'si.available',
                'si.locked',
                'si.consumed',
                'si.frozen',
                'si.return_pending',
                'si.adjusted',
            ])
            ->select()
            ->toArray();

        return $list;
    }

    /**
     * 获取库存流水
     *
     * deploy lj_inventory_log 无 kit_sku/order_no 列：
     * kit_sku 经 inventory_id JOIN store_inventory 过滤，
     * order_no 经 order_id JOIN order 输出（API 响应兼容）。
     *
     * @param int $storeId 门店ID
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getInventoryLog(int $storeId, array $filters, int $page = 1, int $pageSize = 20): array
    {
        $query = Db::name('inventory_log')
            ->alias('l')
            ->leftJoin('store_inventory si', 'si.id = l.inventory_id')
            ->leftJoin('order o', 'o.id = l.order_id')
            ->where('l.store_id', $storeId);

        if (!empty($filters['kit_sku'])) {
            $query->where('si.kit_sku', $filters['kit_sku']);
        }
        if (!empty($filters['log_type'])) {
            $query->where('l.log_type', (int) $filters['log_type']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('l.created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('l.created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->field([
                'l.id as log_id',
                'l.inventory_id',
                'si.kit_sku',
                'l.log_type',
                'l.quantity',
                'l.before_quantity',
                'l.after_quantity',
                'l.order_id',
                'o.order_no',
                'l.operator_name',
                'l.reason',
                'l.idempotent_key',
                'l.created_at',
            ])
            ->order('l.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $logTypeMap = InventoryLogType::options();

        foreach ($list as &$item) {
            $item['log_type_text'] = $logTypeMap[(int) $item['log_type']] ?? '未知';
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /**
     * 锁定库存（订单提交时）
     *
     * 使用幂等键保证同一锁定操作不重复执行（规范 9.2 & 14.5）。
     * 在同一事务内完成：校验库存 → 锁定 → 写流水（含 inventory_id）。
     *
     * @param int $storeId 门店ID
     * @param string $kitSku 套件SKU
     * @param int $quantity 锁定数量
     * @param int $orderId 订单ID
     * @param string $orderNo 订单号（仅用于幂等键构造，不入库）
     * @param string $idempotentKey 幂等键（规范键式：lock:{order_no}:{item_id}）
     * @return array 锁定结果
     * @throws ValidateException
     */
    public function lockInventory(int $storeId, string $kitSku, int $quantity, int $orderId, string $orderNo, string $idempotentKey = ''): array
    {
        if (empty($idempotentKey)) {
            $idempotentKey = "lock:{$orderNo}:{$kitSku}";
        }

        $lockKey = "lock:inventory:{$storeId}:{$kitSku}";
        $token = RedisLock::token();

        // Redis 辅助锁：SET NX EX 原子加锁（不能代替数据库一致性，规范 9.2）
        if (!RedisLock::acquire($lockKey, 10, $token)) {
            throw new ValidateException('库存操作冲突，请重试');
        }

        try {
            // 幂等裁决交给 DB 唯一键：先插流水，冲突时事务回滚后回查原结果
            return $this->transaction(function () use ($storeId, $kitSku, $quantity, $orderId, $idempotentKey) {
                // 行锁
                $inventory = Db::name('store_inventory')
                    ->where('store_id', $storeId)
                    ->where('kit_sku', $kitSku)
                    ->lock(true)
                    ->find();

                if (!$inventory || (int) $inventory['available'] < $quantity) {
                    throw new CodedValidateException('库存套件不足，请调整库存使用策略', 4001);
                }

                $before = (int) $inventory['available'];
                $after = $before - $quantity;

                // 更新库存
                Db::name('store_inventory')
                    ->where('id', $inventory['id'])
                    ->update([
                        'available' => $after,
                        'locked'    => (int) $inventory['locked'] + $quantity,
                    ]);

                // 写入流水（deploy 列：inventory_id NOT NULL，无 kit_sku/order_no）
                $logId = Db::name('inventory_log')->insertGetId([
                    'store_id'        => $storeId,
                    'inventory_id'    => (int) $inventory['id'],
                    'log_type'        => InventoryLogType::ORDER_LOCK->value,
                    'quantity'        => -$quantity,
                    'before_quantity' => $before,
                    'after_quantity'  => $after,
                    'order_id'        => $orderId,
                    'operator_name'   => '系统',
                    'reason'          => '订单提交锁定库存',
                    'idempotent_key'  => $idempotentKey,
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);

                Log::info('库存锁定成功', [
                    'store_id' => $storeId,
                    'kit_sku' => $kitSku,
                    'quantity' => $quantity,
                    'before' => $before,
                    'after' => $after,
                ]);

                return [
                    'success'        => true,
                    'idempotent'     => false,
                    'log_id'         => $logId,
                    'quantity'       => $quantity,
                    'before_quantity' => $before,
                    'after_quantity'  => $after,
                ];
            });
        } catch (Throwable $e) {
            // 幂等命中（uk_inventory_log_idempotent 冲突）：事务已回滚，回查原结果返回
            if (Idempotency::isDuplicateKey($e)) {
                $existingLog = Db::name('inventory_log')
                    ->where('idempotent_key', $idempotentKey)
                    ->find();

                if ($existingLog) {
                    return [
                        'success'        => true,
                        'idempotent'     => true,
                        'log_id'         => (int) $existingLog['id'],
                        'quantity'       => $quantity,
                        'after_quantity' => (int) $existingLog['after_quantity'],
                    ];
                }
            }

            throw $e;
        } finally {
            RedisLock::release($lockKey, $token);
        }
    }

    /**
     * 释放库存（订单取消时）
     *
     * 使用幂等键防止重复释放。
     *
     * @param int $storeId 门店ID
     * @param string $kitSku 套件SKU
     * @param int $quantity 释放数量
     * @param int $orderId 订单ID
     * @param string $orderNo 订单号（仅用于幂等键构造，不入库）
     * @param string $idempotentKey 幂等键（规范键式：release:{order_no}:{item_id}）
     * @return array 释放结果
     * @throws ValidateException
     */
    public function releaseInventory(int $storeId, string $kitSku, int $quantity, int $orderId, string $orderNo, string $idempotentKey = ''): array
    {
        if (empty($idempotentKey)) {
            $idempotentKey = "release:{$orderNo}:{$kitSku}";
        }

        $lockKey = "lock:inventory:{$storeId}:{$kitSku}";
        $token = RedisLock::token();

        if (!RedisLock::acquire($lockKey, 10, $token)) {
            throw new ValidateException('库存操作冲突，请重试');
        }

        try {
            // 幂等裁决交给 DB 唯一键：先插流水，冲突时事务回滚后回查原结果
            return $this->transaction(function () use ($storeId, $kitSku, $quantity, $orderId, $idempotentKey) {
                $inventory = Db::name('store_inventory')
                    ->where('store_id', $storeId)
                    ->where('kit_sku', $kitSku)
                    ->lock(true)
                    ->find();

                if (!$inventory) {
                    throw new ValidateException('库存记录不存在');
                }

                $lockedBefore = (int) $inventory['locked'];
                $availableBefore = (int) $inventory['available'];
                $availableAfter = $availableBefore + $quantity;

                Db::name('store_inventory')
                    ->where('id', $inventory['id'])
                    ->update([
                        'locked'    => max(0, $lockedBefore - $quantity),
                        'available' => $availableAfter,
                    ]);

                $logId = Db::name('inventory_log')->insertGetId([
                    'store_id'        => $storeId,
                    'inventory_id'    => (int) $inventory['id'],
                    'log_type'        => InventoryLogType::CANCEL_RELEASE->value,
                    'quantity'        => $quantity,
                    'before_quantity' => $availableBefore,
                    'after_quantity'  => $availableAfter,
                    'order_id'        => $orderId,
                    'operator_name'   => '系统',
                    'reason'          => '订单取消释放库存',
                    'idempotent_key'  => $idempotentKey,
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);

                Log::info('库存释放成功', [
                    'store_id' => $storeId,
                    'kit_sku' => $kitSku,
                    'quantity' => $quantity,
                ]);

                return [
                    'success'        => true,
                    'idempotent'     => false,
                    'log_id'         => $logId,
                    'quantity'       => $quantity,
                    'before_quantity' => $availableBefore,
                    'after_quantity'  => $availableAfter,
                ];
            });
        } catch (Throwable $e) {
            // 幂等命中（uk_inventory_log_idempotent 冲突）：事务已回滚，回查原结果返回
            if (Idempotency::isDuplicateKey($e)) {
                $existingLog = Db::name('inventory_log')
                    ->where('idempotent_key', $idempotentKey)
                    ->find();

                if ($existingLog) {
                    return [
                        'success'        => true,
                        'idempotent'     => true,
                        'log_id'         => (int) $existingLog['id'],
                        'quantity'       => $quantity,
                        'after_quantity' => (int) $existingLog['after_quantity'],
                    ];
                }
            }

            throw $e;
        } finally {
            RedisLock::release($lockKey, $token);
        }
    }

    /**
     * 核销库存（支付成功后：锁定 → 已消耗）
     *
     * 批次4新增：供 OrderStateService "支付成功" 副作用调用，
     * 替换 PaymentService::consumeInventoryOnPaid 的旧实现。
     * 行锁读取 locked 真实前后数量，流水幂等键 consume:{order_no}:{item_id}
     * 由调用方传入（uk_inventory_log_idempotent 裁决重复核销）。
     *
     * @param int $storeId 门店ID
     * @param string $kitSku 套件SKU
     * @param int $quantity 核销数量（明细真实抵扣数）
     * @param int $orderId 订单ID
     * @param string $orderNo 订单号（仅用于幂等键构造，不入库）
     * @param string $idempotentKey 幂等键（规范键式：consume:{order_no}:{item_id}）
     * @return array 核销结果
     * @throws ValidateException
     */
    public function consumeInventory(int $storeId, string $kitSku, int $quantity, int $orderId, string $orderNo, string $idempotentKey = ''): array
    {
        if (empty($idempotentKey)) {
            $idempotentKey = "consume:{$orderNo}:{$kitSku}";
        }

        $lockKey = "lock:inventory:{$storeId}:{$kitSku}";
        $token = RedisLock::token();

        if (!RedisLock::acquire($lockKey, 10, $token)) {
            throw new ValidateException('库存操作冲突，请重试');
        }

        try {
            return $this->transaction(function () use ($storeId, $kitSku, $quantity, $orderId, $idempotentKey) {
                // 行锁库存行，读取真实前后数量
                $inventory = Db::name('store_inventory')
                    ->where('store_id', $storeId)
                    ->where('kit_sku', $kitSku)
                    ->lock(true)
                    ->find();

                if (!$inventory) {
                    throw new ValidateException('库存记录不存在');
                }

                $lockedBefore = (int) $inventory['locked'];
                if ($lockedBefore < $quantity) {
                    throw new CodedValidateException('锁定库存不足，无法核销', 4001);
                }

                $lockedAfter = $lockedBefore - $quantity;

                Db::name('store_inventory')
                    ->where('id', $inventory['id'])
                    ->update([
                        'locked'   => $lockedAfter,
                        'consumed' => (int) $inventory['consumed'] + $quantity,
                    ]);

                $logId = Db::name('inventory_log')->insertGetId([
                    'store_id'        => $storeId,
                    'inventory_id'    => (int) $inventory['id'],
                    'log_type'        => InventoryLogType::PAY_CONSUME->value,
                    'quantity'        => -$quantity,
                    'before_quantity' => $lockedBefore,
                    'after_quantity'  => $lockedAfter,
                    'order_id'        => $orderId,
                    'operator_name'   => '系统',
                    'reason'          => '支付成功核销库存',
                    'idempotent_key'  => $idempotentKey,
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);

                Log::info('库存核销成功', [
                    'store_id' => $storeId,
                    'kit_sku' => $kitSku,
                    'quantity' => $quantity,
                    'locked_before' => $lockedBefore,
                    'locked_after' => $lockedAfter,
                ]);

                return [
                    'success'         => true,
                    'idempotent'      => false,
                    'log_id'          => $logId,
                    'quantity'        => $quantity,
                    'before_quantity' => $lockedBefore,
                    'after_quantity'  => $lockedAfter,
                ];
            });
        } catch (Throwable $e) {
            // 幂等命中：重复回调/重复转换不重复核销，回查原结果返回
            if (Idempotency::isDuplicateKey($e)) {
                $existingLog = Db::name('inventory_log')
                    ->where('idempotent_key', $idempotentKey)
                    ->find();

                if ($existingLog) {
                    return [
                        'success'        => true,
                        'idempotent'     => true,
                        'log_id'         => (int) $existingLog['id'],
                        'quantity'       => $quantity,
                        'after_quantity' => (int) $existingLog['after_quantity'],
                    ];
                }
            }

            throw $e;
        } finally {
            RedisLock::release($lockKey, $token);
        }
    }

    /**
     * 人工调整库存（后台）
     *
     * 必须审批并记录日志（规范 9.2 & 18.2）。
     *
     * @param int $storeId 门店ID
     * @param string $kitSku 套件SKU
     * @param int $adjustQuantity 调整数量（正数增加，负数减少）
     * @param string $reason 调整原因
     * @param int $operatorId 操作人ID
     * @param string $operatorName 操作人名称
     * @param string $idempotentKey 幂等键（规范键式：adjust:{业务键}，由调用方提供审批单号等业务键）
     * @return array 调整前后的库存
     */
    public function adjustInventory(
        int $storeId,
        string $kitSku,
        int $adjustQuantity,
        string $reason,
        int $operatorId = 0,
        string $operatorName = '管理员',
        string $idempotentKey = '',
    ): array {
        if (empty($idempotentKey)) {
            // 业务确定键：同一门店/SKU/操作人/数量/原因的相同调整请求视为同一笔
            $idempotentKey = sprintf(
                'adjust:%d:%s:%d:%d:%s',
                $storeId,
                $kitSku,
                $operatorId,
                $adjustQuantity,
                md5($reason),
            );
        }

        $lockKey = "lock:inventory:{$storeId}:{$kitSku}";
        $token = RedisLock::token();

        if (!RedisLock::acquire($lockKey, 10, $token)) {
            throw new ValidateException('库存操作冲突，请重试');
        }

        try {
            return $this->transaction(function () use ($storeId, $kitSku, $adjustQuantity, $reason, $operatorId, $operatorName, $idempotentKey) {
                $inventory = Db::name('store_inventory')
                    ->where('store_id', $storeId)
                    ->where('kit_sku', $kitSku)
                    ->lock(true)
                    ->find();

                if (!$inventory) {
                    // 自动创建库存记录
                    $inventoryId = Db::name('store_inventory')->insertGetId([
                        'store_id'        => $storeId,
                        'kit_sku'         => $kitSku,
                        'total_purchased' => 0,
                        'available'       => 0,
                        'locked'          => 0,
                        'consumed'        => 0,
                        'frozen'          => 0,
                        'return_pending'  => 0,
                        'adjusted'        => 0,
                        'created_at'      => date('Y-m-d H:i:s'),
                        'updated_at'      => date('Y-m-d H:i:s'),
                    ]);
                    $inventory = Db::name('store_inventory')->where('id', $inventoryId)->find();
                }

                $before = (int) $inventory['available'];
                $after  = $before + $adjustQuantity;

                if ($after < 0) {
                    throw new ValidateException('调整后库存不能为负数');
                }

                Db::name('store_inventory')
                    ->where('id', $inventory['id'])
                    ->update([
                        'available' => $after,
                        'adjusted'  => (int) $inventory['adjusted'] + $adjustQuantity,
                    ]);

                $logId = Db::name('inventory_log')->insertGetId([
                    'store_id'        => $storeId,
                    'inventory_id'    => (int) $inventory['id'],
                    'log_type'        => InventoryLogType::MANUAL_ADJUST->value,
                    'quantity'        => $adjustQuantity,
                    'before_quantity' => $before,
                    'after_quantity'  => $after,
                    'operator_id'     => $operatorId,
                    'operator_name'   => $operatorName,
                    'reason'          => $reason,
                    'idempotent_key'  => $idempotentKey,
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);

                // 审计日志
                $this->logOperation(
                    module: 'inventory',
                    action: 'adjust',
                    targetType: 'store_inventory',
                    targetId: $storeId,
                    afterData: ['kit_sku' => $kitSku, 'before' => $before, 'after' => $after, 'reason' => $reason],
                    operatorId: $operatorId,
                    operatorName: $operatorName,
                    remark: '人工调整库存',
                );

                return [
                    'success'          => true,
                    'idempotent'       => false,
                    'before_available' => $before,
                    'after_available'  => $after,
                    'log_id'           => $logId,
                ];
            });
        } catch (Throwable $e) {
            // 幂等命中（uk_inventory_log_idempotent 冲突）：事务已回滚，回查原结果返回
            if (Idempotency::isDuplicateKey($e)) {
                $existingLog = Db::name('inventory_log')
                    ->where('idempotent_key', $idempotentKey)
                    ->find();

                if ($existingLog) {
                    return [
                        'success'          => true,
                        'idempotent'       => true,
                        'log_id'           => (int) $existingLog['id'],
                        'before_available' => (int) $existingLog['before_quantity'],
                        'after_available'  => (int) $existingLog['after_quantity'],
                    ];
                }
            }

            throw $e;
        } finally {
            RedisLock::release($lockKey, $token);
        }
    }

    /**
     * 后台查看全部门店库存
     *
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getAllStoreInventory(array $filters, int $page = 1, int $pageSize = 20): array
    {
        $query = Db::name('store_inventory')
            ->alias('si')
            ->leftJoin('store s', 's.id = si.store_id')
            // deploy lj_kit 列名 kit_sku/kit_name
            ->leftJoin('kit k', 'k.kit_sku = si.kit_sku');

        if (!empty($filters['store_id'])) {
            $query->where('si.store_id', (int) $filters['store_id']);
        }
        if (!empty($filters['kit_sku'])) {
            $query->where('si.kit_sku', $filters['kit_sku']);
        }
        if (!empty($filters['keyword'])) {
            $query->where('s.store_name|s.store_no', 'like', '%' . $filters['keyword'] . '%');
        }

        $total = $query->count();
        $list  = $query->field([
                'si.id',
                'si.store_id',
                's.store_no',
                's.store_name',
                'si.kit_sku',
                'k.kit_name as kit_name',
                'si.total_purchased',
                'si.available',
                'si.locked',
                'si.consumed',
                'si.frozen',
                'si.return_pending',
                'si.adjusted',
            ])
            ->order('si.store_id', 'asc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /**
     * 后台全局库存流水查询
     *
     * deploy lj_inventory_log 无 kit_sku/order_no 列：
     * kit_sku 经 inventory_id JOIN store_inventory，order_no 经 order JOIN。
     *
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getGlobalInventoryLog(array $filters, int $page = 1, int $pageSize = 20): array
    {
        $query = Db::name('inventory_log')
            ->alias('l')
            ->leftJoin('store s', 's.id = l.store_id')
            ->leftJoin('store_inventory si', 'si.id = l.inventory_id')
            ->leftJoin('order o', 'o.id = l.order_id');

        if (!empty($filters['store_id'])) {
            $query->where('l.store_id', (int) $filters['store_id']);
        }
        if (!empty($filters['kit_sku'])) {
            $query->where('si.kit_sku', $filters['kit_sku']);
        }
        if (!empty($filters['log_type'])) {
            $query->where('l.log_type', (int) $filters['log_type']);
        }
        if (!empty($filters['order_id'])) {
            $query->where('l.order_id', (int) $filters['order_id']);
        }
        if (!empty($filters['operator_name'])) {
            $query->where('l.operator_name', 'like', '%' . $filters['operator_name'] . '%');
        }
        if (!empty($filters['start_date'])) {
            $query->where('l.created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('l.created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->field([
                'l.id as log_id',
                'l.store_id',
                's.store_no',
                's.store_name',
                'l.inventory_id',
                'si.kit_sku',
                'l.log_type',
                'l.quantity',
                'l.before_quantity',
                'l.after_quantity',
                'l.order_id',
                'o.order_no',
                'l.operator_name',
                'l.reason',
                'l.idempotent_key',
                'l.created_at',
            ])
            ->order('l.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $logTypeMap = InventoryLogType::options();

        foreach ($list as &$item) {
            $item['log_type_text'] = $logTypeMap[(int) $item['log_type']] ?? '未知';
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }
}
