<?php

declare(strict_types=1);

namespace tests\Feature;

use app\common\enum\InventoryLogType;
use app\common\service\InventoryService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 库存并发与幂等资金安全 Feature 测试（真实 MySQL：行锁 + 唯一约束）
 *
 * 单进程 PHPUnit 无法真正多线程，按任务约定用以下方式表达并发语义：
 * - 多个"订单"（不同幂等键）串行竞争同一库存行，断言绝不超卖；
 * - 同一幂等键重入，断言不重复扣减（uk_inventory_log_idempotent 裁决）；
 * - 释放后可再锁，数量守恒。
 *
 * 不变式（每次操作后校验）：locked ≤ 初始库存、available ≥ 0。
 */
class InventoryConcurrencyTest extends FeatureTestCase
{
    private const KIT_SKU = 'KIT-T10-CONC';

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryService();
    }

    private function inventoryRow(): array
    {
        $row = Db::name('store_inventory')
            ->where('store_id', self::STORE_ID)
            ->where('kit_sku', self::KIT_SKU)
            ->find();

        $this->assertNotNull($row, '库存行应存在');

        return $row;
    }

    /**
     * 用例1：多订单竞争同一套件（5 件库存、8 笔需求）绝不超卖
     */
    public function testCompetingOrdersNeverOversell(): void
    {
        $stock = 5;
        $this->seedInventory(self::STORE_ID, self::KIT_SKU, $stock);

        $succeeded = 0;
        $rejected  = 0;

        for ($i = 1; $i <= 8; $i++) {
            try {
                $result = $this->service->lockInventory(
                    self::STORE_ID,
                    self::KIT_SKU,
                    1,
                    1000 + $i,
                    "T10-CONC-{$i}",
                    "lock:T10-CONC-{$i}:1"
                );

                $this->assertFalse($result['idempotent'], '首次锁定不应命中幂等');
                $succeeded++;
            } catch (ValidateException $e) {
                // 超量锁定必须以 4001（库存套件不足）拒绝
                $this->assertSame(4001, $e->getCode(), '超卖请求应以 4001 拒绝');
                $rejected++;
            }

            // 每步不变式：locked 永不超过初始库存、available 永不为负、总量守恒
            $row = $this->inventoryRow();
            $this->assertLessThanOrEqual($stock, (int) $row['locked'], 'locked 不得超过初始库存');
            $this->assertGreaterThanOrEqual(0, (int) $row['available'], 'available 不得为负');
            $this->assertSame($stock, (int) $row['locked'] + (int) $row['available'], '锁定+可用应守恒');
        }

        $this->assertSame($stock, $succeeded, '5 件库存只允许 5 笔锁定成功');
        $this->assertSame(3, $rejected, '3 笔超卖请求应全部被拒');

        // 每笔成功锁定恰好一条流水
        $logCount = Db::name('inventory_log')->where('store_id', self::STORE_ID)->count();
        $this->assertSame($stock, $logCount, '锁定流水数应等于成功锁定数');
    }

    /**
     * 用例2：同一幂等键重入不二次扣减
     */
    public function testIdempotentReplayNeverDoubleDeduct(): void
    {
        $this->seedInventory(self::STORE_ID, self::KIT_SKU, 5);

        $first = $this->service->lockInventory(
            self::STORE_ID,
            self::KIT_SKU,
            2,
            2001,
            'T10-IDEM',
            'lock:T10-IDEM:1'
        );

        $this->assertTrue($first['success']);
        $this->assertFalse($first['idempotent']);

        // 同一幂等键重放 3 次：一律幂等命中，返回原流水，绝不重复扣减
        for ($i = 1; $i <= 3; $i++) {
            $replay = $this->service->lockInventory(
                self::STORE_ID,
                self::KIT_SKU,
                2,
                2001,
                'T10-IDEM',
                'lock:T10-IDEM:1'
            );

            $this->assertTrue($replay['success']);
            $this->assertTrue($replay['idempotent'], "第 {$i} 次重放应命中幂等");
            $this->assertSame((int) $first['log_id'], (int) $replay['log_id'], '幂等重放应返回原流水ID');
        }

        $row = $this->inventoryRow();
        $this->assertSame(3, (int) $row['available'], '仅扣减一次：5-2=3');
        $this->assertSame(2, (int) $row['locked'], '仅锁定一次：2');
        $this->assertSame(1, Db::name('inventory_log')->where('store_id', self::STORE_ID)->count(), '流水只有一条');
    }

    /**
     * 用例3：释放后可再锁，释放同样幂等
     */
    public function testReleaseThenRelockKeepsConservation(): void
    {
        $this->seedInventory(self::STORE_ID, self::KIT_SKU, 5);

        $this->service->lockInventory(self::STORE_ID, self::KIT_SKU, 3, 3001, 'T10-REL', 'lock:T10-REL:1');
        $row = $this->inventoryRow();
        $this->assertSame(2, (int) $row['available']);
        $this->assertSame(3, (int) $row['locked']);

        // 取消订单释放
        $release = $this->service->releaseInventory(self::STORE_ID, self::KIT_SKU, 3, 3001, 'T10-REL', 'release:T10-REL:1');
        $this->assertFalse($release['idempotent']);

        // 重复释放：幂等命中，不重复回补
        $releaseReplay = $this->service->releaseInventory(self::STORE_ID, self::KIT_SKU, 3, 3001, 'T10-REL', 'release:T10-REL:1');
        $this->assertTrue($releaseReplay['idempotent']);

        $row = $this->inventoryRow();
        $this->assertSame(5, (int) $row['available'], '释放后可用库存完整回补');
        $this->assertSame(0, (int) $row['locked']);

        // 释放后的库存可被新订单再次锁定（含一次性锁满）
        $relock = $this->service->lockInventory(self::STORE_ID, self::KIT_SKU, 5, 3002, 'T10-REL2', 'lock:T10-REL2:1');
        $this->assertFalse($relock['idempotent']);

        $row = $this->inventoryRow();
        $this->assertSame(0, (int) $row['available']);
        $this->assertSame(5, (int) $row['locked']);

        // 流水完整性：1 锁定 + 1 释放 + 1 再锁定（释放重放不产生新流水）
        $logs = Db::name('inventory_log')->where('store_id', self::STORE_ID)->order('id', 'asc')->select()->toArray();
        $this->assertCount(3, $logs);
        $this->assertSame(InventoryLogType::ORDER_LOCK->value, (int) $logs[0]['log_type']);
        $this->assertSame(InventoryLogType::CANCEL_RELEASE->value, (int) $logs[1]['log_type']);
        $this->assertSame(InventoryLogType::ORDER_LOCK->value, (int) $logs[2]['log_type']);
    }

    /**
     * 用例4：多订单按不同数量竞争（各锁 2 件，库存 5 件）恰好成交 2 笔
     */
    public function testPartialDemandContentionLockedNeverExceedsStock(): void
    {
        $stock = 5;
        $this->seedInventory(self::STORE_ID, self::KIT_SKU, $stock);

        $succeeded = 0;

        for ($i = 1; $i <= 4; $i++) {
            try {
                $this->service->lockInventory(
                    self::STORE_ID,
                    self::KIT_SKU,
                    2,
                    4000 + $i,
                    "T10-PART-{$i}",
                    "lock:T10-PART-{$i}:1"
                );
                $succeeded++;
            } catch (ValidateException $e) {
                $this->assertSame(4001, $e->getCode());
            }

            $row = $this->inventoryRow();
            $this->assertLessThanOrEqual($stock, (int) $row['locked']);
        }

        $this->assertSame(2, $succeeded, '库存 5 件、每单 2 件，恰好 2 单成交');

        $row = $this->inventoryRow();
        $this->assertSame(4, (int) $row['locked']);
        $this->assertSame(1, (int) $row['available']);
    }
}
