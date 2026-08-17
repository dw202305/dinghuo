<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\OrderStatus;
use app\common\model\Order;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 支付服务（重构版）
 *
 * 创建支付、回调处理、查询、退款。
 * 新增功能：
 * - 支付方式互斥校验（PRD 4.9.4）：一张订单只能选余额/微信/支付宝一种
 * - 幂等键支持（规范 14.5）
 * - 余额支付走 BalanceAccountService
 * - 支付回调幂等处理（重复回调返回原结果）
 * - 统一通过 OrderStateService 变更订单状态（规范 10.1）
 *
 * @see docs/dev_specification_v1.0.md 第十二节
 * @see docs/prd_v3.2.md 4.9.4 & 4.9.5
 */
class PaymentService extends BaseService
{
    /**
     * 支付渠道常量
     */
    const CHANNEL_BALANCE = 3; // 余额
    const CHANNEL_WECHAT  = 1; // 微信支付
    const CHANNEL_ALIPAY  = 2; // 支付宝

    /**
     * 创建支付
     *
     * 流程：
     * 1. 校验订单状态和金额
     * 2. 支付方式互斥校验（PRD 4.9.4）
     * 3. 幂等校验
     * 4. 余额支付直接走 BalanceAccountService
     * 5. 微信/支付宝创建支付单并返回支付参数
     *
     * @param int $storeId 门店ID
     * @param int $orderId 订单ID
     * @param int $payChannel 支付渠道：1微信 2支付宝 3余额
     * @param string $payMethod 支付方式（微信/支付宝用）：JSAPI/H5/NATIVE
     * @param array $meta 附加信息：
     *   - idempotent_key: string 幂等键（必填）
     *   - account_id: int 余额账户ID（余额支付时必填）
     * @return array 支付参数或余额支付结果
     * @throws ValidateException
     */
    public function createPayment(int $storeId, int $orderId, int $payChannel, string $payMethod = '', array $meta = []): array
    {
        // 查询订单
        $order = Db::name('order')
            ->where('id', $orderId)
            ->where('transaction_id', $storeId)
            ->whereIn('order_status', [
                OrderStatus::PENDING_PAY->value,
                OrderStatus::NEED_SUPPLEMENT->value, // 补款场景
            ])
            ->find();

        if (!$order) {
            throw new ValidateException('订单不存在或状态不允许支付');
        }

        $payableCent = (int) $order['payable_amount_cent'];
        if ($payableCent <= 0) {
            throw new ValidateException('订单应付金额为0，无需支付');
        }

        // 支付方式互斥校验（PRD 4.9.4）
        $this->validatePaymentMutualExclusion((int) $order['id'], $payChannel);

        // 幂等键
        $idempotentKey = $meta['idempotent_key'] ?? null;
        if (empty($idempotentKey)) {
            $idempotentKey = "pay_{$order['id']}_{$payChannel}_" . time();
        }

        // 幂等校验：相同幂等键返回原结果
        $existingPayment = Db::name('payment')
            ->where('idempotent_key', $idempotentKey)
            ->find();

        if ($existingPayment) {
            return $this->buildPaymentResult($existingPayment, true);
        }

        // 余额支付 → 走 BalanceAccountService
        if ($payChannel === self::CHANNEL_BALANCE) {
            return $this->handleBalancePayment($order, $payableCent, $meta, $idempotentKey);
        }

        // 微信/支付宝支付
        return $this->handleThirdPartyPayment($order, $payableCent, $payChannel, $payMethod, $idempotentKey);
    }

    /**
     * 支付方式互斥校验
     *
     * 一张订单只能选择余额、微信或支付宝其中一种（PRD 4.9.4）。
     * 检查是否已有不同渠道的有效支付记录。
     *
     * @param int $orderId 订单ID
     * @param int $newChannel 新选择的支付渠道
     * @throws ValidateException
     */
    private function validatePaymentMutualExclusion(int $orderId, int $newChannel): void
    {
        $existingPayments = Db::name('payment')
            ->where('order_id', $orderId)
            ->whereIn('pay_status', [0, 1]) // 待支付或已支付
            ->select()
            ->toArray();

        foreach ($existingPayments as $payment) {
            $existChannel = (int) $payment['pay_channel'];
            if ($existChannel !== $newChannel && (int) $payment['pay_status'] === 1) {
                throw new ValidateException(
                    '单张订单只能选择一种支付方式，该订单已使用其他支付方式完成支付',
                    4104
                );
            }
        }
    }

    /**
     * 处理余额支付
     *
     * @param array $order 订单数据
     * @param int $payableCent 应付金额（分）
     * @param array $meta 附加信息
     * @param string $idempotentKey 幂等键
     * @return array 支付结果
     * @throws ValidateException
     */
    private function handleBalancePayment(array $order, int $payableCent, array $meta, string $idempotentKey): array
    {
        $accountId = $meta['account_id'] ?? null;
        if (!$accountId) {
            // 自动获取账户
            $balanceService = app(BalanceAccountService::class);
            $account = $balanceService->getOrCreateAccount('store', (int) $order['transaction_id']);
            $accountId = (int) $account['id'];
        }

        // 支付前重新校验订单金额和状态
        $currentOrder = Db::name('order')
            ->where('id', $order['id'])
            ->lock(true)
            ->find();

        if (!$currentOrder) {
            throw new ValidateException('订单不存在');
        }

        $currentPayable = (int) $currentOrder['payable_amount_cent'];
        if ($currentPayable !== $payableCent) {
            throw new ValidateException('订单金额已变更，请刷新后重试', 4002);
        }

        // 执行余额支付
        $balanceService = app(BalanceAccountService::class);
        $paymentResult = $balanceService->payByBalance(
            $order['order_no'],
            $payableCent,
            (int) $accountId,
            ['idempotent_key' => $idempotentKey]
        );

        // 更新订单状态（通过 OrderStateService）
        $this->updateOrderAfterPayment(
            (int) $order['id'],
            $paymentResult['payment_no'],
            $payableCent,
            self::CHANNEL_BALANCE
        );

        return $paymentResult;
    }

    /**
     * 处理第三方支付（微信/支付宝）
     *
     * @param array $order 订单数据
     * @param int $payableCent 应付金额（分）
     * @param int $payChannel 支付渠道
     * @param string $payMethod 支付方式
     * @param string $idempotentKey 幂等键
     * @return array 支付参数
     * @throws ValidateException
     */
    private function handleThirdPartyPayment(array $order, int $payableCent, int $payChannel, string $payMethod, string $idempotentKey): array
    {
        // 检查是否已有未完成的支付单
        $existPayment = Db::name('payment')
            ->where('order_id', $order['id'])
            ->where('pay_channel', $payChannel)
            ->where('pay_status', 0)
            ->find();

        if ($existPayment) {
            // 刷新支付参数
            return $this->refreshPaymentParams($existPayment, $payChannel, $payMethod);
        }

        // 生成支付单号
        $paymentNo = $this->generatePaymentNo();
        $now = date('Y-m-d H:i:s');

        return $this->transaction(function () use ($order, $payableCent, $payChannel, $payMethod, $paymentNo, $idempotentKey, $now) {
            // 写入支付记录
            $paymentId = Db::name('payment')->insertGetId([
                'payment_no'        => $paymentNo,
                'order_id'          => $order['id'],
                'order_no'          => $order['order_no'],
                'transaction_id'    => $order['transaction_id'],
                'pay_channel'       => $payChannel,
                'pay_method'        => $payMethod,
                'pay_amount_cent'   => $payableCent,
                'paid_amount_cent'  => 0,
                'pay_status'        => 0,
                'idempotent_key'    => $idempotentKey,
                'created_at'        => $now,
            ]);

            // 更新订单状态为支付处理中（通过 OrderStateService）
            $orderModel = Order::find($order['id']);
            if ($orderModel && $orderModel->order_status === OrderStatus::PENDING_PAY->value) {
                $stateService = app(OrderStateService::class);
                try {
                    $stateService->transition($orderModel, OrderStatus::PAYING, 'store', [
                        'reason' => '发起支付',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('订单状态更新为支付处理中失败', [
                        'order_no' => $order['order_no'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 调用第三方支付接口
            $payParams = $this->callPayChannel($paymentNo, $payableCent, $payChannel, $payMethod);

            $result = [
                'payment_no'    => $paymentNo,
                'pay_amount_cent' => $payableCent,
                'pay_channel'   => $payChannel,
                'pay_channel_text' => $payChannel === 1 ? '微信支付' : '支付宝',
                'expire_seconds' => 1800,
            ];

            if ($payChannel === 1) {
                $result['wechat_params'] = $payParams;
            } else {
                $result['alipay_params'] = $payParams;
            }

            return $result;
        });
    }

    /**
     * 支付成功后更新订单
     *
     * @param int $orderId 订单ID
     * @param string $paymentNo 支付单号
     * @param int $paidCent 实付金额（分）
     * @param int $payChannel 支付渠道
     * @return void
     */
    private function updateOrderAfterPayment(int $orderId, string $paymentNo, int $paidCent, int $payChannel): void
    {
        $order = Order::find($orderId);
        if (!$order) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // 更新订单金额
        $order->save([
            'paid_amount_cent' => $paidCent,
            'paid_at'          => $now,
            'payment_channel'  => $payChannel,
            'price_locked_at'  => $now,
            'price_locked_until' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        // 通过 OrderStateService 变更状态
        $stateService = app(OrderStateService::class);
        try {
            $stateService->transition($order, OrderStatus::PAID_PENDING, 'system', [
                'reason' => '支付成功（' . ($payChannel === 3 ? '余额' : '第三方支付') . '）',
                'payment_no' => $paymentNo,
            ]);
        } catch (\Throwable $e) {
            Log::warning('订单状态更新为已支付待审核失败', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 微信支付回调处理
     *
     * 幂等处理：重复回调返回原结果，不重复处理。
     *
     * @param array $notifyData 微信回调数据
     * @return bool
     */
    public function handleWechatNotify(array $notifyData): bool
    {
        // TODO: 验签（微信 V3 API）
        $paymentNo     = $notifyData['out_trade_no'] ?? '';
        $transactionId = $notifyData['transaction_id'] ?? '';

        if (empty($paymentNo) || empty($transactionId)) {
            Log::error('微信回调参数缺失', $notifyData);
            return false;
        }

        // 幂等：检查是否已处理
        $payment = Db::name('payment')
            ->where('payment_no', $paymentNo)
            ->where('pay_status', 1)
            ->find();

        if ($payment) {
            // 已处理，直接返回成功
            Log::info('微信回调幂等命中，已处理', ['payment_no' => $paymentNo]);
            return true;
        }

        return $this->processPaymentCallback($paymentNo, $transactionId, $notifyData, 'wechat');
    }

    /**
     * 支付宝回调处理
     *
     * @param array $notifyData 支付宝回调数据
     * @return bool
     */
    public function handleAlipayNotify(array $notifyData): bool
    {
        // TODO: 验签（支付宝 SDK）
        $paymentNo     = $notifyData['out_trade_no'] ?? '';
        $transactionId = $notifyData['trade_no'] ?? '';

        if (empty($paymentNo) || empty($transactionId)) {
            Log::error('支付宝回调参数缺失', $notifyData);
            return false;
        }

        // 幂等
        $payment = Db::name('payment')
            ->where('payment_no', $paymentNo)
            ->where('pay_status', 1)
            ->find();

        if ($payment) {
            return true;
        }

        return $this->processPaymentCallback($paymentNo, $transactionId, $notifyData, 'alipay');
    }

    /**
     * 统一支付回调处理
     *
     * @param string $paymentNo 支付单号
     * @param string $transactionId 第三方交易号
     * @param array $notifyData 回调原始数据
     * @param string $channel 渠道标识
     * @return bool
     */
    private function processPaymentCallback(string $paymentNo, string $transactionId, array $notifyData, string $channel): bool
    {
        return $this->transaction(function () use ($paymentNo, $transactionId, $notifyData, $channel) {
            // 行锁
            $payment = Db::name('payment')
                ->where('payment_no', $paymentNo)
                ->lock(true)
                ->find();

            if (!$payment) {
                Log::error('支付回调：支付单不存在', ['payment_no' => $paymentNo]);
                return false;
            }

            // 再次幂等校验
            if ((int) $payment['pay_status'] === 1) {
                return true;
            }

            $paidAt = date('Y-m-d H:i:s');
            $paidCent = (int) $payment['pay_amount_cent'];

            // 校验金额
            $order = Db::name('order')->where('id', $payment['order_id'])->find();
            if (!$order) {
                Log::error('支付回调：订单不存在', ['payment_no' => $paymentNo]);
                return false;
            }

            $orderPayableCent = (int) $order['payable_amount_cent'];
            if ($paidCent !== $orderPayableCent) {
                Log::error('支付回调：金额不一致', [
                    'payment_no' => $paymentNo,
                    'paid_cent' => $paidCent,
                    'payable_cent' => $orderPayableCent,
                ]);
                // 记录异常但不阻断
            }

            // 更新支付记录
            Db::name('payment')
                ->where('id', $payment['id'])
                ->update([
                    'pay_status'        => 1,
                    'transaction_id_ext' => $transactionId,
                    'paid_amount_cent'  => $paidCent,
                    'paid_at'           => $paidAt,
                    'raw_notify'        => json_encode($notifyData, JSON_UNESCAPED_UNICODE),
                    'updated_at'        => $paidAt,
                ]);

            // 更新订单
            Db::name('order')
                ->where('id', $payment['order_id'])
                ->update([
                    'order_status'       => OrderStatus::PAID_PENDING->value,
                    'paid_amount_cent'   => $paidCent,
                    'paid_at'            => $paidAt,
                    'payment_channel'    => $channel === 'wechat' ? 1 : 2,
                    'price_locked_at'    => $paidAt,
                    'price_locked_until' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'updated_at'         => $paidAt,
                ]);

            // 核销库存（锁定 → 已消耗）
            $this->consumeInventoryOnPaid((int) $payment['order_id']);

            // 记录操作日志
            $this->logOperation(
                module: 'payment',
                action: "{$channel}_notify",
                targetType: 'payment',
                targetId: (int) $payment['id'],
                targetNo: $paymentNo,
                afterData: ['pay_status' => 1, 'transaction_id' => $transactionId, 'channel' => $channel],
                remark: "{$channel}支付回调成功",
            );

            Log::info('支付回调处理成功', [
                'payment_no' => $paymentNo,
                'channel' => $channel,
                'paid_cent' => $paidCent,
            ]);

            return true;
        });
    }

    /**
     * 查询支付状态
     *
     * @param int $storeId 门店ID
     * @param int $orderId 订单ID
     * @return array
     */
    public function queryPaymentStatus(int $storeId, int $orderId): array
    {
        $order = Db::name('order')
            ->where('id', $orderId)
            ->where('transaction_id', $storeId)
            ->find();

        if (!$order) {
            throw new ValidateException('订单不存在');
        }

        // 查询最新支付记录
        $payment = Db::name('payment')
            ->where('order_id', $orderId)
            ->order('id', 'desc')
            ->find();

        // 若支付状态为待支付且存在支付单号，主动向第三方查单
        if ($payment && (int) $payment['pay_status'] === 0 && !empty($payment['payment_no'])) {
            $this->queryThirdPartyPayment($payment);
            $payment = Db::name('payment')->where('id', $payment['id'])->find();
        }

        $payStatusMap = [
            0 => '未支付',
            1 => '已支付',
            2 => '支付失败',
            3 => '已退款',
        ];

        $channelMap = [
            1 => '微信支付',
            2 => '支付宝',
            3 => '余额支付',
        ];

        return [
            'order_id'            => $orderId,
            'order_no'            => $order['order_no'],
            'order_status'        => (int) $order['order_status'],
            'payable_amount_cent' => (int) $order['payable_amount_cent'],
            'paid_amount_cent'    => (int) $order['paid_amount_cent'],
            'paid_at'             => $order['paid_at'],
            'payment_no'          => $payment['payment_no'] ?? null,
            'pay_channel'         => $payment['pay_channel'] ?? null,
            'pay_channel_text'    => $channelMap[$payment['pay_channel'] ?? 0] ?? '未知',
            'pay_status'          => (int) ($payment['pay_status'] ?? 0),
            'pay_status_text'     => $payStatusMap[$payment['pay_status'] ?? 0] ?? '未知',
        ];
    }

    /**
     * 刷新已有支付单的支付参数
     *
     * @param array $payment 支付记录
     * @param int $payChannel 渠道
     * @param string $payMethod 支付方式
     * @return array
     */
    private function refreshPaymentParams(array $payment, int $payChannel, string $payMethod): array
    {
        $payParams = $this->callPayChannel(
            $payment['payment_no'],
            (int) $payment['pay_amount_cent'],
            $payChannel,
            $payMethod
        );

        $channelText = $payChannel === 1 ? '微信支付' : '支付宝';

        $result = [
            'payment_no'      => $payment['payment_no'],
            'pay_amount_cent' => (int) $payment['pay_amount_cent'],
            'pay_channel'     => $payChannel,
            'pay_channel_text' => $channelText,
            'expire_seconds'  => 1800,
        ];

        if ($payChannel === 1) {
            $result['wechat_params'] = $payParams;
        } else {
            $result['alipay_params'] = $payParams;
        }

        return $result;
    }

    /**
     * 调用第三方支付接口（骨架）
     *
     * @param string $paymentNo 支付单号
     * @param int $amountCent 金额（分）
     * @param int $channel 渠道
     * @param string $method 支付方式
     * @return array 支付参数
     */
    private function callPayChannel(string $paymentNo, int $amountCent, int $channel, string $method): array
    {
        if ($channel === 1) {
            // TODO: 对接微信支付 V3 API
            return [
                'timeStamp' => (string) time(),
                'nonceStr'  => bin2hex(random_bytes(16)),
                'package'   => 'prepay_id=mock_' . $paymentNo,
                'signType'  => 'RSA',
                'paySign'   => 'mock_sign',
            ];
        }

        if ($channel === 2) {
            // TODO: 对接支付宝 SDK
            return [
                'order_string' => 'alipay_sdk=mock&out_trade_no=' . $paymentNo,
            ];
        }

        throw new ValidateException('不支持的支付渠道');
    }

    /**
     * 主动向第三方支付查单
     *
     * @param array $payment 支付记录
     * @return void
     */
    private function queryThirdPartyPayment(array $payment): void
    {
        // TODO: 微信：调用 wxpay v3 查单接口
        // TODO: 支付宝：调用 alipay.trade.query
        Log::info('主动查单', ['payment_no' => $payment['payment_no']]);
    }

    /**
     * 支付成功后核销库存（锁定 → 已消耗）
     *
     * @param int $orderId 订单ID
     * @return void
     */
    private function consumeInventoryOnPaid(int $orderId): void
    {
        $items = Db::name('order_item')
            ->where('order_id', $orderId)
            ->where('use_inventory', 1)
            ->select();

        if ($items->isEmpty()) {
            return;
        }

        $order = Db::name('order')->where('id', $orderId)->find();
        $storeId = (int) $order['transaction_id'];

        foreach ($items as $item) {
            // TODO: 动态获取 kit_sku（从商品配置）
            $kitSku = 'KIT-STD-V1';

            $affected = Db::name('store_inventory')
                ->where('store_id', $storeId)
                ->where('kit_sku', $kitSku)
                ->where('locked', '>', 0)
                ->update([
                    'locked'   => Db::raw('locked - 1'),
                    'consumed' => Db::raw('consumed + 1'),
                ]);

            if ($affected) {
                Db::name('inventory_log')->insert([
                    'store_id'        => $storeId,
                    'kit_sku'         => $kitSku,
                    'log_type'        => 3, // 支付核销
                    'quantity'        => -1,
                    'before_quantity' => 0, // TODO: 记录真实前后数量
                    'after_quantity'  => 0,
                    'order_id'        => $orderId,
                    'order_no'        => $order['order_no'],
                    'operator_name'   => '系统',
                    'reason'          => '支付成功核销库存',
                    'idempotent_key'  => "consume_{$order['order_no']}_{$item['id']}",
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * 构建支付结果返回
     *
     * @param array $payment 支付记录
     * @param bool $isIdempotent 是否幂等命中
     * @return array
     */
    private function buildPaymentResult(array $payment, bool $isIdempotent = false): array
    {
        $channelText = match ((int) $payment['pay_channel']) {
            1 => '微信支付',
            2 => '支付宝',
            3 => '余额支付',
            default => '未知',
        };

        return [
            'payment_no'      => $payment['payment_no'],
            'pay_amount_cent' => (int) $payment['pay_amount_cent'],
            'pay_channel'     => (int) $payment['pay_channel'],
            'pay_channel_text' => $channelText,
            'pay_status'      => (int) $payment['pay_status'],
            'idempotent'      => $isIdempotent,
        ];
    }

    /**
     * 生成支付单号
     *
     * @return string
     */
    private function generatePaymentNo(): string
    {
        $date = date('Ymd');
        $prefix = 'PAY' . $date;

        $last = Db::name('payment')
            ->where('payment_no', 'like', $prefix . '%')
            ->order('id', 'desc')
            ->value('payment_no');

        $seq = $last ? (int) substr($last, -6) + 1 : 1;

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
