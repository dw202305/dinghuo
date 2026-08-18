<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\CustomerType;
use app\common\enum\OrderStatus;
use app\common\enum\PayStatus;
use app\common\enum\PaymentChannel;
use app\common\enum\PaymentStatus;
use app\common\exception\CodedValidateException;
use app\common\model\Order;
use app\common\support\Idempotency;
use app\common\support\RedisLock;
use app\common\support\SequenceNo;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use Throwable;

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
     * API 入参渠道编码（对外兼容层，仅 createPayment 边界使用）
     * 批次2c：内部一律使用 PaymentChannel 枚举（deploy lj_payment.payment_channel VARCHAR）
     */
    private const API_CODE_WECHAT  = 1;
    private const API_CODE_ALIPAY  = 2;
    private const API_CODE_BALANCE = 3;

    /**
     * API 渠道编码 → PaymentChannel 枚举
     *
     * @param int $apiCode 1微信 2支付宝 3余额
     * @throws ValidateException
     */
    private function channelFromApiCode(int $apiCode): PaymentChannel
    {
        return match ($apiCode) {
            self::API_CODE_WECHAT  => PaymentChannel::WECHAT,
            self::API_CODE_ALIPAY  => PaymentChannel::ALIPAY,
            self::API_CODE_BALANCE => PaymentChannel::BALANCE,
            default => throw new ValidateException('不支持的支付渠道'),
        };
    }

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
     * @param int $payChannel API渠道编码：1微信 2支付宝 3余额（边界层转换为 PaymentChannel）
     * @param string $payMethod 支付方式（微信/支付宝用）：JSAPI/H5/NATIVE
     * @param array $meta 附加信息：
     *   - idempotent_key: string 幂等键（必填）
     *   - account_id: int 余额账户ID（余额支付时必填）
     * @return array 支付参数或余额支付结果
     * @throws ValidateException
     */
    public function createPayment(int $storeId, int $orderId, int $payChannel, string $payMethod = '', array $meta = []): array
    {
        $channel = $this->channelFromApiCode($payChannel);

        // 查询订单（越权防护：限门店主体 transaction_type=1，评审 Warning 7）
        $order = Db::name('order')
            ->where('id', $orderId)
            ->where('transaction_id', $storeId)
            ->where('transaction_type', CustomerType::STORE->value)
            ->whereIn('order_status', [
                OrderStatus::PENDING_PAY->value,
                OrderStatus::NEED_SUPPLEMENT->value, // 补款场景
            ])
            ->find();

        if (!$order) {
            throw new ValidateException('订单不存在或状态不允许支付');
        }

        // deploy lj_order：应付总额为 total_amount_cent，实付为 paid_amount_cent
        $payableCent = (int) $order['total_amount_cent'] - (int) $order['paid_amount_cent'];
        if ($payableCent <= 0) {
            throw new ValidateException('订单应付金额为0，无需支付');
        }

        // 支付方式互斥校验（PRD 4.9.4）
        $this->validatePaymentMutualExclusion((int) $order['id'], $channel);

        // 幂等键（批次4：业务确定化）——优先请求 Idempotent-Key 透传，
        // 缺省 order_pay:{order_no}:{channel}，禁用 time() 拼接
        $idempotentKey = trim((string) ($meta['idempotent_key'] ?? ''));
        if ($idempotentKey === '') {
            $idempotentKey = "order_pay:{$order['order_no']}:{$channel->value}";
        }

        // 重复请求的幂等回查下沉到落库环节（先插、捕 1062 再回查，
        // uk_payment_idempotent 为裁决者），不再先查后插

        // 余额支付 → 走 BalanceAccountService
        if ($channel === PaymentChannel::BALANCE) {
            return $this->handleBalancePayment($order, $payableCent, $meta, $idempotentKey);
        }

        // 微信/支付宝支付
        return $this->handleThirdPartyPayment($order, $payableCent, $channel, $payMethod, $idempotentKey);
    }

    /**
     * 支付方式互斥校验
     *
     * 一张订单只能选择余额、微信或支付宝其中一种（PRD 4.9.4）。
     * 同单已有其他渠道的成功或进行中支付单时，拒绝新渠道。
     * public：供余额支付入口（OrderController::payBalance /
     * OrderService::payOrderByBalance）在进事务前复用（评审 Warning 3）。
     *
     * @param int $orderId 订单ID
     * @param PaymentChannel $newChannel 新选择的支付渠道
     * @throws ValidateException
     */
    public function validatePaymentMutualExclusion(int $orderId, PaymentChannel $newChannel): void
    {
        $existingPayments = Db::name('payment')
            ->where('order_id', $orderId)
            ->whereIn('pay_status', [PayStatus::PENDING->value, PayStatus::SUCCESS->value])
            ->select()
            ->toArray();

        $this->assertPayChannelExclusive($existingPayments, $newChannel);
    }

    /**
     * 支付渠道互斥决策（纯逻辑，可独立测试）
     *
     * 规则（PRD 4.9.4）：
     * - 其他渠道已有成功支付单（pay_status=1）→ 拒绝；
     * - 其他渠道已有进行中支付单（pay_status=0）→ 拒绝；
     * - 失败（2）/已退款（3）支付单不构成阻断；
     * - 同渠道进行中支付单允许（走刷新支付参数）。
     *
     * @param array $existingPayments 该订单已有支付单列表（含 payment_channel/pay_status）
     * @param PaymentChannel|string $newChannel 新选择的支付渠道（枚举或枚举值字符串）
     * @throws ValidateException
     */
    public function assertPayChannelExclusive(array $existingPayments, PaymentChannel|string $newChannel): void
    {
        $newChannelValue = $newChannel instanceof PaymentChannel ? $newChannel->value : $newChannel;

        foreach ($existingPayments as $payment) {
            $existChannel = (string) ($payment['payment_channel'] ?? '');
            if ($existChannel === $newChannelValue) {
                continue;
            }

            $payStatus = (int) ($payment['pay_status'] ?? 0);
            if ($payStatus === PayStatus::SUCCESS->value) {
                throw new CodedValidateException(
                    '单张订单只能选择一种支付方式，该订单已使用其他支付方式完成支付',
                    4104
                );
            }
            if ($payStatus === PayStatus::PENDING->value) {
                throw new CodedValidateException(
                    '单张订单只能选择一种支付方式，该订单已有其他渠道进行中的支付单',
                    4104
                );
            }
        }
    }

    /**
     * 回调幂等决策（纯逻辑，可独立测试）
     *
     * - 待支付（0）→ 'process' 正常处理；
     * - 已支付（1）→ 'idempotent_success' 重复回调直接返回成功，绝不重复入账；
     * - 失败（2）/已退款（3）→ 'reject' 终态支付单不再入账。
     *
     * @param array $payment 支付单记录
     * @return string process|idempotent_success|reject
     */
    public function notifyDecision(array $payment): string
    {
        $payStatus = (int) ($payment['pay_status'] ?? 0);

        return match ($payStatus) {
            1 => 'idempotent_success',
            2, 3 => 'reject',
            default => 'process',
        };
    }

    /**
     * 从回调数据提取实付金额（整数分，禁用 float）
     *
     * - 微信 V3：amount.payer_total（分），兼容 amount.total / resource.ciphertext.amount.payer_total；
     * - 支付宝：total_amount（元，字符串按整数分解析，不走 float）。
     *
     * @param array $notifyData 回调数据
     * @param string $channel 渠道标识 wechat|alipay
     * @return int|null 金额（分），无法提取时返回 null
     */
    public function extractNotifyAmountCent(array $notifyData, string $channel): ?int
    {
        if ($channel === PaymentChannel::WECHAT->value) {
            $candidates = [
                $notifyData['amount']['payer_total'] ?? null,
                $notifyData['amount']['total'] ?? null,
                $notifyData['resource']['ciphertext']['amount']['payer_total'] ?? null,
            ];
            foreach ($candidates as $value) {
                if ($value !== null && is_numeric($value)) {
                    return (int) $value;
                }
            }
            return null;
        }

        if ($channel === PaymentChannel::ALIPAY->value) {
            $yuan = $notifyData['total_amount'] ?? null;
            if ($yuan === null || !is_numeric($yuan)) {
                return null;
            }
            // 元 → 分：先判符号再拆解绝对值，修复 "-0.50" 丢符号问题
            // （评审 Warning 11）；格式非法返回 null
            $yuanStr = trim((string) $yuan);
            if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $yuanStr)) {
                return null;
            }
            $negative = str_starts_with($yuanStr, '-');
            $parts = explode('.', ltrim($yuanStr, '-'));
            $intPart = (int) $parts[0];
            $decPart = (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0', STR_PAD_RIGHT);
            $cent = $intPart * 100 + $decPart;

            return $negative ? -$cent : $cent;
        }

        return null;
    }

    /**
     * 回调金额校验（纯逻辑，可独立测试）
     *
     * 回调金额缺失或与支付单金额不一致均视为非法，拒绝入账。
     *
     * @param int|null $notifyCent 回调实付金额（分）
     * @param int $paymentCent 支付单金额（分）
     * @return bool
     */
    public function isNotifyAmountValid(?int $notifyCent, int $paymentCent): bool
    {
        return $notifyCent !== null && $paymentCent > 0 && $notifyCent === $paymentCent;
    }

    /**
     * 处理余额支付
     *
     * 批次2c：两段式事务合并为单事务（与 OrderController@payBalance 的
     * payByBalanceWithinTransaction 模式一致）：余额扣减、写流水、写支付记录、
     * 订单字段与状态更新全部放入同一个 Db::transaction。
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
        $balanceService = app(BalanceAccountService::class);
        if (!$accountId) {
            // 自动获取账户
            $account = $balanceService->getOrCreateAccount('store', (int) $order['transaction_id']);
            $accountId = (int) $account['id'];
        }

        return $this->transaction(function () use ($balanceService, $order, $payableCent, $accountId, $meta, $idempotentKey) {
            // 支付前重新校验订单金额和状态（行锁内）
            $currentOrder = Db::name('order')
                ->where('id', $order['id'])
                ->lock(true)
                ->find();

            if (!$currentOrder) {
                throw new ValidateException('订单不存在');
            }

            // 订单行锁内复检支付渠道互斥，防事务外校验被并发绕过（评审 Warning 3）
            $this->validatePaymentMutualExclusion((int) $order['id'], PaymentChannel::BALANCE);

            $currentPayable = (int) $currentOrder['total_amount_cent'] - (int) $currentOrder['paid_amount_cent'];
            if ($currentPayable !== $payableCent) {
                throw new CodedValidateException('订单金额已变更，请刷新后重试', 4002);
            }

            // 事务内余额支付（扣余额 + 写流水 + 写支付记录）
            $paymentResult = $balanceService->payByBalanceWithinTransaction(
                (string) $order['order_no'],
                $payableCent,
                (int) $accountId,
                $idempotentKey,
                [
                    'order_id'    => (int) $order['id'],
                    'operator_id' => (int) ($meta['operator_id'] ?? 0),
                ]
            );

            // 幂等命中：不重复更新订单
            if (!empty($paymentResult['idempotent'])) {
                return $paymentResult;
            }

            // 同一事务内更新订单非状态支付字段
            Db::name('order')
                ->where('id', $order['id'])
                ->update([
                    'paid_amount_cent' => (int) $currentOrder['paid_amount_cent'] + $payableCent,
                    'paid_at'          => date('Y-m-d H:i:s'),
                    'payment_status'   => PaymentStatus::PAID->value,
                ]);

            // 状态变更走状态机：PENDING_PAY → PAYING（store）→ PAID_PENDING（system）
            $orderModel = Order::find((int) $order['id']);
            if ($orderModel) {
                $stateService = app(OrderStateService::class);
                if ($orderModel->order_status === OrderStatus::PENDING_PAY->value) {
                    $stateService->transition($orderModel, OrderStatus::PAYING, 'store', [
                        'reason' => '发起支付（余额支付）',
                    ]);
                }
                $stateService->transition($orderModel, OrderStatus::PAID_PENDING, 'system', [
                    'reason'     => '余额支付成功',
                    'payment_no' => $paymentResult['payment_no'],
                ]);
            }

            return $paymentResult;
        });
    }

    /**
     * 处理第三方支付（微信/支付宝）
     *
     * 批次4（网络调用移出事务）：
     * 事务内只做"落支付单 + 订单状态置支付中"→提交；
     * 事务外调渠道适配器；失败时补偿更新支付单为失败态，
     * 并以 system 角色回退订单 PAYING → PENDING_PAY（评审 Critical 2）。
     * 幂等：支付单插入捕 uk_payment_idempotent 冲突后回查原单返回。
     * 取号+落库包进 SequenceNo::withRetry：payment_no 撞号（按幂等键回查
     * 为 null）时重新取号重试，不携带不存在的 payment_no 调渠道
     * （评审 Warning 12 / 补漏 14）。
     *
     * @param array $order 订单数据
     * @param int $payableCent 应付金额（分）
     * @param PaymentChannel $channel 支付渠道
     * @param string $payMethod 支付方式
     * @param string $idempotentKey 幂等键
     * @return array 支付参数
     * @throws ValidateException
     */
    private function handleThirdPartyPayment(array $order, int $payableCent, PaymentChannel $channel, string $payMethod, string $idempotentKey): array
    {
        // 同渠道已有进行中支付单 → 刷新支付参数（事务外，无网络调用）
        $existPayment = Db::name('payment')
            ->where('order_id', $order['id'])
            ->where('payment_channel', $channel->value)
            ->where('pay_status', PayStatus::PENDING->value)
            ->find();

        if ($existPayment) {
            return $this->refreshPaymentParams($existPayment, $channel, $payMethod);
        }

        $now = date('Y-m-d H:i:s');

        // 事务内：取号+落支付单（withRetry 包住，撞号重取）+ 推进订单状态为支付中（严禁网络调用）
        $txResult = $this->transaction(function () use ($order, $payableCent, $channel, $payMethod, $idempotentKey, $now) {
            // 写入支付记录（deploy lj_payment 结构：payment_channel VARCHAR，
            // 无 paid_amount_cent 列；transaction_id 仅回调时写入第三方流水号）。
            // withRetry：payment_no 撞 1062 且按幂等键回查为 null 时重新取号重试
            return SequenceNo::withRetry(function () use ($order, $payableCent, $channel, $payMethod, $idempotentKey, $now) {
                $paymentNo = $this->generatePaymentNo();

                try {
                    Db::name('payment')->insert([
                        'payment_no'        => $paymentNo,
                        'order_id'          => $order['id'],
                        'order_no'          => $order['order_no'],
                        'payment_channel'   => $channel->value,
                        'pay_method'        => $payMethod !== '' ? $payMethod : 'JSAPI',
                        'pay_amount_cent'   => $payableCent,
                        'pay_status'        => PayStatus::PENDING->value,
                        'idempotent_key'    => $idempotentKey,
                        'transaction_subject_type' => (int) ($order['transaction_type'] ?? CustomerType::STORE->value),
                        'transaction_subject_id'   => (int) $order['transaction_id'],
                        'created_at'        => $now,
                    ]);
                } catch (Throwable $e) {
                    if (!Idempotency::isDuplicateKey($e)) {
                        throw $e;
                    }

                    // 幂等命中：并发下同一幂等键已有支付单，回查原单返回
                    $existing = Db::name('payment')->where('idempotent_key', $idempotentKey)->find();
                    if ($existing) {
                        return ['payment_no' => (string) $existing['payment_no'], 'existing' => $existing];
                    }

                    // 回查为 null → payment_no 撞号，上抛由 withRetry 重新取号重试
                    throw $e;
                }

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

                return ['payment_no' => $paymentNo, 'existing' => null];
            });
        });

        // 幂等命中：直接返回原支付单结果
        if (is_array($txResult['existing'] ?? null)) {
            return $this->buildPaymentResult($txResult['existing'], true);
        }

        $paymentNo = (string) $txResult['payment_no'];

        // 事务外：调用第三方支付接口；失败时补偿更新支付单为失败态，
        // 并回退订单 PAYING → PENDING_PAY，避免订单永久卡死在支付中（评审 Critical 2）
        try {
            $payParams = $this->callPayChannel($paymentNo, $payableCent, $channel, $payMethod);
        } catch (Throwable $e) {
            Db::name('payment')
                ->where('payment_no', $paymentNo)
                ->update([
                    'pay_status' => PayStatus::FAILED->value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->rollbackPayingToPending((int) $order['id'], (string) $order['order_no'], '支付渠道调用失败');

            Log::error('支付渠道调用失败，支付单已置失败态', [
                'payment_no' => $paymentNo,
                'order_no'   => $order['order_no'],
                'channel'    => $channel->value,
                'error'      => $e->getMessage(),
            ]);

            throw new ValidateException('支付渠道调用失败，请重试');
        }

        $result = [
            'payment_no'    => $paymentNo,
            'pay_amount_cent' => $payableCent,
            'pay_channel'   => $channel->value,
            'pay_channel_text' => $channel->label(),
            'expire_seconds' => 1800,
        ];

        if ($channel === PaymentChannel::WECHAT) {
            $result['wechat_params'] = $payParams;
        } else {
            $result['alipay_params'] = $payParams;
        }

        return $result;
    }

    /**
     * 补偿回退：订单 PAYING → PENDING_PAY（system 角色）
     *
     * 渠道调用失败/支付单过期时执行，防止订单永久卡死在支付中
     * （评审 Critical 2；对应矩阵 payment_processing:pending_payment）。
     * 补偿失败仅告警不上抛（主流程已在失败路径）。
     *
     * @param int $orderId 订单ID
     * @param string $orderNo 订单号（日志用）
     * @param string $reason 回退原因
     * @return void
     */
    private function rollbackPayingToPending(int $orderId, string $orderNo, string $reason): void
    {
        try {
            $orderModel = Order::find($orderId);
            if (!$orderModel || (int) $orderModel->order_status !== OrderStatus::PAYING->value) {
                return;
            }

            app(OrderStateService::class)->transition($orderModel, OrderStatus::PENDING_PAY, 'system', [
                'reason' => "{$reason}，回退待支付",
            ]);
        } catch (Throwable $e) {
            Log::error('PAYING→PENDING_PAY 补偿回退失败', [
                'order_no' => $orderNo,
                'reason'   => $reason,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * 微信支付回调处理
     *
     * 验签已在 PaymentController 层经 WechatPayVerifier 完成（规范 12.2）。
     * 幂等处理：重复回调返回原结果，不重复入账。
     *
     * @param array $notifyData 微信回调数据
     * @return bool
     */
    public function handleWechatNotify(array $notifyData): bool
    {
        $paymentNo     = $notifyData['out_trade_no'] ?? '';
        $transactionId = $notifyData['transaction_id'] ?? '';

        if (empty($paymentNo) || empty($transactionId)) {
            Log::error('微信回调参数缺失', $notifyData);
            return false;
        }

        // 幂等/终态决策：已支付直接返回成功，失败/已退款单拒绝
        $payment = Db::name('payment')
            ->where('payment_no', $paymentNo)
            ->find();

        if ($payment) {
            $decision = $this->notifyDecision($payment);
            if ($decision === 'idempotent_success') {
                Log::info('微信回调幂等命中，已处理', ['payment_no' => $paymentNo]);
                return true;
            }
            if ($decision === 'reject') {
                Log::error('微信回调：支付单已处于终态，拒绝入账', [
                    'payment_no' => $paymentNo,
                    'pay_status' => $payment['pay_status'],
                ]);
                return false;
            }
        }

        return $this->processPaymentCallback($paymentNo, $transactionId, $notifyData, PaymentChannel::WECHAT->value);
    }

    /**
     * 支付宝回调处理
     *
     * 验签已在 PaymentController 层经 AlipayPayVerifier 完成（规范 12.2）。
     *
     * @param array $notifyData 支付宝回调数据
     * @return bool
     */
    public function handleAlipayNotify(array $notifyData): bool
    {
        $paymentNo     = $notifyData['out_trade_no'] ?? '';
        $transactionId = $notifyData['trade_no'] ?? '';

        if (empty($paymentNo) || empty($transactionId)) {
            Log::error('支付宝回调参数缺失', $notifyData);
            return false;
        }

        // 幂等/终态决策
        $payment = Db::name('payment')
            ->where('payment_no', $paymentNo)
            ->find();

        if ($payment) {
            $decision = $this->notifyDecision($payment);
            if ($decision === 'idempotent_success') {
                return true;
            }
            if ($decision === 'reject') {
                Log::error('支付宝回调：支付单已处于终态，拒绝入账', [
                    'payment_no' => $paymentNo,
                    'pay_status' => $payment['pay_status'],
                ]);
                return false;
            }
        }

        return $this->processPaymentCallback($paymentNo, $transactionId, $notifyData, PaymentChannel::ALIPAY->value);
    }

    /**
     * 统一支付回调处理
     *
     * 资金安全规则：
     * - 回调金额缺失或与支付单金额不一致 → 支付单置失败态 + Log::error 告警 + 拒绝入账，不更新订单；
     * - 支付单金额与订单应付金额不一致 → 同样阻断；
     * - transaction_id 仅在收到第三方回调时写入真实流水号（唯一索引）；
     * - 订单状态变更一律走 OrderStateService（规范 10.1，角色 system）。
     *
     * 批次4：Redis 短锁 notify:{channel}:{payment_no}（SET NX EX + token）
     * 拦截并发重复回调；最终幂等仍由支付单行锁 + 终态决策裁决。
     * 库存核销不再在此直调，由状态机"支付成功"副作用统一触发。
     *
     * @param string $paymentNo 支付单号
     * @param string $transactionId 第三方交易号
     * @param array $notifyData 回调原始数据
     * @param string $channel 渠道标识
     * @return bool
     */
    private function processPaymentCallback(string $paymentNo, string $transactionId, array $notifyData, string $channel): bool
    {
        // 并发重复回调护栏：同一支付单同时只处理一个回调
        $notifyLockKey = "notify:{$channel}:{$paymentNo}";
        $notifyToken = RedisLock::token();

        if (!RedisLock::acquire($notifyLockKey, 30, $notifyToken)) {
            Log::warning('支付回调并发重复，交由第三方重试', [
                'payment_no' => $paymentNo,
                'channel'    => $channel,
            ]);

            return false;
        }

        try {
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

            // 行锁内再次幂等/终态决策
            $decision = $this->notifyDecision($payment);
            if ($decision === 'idempotent_success') {
                return true;
            }
            if ($decision === 'reject') {
                Log::error('支付回调：支付单已处于终态，拒绝入账', [
                    'payment_no' => $paymentNo,
                    'pay_status' => $payment['pay_status'],
                ]);
                return false;
            }

            $paidAt = date('Y-m-d H:i:s');
            $paymentCent = (int) $payment['pay_amount_cent'];

            // 校验回调实付金额：缺失或不一致 → 阻断，支付单置失败态，拒绝入账
            $notifyCent = $this->extractNotifyAmountCent($notifyData, $channel);
            if (!$this->isNotifyAmountValid($notifyCent, $paymentCent)) {
                $this->rejectCallbackPayment($payment, $notifyData, '回调金额缺失或与支付单金额不一致', [
                    'notify_cent'   => $notifyCent,
                    'payment_cent'  => $paymentCent,
                ]);
                return false;
            }

            // 校验支付单金额与订单应付金额（deploy：total_amount_cent 应付总额）；
            // 订单读取加行锁，paid_amount_cent 累加基于锁内最新值（评审 Warning 4）
            $order = Db::name('order')->where('id', $payment['order_id'])->lock(true)->find();
            if (!$order) {
                Log::error('支付回调：订单不存在', ['payment_no' => $paymentNo]);
                return false;
            }

            $orderPayableCent = (int) $order['total_amount_cent'] - (int) $order['paid_amount_cent'];

            // 订单已处已付态（PAID_PENDING 及之后）且金额不匹配：按幂等成功/冲突告警处理，
            // 返回成功终止第三方重试，留待人工对账；勿直接拒绝/回滚导致第三方死循环重试
            $lockedOrderStatus = OrderStatus::from((int) $order['order_status']);
            if ($paymentCent !== $orderPayableCent) {
                if ($lockedOrderStatus->isPaid()) {
                    Log::error('支付回调冲突告警：订单已处已付态且回调金额与剩余应付不一致，按幂等成功返回', [
                        'payment_no'   => $paymentNo,
                        'order_id'     => $payment['order_id'],
                        'order_status' => (int) $order['order_status'],
                        'payment_cent' => $paymentCent,
                        'payable_cent' => $orderPayableCent,
                    ]);
                    return true;
                }

                $this->rejectCallbackPayment($payment, $notifyData, '支付单金额与订单应付金额不一致', [
                    'payment_cent' => $paymentCent,
                    'payable_cent' => $orderPayableCent,
                ]);
                return false;
            }

            // 更新支付记录：transaction_id 写入真实第三方流水号（唯一索引）
            Db::name('payment')
                ->where('id', $payment['id'])
                ->update([
                    'pay_status'     => PayStatus::SUCCESS->value,
                    'transaction_id' => $transactionId,
                    'paid_at'        => $paidAt,
                    'notify_content' => json_encode($notifyData, JSON_UNESCAPED_UNICODE),
                    'updated_at'     => $paidAt,
                ]);

            // 更新订单非状态支付字段（状态变更一律走状态机，规范 10.1；
            // deploy lj_order 无 payment_channel 列，渠道只存 lj_payment）
            Db::name('order')
                ->where('id', $payment['order_id'])
                ->update([
                    'paid_amount_cent' => (int) $order['paid_amount_cent'] + $paymentCent,
                    'paid_at'          => $paidAt,
                    'payment_status'   => PaymentStatus::PAID->value,
                    'updated_at'       => $paidAt,
                ]);

            // 订单状态走状态机（payment_processing → paid_pending_review，角色 system）；
            // 转换失败时异常上抛回滚整个事务，回调返回失败由第三方重试
            $orderModel = Order::find((int) $payment['order_id']);
            if ($orderModel) {
                $stateService = app(OrderStateService::class);

                // 防御兼容：订单仍停留在待支付（发起支付转换未成功）时，
                // 先补全 PENDING_PAY → PAYING，再推进支付成功转换
                if ((int) $orderModel->order_status === OrderStatus::PENDING_PAY->value) {
                    $stateService->transition($orderModel, OrderStatus::PAYING, 'store', [
                        'reason' => "发起支付（{$channel}回调补全）",
                    ]);
                }

                $stateService->transition($orderModel, OrderStatus::PAID_PENDING, 'system', [
                    'reason'     => "{$channel}支付回调成功",
                    'payment_no' => $paymentNo,
                ]);
            }

            // 核销库存（锁定 → 已消耗）：批次4 起由 OrderStateService
            // "支付成功" 副作用统一触发（余额/第三方路径一致），此处不再直调

            // 记录操作日志
            $this->logOperation(
                module: 'payment',
                action: "{$channel}_notify",
                targetType: 'payment',
                targetId: (int) $payment['id'],
                targetNo: $paymentNo,
                afterData: ['pay_status' => PayStatus::SUCCESS->value, 'transaction_id' => $transactionId, 'channel' => $channel],
                remark: "{$channel}支付回调成功",
            );

            Log::info('支付回调处理成功', [
                'payment_no' => $paymentNo,
                'channel' => $channel,
                'paid_cent' => $paymentCent,
            ]);

            return true;
            });
        } finally {
            RedisLock::release($notifyLockKey, $notifyToken);
        }
    }

    /**
     * 拒绝回调入账：支付单置失败态并告警
     *
     * 不更新订单、不入账，保留回调原文供对账排查。
     *
     * @param array $payment 支付单记录
     * @param array $notifyData 回调原始数据
     * @param string $reason 拒绝原因
     * @param array $context 金额上下文
     * @return void
     */
    private function rejectCallbackPayment(array $payment, array $notifyData, string $reason, array $context = []): void
    {
        Db::name('payment')
            ->where('id', $payment['id'])
            ->update([
                'pay_status'     => PayStatus::FAILED->value,
                'notify_content' => json_encode($notifyData, JSON_UNESCAPED_UNICODE),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        Log::error('支付回调拒绝入账：' . $reason, array_merge([
            'payment_no' => $payment['payment_no'],
            'order_id'   => $payment['order_id'],
        ], $context));
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
            ->where('transaction_type', CustomerType::STORE->value)
            ->find();

        if (!$order) {
            throw new ValidateException('订单不存在');
        }

        // 查询最新支付记录
        $payment = Db::name('payment')
            ->where('order_id', $orderId)
            ->order('id', 'desc')
            ->find();

        // 若支付状态为待支付且存在支付单号，先做过期判定（过期置失败并回退订单状态），
        // 未过期再主动向第三方查单
        if ($payment && (int) $payment['pay_status'] === PayStatus::PENDING->value && !empty($payment['payment_no'])) {
            if (!$this->expirePendingPaymentIfNeeded($payment)) {
                $this->queryThirdPartyPayment($payment);
            }
            $payment = Db::name('payment')->where('id', $payment['id'])->find();
        }

        $channel = PaymentChannel::tryFrom((string) ($payment['payment_channel'] ?? ''));
        $payStatus = PayStatus::from((int) ($payment['pay_status'] ?? 0));

        return [
            'order_id'            => $orderId,
            'order_no'            => $order['order_no'],
            'order_status'        => (int) $order['order_status'],
            // deploy lj_order：应付总额 total_amount_cent 减已付即待付（响应键名保持兼容）
            'payable_amount_cent' => (int) $order['total_amount_cent'] - (int) $order['paid_amount_cent'],
            'paid_amount_cent'    => (int) $order['paid_amount_cent'],
            'paid_at'             => $order['paid_at'],
            'payment_no'          => $payment['payment_no'] ?? null,
            // 批次2c：渠道值由 int 改为 deploy VARCHAR 枚举值（balance/wechat/alipay）
            'pay_channel'         => $payment['payment_channel'] ?? null,
            'pay_channel_text'    => $channel?->label() ?? '未知',
            'pay_status'          => $payStatus->value,
            'pay_status_text'     => $payStatus->label(),
        ];
    }

    /**
     * 刷新已有支付单的支付参数
     *
     * @param array $payment 支付记录
     * @param PaymentChannel $channel 渠道
     * @param string $payMethod 支付方式
     * @return array
     */
    private function refreshPaymentParams(array $payment, PaymentChannel $channel, string $payMethod): array
    {
        $payParams = $this->callPayChannel(
            $payment['payment_no'],
            (int) $payment['pay_amount_cent'],
            $channel,
            $payMethod
        );

        $result = [
            'payment_no'      => $payment['payment_no'],
            'pay_amount_cent' => (int) $payment['pay_amount_cent'],
            'pay_channel'     => $channel->value,
            'pay_channel_text' => $channel->label(),
            'expire_seconds'  => 1800,
        ];

        if ($channel === PaymentChannel::WECHAT) {
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
     * @param PaymentChannel $channel 渠道
     * @param string $method 支付方式
     * @return array 支付参数
     */
    private function callPayChannel(string $paymentNo, int $amountCent, PaymentChannel $channel, string $method): array
    {
        if ($channel === PaymentChannel::WECHAT) {
            // TODO: 对接微信支付 V3 API
            return [
                'timeStamp' => (string) time(),
                'nonceStr'  => bin2hex(random_bytes(16)),
                'package'   => 'prepay_id=mock_' . $paymentNo,
                'signType'  => 'RSA',
                'paySign'   => 'mock_sign',
            ];
        }

        if ($channel === PaymentChannel::ALIPAY) {
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
     * 支付单过期判定与补偿（查询路径）
     *
     * 待支付单超过拉起时效（1800s）：置支付单为失败态，并以 system 角色
     * 回退订单 PAYING → PENDING_PAY（评审 Critical 2 ③，与渠道调用失败补偿对称）。
     *
     * @param array $payment 支付记录
     * @return bool true=已过期并完成补偿
     */
    private function expirePendingPaymentIfNeeded(array $payment): bool
    {
        $createdAt = (string) ($payment['created_at'] ?? '');
        if ($createdAt === '') {
            return false;
        }

        $createdTs = strtotime($createdAt);
        if ($createdTs === false || (time() - $createdTs) <= 1800) {
            return false;
        }

        Db::name('payment')
            ->where('id', $payment['id'])
            ->where('pay_status', PayStatus::PENDING->value)
            ->update([
                'pay_status' => PayStatus::FAILED->value,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $this->rollbackPayingToPending((int) $payment['order_id'], (string) $payment['order_no'], '支付单过期');

        Log::info('支付单过期，已置失败态并回退订单状态', [
            'payment_no' => $payment['payment_no'],
            'order_no'   => $payment['order_no'],
        ]);

        return true;
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
        $channel = PaymentChannel::tryFrom((string) ($payment['payment_channel'] ?? ''));

        return [
            'payment_no'      => $payment['payment_no'],
            'pay_amount_cent' => (int) $payment['pay_amount_cent'],
            'pay_channel'     => $channel?->value,
            'pay_channel_text' => $channel?->label() ?? '未知',
            'pay_status'      => (int) $payment['pay_status'],
            'idempotent'      => $isIdempotent,
        ];
    }

    /**
     * 生成支付单号
     *
     * 格式保持 PAY{Ymd}{6位序号} 不变；批次2a：取号机制改为
     * SequenceNo（Redis INCR + MySQL 降级）。
     *
     * @return string
     */
    private function generatePaymentNo(): string
    {
        return SequenceNo::generate('payment', 'PAY');
    }
}
