<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\BalanceValidate;
use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use app\common\service\BalanceAccountService;
use app\common\service\OrderService;
use think\exception\ValidateException;

/**
 * 储值账户控制器（门店端，批次3新增）
 *
 * 对应 route/app.php 2.9 节：
 * - POST   /api/v1/balance-accounts/:id/recharge     发起储值
 * - POST   /api/v1/balance-accounts/:id/pay          余额支付
 * - GET    /api/v1/balance-accounts/:id/transactions 流水分页
 * - GET    /api/v1/balance-accounts/:id              账户详情
 *
 * 资金安全规则（PRD 4.9 & 规范 12.0）：
 * - 登录账号 ID 不直接当资金账户 ID：先经 BalanceAccountService::resolveCustomerByAccount
 *   解析客户主体，再定位资金账户，并校验路径 :id 与解析结果一致（防越权）；
 * - 余额变动一律委托 BalanceAccountService（同一事务 + 乐观锁 + 幂等键）；
 * - Controller 只做参数校验与编排，不直接操作 Db/余额/状态。
 */
class BalanceAccountController extends BaseController
{
    /** 储值方式映射：前端 pay_channel（1微信 2支付宝）→ BalanceAccountService 方式别名 */
    private const RECHARGE_METHOD_BY_CHANNEL = [
        1 => 'wechat',
        2 => 'alipay',
    ];

    /** 账户状态文案（lj_customer_balance_account.account_status） */
    private const ACCOUNT_STATUS_TEXT = [
        1 => '正常',
        2 => '已冻结',
        3 => '已注销',
    ];

    protected BalanceAccountService $balanceService;

    protected function initialize(): void
    {
        $this->balanceService = new BalanceAccountService();
    }

    /**
     * 解析当前登录账号拥有的资金账户，并校验路径 :id 一致
     *
     * @throws BusinessException|ValidateException
     */
    private function resolveOwnedAccount(int $pathAccountId): array
    {
        $customer = $this->balanceService->resolveCustomerByAccount($this->getAccountId());
        $account = $this->balanceService->getOrCreateAccount($customer['customer_type'], $customer['customer_id']);

        if ((int) $account['id'] !== $pathAccountId) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '无权访问该储值账户');
        }

        return $account;
    }

    /**
     * 储值账户详情
     * GET /api/v1/balance-accounts/:id
     */
    public function detail(int $id): \think\Response
    {
        try {
            $account = $this->resolveOwnedAccount($id);
        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), ErrorCode::PARAM_INVALID);
        }

        $status = (int) $account['account_status'];

        return $this->success([
            'id'            => (int) $account['id'],
            'customer_type' => (int) $account['customer_type'],
            'customer_id'   => (int) $account['customer_id'],
            'balance_cent'  => (int) $account['available_balance_cent'],
            'frozen_cent'   => (int) $account['frozen_balance_cent'],
            'total_recharge_cent'   => (int) $account['total_recharge_cent'],
            'total_consumed_cent'   => (int) $account['total_consumed_cent'],
            'total_refund_cent'     => (int) $account['total_refund_cent'],
            'status'        => $status,
            'status_text'   => self::ACCOUNT_STATUS_TEXT[$status] ?? '未知',
        ]);
    }

    /**
     * 发起储值（创建充值单，入账以支付回调/财务审核为准）
     * POST /api/v1/balance-accounts/:id/recharge
     */
    public function recharge(int $id): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(BalanceValidate::class)->scene('recharge')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $account = $this->resolveOwnedAccount($id);

            $payChannel = (int) $data['pay_channel'];
            $method = self::RECHARGE_METHOD_BY_CHANNEL[$payChannel];

            $result = $this->balanceService->recharge(
                (int) $account['id'],
                (int) $data['amount_cent'],
                $method,
                [
                    // 批次4：请求体 idempotent_key 优先，其次 Idempotent-Key 头，
                    // 均缺省时由服务层按 recharge:{recharge_no} 构造业务确定键
                    'idempotent_key' => (string) (($data['idempotent_key'] ?? '') !== ''
                        ? $data['idempotent_key']
                        : $this->app->request->header('Idempotent-Key', '')),
                    'applicant_id'   => $this->getAccountId(),
                    'remark'         => (string) ($data['remark'] ?? ''),
                ],
            );

            return $this->success([
                'recharge_no' => $result['recharge_no'],
                'amount_cent' => (int) $result['amount_cent'],
                'pay_channel' => $payChannel,
                'status'      => (int) $result['status'],
            ], '储值单创建成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::PARAM_INVALID);
        }
    }

    /**
     * 余额支付（复用批次1事务模式：余额扣减+流水+支付记录+订单状态同一事务）
     * POST /api/v1/balance-accounts/:id/pay
     */
    public function pay(int $id): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(BalanceValidate::class)->scene('pay')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $account = $this->resolveOwnedAccount($id);

            $result = app(OrderService::class)->payOrderByBalance(
                $this->getStoreId(),
                (string) $data['order_no'],
                $this->getAccountId(),
                (int) $account['id'],
                (int) $data['amount_cent'],
            );

            return $this->success([
                'payment_no' => $result['payment_no'] ?? null,
                'order_no'   => $result['order_no'] ?? $data['order_no'],
                'idempotent' => (bool) ($result['idempotent'] ?? false),
            ], '余额支付成功');

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            // 余额不足等资金异常：沿用 ValidateException 携带的业务码
            return $this->error($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : ErrorCode::BALANCE_INSUFFICIENT);
        }
    }

    /**
     * 交易流水分页查询
     * GET /api/v1/balance-accounts/:id/transactions
     */
    public function transactions(int $id): \think\Response
    {
        try {
            validate(BalanceValidate::class)->scene('transactions')->check($this->app->request->param());
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $account = $this->resolveOwnedAccount($id);

            [$page, $pageSize] = $this->getPageParams();
            $type = (int) $this->app->request->param('type', 0);

            $result = $this->balanceService->listTransactions((int) $account['id'], $type, $page, $pageSize);

            return $this->success($result);

        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), ErrorCode::PARAM_INVALID);
        }
    }
}
