<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\Order;
use app\common\model\OrderItem;
use app\common\model\Store;
use app\common\enum\CustomerType;
use app\common\enum\ErrorCode;
use app\common\enum\OrderStatus;
use app\common\enum\PaymentChannel;
use app\common\enum\PaymentStatus;
use app\common\exception\BusinessException;
use app\common\support\RedisLock;
use app\common\support\SequenceNo;
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
     * 2. 生成订单号
     * 3. 创建订单主体
     * 4. 创建窗帘明细（调用 PriceService 计价）
     * 5. 填充归属快照（OwnershipService）
     * 6. 汇总订单金额
     *
     * 批次2c：deploy lj_order 无 idempotent_key/price_version_id/subtotal_amount_cent/
     * payable_amount_cent/refund_amount_cent 列，相应写入已删除；应付总额落
     * total_amount_cent，实付累计 paid_amount_cent；订单级幂等策略移交批次4决策。
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

        return $this->transaction(function () use ($store, $accountId, $data) {
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

            // 取号+落库包进 withRetry（评审补漏 14）：order_no 撞唯一键
            // 冲突时重新取号重试（≤3 次），与支付单/储值单插入一致
            $order = SequenceNo::withRetry(function () use ($store, $accountId, $data, $subtotalCent) {
                // 生成订单号
                $orderNo = Order::generateOrderNo($store->store_no);

                // 创建订单主体（deploy lj_order 实际列）
                $order = new Order();
                $order->save([
                    'order_no'          => $orderNo,
                    'transaction_type'  => CustomerType::STORE->value,
                    'transaction_id'    => $store->id,
                    'service_store_id'  => $store->id,
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
                    // 金额字段（分，整数）：应付总额 total_amount_cent，实付 paid_amount_cent
                    // discount_amount_cent 默认 0；无 subtotal/payable/refund_amount_cent 列
                    'total_amount_cent' => (int) $subtotalCent,
                    'discount_amount_cent' => 0,
                    'paid_amount_cent'  => 0,
                ]);

                return $order;
            });

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

            // 批次2c：deploy lj_order_item 无 is_nonstandard/nonstandard_hint 列，
            // 非标提示语义改存 remark（PRD 提示语义保留，列已废弃）
            $remark = $itemData['remark'] ?? null;
            if (!empty($result['nonstandard_hint'])) {
                $hint = '[非标]' . $result['nonstandard_hint'];
                $remark = $remark === null || $remark === '' ? $hint : $remark . '；' . $hint;
            }

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
                // 轨道（deploy 列：轨长米数 NOT NULL + 费用合计）
                'track_color'     => $itemData['track_color'] ?? '黑色',
                'track_horizontal_length_m' => $result['track_horizontal_length_m'],
                'track_vertical_length_m'   => $result['track_vertical_length_m'],
                'track_amount_cent'         => (int) $result['track_cent'],
                // 面料快照（PRD 4.3 & 规范 7.4）
                'fabric_no'       => $itemData['fabric_no'] ?? '',
                'fabric_price_cent' => $this->getFabricPriceCent($itemData['fabric_no'] ?? ''),
                'fabric_amount_cent' => (int) $result['fabric_cent'],
                // 选装配件（deploy NOT NULL DEFAULT 0 子项列全部显式写入）
                'power_type'      => $itemData['power_type'] ?? 1,
                'power_surcharge_cent' => (int) $result['power_surcharge_cent'],
                'remote_type'     => $itemData['remote_type'] ?? 1,
                'remote_surcharge_cent' => (int) $result['remote_surcharge_cent'],
                'wall_control_type' => $itemData['wall_control_type'] ?? 0,
                'wall_control_quantity' => $itemData['wall_control_quantity'] ?? 0,
                'wall_control_price_cent'  => (int) $result['wall_control_price_cent'],
                'wall_control_amount_cent' => (int) $result['wall_control_amount_cent'],
                'accessory_amount_cent' => (int) $result['accessory_cent'],
                // 套件（deploy 列：kit_id/kit_price_cent/kit_amount_cent/use_inventory）
                'use_inventory'   => $itemData['use_inventory'] ?? 0,
                'kit_id'          => (int) $result['kit_id'],
                'kit_price_cent'  => (int) $result['kit_price_cent'],
                'kit_amount_cent' => (int) $result['kit_cent'],
                // 各项费用快照（分）
                'nonstandard_amount_cent' => (int) $result['nonstandard_cent'],
                'item_total_cent'       => (int) $result['item_total_cent'],
                // 安装条件
                'install_condition' => $itemData['install_condition'] ?? null,
                'remark'          => $remark,
                // 初始状态（deploy 列名 qc_status/shipping_status）
                'technical_status'  => 0,
                'production_status' => 0,
                'qc_status'         => 0,
                'shipping_status'   => 0,
            ]);
        }
    }

    /**
     * 计算订单金额汇总（分）
     *
     * 使用 bcmath 计算订单级金额汇总。
     * 批次2c：列名对齐 deploy lj_order——轨道 track_amount_cent、面料 fabric_area_total/
     * fabric_amount_cent、选装 accessory_amount_cent、非标 nonstandard_amount_cent；
     * 套件费归并 new_purchase_amount_cent（库存抵扣套件费为0，语义无损）；
     * 应付总额落 total_amount_cent（deploy 无 subtotal/payable/other_fee 列）。
     * 公式：应付总额 = 明细合计 - 优惠
     *
     * @param Order $order 订单
     * @param PriceService $priceService 计价服务
     * @param int $storeId 门店ID
     * @return void
     */
    public function calculateOrderAmount(Order $order, ?PriceService $priceService = null, int $storeId = 0): void
    {
        $items = OrderItem::where('order_id', $order->id)->select();

        // 初始化汇总（分，字符串）
        $trackTotalCent     = '0';
        $fabricTotalCent    = '0';
        $accessoryTotalCent = '0';
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
        $totalCent = bcsub($subtotalCent, $discountCent, 0);

        // 应付总额不能为负
        if (bccomp($totalCent, '0', 0) < 0) {
            $totalCent = '0';
        }

        $order->save([
            'item_count'              => count($items),
            'track_amount_cent'       => (int) $trackTotalCent,
            'fabric_area_total'       => $areaTotalM2,
            'fabric_amount_cent'      => (int) $fabricTotalCent,
            'accessory_amount_cent'   => (int) $accessoryTotalCent,
            'inventory_used_count'    => $inventoryUsed,
            'new_purchase_count'      => $newPurchase,
            'new_purchase_amount_cent' => (int) $newPurchaseAmountCent,
            'nonstandard_amount_cent' => (int) $nonstandardTotalCent,
            'total_amount_cent'       => (int) $totalCent,
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

    // ================================================================
    // 批次3新增：门店端订单编辑/预览/提交/删除最小编排方法
    //（Controller 薄封装层，业务规则全部在此实现；
    //   计价权归 PriceService，状态变更归 OrderStateService，
    //   库存归 InventoryService，余额归 BalanceAccountService）
    // ================================================================

    /**
     * 按订单号查找本门店订单（含门店数据越权过滤）
     *
     * @param int $storeId 门店ID
     * @param string $orderNo 订单号
     * @return Order|null
     */
    public function findStoreOrder(int $storeId, string $orderNo): ?Order
    {
        return Order::where('order_no', $orderNo)
            ->where('transaction_type', CustomerType::STORE->value)
            ->where('transaction_id', $storeId)
            ->find();
    }

    /**
     * 查找本门店订单，不存在抛 3002 业务异常
     */
    private function findStoreOrderOrFail(int $storeId, string $orderNo): Order
    {
        $order = $this->findStoreOrder($storeId, $orderNo);
        if (!$order) {
            throw new BusinessException(ErrorCode::DATA_NOT_FOUND, '订单不存在');
        }
        return $order;
    }

    /**
     * 仅草稿单可编辑明细（api_part2：明细增删改均在草稿阶段）
     */
    private function assertDraftOrder(Order $order): void
    {
        if (OrderStatus::from($order->order_status) !== OrderStatus::DRAFT) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '当前订单状态不允许修改明细');
        }
    }

    /**
     * 更新订单基本信息（草稿/待支付可改，api_part2 §2.3）
     *
     * 仅允许白名单字段，只写前端实际传入的字段（receiver_* 列为 NOT NULL，
     * 未传入不覆盖）。
     *
     * @throws BusinessException
     */
    public function updateOrderInfo(int $storeId, string $orderNo, array $data, int $operatorId = 0): Order
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);

        $status = OrderStatus::from($order->order_status);
        if (!in_array($status, [OrderStatus::DRAFT, OrderStatus::PENDING_PAY], true)) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '当前订单状态不允许修改');
        }

        $allowed = [
            'delivery_method', 'address_id',
            'receiver_name', 'receiver_phone', 'receiver_province',
            'receiver_city', 'receiver_district', 'receiver_detail',
            'project_name', 'end_customer', 'expected_delivery_date',
            'invoice_required', 'remark', 'attachments',
        ];
        $update = array_intersect_key($data, array_flip($allowed));

        // 与 createOrder 保持一致：attachments 手动 JSON 编码
        if (isset($update['attachments']) && is_array($update['attachments'])) {
            $update['attachments'] = json_encode($update['attachments']);
        }

        if (!empty($update)) {
            $order->save($update);

            $this->logOperation(
                module: 'order',
                action: 'update',
                targetType: 'order',
                targetId: (int) $order->id,
                targetNo: $order->order_no,
                operatorId: $operatorId,
                remark: '更新订单基本信息',
            );
        }

        return $order;
    }

    /**
     * 新增窗帘明细（仅草稿单）
     *
     * 计价由 PriceService 实时计算，不信任前端金额（规范 8.1）。
     *
     * @throws BusinessException|ValidateException
     */
    public function addItemToOrder(int $storeId, string $orderNo, array $itemData): OrderItem
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);
        $this->assertDraftOrder($order);

        $priceService = app(PriceService::class);
        // 尺寸/面料等业务校验由 PriceService 抛 ValidateException（4202/4201 语义）
        $result = $priceService->calculateItemAmount($storeId, $itemData);

        return $this->transaction(function () use ($order, $itemData, $result, $priceService, $storeId) {
            // 订单行锁 + MAX(sequence)+1 取序号（评审 Warning 13）：
            // count()+1 在删除明细后重号会撞 item_no 唯一约束
            Order::where('id', $order->id)->lock(true)->find();
            $sequence = (int) OrderItem::where('order_id', $order->id)->max('sequence') + 1;

            $item = new OrderItem();
            $item->save($this->buildItemSaveData($order, $sequence, $itemData, $result));

            // 同步订单金额汇总
            $this->calculateOrderAmount($order, $priceService, $storeId);

            $this->logOperation(
                module: 'order',
                action: 'add_item',
                targetType: 'order_item',
                targetId: (int) $item->id,
                targetNo: $item->item_no,
                remark: '新增窗帘明细',
            );

            return $item;
        });
    }

    /**
     * 更新窗帘明细（仅草稿单，未传字段沿用原值后重新计价）
     *
     * @throws BusinessException|ValidateException
     */
    public function updateOrderItem(int $storeId, string $orderNo, int $itemId, array $itemData): OrderItem
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);
        $this->assertDraftOrder($order);

        $item = OrderItem::where('id', $itemId)
            ->where('order_id', $order->id)
            ->find();
        if (!$item) {
            throw new BusinessException(ErrorCode::DATA_NOT_FOUND, '窗帘明细不存在');
        }

        $priceService = app(PriceService::class);
        $merged = $this->itemToPricingData($item, $itemData);
        $result = $priceService->calculateItemAmount($storeId, $merged);

        return $this->transaction(function () use ($order, $item, $merged, $result, $priceService, $storeId) {
            $item->save($this->buildItemSaveData($order, (int) $item->sequence, $merged, $result));

            $this->calculateOrderAmount($order, $priceService, $storeId);

            $this->logOperation(
                module: 'order',
                action: 'update_item',
                targetType: 'order_item',
                targetId: (int) $item->id,
                targetNo: $item->item_no,
                remark: '更新窗帘明细',
            );

            return $item;
        });
    }

    /**
     * 删除窗帘明细（仅草稿单；草稿阶段尚未锁库存，无需释放）
     *
     * @throws BusinessException
     */
    public function deleteOrderItem(int $storeId, string $orderNo, int $itemId): void
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);
        $this->assertDraftOrder($order);

        $item = OrderItem::where('id', $itemId)
            ->where('order_id', $order->id)
            ->find();
        if (!$item) {
            throw new BusinessException(ErrorCode::DATA_NOT_FOUND, '窗帘明细不存在');
        }

        $this->transaction(function () use ($order, $item, $storeId) {
            $item->delete();

            $this->calculateOrderAmount($order, app(PriceService::class), $storeId);

            $this->logOperation(
                module: 'order',
                action: 'delete_item',
                targetType: 'order_item',
                targetId: (int) $item->id,
                targetNo: (string) $item->item_no,
                remark: '删除窗帘明细',
            );
        });
    }

    /**
     * 复制窗帘明细（仅草稿单）
     *
     * @param array $options copy_dimensions(0|1，默认1)；不复制尺寸时可传 width_cm/height_cm
     * @throws BusinessException|ValidateException
     */
    public function copyOrderItem(int $storeId, string $orderNo, int $sourceItemId, array $options = []): OrderItem
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);
        $this->assertDraftOrder($order);

        $source = OrderItem::where('id', $sourceItemId)
            ->where('order_id', $order->id)
            ->find();
        if (!$source) {
            throw new BusinessException(ErrorCode::DATA_NOT_FOUND, '源窗帘明细不存在');
        }

        $copyDimensions = (int) ($options['copy_dimensions'] ?? 1) === 1;

        $itemData = $this->itemToPricingData($source, []);
        $itemData['remark'] = null;

        if (!$copyDimensions) {
            if (empty($options['width_cm']) || empty($options['height_cm'])) {
                throw new BusinessException(ErrorCode::PARAM_MISSING, '不复制尺寸时必须提供新的宽度和高度');
            }
            $itemData['width_cm']  = (string) $options['width_cm'];
            $itemData['height_cm'] = (string) $options['height_cm'];
        }

        $priceService = app(PriceService::class);
        $result = $priceService->calculateItemAmount($storeId, $itemData);

        return $this->transaction(function () use ($order, $itemData, $result, $priceService, $storeId) {
            // 订单行锁 + MAX(sequence)+1 取序号（评审 Warning 13，同 addItemToOrder）
            Order::where('id', $order->id)->lock(true)->find();
            $sequence = (int) OrderItem::where('order_id', $order->id)->max('sequence') + 1;

            $item = new OrderItem();
            $item->save($this->buildItemSaveData($order, $sequence, $itemData, $result));

            $this->calculateOrderAmount($order, $priceService, $storeId);

            $this->logOperation(
                module: 'order',
                action: 'copy_item',
                targetType: 'order_item',
                targetId: (int) $item->id,
                targetNo: $item->item_no,
                remark: '复制窗帘明细',
            );

            return $item;
        });
    }

    /**
     * 订单预览（读已落库快照，含明细费用与整单汇总）
     *
     * @throws BusinessException
     */
    public function getOrderPreview(int $storeId, string $orderNo): array
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);

        $items = OrderItem::where('order_id', $order->id)
            ->order('sequence', 'asc')
            ->select();

        $list = [];
        foreach ($items as $item) {
            $list[] = [
                'item_id'       => (int) $item->id,
                'item_no'       => $item->item_no,
                'install_position' => $item->install_position,
                'width_cm'      => (string) $item->width_cm,
                'height_cm'     => (string) $item->height_cm,
                'area_m2'       => (string) $item->area_m2,
                'track_amount_cent'     => (int) $item->track_amount_cent,
                'fabric_amount_cent'    => (int) $item->fabric_amount_cent,
                'accessory_amount_cent' => (int) $item->accessory_amount_cent,
                'kit_amount_cent'       => (int) $item->kit_amount_cent,
                'nonstandard_amount_cent' => (int) $item->nonstandard_amount_cent,
                'item_total_cent'       => (int) $item->item_total_cent,
            ];
        }

        return [
            'order_no' => $order->order_no,
            'items'    => $list,
            'summary'  => [
                'total_amount_cent'    => (int) $order->total_amount_cent,
                'discount_amount_cent' => (int) $order->discount_amount_cent,
                'paid_amount_cent'     => (int) $order->paid_amount_cent,
            ],
        ];
    }

    /**
     * 价格预览（PriceService 实时重算，下单前查看后端计算结果，规范 §8）
     *
     * 不落库，只返回实时计价结果；PriceService 拥有最终计价权。
     *
     * @throws BusinessException|ValidateException
     */
    public function repriceOrder(int $storeId, string $orderNo): array
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);

        $items = OrderItem::where('order_id', $order->id)
            ->order('sequence', 'asc')
            ->select();

        if ($items->isEmpty()) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '订单无窗帘明细，无法计价');
        }

        $pricingItems = [];
        foreach ($items as $item) {
            $pricingItems[] = $this->itemToPricingData($item, []);
        }

        $preview = app(PriceService::class)->previewPrice($storeId, $pricingItems);

        $itemResults = [];
        foreach ($items as $index => $item) {
            $r = $preview['items'][$index];
            $itemResults[] = [
                'item_id'        => (int) $item->id,
                'item_no'        => $item->item_no,
                'track_amount_cent'     => (int) $r['track_cent'],
                'fabric_amount_cent'    => (int) $r['fabric_cent'],
                'accessory_amount_cent' => (int) $r['accessory_cent'],
                'kit_amount_cent'       => (int) $r['kit_cent'],
                'nonstandard_amount_cent' => (int) $r['nonstandard_cent'],
                'item_total_cent'       => (int) $r['item_total_cent'],
                'is_nonstandard'        => (bool) $r['is_nonstandard'],
                'nonstandard_hint'      => $r['nonstandard_hint'],
            ];
        }

        return [
            'order_no'          => $order->order_no,
            'items'             => $itemResults,
            'total_amount_cent' => (int) $preview['payable_cent'],
        ];
    }

    /**
     * 删除草稿订单（软删除，仅草稿状态）
     *
     * @throws BusinessException
     */
    public function deleteDraftOrder(int $storeId, string $orderNo, int $operatorId = 0): void
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);

        if (OrderStatus::from($order->order_status) !== OrderStatus::DRAFT) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '仅草稿订单可删除');
        }

        $order->save(['deleted_at' => date('Y-m-d H:i:s')]);

        $this->logOperation(
            module: 'order',
            action: 'delete',
            targetType: 'order',
            targetId: (int) $order->id,
            targetNo: $order->order_no,
            operatorId: $operatorId,
            remark: '删除草稿订单',
        );
    }

    /**
     * 提交订单（下单主流程入口）
     *
     * 草稿单 → PriceService 最终计价（重算全部明细并落库）→
     * 库存锁定（use_inventory 明细）→ 价格锁定 30 天 →
     * OrderStateService 状态机提交（DRAFT → PENDING_PAY，禁止裸改状态）。
     *
     * 批次4 订单级防重（批次3 移交项）：
     * - 优先前端幂等键（Idempotent-Key 头）：键 `submit:{idempotentKey}` 短锁；
     * - 无则 `submit:{storeId}:{orderNo}` 短锁（Redis SET NX EX 30s）防双击重复提交；
     * - 重复提交直接拒绝（短锁非幂等缓存，不返回上次结果），
     *   最终兜底：order_no 唯一约束 + 状态机 DRAFT→PENDING_PAY 单次合法。
     *
     * @param string $idempotentKey 前端幂等键（Idempotent-Key 头，可为空）
     * @return array 提交结果（订单号/状态/应付/价格锁定截止）
     * @throws BusinessException|ValidateException
     */
    public function submitOrder(int $storeId, int $accountId, string $orderNo, string $idempotentKey = ''): array
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);

        if (OrderStatus::from($order->order_status) !== OrderStatus::DRAFT) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '仅草稿订单可提交');
        }

        $items = OrderItem::where('order_id', $order->id)->order('sequence', 'asc')->select();
        if ($items->isEmpty()) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '订单无窗帘明细，无法提交');
        }

        // 订单级防双击短锁：优先前端幂等键，否则按 门店+草稿单号（30s 自动过期）
        $clientKey = trim($idempotentKey);
        $submitLockKey = $clientKey !== ''
            ? "submit:{$clientKey}"
            : "submit:{$storeId}:{$orderNo}";
        $lockToken = RedisLock::token();
        if (!RedisLock::acquire($submitLockKey, 30, $lockToken)) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '订单正在提交，请勿重复操作');
        }

        try {
            return $this->transaction(function () use ($order, $items, $storeId, $accountId) {
            $priceService = app(PriceService::class);
            $inventoryService = app(InventoryService::class);

            // 1. 最终计价：重算全部明细并更新价格快照（PriceService 最终计价权）
            foreach ($items as $item) {
                $itemData = $this->itemToPricingData($item, []);
                $result = $priceService->calculateItemAmount($storeId, $itemData);
                $item->save($this->buildItemSaveData($order, (int) $item->sequence, $itemData, $result));

                // 2. 库存锁定：使用库存套件的明细逐副锁定
                //    幂等键与状态机副作用（提交锁库存）同键：lock:{order_no}:{item_id}
                if ((int) $item->use_inventory === 1) {
                    $inventoryService->lockInventory(
                        $storeId,
                        (string) $result['kit_sku'],
                        1,
                        (int) $order->id,
                        $order->order_no,
                        "lock:{$order->order_no}:{$item->id}",
                    );
                }
            }

            // 3. 订单金额汇总落库（应付总额 total_amount_cent）
            $this->calculateOrderAmount($order, $priceService, $storeId);

            // 4. 价格锁定 30 天（api_part2 提交订单响应契约）
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+30 days'));
            $order->save([
                'price_locked_at'    => date('Y-m-d H:i:s'),
                'price_locked_until' => $lockedUntil,
            ]);

            // 5. 状态机提交：DRAFT → PENDING_PAY（规范 10.1）
            app(OrderStateService::class)->transition($order, OrderStatus::PENDING_PAY, 'store', [
                'operator_id' => $accountId,
                'reason'      => '门店提交订单',
            ]);

            $this->logOperation(
                module: 'order',
                action: 'submit',
                targetType: 'order',
                targetId: (int) $order->id,
                targetNo: $order->order_no,
                operatorId: $accountId,
                remark: '提交订单',
            );

            return [
                'id'                 => (int) $order->id,
                'order_no'           => $order->order_no,
                'order_status'       => OrderStatus::PENDING_PAY->value,
                'total_amount_cent'  => (int) $order->total_amount_cent,
                'price_locked_until' => $lockedUntil,
            ];
            });
        } finally {
            RedisLock::release($submitLockKey, $lockToken);
        }
    }

    /**
     * 订单余额支付（复用批次1 事务模式）
     *
     * 余额扣减、写流水、写支付记录、订单金额/状态更新同一事务；
     * 状态变更一律走 OrderStateService（规范 10.1）。
     * 供 BalanceAccountController::pay 调用（订单余额主入口仍在
     * OrderController::payBalance，两者语义一致）。
     *
     * @param int $balanceAccountId 资金账户ID（lj_customer_balance_account.id）
     * @param int $amountCent 前端传入支付金额（必须等于订单未付金额，不支持部分/混合支付）
     * @throws BusinessException|ValidateException
     */
    public function payOrderByBalance(int $storeId, string $orderNo, int $loginAccountId, int $balanceAccountId, int $amountCent): array
    {
        $order = $this->findStoreOrderOrFail($storeId, $orderNo);

        if (OrderStatus::from($order->order_status) !== OrderStatus::PENDING_PAY) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '订单状态不允许支付');
        }

        $unpaidCent = (int) $order->total_amount_cent - (int) $order->paid_amount_cent;
        if ($unpaidCent <= 0) {
            throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '订单已支付完成');
        }
        if ($amountCent !== $unpaidCent) {
            throw new BusinessException(ErrorCode::PAYMENT_AMOUNT_MISMATCH, '支付金额与订单未付金额不一致');
        }

        $balanceService = app(BalanceAccountService::class);
        // 批次4：支付幂等键含渠道维度，与 PaymentService::createPayment 规范一致
        $idempotentKey = "order_pay:{$orderNo}:" . PaymentChannel::BALANCE->value;

        // 进事务前互斥校验（评审 Warning 3，PRD 4.9.4）：同单已有其他渠道
        // 待支付/成功支付单时拒绝余额支付，抛 4104
        $paymentService = app(PaymentService::class);
        $paymentService->validatePaymentMutualExclusion((int) $order->id, PaymentChannel::BALANCE);

        $result = $this->transaction(function () use ($balanceService, $paymentService, $order, $unpaidCent, $loginAccountId, $balanceAccountId, $idempotentKey) {
            // 订单行锁后复检（评审 Warning 3）：状态/未付金额/渠道互斥以锁内最新值为准
            $lockedOrder = Order::where('id', $order->id)->lock(true)->find();
            if (!$lockedOrder) {
                throw new BusinessException(ErrorCode::DATA_NOT_FOUND, '订单不存在');
            }
            if (OrderStatus::from((int) $lockedOrder->order_status) !== OrderStatus::PENDING_PAY) {
                throw new BusinessException(ErrorCode::ILLEGAL_STATUS_TRANSITION, '订单状态不允许支付');
            }
            $lockedUnpaidCent = (int) $lockedOrder->total_amount_cent - (int) $lockedOrder->paid_amount_cent;
            if ($lockedUnpaidCent !== $unpaidCent || $lockedUnpaidCent <= 0) {
                throw new BusinessException(ErrorCode::PAYMENT_AMOUNT_MISMATCH, '订单金额已变更，请刷新后重试');
            }
            $paymentService->validatePaymentMutualExclusion((int) $lockedOrder->id, PaymentChannel::BALANCE);

            $payResult = $balanceService->payByBalanceWithinTransaction(
                $lockedOrder->order_no,
                $lockedUnpaidCent,
                $balanceAccountId,
                $idempotentKey,
                ['order_id' => (int) $lockedOrder->id, 'operator_id' => $loginAccountId],
            );

            // 幂等命中：不重复更新订单
            if (!empty($payResult['idempotent'])) {
                return $payResult;
            }

            // 更新订单非状态支付字段（批次2c：渠道只存 lj_payment；累加基于锁内最新值）
            Db::name('order')
                ->where('id', $lockedOrder->id)
                ->update([
                    'paid_amount_cent' => (int) $lockedOrder->paid_amount_cent + $lockedUnpaidCent,
                    'paid_at'          => date('Y-m-d H:i:s'),
                    'payment_status'   => PaymentStatus::PAID->value,
                ]);

            // 状态变更走状态机：PENDING_PAY → PAYING（store）→ PAID_PENDING（system）
            $stateService = app(OrderStateService::class);
            $stateService->transition($lockedOrder, OrderStatus::PAYING, 'store', [
                'reason'      => '发起支付（余额支付）',
                'operator_id' => $loginAccountId,
            ]);
            $stateService->transition($lockedOrder, OrderStatus::PAID_PENDING, 'system', [
                'reason'     => '余额支付成功',
                'payment_no' => $payResult['payment_no'],
            ]);

            return $payResult;
        });

        return $result;
    }

    /**
     * 由已落库明细构造计价入参（可叠加覆盖字段）
     *
     * @param OrderItem $item 已落库明细
     * @param array $overrides 覆盖字段（null 值忽略）
     * @return array PriceService::calculateItemAmount 入参
     */
    private function itemToPricingData(OrderItem $item, array $overrides = []): array
    {
        // 剖除旧的非标提示，避免重复追加
        $remark = (string) ($item->remark ?? '');
        $remark = trim((string) preg_replace('/；?\[非标\][^；]*/u', '', $remark), " \t\n\r\0\x0B；;");

        $base = [
            'install_position' => (string) $item->install_position,
            'width_cm'         => (string) $item->width_cm,
            'height_cm'        => (string) $item->height_cm,
            'track_color'      => (string) $item->track_color,
            'fabric_no'        => (string) $item->fabric_no,
            'power_type'       => (int) $item->power_type,
            'remote_type'      => (int) $item->remote_type,
            'wall_control_type'     => (int) $item->wall_control_type,
            'wall_control_quantity' => (int) $item->wall_control_quantity,
            'use_inventory'    => (int) $item->use_inventory,
            'inventory_deduct_count' => (int) $item->use_inventory === 1 ? 1 : 0,
            'install_condition' => $item->install_condition,
            'remark'           => $remark === '' ? null : $remark,
        ];

        return array_merge($base, array_filter($overrides, static fn ($v) => $v !== null));
    }

    /**
     * 构造明细落库数据（含计价快照，与 createOrderItems 字段对齐）
     *
     * @param Order $order 订单
     * @param int $sequence 序号
     * @param array $itemData 明细入参
     * @param array $result PriceService 计价结果
     * @return array OrderItem save 数据
     */
    private function buildItemSaveData(Order $order, int $sequence, array $itemData, array $result): array
    {
        $itemNo = $order->order_no . '-C' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        // 非标提示存 remark（批次2c：deploy 无 is_nonstandard 列）
        $remark = $itemData['remark'] ?? null;
        if (!empty($result['nonstandard_hint'])) {
            $hint = '[非标]' . $result['nonstandard_hint'];
            $remark = $remark === null || $remark === '' ? $hint : $remark . '；' . $hint;
        }

        return [
            'item_no'          => $itemNo,
            'order_id'         => $order->id,
            'sequence'         => $sequence,
            'install_position' => $itemData['install_position'] ?? '',
            'width_cm'         => $itemData['width_cm'],
            'height_cm'        => $itemData['height_cm'],
            'area_m2'          => $result['area_m2'],
            'track_color'      => $itemData['track_color'] ?? '黑色',
            'track_horizontal_length_m' => $result['track_horizontal_length_m'],
            'track_vertical_length_m'   => $result['track_vertical_length_m'],
            'track_amount_cent'         => (int) $result['track_cent'],
            'fabric_no'        => $itemData['fabric_no'] ?? '',
            'fabric_price_cent' => $this->getFabricPriceCent((string) ($itemData['fabric_no'] ?? '')),
            'fabric_amount_cent' => (int) $result['fabric_cent'],
            'power_type'       => $itemData['power_type'] ?? 1,
            'power_surcharge_cent' => (int) $result['power_surcharge_cent'],
            'remote_type'      => $itemData['remote_type'] ?? 1,
            'remote_surcharge_cent' => (int) $result['remote_surcharge_cent'],
            'wall_control_type' => $itemData['wall_control_type'] ?? 0,
            'wall_control_quantity' => $itemData['wall_control_quantity'] ?? 0,
            'wall_control_price_cent'  => (int) $result['wall_control_price_cent'],
            'wall_control_amount_cent' => (int) $result['wall_control_amount_cent'],
            'accessory_amount_cent' => (int) $result['accessory_cent'],
            'use_inventory'    => $itemData['use_inventory'] ?? 0,
            'kit_id'           => (int) $result['kit_id'],
            'kit_price_cent'   => (int) $result['kit_price_cent'],
            'kit_amount_cent'  => (int) $result['kit_cent'],
            'nonstandard_amount_cent' => (int) $result['nonstandard_cent'],
            'item_total_cent'  => (int) $result['item_total_cent'],
            'install_condition' => $itemData['install_condition'] ?? null,
            'remark'           => $remark,
        ];
    }
}
