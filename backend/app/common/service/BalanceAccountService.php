<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\AccountStatus;
use app\common\enum\BalanceTxnType;
use app\common\enum\CustomerType;
use app\common\enum\FundDirection;
use app\common\enum\FundType;
use app\common\enum\PayStatus;
use app\common\enum\PaymentChannel;
use app\common\enum\RechargeMethod;
use app\common\enum\RechargeStatus;
use app\common\exception\CodedValidateException;
use app\common\support\Idempotency;
use app\common\support\SequenceNo;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use Throwable;

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
 * 批次2c：全部字段对齐 deploy/mysql/init.sql
 * （customer_type TINYINT、*_cent 列名、account_status、流水 TINYINT 枚举、
 * before/after_balance_cent、ref_order_id/ref_payment_id/ref_recharge_id）。
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
     * @param string $customerType 客户主体类型别名：store（门店）或 partner（城市合伙人）
     * @param int $customerId 客户主体ID
     * @return array 账户信息
     */
    public function getOrCreateAccount(string $customerType, int $customerId): array
    {
        $type = CustomerType::fromAlias($customerType);

        $account = Db::name('customer_balance_account')
            ->where('customer_type', $type->value)
            ->where('customer_id', $customerId)
            ->where('currency', 'CNY')
            ->where('account_status', AccountStatus::NORMAL->value) // 正常状态
            ->find();

        if ($account) {
            return $account;
        }

        // 创建新账户（deploy 列：*_cent 余额列 + account_status）
        $now = date('Y-m-d H:i:s');
        try {
            $id = Db::name('customer_balance_account')->insertGetId([
                'customer_type'          => $type->value,
                'customer_id'            => $customerId,
                'currency'               => 'CNY',
                'available_balance_cent' => 0,
                'frozen_balance_cent'    => 0,
                'total_recharge_cent'    => 0,
                'total_consumed_cent'    => 0,
                'total_refund_cent'      => 0,
                'total_adjustment_cent'  => 0,
                'account_status'         => AccountStatus::NORMAL->value,
                'version'                => 0,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        } catch (Throwable $e) {
            if (!Idempotency::isDuplicateKey($e)) {
                throw $e;
            }

            // 并发首建撞唯一键（评审 Warning 9）：另一请求已创建，重新 find 返回
            $account = Db::name('customer_balance_account')
                ->where('customer_type', $type->value)
                ->where('customer_id', $customerId)
                ->where('currency', 'CNY')
                ->where('account_status', AccountStatus::NORMAL->value)
                ->find();

            if ($account) {
                return $account;
            }

            throw $e;
        }

        return Db::name('customer_balance_account')->where('id', $id)->find();
    }

    /**
     * 通过登录账号解析客户主体（lj_account_customer 关联）
     *
     * 登录账号 ID 不等于资金账户 ID：需先经账号-客户主体关联表
     * 解析出 customer_type + customer_id，再定位资金账户。
     *
     * @param int $accountId 登录账号ID
     * @return array ['customer_type' => 'store'|'partner', 'customer_id' => int]
     * @throws ValidateException
     */
    public function resolveCustomerByAccount(int $accountId): array
    {
        if ($accountId <= 0) {
            throw new ValidateException('登录账号无效');
        }

        $binding = Db::name('account_customer')
            ->where('account_id', $accountId)
            ->where('status', 1)
            ->order('id', 'asc')
            ->find();

        if (!$binding) {
            throw new ValidateException('当前账号未关联客户主体，无法使用余额支付');
        }

        // deploy 结构：customer_type TINYINT 1门店 2城市合伙人
        $type = CustomerType::tryFrom((int) $binding['customer_type']);
        if ($type === null) {
            throw new ValidateException('客户主体类型无效');
        }

        return [
            'customer_type' => $type->alias(),
            'customer_id'   => (int) $binding['customer_id'],
        ];
    }

    /**
     * 储值方式别名 → 枚举（API/旧接口兼容层）
     *
     * @param string $method wechat/alipay/offline/test
     * @throws ValidateException
     */
    private function rechargeMethodFromAlias(string $method): RechargeMethod
    {
        return match ($method) {
            'wechat'  => RechargeMethod::WECHAT,
            'alipay'  => RechargeMethod::ALIPAY,
            'offline' => RechargeMethod::OFFLINE,
            'test'    => RechargeMethod::TEST,
            default   => throw new ValidateException('不支持的储值方式'),
        };
    }

    /**
     * 创建储值充值单
     *
     * 储值方式：微信、支付宝、线下或测试（PRD 4.9.3）。
     * 微信和支付宝充值以支付平台成功回调为准。
     * 线下储值必须关联付款凭证并经财务审核。
     * 生产环境禁止无审批人工加款。
     *
     * deploy 状态机：第三方储值创建=1待支付，线下/测试储值创建=3待审核；
     * 入账统一推进到 4已入账（批次2c）。
     *
     * @param int $accountId 账户ID
     * @param int $amountCent 充值金额（分）
     * @param string $method 储值方式别名：wechat/alipay/offline/test
     * @param array $meta 附加信息：
     *   - idempotent_key: string 幂等键（必填）
     *   - voucher: string 线下凭证信息
     *   - applicant_id: int 申请人ID
     *   - applicant_name: string 申请人姓名
     *   - remark: string 备注
     * @return array 储值单信息
     * @throws ValidateException
     */
    public function recharge(int $accountId, int $amountCent, string $method, array $meta = []): array
    {
        if ($amountCent <= 0) {
            throw new ValidateException('充值金额必须大于0');
        }

        $rechargeMethod = $this->rechargeMethodFromAlias($method);

        $account = Db::name('customer_balance_account')
            ->where('id', $accountId)
            ->find();

        if (!$account) {
            throw new ValidateException('账户不存在');
        }

        if ((int) $account['account_status'] === AccountStatus::FROZEN->value) {
            throw new CodedValidateException('账户已冻结', 4106);
        }
        if ((int) $account['account_status'] === AccountStatus::CANCELLED->value) {
            throw new CodedValidateException('账户已注销', 4106);
        }

        // 初始状态：第三方储值待支付（回调入账），线下/测试储值待审核
        $initialStatus = $rechargeMethod->isThirdParty()
            ? RechargeStatus::PENDING_PAY->value
            : RechargeStatus::PENDING_REVIEW->value;

        $now = date('Y-m-d H:i:s');
        $clientKey = trim((string) ($meta['idempotent_key'] ?? ''));

        // 取号+落库包进 withRetry（评审补漏 14）：recharge_no 撞唯一键且
        // 按幂等键回查为 null（非幂等命中）时重新取号重试
        $recharge = SequenceNo::withRetry(function () use ($account, $accountId, $amountCent, $rechargeMethod, $initialStatus, $meta, $clientKey, $now) {
            // 生成储值单号
            $rechargeNo = $this->generateRechargeNo();

            // 幂等键（批次4：业务确定化，禁用 time()/mt_rand）——
            // 优先调用方透传（Idempotent-Key），缺省 recharge:{recharge_no}
            $idempotentKey = $clientKey !== '' ? $clientKey : "recharge:{$rechargeNo}";

            // 先插、捕 uk_recharge_idempotent 冲突再回查原单返回（不再先查后插）
            $recharge = Idempotency::insertOrFetch('recharge_order', [
                'recharge_no'     => $rechargeNo,
                'account_id'      => $accountId,
                'customer_type'   => (int) $account['customer_type'],
                'customer_id'     => (int) $account['customer_id'],
                'amount_cent'     => $amountCent,
                'recharge_method' => $rechargeMethod->value,
                'offline_voucher' => $meta['voucher'] ?? null,
                'status'          => $initialStatus,
                'applicant_id'    => $meta['applicant_id'] ?? null,
                'applicant_name'  => $meta['applicant_name'] ?? null,
                'idempotent_key'  => $idempotentKey,
                'remark'          => $meta['remark'] ?? '',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            if ($recharge === null) {
                // 回查为 null → recharge_no 撞号（非幂等命中）：携带
                // Duplicate entry 标记上抛，由 withRetry 重新取号重试
                throw new \RuntimeException("Duplicate entry: recharge_no 撞号（recharge_no={$rechargeNo}）");
            }

            return $recharge;
        });

        return $recharge;
    }

    /**
     * 确认储值入账
     *
     * 支付平台回调成功或线下储值财务审核通过后调用。
     * 在同一事务内：更新储值单状态 + 增加账户余额 + 写入资金流水。
     *
     * @param string $rechargeNo 储值单号
     * @param int $reviewerId 审核人ID（线下审核路径传入）
     * @param string $reviewerName 审核人姓名
     * @return void
     * @throws ValidateException
     */
    public function confirmRecharge(string $rechargeNo, int $reviewerId = 0, string $reviewerName = ''): void
    {
        $this->transaction(function () use ($rechargeNo, $reviewerId, $reviewerName) {
            // 锁定储值单
            $recharge = Db::name('recharge_order')
                ->where('recharge_no', $rechargeNo)
                ->lock(true)
                ->find();

            if (!$recharge) {
                throw new ValidateException('储值单不存在');
            }

            $status = RechargeStatus::from((int) $recharge['status']);

            // 幂等：已入账
            if ($status === RechargeStatus::CREDITED) {
                return;
            }

            if (!$status->canCredit()) {
                throw new ValidateException('储值单状态不允许入账：' . $status->label());
            }

            // 锁定账户
            $account = Db::name('customer_balance_account')
                ->where('id', $recharge['account_id'])
                ->lock(true)
                ->find();

            if (!$account) {
                throw new ValidateException('账户不存在');
            }
            if ((int) $account['account_status'] !== AccountStatus::NORMAL->value) {
                throw new CodedValidateException('账户状态异常，无法入账', 4106);
            }

            $amountCent = (int) $recharge['amount_cent'];
            $balanceBefore = (int) $account['available_balance_cent'];
            $balanceAfter = $balanceBefore + $amountCent;
            $now = date('Y-m-d H:i:s');

            // 更新储值单状态（deploy：4已入账 + credited_at）
            Db::name('recharge_order')
                ->where('id', $recharge['id'])
                ->update([
                    'status'        => RechargeStatus::CREDITED->value,
                    'reviewer_id'   => $reviewerId > 0 ? $reviewerId : ($recharge['reviewer_id'] ?? null),
                    'reviewer_name' => $reviewerName !== '' ? $reviewerName : ($recharge['reviewer_name'] ?? null),
                    'reviewed_at'   => $recharge['reviewed_at'] ?? $now,
                    'credited_at'   => $now,
                    'updated_at'    => $now,
                ]);

            // 更新账户余额
            Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->update([
                    'available_balance_cent' => $balanceAfter,
                    'total_recharge_cent'    => (int) $account['total_recharge_cent'] + $amountCent,
                    'version'                => (int) $account['version'] + 1,
                    'updated_at'             => $now,
                ]);

            // 测试储值资金属性为测试资金（deploy lj_recharge_order 无 fund_type 列，
            // 由储值方式 recharge_method=4测试 推导流水 fund_type）
            $fundType = (int) $recharge['recharge_method'] === RechargeMethod::TEST->value
                ? FundType::TEST
                : FundType::REAL;

            // 写入资金流水（不可变）
            $this->writeBalanceTransaction(
                account: $account,
                type: BalanceTxnType::RECHARGE,
                direction: FundDirection::INCOME,
                amountCent: $amountCent,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                fundType: $fundType,
                reason: '储值入账',
                paymentChannel: $this->txnChannelOfRecharge((int) $recharge['recharge_method']),
                idempotentKey: "recharge_credit_{$recharge['id']}",
                refRechargeId: (int) $recharge['id'],
                reviewerId: $reviewerId,
            );

            Log::info('储值入账成功', [
                'recharge_no' => $rechargeNo,
                'amount_cent' => $amountCent,
                'balance_after' => $balanceAfter,
            ]);
        });
    }

    /**
     * 储值方式 → 流水支付渠道值（deploy lj_customer_balance_transaction.payment_channel：
     * wechat/alipay/offline/test）
     */
    private function txnChannelOfRecharge(int $rechargeMethod): string
    {
        return match ($rechargeMethod) {
            RechargeMethod::WECHAT->value  => 'wechat',
            RechargeMethod::ALIPAY->value  => 'alipay',
            RechargeMethod::OFFLINE->value => 'offline',
            RechargeMethod::TEST->value    => 'test',
            default => '',
        };
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

        // 幂等裁决交给 DB 唯一键（批次4：先插、捕 1062 再回查，不再先查后插）
        return $this->transaction(function () use ($orderNo, $amountCent, $accountId, $idempotentKey, $meta) {
            return $this->payByBalanceWithinTransaction($orderNo, $amountCent, $accountId, $idempotentKey, $meta);
        });
    }

    /**
     * 余额支付事务内逻辑（不开启事务，供外部事务复用）
     *
     * 调用方必须确保已处于数据库事务内（如订单余额支付需将余额扣减、
     * 写流水、写支付记录、订单状态更新放入同一个 Db::transaction）。
     * 单独调用请使用 payByBalance()。
     *
     * @param string $orderNo 订单号
     * @param int $amountCent 支付金额（分）
     * @param int $accountId 资金账户ID
     * @param string $idempotentKey 幂等键
     * @param array $meta 附加信息（order_id/operator_id 等）
     * @return array 支付结果（幂等命中时 idempotent=true）
     * @throws ValidateException
     */
    public function payByBalanceWithinTransaction(string $orderNo, int $amountCent, int $accountId, string $idempotentKey, array $meta = []): array
    {
        // 锁定账户（乐观锁 + 行锁）
        $account = Db::name('customer_balance_account')
            ->where('id', $accountId)
            ->lock(true)
            ->find();

        if (!$account) {
            throw new ValidateException('账户不存在');
        }

        if ((int) $account['account_status'] === AccountStatus::FROZEN->value) {
            throw new CodedValidateException('账户已冻结，无法支付', 4106);
        }
        if ((int) $account['account_status'] === AccountStatus::CANCELLED->value) {
            throw new CodedValidateException('账户已注销，无法支付', 4106);
        }

        $availableBalance = (int) $account['available_balance_cent'];

        // 余额不足整笔失败，不部分扣减（PRD 4.9.4）；带错误码必须用
        // CodedValidateException（原生 ValidateException 第二参是字段名，评审 Warning 8）
        if ($availableBalance < $amountCent) {
            throw new CodedValidateException(
                "余额不足：可用{$availableBalance}分，需{$amountCent}分",
                \app\common\enum\ErrorCode::BALANCE_INSUFFICIENT
            );
        }

        $balanceBefore = $availableBalance;
        $balanceAfter = $balanceBefore - $amountCent;
        $now = date('Y-m-d H:i:s');

        // 幂等闸门 + 取号重试（批次4/评审补漏 14）：先插支付单，捕
        // uk_payment_idempotent 冲突再回查；回查为 null（payment_no 撞号）
        // 时由 withRetry 重新取号重试。deploy lj_payment：
        // payment_channel=balance，transaction_id 仅存第三方流水号，
        // 余额支付保持为空避免撞唯一索引
        $gateResult = SequenceNo::withRetry(function () use ($orderNo, $amountCent, $account, $idempotentKey, $now, $meta) {
            $paymentNo = $this->generatePaymentNo('BAL');

            try {
                $paymentId = Db::name('payment')->insertGetId([
                    'payment_no'        => $paymentNo,
                    'order_id'          => (int) ($meta['order_id'] ?? 0),
                    'order_no'          => $orderNo,
                    'payment_channel'   => PaymentChannel::BALANCE->value,
                    'pay_method'        => 'BALANCE',
                    'pay_amount_cent'   => $amountCent,
                    'pay_status'        => PayStatus::SUCCESS->value,
                    'idempotent_key'    => $idempotentKey,
                    'transaction_subject_type' => (int) $account['customer_type'],
                    'transaction_subject_id'   => (int) $account['customer_id'],
                    'paid_at'           => $now,
                    'created_at'        => $now,
                ]);
            } catch (Throwable $e) {
                if (!Idempotency::isDuplicateKey($e)) {
                    throw $e;
                }

                $existingPayment = Db::name('payment')
                    ->where('idempotent_key', $idempotentKey)
                    ->where('payment_channel', PaymentChannel::BALANCE->value)
                    ->find();

                if ($existingPayment) {
                    return ['idempotent' => true, 'payment' => $existingPayment];
                }

                // 回查为 null → payment_no 撞号，上抛由 withRetry 重新取号重试
                throw $e;
            }

            return ['idempotent' => false, 'payment_id' => $paymentId, 'payment_no' => $paymentNo];
        });

        if (!empty($gateResult['idempotent'])) {
            return $this->buildBalancePayResult($gateResult['payment'], $orderNo, true);
        }

        $paymentId = (int) $gateResult['payment_id'];
        $paymentNo = (string) $gateResult['payment_no'];

        // 扣减余额
        $affected = Db::name('customer_balance_account')
            ->where('id', $account['id'])
            ->where('version', $account['version'])
            ->update([
                'available_balance_cent' => $balanceAfter,
                'total_consumed_cent'    => (int) $account['total_consumed_cent'] + $amountCent,
                'version'                => (int) $account['version'] + 1,
                'updated_at'             => $now,
            ]);

        if ($affected === 0) {
            throw new ValidateException('余额更新冲突（乐观锁），请重试');
        }

        // 写入资金流水
        $transactionId = $this->writeBalanceTransaction(
            account: $account,
            type: BalanceTxnType::CONSUME,
            direction: FundDirection::EXPENSE,
            amountCent: $amountCent,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            fundType: FundType::REAL,
            reason: '订单余额支付',
            paymentChannel: PaymentChannel::BALANCE->value,
            idempotentKey: "balance_pay_{$idempotentKey}",
            refOrderId: (int) ($meta['order_id'] ?? 0) > 0 ? (int) $meta['order_id'] : null,
            refPaymentId: (int) $paymentId,
            operatorId: (int) ($meta['operator_id'] ?? 0),
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
            'pay_channel'  => PaymentChannel::BALANCE->value,
            'pay_status'   => PayStatus::SUCCESS->value,
            'amount_cent'  => $amountCent,
            'paid_at'      => $now,
            'idempotent'   => false,
        ];
    }

    /**
     * 构建余额支付幂等返回结果
     */
    private function buildBalancePayResult(array $payment, string $orderNo, bool $idempotent): array
    {
        return [
            'payment_no'   => $payment['payment_no'],
            'order_no'     => $orderNo,
            'pay_channel'  => PaymentChannel::BALANCE->value,
            'pay_status'   => (int) $payment['pay_status'],
            'amount_cent'  => (int) $payment['pay_amount_cent'],
            'paid_at'      => $payment['paid_at'],
            'idempotent'   => $idempotent,
        ];
    }

    /**
     * 余额退款
     *
     * 使用余额支付的订单，退款退回原客户主体余额（PRD 4.9.6）。
     * 退款必须关联原余额支付流水。
     *
     * deploy lj_payment 无 refund_no 列：退款信息存 refund_amount_cent/
     * refunded_at/refund_reason，幂等以退款流水幂等键保障（批次2c）。
     *
     * @param string $paymentNo 原支付单号
     * @param int $amountCent 退款金额（分）
     * @param array $meta 附加信息：
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

        // 退款幂等键：同一支付单同一金额重复退款只执行一次（业务确定键）
        $refundIdempotentKey = "refund:{$paymentNo}:{$amountCent}";

        // 批次4：不再先查后插，事务内流水插入撞 uk_balance_txn_idempotent
        // 时事务回滚，捕 1062 后回查原结果返回
        try {
            return $this->transaction(function () use ($paymentNo, $amountCent, $meta, $refundIdempotentKey) {
            // 查找原支付记录
            $originalPayment = Db::name('payment')
                ->where('payment_no', $paymentNo)
                ->where('payment_channel', PaymentChannel::BALANCE->value)
                ->lock(true)
                ->find();

            if (!$originalPayment) {
                throw new ValidateException('原支付记录不存在');
            }

            // 已付金额以 pay_amount_cent 为准（deploy 无 paid_amount_cent 列）
            $paidAmountCent = (int) $originalPayment['pay_amount_cent'];
            $refundedCent = (int) ($originalPayment['refund_amount_cent'] ?? 0);

            // 拦截条件改为"累计已退满"（评审 Warning 6）：部分退款时
            // pay_status 保持 SUCCESS，满额才置 REFUNDED 终态
            if ($refundedCent >= $paidAmountCent) {
                throw new ValidateException('该支付单已全额退款');
            }
            if ((int) $originalPayment['pay_status'] !== PayStatus::SUCCESS->value) {
                throw new ValidateException('支付单未成功，无法退款');
            }

            // 累计退款不得超过原支付金额
            if ($amountCent + $refundedCent > $paidAmountCent) {
                throw new ValidateException('退款金额不能大于原支付金额剩余可退金额');
            }

            $newRefundedCent = $refundedCent + $amountCent;
            $newPayStatus = $newRefundedCent >= $paidAmountCent
                ? PayStatus::REFUNDED->value
                : PayStatus::SUCCESS->value;

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

            if (!$account) {
                throw new ValidateException('余额账户不存在');
            }

            $balanceBefore = (int) $account['available_balance_cent'];
            $balanceAfter = $balanceBefore + $amountCent;
            $now = date('Y-m-d H:i:s');

            // 退回余额
            $affected = Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->where('version', $account['version'])
                ->update([
                    'available_balance_cent' => $balanceAfter,
                    'total_refund_cent'      => (int) $account['total_refund_cent'] + $amountCent,
                    'version'                => (int) $account['version'] + 1,
                    'updated_at'             => $now,
                ]);

            if ($affected === 0) {
                throw new ValidateException('余额更新冲突（乐观锁），请重试');
            }

            // 更新原支付记录退款状态（deploy 列：refund_amount_cent/refunded_at/refund_reason）；
            // 累计未满保持 SUCCESS，满额才置 REFUNDED 终态（评审 Warning 6）
            Db::name('payment')
                ->where('id', $originalPayment['id'])
                ->update([
                    'pay_status'         => $newPayStatus,
                    'refund_amount_cent' => $newRefundedCent,
                    'refunded_at'        => $now,
                    'refund_reason'      => $meta['reason'] ?? null,
                    'updated_at'         => $now,
                ]);

            // 写入退款资金流水
            $this->writeBalanceTransaction(
                account: $account,
                type: BalanceTxnType::REFUND,
                direction: FundDirection::INCOME,
                amountCent: $amountCent,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                fundType: FundType::REAL,
                reason: $meta['reason'] ?? '余额退款',
                paymentChannel: PaymentChannel::BALANCE->value,
                idempotentKey: $refundIdempotentKey,
                refOrderId: (int) ($originalPayment['order_id'] ?? 0) > 0 ? (int) $originalPayment['order_id'] : null,
                refPaymentId: (int) $originalPayment['id'],
                operatorId: (int) ($meta['operator_id'] ?? 0),
            );

            Log::info('余额退款成功', [
                'payment_no'  => $paymentNo,
                'amount_cent' => $amountCent,
                'balance_after' => $balanceAfter,
            ]);

            return [
                'payment_no'  => $paymentNo,
                'amount_cent' => $amountCent,
                'pay_status'  => $newPayStatus,
                'refunded_at' => $now,
                'idempotent'  => false,
            ];
            });
        } catch (Throwable $e) {
            // 幂等命中（uk_balance_txn_idempotent 冲突）：事务已回滚，回查原结果返回
            if (Idempotency::isDuplicateKey($e)) {
                $existingTxn = Db::name('customer_balance_transaction')
                    ->where('idempotent_key', $refundIdempotentKey)
                    ->find();

                if ($existingTxn) {
                    // 幂等返回状态以支付单当前实际状态为准（部分退款时仍为 SUCCESS）
                    $currentPayment = Db::name('payment')->where('payment_no', $paymentNo)->find();

                    return [
                        'payment_no'  => $paymentNo,
                        'amount_cent' => $amountCent,
                        'pay_status'  => (int) ($currentPayment['pay_status'] ?? PayStatus::REFUNDED->value),
                        'idempotent'  => true,
                    ];
                }
            }

            throw $e;
        }
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

            $available = (int) $account['available_balance_cent'];
            if ($available < $amountCent) {
                throw new ValidateException("可用余额不足：可用{$available}分，需冻结{$amountCent}分");
            }

            $balanceBefore = $available;
            $availableAfter = $balanceBefore - $amountCent;
            $frozenBefore = (int) $account['frozen_balance_cent'];
            $frozenAfter = $frozenBefore + $amountCent;
            $now = date('Y-m-d H:i:s');

            Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->update([
                    'available_balance_cent' => $availableAfter,
                    'frozen_balance_cent'    => $frozenAfter,
                    'version'                => (int) $account['version'] + 1,
                    'updated_at'             => $now,
                ]);

            $this->writeBalanceTransaction(
                account: $account,
                type: BalanceTxnType::FREEZE,
                direction: FundDirection::EXPENSE,
                amountCent: $amountCent,
                balanceBefore: $balanceBefore,
                balanceAfter: $availableAfter,
                fundType: FundType::REAL,
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

            $frozen = (int) $account['frozen_balance_cent'];
            if ($frozen < $amountCent) {
                throw new ValidateException("冻结余额不足：冻结{$frozen}分，需解冻{$amountCent}分");
            }

            $availableBefore = (int) $account['available_balance_cent'];
            $availableAfter = $availableBefore + $amountCent;
            $frozenAfter = $frozen - $amountCent;
            $now = date('Y-m-d H:i:s');

            Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->update([
                    'available_balance_cent' => $availableAfter,
                    'frozen_balance_cent'    => $frozenAfter,
                    'version'                => (int) $account['version'] + 1,
                    'updated_at'             => $now,
                ]);

            $this->writeBalanceTransaction(
                account: $account,
                type: BalanceTxnType::UNFREEZE,
                direction: FundDirection::INCOME,
                amountCent: $amountCent,
                balanceBefore: $availableBefore,
                balanceAfter: $availableAfter,
                fundType: FundType::REAL,
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

            $balanceBefore = (int) $account['available_balance_cent'];
            $balanceAfter = $balanceBefore + $amountCent;

            if ($balanceAfter < 0) {
                throw new ValidateException("调整后余额不能为负数：当前{$balanceBefore}分，调整{$amountCent}分");
            }

            $now = date('Y-m-d H:i:s');
            $direction = $amountCent > 0 ? FundDirection::INCOME : FundDirection::EXPENSE;

            Db::name('customer_balance_account')
                ->where('id', $account['id'])
                ->update([
                    'available_balance_cent' => $balanceAfter,
                    'total_adjustment_cent'  => (int) $account['total_adjustment_cent'] + $amountCent,
                    'version'                => (int) $account['version'] + 1,
                    'updated_at'             => $now,
                ]);

            $this->writeBalanceTransaction(
                account: $account,
                type: BalanceTxnType::MANUAL_ADJUST,
                direction: $direction,
                amountCent: abs($amountCent),
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                fundType: FundType::REAL,
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
                beforeData: ['available_balance_cent' => $balanceBefore],
                afterData: ['available_balance_cent' => $balanceAfter, 'adjust_amount' => $amountCent],
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
     * 写入资金流水（deploy lj_customer_balance_transaction 结构）
     *
     * 每笔余额变化必须生成不可变流水，禁止修改或删除（PRD 4.9.2 & 15.11）。
     * 冲正通过新的反向流水完成。
     *
     * 批次2c：删除 customer_snapshot/ref_type/ref_no 写入，关联改存
     * ref_order_id/ref_payment_id/ref_recharge_id（按业务语义映射）；
     * 前后余额列 before_balance_cent/after_balance_cent；
     * transaction_type/direction/fund_type 全部 TINYINT 枚举。
     *
     * @param array $account 账户当前数据
     * @param BalanceTxnType $type 流水类型
     * @param FundDirection $direction 资金方向
     * @param int $amountCent 金额（分）
     * @param int $balanceBefore 变动前余额
     * @param int $balanceAfter 变动后余额
     * @param FundType $fundType 资金属性
     * @param string $reason 原因
     * @param string $paymentChannel 支付渠道（wechat/alipay/offline/test/balance）
     * @param string $idempotentKey 业务幂等键（为空时以流水号生成）
     * @param int|null $refOrderId 关联订单ID
     * @param int|null $refPaymentId 关联支付单ID
     * @param int|null $refRechargeId 关联储值单ID
     * @param int $operatorId 操作人ID
     * @param int $reviewerId 审核人ID
     * @return int 流水ID
     */
    private function writeBalanceTransaction(
        array $account,
        BalanceTxnType $type,
        FundDirection $direction,
        int $amountCent,
        int $balanceBefore,
        int $balanceAfter,
        FundType $fundType = FundType::REAL,
        string $reason = '',
        string $paymentChannel = '',
        string $idempotentKey = '',
        ?int $refOrderId = null,
        ?int $refPaymentId = null,
        ?int $refRechargeId = null,
        int $operatorId = 0,
        int $reviewerId = 0,
    ): int {
        // 生成唯一流水号
        $transactionNo = $this->generateTransactionNo();

        if ($idempotentKey === '') {
            $idempotentKey = "txn_{$transactionNo}";
        }

        // think-orm insertGetId 返回数字字符串，强转 int 以满足返回类型声明
        return (int) Db::name('customer_balance_transaction')->insertGetId([
            'transaction_no'        => $transactionNo,
            'account_id'            => (int) $account['id'],
            'customer_type'         => (int) $account['customer_type'],
            'customer_id'           => (int) $account['customer_id'],
            'transaction_type'      => $type->value,
            'fund_type'             => $fundType->value,
            'direction'             => $direction->value,
            'amount_cent'           => $amountCent,
            'before_balance_cent'   => $balanceBefore,
            'after_balance_cent'    => $balanceAfter,
            'ref_order_id'          => $refOrderId,
            'ref_payment_id'        => $refPaymentId,
            'ref_recharge_id'       => $refRechargeId,
            'idempotent_key'        => $idempotentKey,
            'payment_channel'       => $paymentChannel !== '' ? $paymentChannel : null,
            'operator_id'           => $operatorId > 0 ? $operatorId : null,
            'reviewer_id'           => $reviewerId > 0 ? $reviewerId : null,
            'reason'                => $reason,
            'created_at'            => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 获取储值账户（不存在则抛异常）
     *
     * 批次3新增：供 BalanceAccountController detail/transactions 使用。
     *
     * @param int $accountId 资金账户ID
     * @return array 账户记录
     * @throws ValidateException
     */
    public function getAccount(int $accountId): array
    {
        $account = Db::name('customer_balance_account')
            ->where('id', $accountId)
            ->find();

        if (!$account) {
            throw new ValidateException('储值账户不存在');
        }

        return $account;
    }

    /**
     * 资金流水分页查询
     *
     * 批次3新增：只读查询，不改变任何余额。流水为不可变记录（规范 12.0）。
     *
     * @param int $accountId 资金账户ID
     * @param int $type 流水类型筛选（0全部，对应 lj_customer_balance_transaction.transaction_type）
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array ['list','total','page','page_size']
     */
    public function listTransactions(int $accountId, int $type = 0, int $page = 1, int $pageSize = 20): array
    {
        $query = Db::name('customer_balance_transaction')
            ->where('account_id', $accountId);

        if ($type > 0) {
            $query->where('transaction_type', $type);
        }

        $total = (clone $query)->count();
        $list = $query->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return [
            'list'      => $list,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 生成储值单号
     *
     * 格式保持 RC{Ymd}{6位序号} 不变；批次2a：取号机制改为
     * SequenceNo（Redis INCR + MySQL 降级）。
     *
     * @return string
     */
    private function generateRechargeNo(): string
    {
        return SequenceNo::generate('recharge', 'RC');
    }

    /**
     * 生成余额支付单号
     *
     * 格式保持 BAL{Ymd}{6位序号} 不变（批次2c：deploy lj_payment 无
     * refund_no 列，退款不再生成独立单号）。
     *
     * @param string $prefix 前缀（BAL=余额支付）
     * @return string
     */
    private function generatePaymentNo(string $prefix = 'BAL'): string
    {
        return SequenceNo::generate('balance_payment', $prefix);
    }

    /**
     * 生成资金流水号
     *
     * 格式保持 TXN{Ymd}{8位序号} 不变（位宽8位与其他单号不同，
     * 故使用 next() 取号后自行格式化）。
     *
     * @return string
     */
    private function generateTransactionNo(): string
    {
        $seq = SequenceNo::next('balance_txn');

        return 'TXN' . date('Ymd') . str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
    }
}
