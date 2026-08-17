<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\Order;
use app\common\model\OrderItem;
use app\common\model\Store;
use app\common\enum\OrderStatus;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 订单服务（重构版）
 *
 * 处理订单创建、金额计算和归属快照填充。
 * 状态变更统一委托 OrderStateService（规范 10.1）。
 * 所有金额使用 bcmath 整数计算，单位为"分"（规范 7.2）。
 * 归属快照由 OwnershipService 填充（规范 11）。
 *
 * @see docs/dev_specification_v1.0.md 第四节 & 第十节 & 第十一节
 * @see docs/prd_v3.2.md 4.0 & 4.1-4.7
 */
class OrderService extends BaseService
{
    /**
     * 创建订单
     *
     * 完整流程：
     * 1. 校验门店有效性
     * 2. 幂等校验（idempotent_key）
     * 3. 生成订单号
     * 4. 创建订单主体
     * 5. 创建窗帘明细（调用 PriceService 计价）
     * 6. 填充归属快照（OwnershipService）
     * 7. 汇总订单金额
     * 8. 关联 price_version_id
     *
     * @param int $storeId 门店ID
     * @param int $accountId 下单账号ID
     * @param array $data 订单数据
     * @return Order
     * @throws ValidateException
     */
    public function createOrder(int $storeId, int $accountId, array $data): Order
    {
        $store = Store::find($storeId);
        if (!$store || $store->status !== 1) {
            throw new ValidateException('门店不存在或已停用');
        }

        // 幂等键支持（规范 14.5）
        $idempotentKey = $data['idempotent_key'] ?? null;
        if ($idempotentKey) {
            $existing = Order::where('idempotent_key', $idempotentKey)->find();
            if ($existing) {
                return $existing;
            }
        }

        return $this->transaction(function () use ($store, $accountId, $data, $idempotentKey) {
            // 生成订单号
            $orderNo = Order::generateOrderNo($store->store_no);

            // 获取计价服务
            $priceService = app(PriceService::class);

            // 计算所有明细价格
            $itemsData = $data['items'] ?? [];
            $itemResults = [];
            $subtotalCent = '0';

            foreach ($itemsData as $itemData) {
                $result = $priceService->calculateItemAmount($storeId, $itemData);
                $itemResults[] = $result;
                $subtotalCent = bcadd($subtotalCent, $result['item_total_cent'], 0);
            }

            // 创建订单主体
            $order = new Order();
            $order->save([
                'order_no'          => $orderNo,
                'transaction_type'  => 1, // 门店
                'transaction_id'    => $store->id,
                'created_by'        => $accountId,
                'delivery_method'   => $data['delivery_method'] ?? 1,
                'address_id'        => $data['address_id'] ?? null,
                'receiver_name'     => $data['receiver_name'] ?? '',
                'receiver_phone'    => $data['receiver_phone'] ?? '',
                'receiver_province' => $data['receiver_province'] ?? '',
                'receiver_city'     => $data['receiver_city'] ?? '',
                'receiver_district' => $data['receiver_district'] ?? '',
                'receiver_detail'   => $data['receiver_detail'] ?? '',
                'project_name'      => $data['project_name'] ?? null,
                'end_customer'      => $data['end_customer'] ?? null,
                'order_status'      => OrderStatus::DRAFT->value,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'invoice_required'  => $data['invoice_required'] ?? 0,
                'remark'            => $data['remark'] ?? null,
                'attachments'       => isset($data['attachments']) ? json_encode($data['attachments']) : null,
                // 幂等键
                'idempotent_key'    => $idempotentKey,
                // 价格版本
                'price_version_id'  => $data['price_version_id'] ?? null,
                // 金额字段（分，整数）
                'subtotal_amount_cent' => (int) $subtotalCent,
                'discount_amount_cent' => 0,
                'payable_amount_cent'  => (int) $subtotalCent,
                'paid_amount_cent'     => 0,
                'refund_amount_cent'   => 0,
            ]);

            // 创建窗帘明细（保存计价结果和价格快照）
            $this->createOrderItems($order, $itemsData, $itemResults, $priceService, $storeId);

            // 填充归属快照（PRD 4.0.4 & 规范 11）
            $ownershipService = app(OwnershipService::class);
            $ownershipService->fillOwnershipSnapshot($order);

            // 汇总订单金额
            $this->calculateOrderAmount($order, $priceService, $storeId);

            // 记录操作日志
            $this->logOperation(
                module: 'order',
                action: 'create',
                targetType: 'order',
                targetId: (int) $order->id,
                targetNo: $order->order_no,
                operatorId: $accountId,
                remark: '创建订单',
            );

            Log::info('订单创建成功', [
                'order_no' => $order->order_no,
                'store_id' => $storeId,
                'items_count' => count($itemsData),
                'subtotal_cent' => $subtotalCent,
            ]);

            return $order;
        });
    }

    /**
     * 创建窗帘明细项（含计价快照）
     *
     * 每副窗帘保存完整的计价结果快照，包括价格版本和各项费用。
     *
     * @param Order $order 订单
     * @param array $itemsData 原始明细数据
     * @param array $itemResults PriceService 计算结果
     * @param PriceService $priceService 计价服务
     * @param int $storeId 门店ID
     * @return void
     */
    private function createOrderItems(Order $order, array $itemsData, array $itemResults, PriceService $priceService, int $storeId): void
    {
        foreach ($itemsData as $index => $itemData) {
            $result = $itemResults[$index];
            $itemNo = $order->order_no . '-C' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            $item = new OrderItem();
            $item->save([
                'item_no'         => $itemNo,
                'order_id'        => $order->id,
                'sequence'        => $index + 1,
                'install_position' => $itemData['install_position'] ?? '',
                // 尺寸（厘米，DECIMAL）
                'width_cm'        => $itemData['width_cm'],
                'height_cm'       => $itemData['height_cm'],
                'area_m2'         => $result['area_m2'],
                // 轨道
                'track_color'     => $itemData['track_color'] ?? '黑色',
                // 面料快照（PRD 4.3 & 规范 7.4）
                'fabric_no'       => $itemData['fabric_no'] ?? '',
                'fabric_price_cent' => $this->getFabricPriceCent($itemData['fabric_no'] ?? ''),
                // 选装配件
                'power_type'      => $itemData['power_type'] ?? 1,
                'remote_type'     => $itemData['remote_type'] ?? 1,
                'wall_control_type' => $itemData['wall_control_type'] ?? 0,
                'wall_control_quantity' => $itemData['wall_control_quantity'] ?? 0,
                // 套件
                'use_inventory'   => $itemData['use_inventory'] ?? 0,
                'inventory_deduct_count' => $itemData['inventory_deduct_count'] ?? 0,
                'kit_price_cent'  => $priceService->getKitPriceCent($storeId),
                // 各项费用快照（分）
                'horizontal_track_cent' => (int) $result['horizontal_track_cent'],
                'vertical_track_cent'   => (int) $result['vertical_track_cent'],
                'track_amount_cent'     => (int) $result['track_cent'],
                'fabric_amount_cent'    => (int) $result['fabric_cent'],
                'accessory_amount_cent' => (int) $result['accessory_cent'],
                'kit_amount_cent'       => (int) $result['kit_cent'],
                'nonstandard_amount_cent' => (int) $result['nonstandard_cent'],
                'item_total_cent'       => (int) $result['item_total_cent'],
                // 非标标记
                'is_nonstandard'  => $result['is_nonstandard'] ? 1 : 0,
                'nonstandard_hint' => $result['nonstandard_hint'],
                // 安装条件
                'install_condition' => $itemData['install_condition'] ?? null,
                'remark'          => $itemData['remark'] ?? null,
                // 初始状态
                'technical_status'  => 0,
                'production_status' => 0,
                'quality_status'    => 0,
                'shipment_status'   => 0,
            ]);
        }
    }

    /**
     * 计算订单金额汇总（分）
     *
     * 使用 bcmath 计算订单级金额汇总。
     * 公式：应付 = 商品小计 - 优惠 + 其他费用
     *
     * @param Order $order 订单
     * @param PriceService $priceService 计价服务
     * @param int $storeId 门店ID
     * @return void
     */
    public function calculateOrderAmount(Order $order, ?PriceService $priceService = null, int $storeId = 0): void
    {
        if (!$priceService) {
            $priceService = app(PriceService::class);
        }

        $items = OrderItem::where('order_id', $order->id)->select();

        // 初始化汇总（分，字符串）
        $trackTotalCent     = '0';
        $fabricTotalCent    = '0';
        $accessoryTotalCent = '0';
        $kitTotalCent       = '0';
        $nonstandardTotalCent = '0';
        $areaTotalM2        = '0';
        $inventoryUsed      = 0;
        $newPurchase        = 0;
        $newPurchaseAmountCent = '0';
        $subtotalCent       = '0';

        foreach ($items as $item) {
            $trackTotalCent       = bcadd($trackTotalCent, (string) ($item->track_amount_cent ?? 0), 0);
            $fabricTotalCent      = bcadd($fabricTotalCent, (string) ($item->fabric_amount_cent ?? 0), 0);
            $accessoryTotalCent   = bcadd($accessoryTotalCent, (string) ($item->accessory_amount_cent ?? 0), 0);
            $kitTotalCent         = bcadd($kitTotalCent, (string) ($item->kit_amount_cent ?? 0), 0);
            $nonstandardTotalCent = bcadd($nonstandardTotalCent, (string) ($item->nonstandard_amount_cent ?? 0), 0);
            $areaTotalM2          = bcadd($areaTotalM2, (string) ($item->area_m2 ?? '0'), 4);
            $subtotalCent         = bcadd($subtotalCent, (string) ($item->item_total_cent ?? 0), 0);

            if ($item->use_inventory) {
                $inventoryUsed++;
            } else {
                $newPurchase++;
                $newPurchaseAmountCent = bcadd($newPurchaseAmountCent, (string) ($item->kit_amount_cent ?? 0), 0);
            }
        }

        $discountCent = (string) ($order->discount_amount_cent ?? 0);
        $payableCent = bcsub(bcadd($subtotalCent, (string) ($order->other_fee_cent ?? 0), 0), $discountCent, 0);

        // 应付金额不能为负
        if (bccomp($payableCent, '0', 0) < 0) {
            $payableCent = '0';
        }

        $order->save([
            'item_count'              => count($items),
            'track_total_amount_cent' => (int) $trackTotalCent,
            'fabric_area_total_m2'    => $areaTotalM2,
            'fabric_total_amount_cent' => (int) $fabricTotalCent,
            'accessory_total_amount_cent' => (int) $accessoryTotalCent,
            'inventory_used_count'    => $inventoryUsed,
            'new_purchase_count'      => $newPurchase,
            'new_purchase_amount_cent' => (int) $newPurchaseAmountCent,
            'kit_total_amount_cent'   => (int) $kitTotalCent,
            'nonstandard_total_amount_cent' => (int) $nonstandardTotalCent,
            'subtotal_amount_cent'    => (int) $subtotalCent,
            'payable_amount_cent'     => (int) $payableCent,
        ]);
    }

    /**
     * 获取面料单价（分）
     *
     * @param string $fabricNo 面料编号
     * @return int
     */
    private function getFabricPriceCent(string $fabricNo): int
    {
        if (empty($fabricNo)) {
            return 0;
        }

        $fabric = Db::name('fabric')
            ->where('fabric_no', $fabricNo)
            ->where('listing_status', 1)
            ->where('orderable', 1)
            ->find();

        return $fabric ? (int) ($fabric['price_per_sqm_cent'] ?? 0) : 0;
    }
}
