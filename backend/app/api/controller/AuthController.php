<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\model\Account;
use app\common\model\AccountCustomer;
use app\common\middleware\AuthMiddleware;
use app\common\service\AuthService;

/**
 * 认证控制器
 * 处理登录/注册/切换门店/获取当前登录信息等
 */
class AuthController extends BaseController
{
    /**
     * 手机号+密码登录
     * POST /api/v1/store/auth/login
     */
    public function login(): \think\Response
    {
        $phone = $this->app->request->post('phone', '');
        $password = $this->app->request->post('password', '');

        if (empty($phone) || empty($password)) {
            return $this->paramError('手机号和密码不能为空');
        }

        $account = Account::where('phone', $phone)
            ->where('status', 1)
            ->find();

        if (!$account || !$account->verifyPassword($password)) {
            return $this->error('手机号或密码错误', 2001);
        }

        // 更新登录时间
        $account->save(['last_login_at' => date('Y-m-d H:i:s')]);

        // 生成 Token（不含 store_id）
        $token = AuthService::generateToken($account);

        // 登录时写入默认门店到 Redis
        $defaultStoreId = $account->getDefaultStoreId();
        if ($defaultStoreId > 0) {
            AuthMiddleware::updateCurrentStore($account->id, $defaultStoreId);
        }

        // 获取关联门店列表供前端展示
        $storeIds = $account->getStoreIds();
        $stores = [];
        if (!empty($storeIds)) {
            $stores = \app\common\model\Store::whereIn('id', $storeIds)
                ->where('status', 1)
                ->column('store_name', 'id');
        }

        return $this->success([
            'token' => $token,
            'account' => [
                'id'         => $account->id,
                'phone'      => $account->phone,
                'real_name'  => $account->real_name,
                'role'       => $account->account_role,
            ],
            'current_store_id' => $defaultStoreId,
            'stores' => $stores,
        ], '登录成功');
    }

    /**
     * 切换门店
     * POST /api/v1/store/auth/switch-store
     * Body: { "store_id": 123 }
     */
    public function switchStore(): \think\Response
    {
        $accountId = $this->getAccountId();
        $storeId   = (int) $this->app->request->post('store_id', 0);

        if ($storeId <= 0) {
            return $this->paramError('门店ID不能为空');
        }

        // 校验该账号是否关联此门店
        $relation = AccountCustomer::where('account_id', $accountId)
            ->where('customer_id', $storeId)
            ->where('customer_type', 1)
            ->where('status', 1)
            ->find();

        if (!$relation) {
            return $this->error('无权切换到该门店', 2003);
        }

        // 更新 Redis 中的当前门店
        AuthMiddleware::updateCurrentStore($accountId, $storeId);

        return $this->success([
            'current_store_id' => $storeId,
        ], '切换成功');
    }

    /**
     * 获取当前登录信息
     * GET /api/v1/store/auth/me
     */
    public function me(): \think\Response
    {
        $accountId = $this->getAccountId();
        $account = Account::find($accountId);

        if (!$account) {
            return $this->unauthorized();
        }

        return $this->success([
            'id'         => $account->id,
            'phone'      => $account->phone,
            'real_name'  => $account->real_name,
            'role'       => $account->account_role,
            'verify_status' => $account->verify_status,
        ]);
    }
}
