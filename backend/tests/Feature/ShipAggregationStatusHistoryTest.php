<?php

declare(strict_types=1);

namespace tests\Feature;

use app\common\enum\OrderStatus;
use app\common\model\OrderItem;
use app\common\service\OrderStateService;
use think\facade\Db;

/**
 * 发货聚合 + writeStatusHistory 回归 Feature 测试（评审 Critical 1 / 相关修复）
 *
 * - 子单 production_status 推进到 4（已发货）时，主单状态按聚合规则更新：
 *   部分子单发货 → 部分发货；全部发货 → 已完成（子单维度无独立签收态，
 *   全部已发货即完成，评审 Critical 1 修复）；
 * - 聚合路径调用 writeStatusHistory（6 参签名）不得抛参数错误，
 *   lj_order_status_history 必须留有聚合记录。
 */
class ShipAggregationStatusHistoryTest extends FeatureTestCase
{
    private OrderStateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderStateService();
    }

    /**
     * 造一行窗帘明细（production_status 默认 3=质检通过/已完成，等待发货推进）
     */
    private function seedOrderItem(int $orderId, int $sequence, int $productionStatus = 3): int
    {
        return (int) Db::name('order_item')->insertGetId([
            'item_no'                    => 'SS-T10-' . bin2hex(random_bytes(4)) . "-C{$sequence}",
            'order_id'                   => $orderId,
            'sequence'                   => $sequence,
            'install_position'           => '测试房间',
            'width_cm'                   => '200.0',
            'height_cm'                  => '250.0',
            'area_m2'                    => '5.0000',
            'track_color'                => '白色',
            'track_horizontal_length_m'  => '2.00',
            'track_vertical_length_m'    => '0.00',
            'track_amount_cent'          => 10000,
            'fabric_no'                  => 'FAB-T10-' . $sequence,
            'fabric_price_cent'          => 20000,
            'fabric_amount_cent'         => 100000,
            'item_total_cent'            => 110000,
            'production_status'          => $productionStatus,
            'created_at'                 => date('Y-m-d H:i:s'),
            'updated_at'                 => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 用例1：部分发货聚合 → PARTIAL_SHIP，历史表有聚合记录且不抛参数错误
     */
    public function testPartialShipAggregationWritesHistory(): void
    {
        $order = $this->seedOrder(['order_status' => OrderStatus::PENDING_SHIP->value, 'item_count' => 2]);
        $itemA = $this->seedOrderItem((int) $order['id'], 1);
        $this->seedOrderItem((int) $order['id'], 2);

        $item = OrderItem::find($itemA);
        $this->service->transitionItem($item, 4, 'admin', ['reason' => '第一副发货']);

        $this->assertSame(
            OrderStatus::PARTIAL_SHIP->value,
            (int) $this->freshOrder((int) $order['id'])['order_status'],
            '1/2 子单发货，主单应聚合为部分发货'
        );

        $history = Db::name('order_status_history')
            ->where('order_id', $order['id'])
            ->where('to_status', 'partial_ship')
            ->where('role', 'system')
            ->find();
        $this->assertNotNull($history, '聚合转换应写入状态历史（writeStatusHistory 不抛参数错误）');
        $this->assertSame('pending_ship', (string) $history['from_status']);
        $this->assertStringContainsString('聚合', (string) $history['action']);
    }

    /**
     * 用例2：全部发货聚合 → COMPLETED（评审 Critical 1），历史表留有记录
     */
    public function testAllItemsShippedAggregatesToCompleted(): void
    {
        $order = $this->seedOrder(['order_status' => OrderStatus::PENDING_SHIP->value, 'item_count' => 2]);
        $itemA = $this->seedOrderItem((int) $order['id'], 1);
        $itemB = $this->seedOrderItem((int) $order['id'], 2);

        $this->service->transitionItem(OrderItem::find($itemA), 4, 'admin');
        $this->service->transitionItem(OrderItem::find($itemB), 4, 'warehouse');

        $this->assertSame(
            OrderStatus::COMPLETED->value,
            (int) $this->freshOrder((int) $order['id'])['order_status'],
            '全部子单已发货，主单应聚合为已完成（子单无独立签收态）'
        );

        // 两次聚合均应落历史：部分发货一次 + 完成一次
        $partialRow = Db::name('order_status_history')
            ->where('order_id', $order['id'])
            ->where('to_status', 'partial_ship')
            ->find();
        $completedRow = Db::name('order_status_history')
            ->where('order_id', $order['id'])
            ->where('from_status', 'partial_ship')
            ->where('to_status', 'completed')
            ->where('role', 'system')
            ->find();
        $this->assertNotNull($partialRow, '第一次聚合（部分发货）应有历史记录');
        $this->assertNotNull($completedRow, '第二次聚合（完成）应有历史记录');

        // 子单状态确实推进到 4
        $this->assertSame(4, (int) Db::name('order_item')->where('id', $itemA)->value('production_status'));
        $this->assertSame(4, (int) Db::name('order_item')->where('id', $itemB)->value('production_status'));
    }

    /**
     * 用例3：非法子单状态跳跃被拒（0→4 不允许），主单状态不受影响
     */
    public function testIllegalItemTransitionRejected(): void
    {
        $order = $this->seedOrder(['order_status' => OrderStatus::PENDING_SHIP->value, 'item_count' => 1]);
        $itemId = $this->seedOrderItem((int) $order['id'], 1, 0);

        try {
            $this->service->transitionItem(OrderItem::find($itemId), 4, 'admin');
            $this->fail('子单状态 0→4 跳跃应被拒绝');
        } catch (\think\exception\ValidateException $e) {
            $this->assertSame(4003, $e->getCode());
        }

        $this->assertSame(OrderStatus::PENDING_SHIP->value, (int) $this->freshOrder((int) $order['id'])['order_status']);
        $this->assertSame(0, (int) Db::name('order_item')->where('id', $itemId)->value('production_status'));
    }
}
