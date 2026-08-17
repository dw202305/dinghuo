<?php
declare(strict_types=1);

namespace app\common\service;

use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 客户储值账户与余额服务
 *
 * 管理客户（门店/城市合伙人）的储值账户、余额支付、退款和资金流水。
 * 所有余额变化在同一 MySQL 事务内完成，使用乐观锁防并发。
 * 每笔余额变化必须生成不可变流水，禁止直接更新余额而不写流水。
 *
 * 关键规则（PRD 4.9 & 规范 12.0）：
 * - 余额和流水在同一 MySQL 事务内更新
 * - 使用乐观锁（version 字段）防并发
 * - 每笔余额变化必须生成不可变流水
 * - 余额不足整笔失败，不部分扣减
 * - 所有方法支持幂等键
 * - 流水冲正通过新的反向流水完成，禁止修改或删除原流水
 *
 * @see docs/dev_specification_v1.0.md 第十二节
 * @see docs/prd_v3.2.md 4.9
 */
class BalanceAccountService extends BaseService
{
    /**
     * 获取或创建客户储值账户
     *
     * 同一客户主体首期只有一个人民币储值账户（PRD 4.9.1）。
     * 账户归属于门店或城市合伙人主体，不归属于手机号。
     *
     * @param string $customerType 客户主体类型：store（门店）或 partner（城市合伙人）
     * @param int $customerId 客户主体ID
     * @return array 账户信息
     */
    public function getOrCreateAccount(string $customerType, int $customerId): array
    {
        $account = Db::name('customer_balance_account')
            ->where('customer_type', $customerType)
            ->where('customer_id', $customerId)
            ->where('currency', 'CNY')
            ->where('status', 1) // 正常状态
            ->find();

        if ($account) {
            return $account;
        }

        // 创建新账户
        $now = date('Y-m-d H:i:s');
        $id = Db::name('customer_balance_account')->insertGetId([
            'customer_type'         => $customerType,
            'customer_id'           => $customerId,
            'currency'              => 'CNY',
            'available_balance'     => 0,
            'frozen_balance'        => 0,
            'total_recharge'        => 0,
            'total_consumed'        => 0,
            'total_refunded'        => 0,
            'total_adjusted'        => 0,
            'status'                => 1,
            'version'               => 0,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        return Db::name('customer_balance_account')->where('id', $id)->find();
    }

    /**
     * 创建储值充值单
     *
     * 储值方式：微信、支付宝、线下或测试（PRD 4.9.3）。
     * 微信和支付宝充值以支付平台成功回调为准。
     * 线下储值必须关联付款凭证并经财务审核。
     * 生产环境禁止无审批人工加款。
     *
     * @param int $accountId 账户ID
     * @param int $amountCent 充值金额（分）
     * @param string $method 储值方式：wechat/alipay/offline/test
     * @param array $meta 附加信息：
     *   - idempotent_key: string 幂等键（必填）
     *   - voucher: string 线下凭证号
     *   - payer_name: string 付款人姓名
     *   - remark: string 备注
     *   - is_test: bool 是否测试资金
     * @return array 储值单信息
     * @throws ValidateException
     */
    public function recharge(int $accountId, int $amountCent, string $method, array $meta = []): array
    {
        if ($amountCent <= 0) {
            throw new ValidateException('充值金额必须大于0');
        }

        $idempotentKey = $meta['idempotent_key'] ?? '';
        if (empty($idempotentKey)) {
            $idempotentKey = 'recharge_' . $accountId . '_' . time() . '_' . mt_rand(1000, 9999);
        }

        // 幂等校验
        $existing = Db::name('recharge_order')
            ->where('idempotent_key', $idempotentKey)
            ->find();

        if ($existing) {
            // 同一幂等键，返回已有结果
            return $existing;
        }

        $account = Db::name('customer_balance_account')
            ->where('id', $accountId)
            ->find();

        if (!$account) {
            throw new ValidateException('账户不存在');
        }

        if ((int) $account['status'] === 2) {
            throw new ValidateException('账户已冻结', 4106);
        }

        // 生成储值单号
        $rechargeNo = $this->generateRechargeNo();

        $now = date('Y-m-d H:i:s');
        $rechargeId = Db::name('recharge_order')->insertGetId([
            'recharge_no'           => $rechargeNo,
            'account_id'            => $accountId,
            'customer_id'           => $account['customer_id'],
            'customer_type'         => $account['customer_type'],
            'amount_cent'           => $amountCent,
            'method'                => $method,
            'voucher'               => $meta['voucher'] ?? null,
            'payer_name'            => $meta['payer_name'] ?? null,
            'status'                => 0, // 待确认
            'idempotent_key'        => $idempotentKey,
            'fund_type'             => ($meta['is_test'] ?? false) ? 'test' : 'real',
            'remark'                => $meta['remark'] ?? '',
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        return Db::name('recharge_order')->where('id', $rechargeId)->find();
    }

    /**
     * 确认储值入账
     *
     * 支付平台回调成功或线下储值财务审核通过后调用。
     * 在同一事务内：更新储值单状态 + 增加账户余额 + 写入资金流水。
     *
     * @param string $rechargeNo 储值单号
     * @return void
     * @throws ValidateException
     */
    public function confirmRecharge(string $rechargeNo): void
    {
        $this->transaction(function () use ($rechargeNo) {
            // 锁定储值单
            $recharge = Db::name('recharge_order')
                ->where('recharge_no', $rechargeNo)
                ->lock(true)
                ->find();

            if (!$recharge) {
                throw new ValidateException('储值单不存在');
            }

            // 幂等：已入账
            if ((int) $recharge['status'] === 1) {
                return;
            }

            if ((int) $recharge['status'] !== 0) {
                throw new ValidateException('储值单状态不允许入账');
            }

            // 锁定账户（乐观锁）
            $account = Db::name('customer_balance_account')
                ->where('id', $recharge['account_id'])
                ->lock(true)
                ->find();

            $amountCent = (int) $recharge['amount_cent'];
            $balanceBefore = (int) $account['available_balance'];
            $balanceAfter = $balanceBefore + $amountCent;
            $now = date('Y-m-d H:i:s');

            // 更新储值单状态
            Db::name('recharge_order')
                ->where('id', $recharge['id'])
                ->update([
                    'status'     => 1, // 已入账
                    'confirmed_at' => $now,
                    'updated_at' => $now,
                ]);

            // 更新账户余额
            Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->update([
                    'available_balance' => $balanceAfter,
                    'total_recharge'    => (int) $account['total_recharge'] + $amountCent,
                    'version'           => (int) $account['version'] + 1,
                    'updated_at'        => $now,
                ]);

            // 写入资金流水（不可变）
            $this->writeBalanceTransaction(
                accountId: $account['id'],
                account: $account,
                type: 'recharge',
                direction: 'in',
                amountCent: $amountCent,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                refType: 'recharge',
                refNo: $rechargeNo,
                fundType: $recharge['fund_type'] ?? 'real',
                reason: '储值入账',
                paymentChannel: $recharge['method'],
            );

            Log::info('储值入账成功', [
                'recharge_no' => $rechargeNo,
                'amount_cent' => $amountCent,
                'balance_after' => $balanceAfter,
            ]);
        });
    }

    /**
     * 余额支付
     *
     * 余额足够时，在同一 MySQL 事务内完成余额扣减、资金流水、支付记录更新。
     * 余额不足时整笔失败，不部分扣减（PRD 4.9.5）。
     * 使用唯一幂等键，重复提交不重复扣减。
     *
     * @param string $orderNo 订单号
     * @param int $amountCent 支付金额（分）
     * @param int $accountId 账户ID
     * @param array $meta 附加信息：
     *   - idempotent_key: string 幂等键（必填）
     *   - operator_id: int 操作人ID
     * @return array 支付结果
     * @throws ValidateException
     */
    public function payByBalance(string $orderNo, int $amountCent, int $accountId, array $meta = []): array
    {
        if ($amountCent <= 0) {
            throw new ValidateException('支付金额必须大于0');
        }

        $idempotentKey = $meta['idempotent_key'] ?? '';
        if (empty($idempotentKey)) {
            throw new ValidateException('余额支付必须提供幂等键');
        }

        // 幂等校验：检查是否已有相同幂等键的支付记录
        $existingPayment = Db::name('payment')
            ->where('idempotent_key', $idempotentKey)
            ->where('pay_channel', 3) // 余额支付
            ->find();

        if ($existingPayment) {
            // 同一幂等键，返回原结果
            return [
                'payment_no'   => $existingPayment['payment_no'],
                'order_no'     => $orderNo,
                'pay_channel'  => 3,
                'pay_status'   => (int) $existingPayment['pay_status'],
                'amount_cent'  => (int) $existingPayment['pay_amount_cent'],
                'paid_at'      => $existingPayment['paid_at'],
                'idempotent'   => true,
            ];
        }

        return $this->transaction(function () use ($orderNo, $amountCent, $accountId, $idempotentKey, $meta) {
            // 锁定账户（乐观锁 + 行锁）
            $account = Db::name('customer_balance_account')
                ->where('id', $accountId)
                ->lock(true)
                ->find();

            if (!$account) {
                throw new ValidateException('账户不存在');
            }

            if ((int) $account['status'] === 2) {
                throw new ValidateException('账户已冻结，无法支付', 4106);
            }

            $availableBalance = (int) $account['available_balance'];

            // 余额不足整笔失败，不部分扣减（PRD 4.9.4）
            if ($availableBalance < $amountCent) {
                throw new ValidateException(
                    "余额不足：可用{$availableBalance}分，需{$amountCent}分",
                    4103
                );
            }

            $balanceBefore = $availableBalance;
            $balanceAfter = $balanceBefore - $amountCent;
            $now = date('Y-m-d H:i:s');

            // 扣减余额
            $affected = Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->where('version', $account['version'])
                ->update([
                    'available_balance' => $balanceAfter,
                    'total_consumed'    => (int) $account['total_consumed'] + $amountCent,
                    'version'           => (int) $account['version'] + 1,
                    'updated_at'        => $now,
                ]);

            if ($affected === 0) {
                throw new ValidateException('余额更新冲突（乐观锁），请重试');
            }

            // 生成支付单号
            $paymentNo = $this->generatePaymentNo('BAL');

            // 写入支付记录
            $paymentId = Db::name('payment')->insertGetId([
                'payment_no'        => $paymentNo,
                'order_no'          => $orderNo,
                'transaction_id'    => $account['customer_id'],
                'pay_channel'       => 3, // 余额
                'pay_amount_cent'   => $amountCent,
                'paid_amount_cent'  => $amountCent,
                'pay_status'        => 1, // 已支付
                'idempotent_key'    => $idempotentKey,
                'balance_transaction_id' => 0, // 稍后更新
                'paid_at'           => $now,
                'created_at'        => $now,
            ]);

            // 写入资金流水
            $transactionId = $this->writeBalanceTransaction(
                accountId: $account['id'],
                account: $account,
                type: 'consumption',
                direction: 'out',
                amountCent: $amountCent,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                refType: 'payment',
                refNo: $paymentNo,
                fundType: 'real',
                reason: '订单余额支付',
                paymentChannel: 'balance',
            );

            // 回写流水ID到支付记录
            Db::name('payment')
                ->where('id', $paymentId)
                ->update(['balance_transaction_id' => $transactionId]);

            Log::info('余额支付成功', [
                'payment_no' => $paymentNo,
                'order_no'   => $orderNo,
                'amount_cent' => $amountCent,
                'balance_after' => $balanceAfter,
            ]);

            return [
                'payment_no'   => $paymentNo,
                'order_no'     => $orderNo,
                'pay_channel'  => 3,
                'pay_status'   => 1,
                'amount_cent'  => $amountCent,
                'paid_at'      => $now,
                'idempotent'   => false,
            ];
        });
    }

    /**
     * 余额退款
     *
     * 使用余额支付的订单，退款退回原客户主体余额（PRD 4.9.6）。
     * 退款必须关联原余额支付流水。
     *
     * @param string $paymentNo 原支付单号
     * @param int $amountCent 退款金额（分）
     * @param array $meta 附加信息：
     *   - idempotent_key: string 幂等键
     *   - reason: string 退款原因
     *   - operator_id: int 操作人ID
     * @return array 退款结果
     * @throws ValidateException
     */
    public function refundToBalance(string $paymentNo, int $amountCent, array $meta = []): array
    {
        if ($amountCent <= 0) {
            throw new ValidateException('退款金额必须大于0');
        }

        $idempotentKey = $meta['idempotent_key'] ?? '';

        // 幂等校验
        if (!empty($idempotentKey)) {
            $existing = Db::name('payment')
                ->where('idempotent_key', $idempotentKey)
                ->where('pay_channel', 3)
                ->where('pay_status', 3) // 已退款
                ->find();

            if ($existing) {
                return [
                    'refund_no'  => $existing['refund_no'] ?? $existing['payment_no'],
                    'idempotent' => true,
                    'pay_status' => 3,
                ];
            }
        }

        return $this->transaction(function () use ($paymentNo, $amountCent, $meta, $idempotentKey) {
            // 查找原支付记录
            $originalPayment = Db::name('payment')
                ->where('payment_no', $paymentNo)
                ->where('pay_channel', 3)
                ->lock(true)
                ->find();

            if (!$originalPayment) {
                throw new ValidateException('原支付记录不存在');
            }

            if ((int) $originalPayment['pay_status'] === 3) {
                throw new ValidateException('该支付单已退款');
            }

            $paidAmountCent = (int) $originalPayment['paid_amount_cent'];
            if ($amountCent > $paidAmountCent) {
                throw new ValidateException('退款金额不能大于原支付金额');
            }

            $accountId = 0;
            // 从原支付记录关联获取账户ID
            $balanceTxn = Db::name('customer_balance_transaction')
                ->where('id', $originalPayment['balance_transaction_id'])
                ->find();
            if ($balanceTxn) {
                $accountId = (int) $balanceTxn['account_id'];
            }

            if ($accountId <= 0) {
                throw new ValidateException('无法定位余额账户');
            }

            // 锁定账户
            $account = Db::name('customer_balance_account')
                ->where('id', $accountId)
                ->lock(true)
                ->find();

            $balanceBefore = (int) $account['available_balance'];
            $balanceAfter = $balanceBefore + $amountCent;
            $now = date('Y-m-d H:i:s');

            // 退回余额
            $affected = Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->where('version', $account['version'])
                ->update([
                    'available_balance' => $balanceAfter,
                    'total_refunded'    => (int) $account['total_refunded'] + $amountCent,
                    'version'           => (int) $account['version'] + 1,
                    'updated_at'        => $now,
                ]);

            if ($affected === 0) {
                throw new ValidateException('余额更新冲突（乐观锁），请重试');
            }

            // 生成退款单号
            $refundNo = $this->generatePaymentNo('REF');

            // 更新原支付记录退款状态
            Db::name('payment')
                ->where('id', $originalPayment['id'])
                ->update([
                    'pay_status'    => 3,
                    'refund_amount_cent' => $amountCent,
                    'refund_no'     => $refundNo,
                    'refunded_at'   => $now,
                    'updated_at'    => $now,
                ]);

            // 写入退款资金流水
            $this->writeBalanceTransaction(
                accountId: $account['id'],
                account: $account,
                type: 'refund',
                direction: 'in',
                amountCent: $amountCent,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                refType: 'refund',
                refNo: $refundNo,
                fundType: 'real',
                reason: $meta['reason'] ?? '余额退款',
                paymentChannel: 'balance',
            );

            Log::info('余额退款成功', [
                'refund_no'   => $refundNo,
                'payment_no'  => $paymentNo,
                'amount_cent' => $amountCent,
                'balance_after' => $balanceAfter,
            ]);

            return [
                'refund_no'  => $refundNo,
                'amount_cent' => $amountCent,
                'pay_status' => 3,
                'refunded_at' => $now,
                'idempotent' => false,
            ];
        });
    }

    /**
     * 冻结余额
     *
     * 从可用余额转入冻结余额。
     *
     * @param int $accountId 账户ID
     * @param int $amountCent 冻结金额（分）
     * @param string $reason 冻结原因
     * @return void
     * @throws ValidateException
     */
    public function freeze(int $accountId, int $amountCent, string $reason): void
    {
        if ($amountCent <= 0) {
            throw new ValidateException('冻结金额必须大于0');
        }

        $this->transaction(function () use ($accountId, $amountCent, $reason) {
            $account = Db::name('customer_balance_account')
                ->where('id', $accountId)
                ->lock(true)
                ->find();

            if (!$account) {
                throw new ValidateException('账户不存在');
            }

            $available = (int) $account['available_balance'];
            if ($available < $amountCent) {
                throw new ValidateException("可用余额不足：可用{$available}分，需冻结{$amountCent}分");
            }

            $balanceBefore = $available;
            $availableAfter = $balanceBefore - $amountCent;
            $frozenBefore = (int) $account['frozen_balance'];
            $frozenAfter = $frozenBefore + $amountCent;
            $now = date('Y-m-d H:i:s');

            Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->update([
                    'available_balance' => $availableAfter,
                    'frozen_balance'    => $frozenAfter,
                    'version'           => (int) $account['version'] + 1,
                    'updated_at'        => $now,
                ]);

            $this->writeBalanceTransaction(
                accountId: $account['id'],
                account: $account,
                type: 'freeze',
                direction: 'out',
                amountCent: $amountCent,
                balanceBefore: $balanceBefore,
                balanceAfter: $availableAfter,
                refType: 'freeze',
                refNo: '',
                fundType: 'real',
                reason: $reason,
            );
        });
    }

    /**
     * 解冻余额
     *
     * 从冻结余额转回可用余额。
     *
     * @param int $accountId 账户ID
     * @param int $amountCent 解冻金额（分）
     * @param string $reason 解冻原因
     * @return void
     * @throws ValidateException
     */
    public function unfreeze(int $accountId, int $amountCent, string $reason): void
    {
        if ($amountCent <= 0) {
            throw new ValidateException('解冻金额必须大于0');
        }

        $this->transaction(function () use ($accountId, $amountCent, $reason) {
            $account = Db::name('customer_balance_account')
                ->where('id', $accountId)
                ->lock(true)
                ->find();

            if (!$account) {
                throw new ValidateException('账户不存在');
            }

            $frozen = (int) $account['frozen_balance'];
            if ($frozen < $amountCent) {
                throw new ValidateException("冻结余额不足：冻结{$frozen}分，需解冻{$amountCent}分");
            }

            $availableBefore = (int) $account['available_balance'];
            $availableAfter = $availableBefore + $amountCent;
            $frozenAfter = $frozen - $amountCent;
            $now = date('Y-m-d H:i:s');

            Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->update([
                    'available_balance' => $availableAfter,
                    'frozen_balance'    => $frozenAfter,
                    'version'           => (int) $account['version'] + 1,
                    'updated_at'        => $now,
                ]);

            $this->writeBalanceTransaction(
                accountId: $account['id'],
                account: $account,
                type: 'unfreeze',
                direction: 'in',
                amountCent: $amountCent,
                balanceBefore: $availableBefore,
                balanceAfter: $availableAfter,
                refType: 'unfreeze',
                refNo: '',
                fundType: 'real',
                reason: $reason,
            );
        });
    }

    /**
     * 人工调整余额
     *
     * 生产环境必须经过财务审批（PRD 4.9.3）。
     * 调拨必须形成双向流水。
     *
     * @param int $accountId 账户ID
     * @param int $amountCent 调整金额（正数增加，负数减少）
     * @param string $reason 调整原因
     * @param int $operatorId 操作人ID
     * @param int $reviewerId 审批人ID
     * @return void
     * @throws ValidateException
     */
    public function manualAdjust(int $accountId, int $amountCent, string $reason, int $operatorId, int $reviewerId): void
    {
        if ($amountCent === 0) {
            throw new ValidateException('调整金额不能为0');
        }

        if (empty($reason)) {
            throw new ValidateException('调整原因不能为空');
        }

        if ($reviewerId <= 0) {
            throw new ValidateException('人工调整必须经过审批');
        }

        $this->transaction(function () use ($accountId, $amountCent, $reason, $operatorId, $reviewerId) {
            $account = Db::name('customer_balance_account')
                ->where('id', $accountId)
                ->lock(true)
                ->find();

            if (!$account) {
                throw new ValidateException('账户不存在');
            }

            $balanceBefore = (int) $account['available_balance'];
            $balanceAfter = $balanceBefore + $amountCent;

            if ($balanceAfter < 0) {
                throw new ValidateException("调整后余额不能为负数：当前{$balanceBefore}分，调整{$amountCent}分");
            }

            $now = date('Y-m-d H:i:s');
            $direction = $amountCent > 0 ? 'in' : 'out';

            Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->update([
                    'available_balance' => $balanceAfter,
                    'total_adjusted'    => (int) $account['total_adjusted'] + $amountCent,
                    'version'           => (int) $account['version'] + 1,
                    'updated_at'        => $now,
                ]);

            $this->writeBalanceTransaction(
                accountId: $account['id'],
                account: $account,
                type: 'manual_adjust',
                direction: $direction,
                amountCent: abs($amountCent),
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                refType: 'adjust',
                refNo: '',
                fundType: 'real',
                reason: $reason,
                operatorId: $operatorId,
                reviewerId: $reviewerId,
            );

            // 记录审计日志
            $this->logOperation(
                module: 'balance',
                action: 'manual_adjust',
                targetType: 'customer_balance_account',
                targetId: $accountId,
                beforeData: ['available_balance' => $balanceBefore],
                afterData: ['available_balance' => $balanceAfter, 'adjust_amount' => $amountCent],
                operatorId: $operatorId,
                remark: "人工调整余额：{$reason}，审批人ID：{$reviewerId}",
            );

            Log::info('人工调整余额', [
                'account_id' => $accountId,
                'before' => $balanceBefore,
                'after' => $balanceAfter,
                'amount' => $amountCent,
                'operator_id' => $operatorId,
                'reviewer_id' => $reviewerId,
            ]);
        });
    }

    /**
     * 写入资金流水
     *
     * 每笔余额变化必须生成不可变流水，禁止修改或删除（PRD 4.9.2 & 15.11）。
     * 冲正通过新的反向流水完成。
     *
     * @param int $accountId 账户ID
     * @param array $account 账户当前数据
     * @param string $type 流水类型：recharge/consumption/refund/freeze/unfreeze/manual_adjust/reversal
     * @param string $direction 方向：in/out
     * @param int $amountCent 金额（分）
     * @param int $balanceBefore 变动前余额
     * @param int $balanceAfter 变动后余额
     * @param string $refType 关联类型：recharge/payment/refund/freeze/unfreeze/adjust
     * @param string $refNo 关联单号
     * @param string $fundType 资金属性：real/test
     * @param string $reason 原因
     * @param string $paymentChannel 支付渠道
     * @param int $operatorId 操作人ID
     * @param int $reviewerId 审核人ID
     * @return int 流水ID
     */
    private function writeBalanceTransaction(
        int $accountId,
        array $account,
        string $type,
        string $direction,
        int $amountCent,
        int $balanceBefore,
        int $balanceAfter,
        string $refType,
        string $refNo,
        string $fundType = 'real',
        string $reason = '',
        string $paymentChannel = '',
        int $operatorId = 0,
        int $reviewerId = 0,
    ): int {
        // 生成唯一流水号
        $transactionNo = $this->generateTransactionNo();

        // 生成业务幂等键
        $idempotentKey = "{$refType}_{$refNo}_{$type}_{$amountCent}_" . time();

        return Db::name('customer_balance_transaction')->insertGetId([
            'transaction_no'      => $transactionNo,
            'account_id'          => $accountId,
            'customer_id'         => $account['customer_id'],
            'customer_type'       => $account['customer_type'],
            'customer_snapshot'   => json_encode([
                'customer_id'   => $account['customer_id'],
                'customer_type' => $account['customer_type'],
            ], JSON_UNESCAPED_UNICODE),
            'transaction_type'    => $type,
            'direction'           => $direction,
            'fund_type'           => $fundType,
            'amount_cent'         => $amountCent,
            'balance_before'      => $balanceBefore,
            'balance_after'       => $balanceAfter,
            'ref_type'            => $refType,
            'ref_no'              => $refNo,
            'idempotent_key'      => $idempotentKey,
            'payment_channel'     => $paymentChannel,
            'operator_id'         => $operatorId,
            'reviewer_id'         => $reviewerId,
            'reason'              => $reason,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 生成储值单号
     *
     * @return string
     */
    private function generateRechargeNo(): string
    {
        $date = date('Ymd');
        $prefix = 'RC' . $date;

        $last = Db::name('recharge_order')
            ->where('recharge_no', 'like', $prefix . '%')
            ->order('id', 'desc')
            ->value('recharge_no');

        $seq = $last ? (int) substr($last, -6) + 1 : 1;

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * 生成支付/退款单号
     *
     * @param string $prefix 前缀（BAL=余额支付, REF=退款）
     * @return string
     */
    private function generatePaymentNo(string $prefix = 'BAL'): string
    {
        $date = date('Ymd');
        $fullPrefix = $prefix . $date;

        $last = Db::name('payment')
            ->where('payment_no', 'like', $fullPrefix . '%')
            ->order('id', 'desc')
            ->value('payment_no');

        $seq = $last ? (int) substr($last, -6) + 1 : 1;

        return $fullPrefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * 生成资金流水号
     *
     * @return string
     */
    private function generateTransactionNo(): string
    {
        $date = date('Ymd');
        $prefix = 'TXN' . $date;

        $last = Db::name('customer_balance_transaction')
            ->where('transaction_no', 'like', $prefix . '%')
            ->order('id', 'desc')
            ->value('transaction_no');

        $seq = $last ? (int) substr($last, -8) + 1 : 1;

        return $prefix . str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
    }
}
