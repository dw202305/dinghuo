<?php
declare(strict_types=1);

namespace app\common\service;

use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;

/**
 * 库存服务（重构版）
 *
 * 管理套件库存的查询、锁定、核销、释放和调整。
 * 新增功能：
 * - 所有库存操作支持幂等键（规范 9.2 & 14.5）
 * - 同一幂等键重复请求返回原结果，不重复执行
 * - 确保所有库存变化生成流水
 * - 库存操作在事务内完成，使用行锁防并发（规范 9.2）
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
            ->leftJoin('kit k', 'k.sku = si.kit_sku')
            ->where('si.store_id', $storeId)
            ->field([
                'si.kit_sku',
                'k.name as kit_name',
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
     * @param int $storeId 门店ID
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getInventoryLog(int $storeId, array $filters, int $page = 1, int $pageSize = 20): array
    {
        $query = Db::name('inventory_log')
            ->where('store_id', $storeId);

        if (!empty($filters['kit_sku'])) {
            $query->where('kit_sku', $filters['kit_sku']);
        }
        if (!empty($filters['log_type'])) {
            $query->where('log_type', (int) $filters['log_type']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date'] . '23:59:59');
        }

        $total = $query->count();
        $list  = $query->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $logTypeMap = [
            1 => '采购入账', 2 => '订单锁定', 3 => '支付核销', 4 => '取消释放',
            5 => '退款退回', 6 => '售后更换', 7 => '人工调整', 8 => '门店调拨',
        ];

        foreach ($list as &$item) {
            $item['log_type_text'] = $logTypeMap[$item['log_type']] ?? '未知';
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /**
     * 锁定库存（订单提交时）
     *
     * 使用幂等键保证同一锁定操作不重复执行（规范 9.2 & 14.5）。
     * 在同一事务内完成：校验库存 → 锁定 → 写流水。
     *
     * @param int $storeId 门店ID
     * @param string $kitSku 套件SKU
     * @param int $quantity 锁定数量
     * @param int $orderId 订单ID
     * @param string $orderNo 订单号
     * @param string $idempotentKey 幂等键（必填）
     * @return array 锁定结果
     * @throws ValidateException
     */
    public function lockInventory(int $storeId, string $kitSku, int $quantity, int $orderId, string $orderNo, string $idempotentKey = ''): array
    {
        if (empty($idempotentKey)) {
            $idempotentKey = "lock_{$orderNo}_{$kitSku}";
        }

        // 幂等校验：同一幂等键返回原结果
        $existingLog = Db::name('inventory_log')
            ->where('idempotent_key', $idempotentKey)
            ->find();

        if ($existingLog) {
            return [
                'success'      => true,
                'idempotent'   => true,
                'log_id'       => (int) $existingLog['id'],
                'quantity'     => $quantity,
                'after_quantity' => (int) $existingLog['after_quantity'],
            ];
        }

        $lockKey = "lock:inventory:{$storeId}:{$kitSku}";

        try {
            // Redis 辅助锁（不能代替数据库一致性，规范 9.2）
            $lock = Cache::store('redis')->get($lockKey);
            if ($lock) {
                throw new ValidateException('库存操作冲突，请重试');
            }
            Cache::store('redis')->set($lockKey, 1, 10);

            return $this->transaction(function () use ($storeId, $kitSku, $quantity, $orderId, $orderNo, $idempotentKey, $lockKey) {
                // 行锁
                $inventory = Db::name('store_inventory')
                    ->where('store_id', $storeId)
                    ->where('kit_sku', $kitSku)
                    ->lock(true)
                    ->find();

                if (!$inventory || (int) $inventory['available'] < $quantity) {
                    throw new ValidateException('库存套件不足，请调整库存使用策略', 4001);
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

                // 写入流水（含幂等键）
                $logId = Db::name('inventory_log')->insertGetId([
                    'store_id'        => $storeId,
                    'kit_sku'         => $kitSku,
                    'log_type'        => 2, // 订单锁定
                    'quantity'        => -$quantity,
                    'before_quantity' => $before,
                    'after_quantity'  => $after,
                    'order_id'        => $orderId,
                    'order_no'        => $orderNo,
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
        } finally {
            Cache::store('redis')->delete($lockKey);
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
     * @param string $orderNo 订单号
     * @param string $idempotentKey 幂等键
     * @return array 释放结果
     * @throws ValidateException
     */
    public function releaseInventory(int $storeId, string $kitSku, int $quantity, int $orderId, string $orderNo, string $idempotentKey = ''): array
    {
        if (empty($idempotentKey)) {
            $idempotentKey = "release_{$orderNo}_{$kitSku}";
        }

        // 幂等校验
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

        $lockKey = "lock:inventory:{$storeId}:{$kitSku}";

        try {
            $lock = Cache::store('redis')->get($lockKey);
            if ($lock) {
                throw new ValidateException('库存操作冲突，请重试');
            }
            Cache::store('redis')->set($lockKey, 1, 10);

            return $this->transaction(function () use ($storeId, $kitSku, $quantity, $orderId, $orderNo, $idempotentKey) {
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
                    'kit_sku'         => $kitSku,
                    'log_type'        => 4, // 取消释放
                    'quantity'        => $quantity,
                    'before_quantity' => $availableBefore,
                    'after_quantity'  => $availableAfter,
                    'order_id'        => $orderId,
                    'order_no'        => $orderNo,
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
        } finally {
            Cache::store('redis')->delete($lockKey);
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
     * @param string $idempotentKey 幂等键
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
            $idempotentKey = "adjust_{$storeId}_{$kitSku}_" . time();
        }

        // 幂等校验
        $existingLog = Db::name('inventory_log')
            ->where('idempotent_key', $idempotentKey)
            ->find();

        if ($existingLog) {
            return [
                'success'         => true,
                'idempotent'      => true,
                'log_id'          => (int) $existingLog['id'],
                'before_available' => (int) $existingLog['before_quantity'],
                'after_available'  => (int) $existingLog['after_quantity'],
            ];
        }

        $lockKey = "lock:inventory:{$storeId}:{$kitSku}";

        try {
            $lock = Cache::store('redis')->get($lockKey);
            if ($lock) {
                throw new ValidateException('库存操作冲突，请重试');
            }
            Cache::store('redis')->set($lockKey, 1, 10);

            return $this->transaction(function () use ($storeId, $kitSku, $adjustQuantity, $reason, $operatorId, $operatorName, $idempotentKey) {
                $inventory = Db::name('store_inventory')
                    ->where('store_id', $storeId)
                    ->where('kit_sku', $kitSku)
                    ->lock(true)
                    ->find();

                if (!$inventory) {
                    // 自动创建库存记录
                    Db::name('store_inventory')->insert([
                        'store_id'        => $storeId,
                        'kit_sku'         => $kitSku,
                        'total_purchased' => 0,
                        'available'       => 0,
                        'locked'          => 0,
                        'consumed'        => 0,
                        'frozen'          => 0,
                        'return_pending'  => 0,
                        'adjusted'        => 0,
                    ]);
                    $inventory = Db::name('store_inventory')
                        ->where('store_id', $storeId)
                        ->where('kit_sku', $kitSku)
                        ->find();
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
                    'kit_sku'         => $kitSku,
                    'log_type'        => 7, // 人工调整
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
        } finally {
            Cache::store('redis')->delete($lockKey);
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
            ->leftJoin('kit k', 'k.sku = si.kit_sku');

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
                'k.name as kit_name',
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
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getGlobalInventoryLog(array $filters, int $page = 1, int $pageSize = 20): array
    {
        $query = Db::name('inventory_log')
            ->alias('l')
            ->leftJoin('store s', 's.id = l.store_id');

        if (!empty($filters['store_id'])) {
            $query->where('l.store_id', (int) $filters['store_id']);
        }
        if (!empty($filters['kit_sku'])) {
            $query->where('l.kit_sku', $filters['kit_sku']);
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
                'l.kit_sku',
                'l.log_type',
                'l.quantity',
                'l.before_quantity',
                'l.after_quantity',
                'l.order_no',
                'l.operator_name',
                'l.reason',
                'l.idempotent_key',
                'l.created_at',
            ])
            ->order('l.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $logTypeMap = [
            1 => '采购入账', 2 => '订单锁定', 3 => '支付核销', 4 => '取消释放',
            5 => '退款退回', 6 => '售后更换', 7 => '人工调整', 8 => '门店调拨',
        ];

        foreach ($list as &$item) {
            $item['log_type_text'] = $logTypeMap[$item['log_type']] ?? '未知';
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }
}
