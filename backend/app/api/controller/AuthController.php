<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\model\Account;
use app\common\model\AccountCustomer;
use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use app\common\middleware\AuthMiddleware;
use app\common\service\AuthService;
use app\common\service\auth\MockWechatLoginAdapter;
use app\common\service\auth\WechatLoginAdapter;
use app\common\service\auth\WechatMiniProgramLoginAdapter;
use app\common\service\sms\AliyunSmsSender;
use app\common\service\sms\MockSmsSender;
use app\common\service\sms\SmsSenderInterface;
use app\api\validate\AuthValidate;
use think\exception\ValidateException;
use think\facade\Cache;

/**
 * 认证控制器
 * 处理登录/注册/切换门店/获取当前登录信息等
 *
 * 批次3新增：sendCode / wechatLogin / logout / profile
 * （原密码登录 login 为 api.md 旧契约，保持不变）
 */
class AuthController extends BaseController
{
    /** 短信验证码 Redis Key 前缀 */
    private const SMS_CODE_PREFIX = 'sms:code:';
    /** 短信防刷限制 Redis Key 前缀 */
    private const SMS_LIMIT_PREFIX = 'sms:limit:';
    /** 验证码有效期（秒）：5 分钟（api_part2 契约 expire_seconds=300） */
    private const SMS_CODE_TTL = 300;
    /** 防刷间隔（秒）：同一手机号 60 秒限发一次 */
    private const SMS_LIMIT_TTL = 60;

    /** 账号角色文案映射（lj_account.account_role） */
    private const ROLE_TEXT = [
        1 => '门店管理员',
        2 => '下单员',
        3 => '财务',
        4 => '安装售后',
        5 => '只读',
    ];
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

    /**
     * 发送短信验证码（批次3新增）
     * POST /api/v1/auth/send-code
     * Body: { "phone": "13800138000", "scene": "login" }
     *
     * 实现要点：
     * - 验证码存 Redis（Cache facade），键 sms:code:{phone}，TTL 5 分钟；
     * - 防刷：sms:limit:{phone} 键存在即拦截，同号 60 秒限一次；
     * - 发送通道由 env('sms.driver') 选择（aliyun 真实通道为 TODO 骨架，
     *   其余环境使用 MockSmsSender 写日志）。
     */
    public function sendCode(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AuthValidate::class)->scene('send_code')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $phone = (string) $data['phone'];
        $scene = (string) $data['scene'];
        $redis = Cache::store('redis');

        // 防刷：同一手机号 60 秒限发一次（批次5：改用专用限流错误码 RATE_LIMITED，HTTP 429）
        if ($redis->get(self::SMS_LIMIT_PREFIX . $phone)) {
            return $this->error('验证码发送过于频繁，请60秒后重试', ErrorCode::RATE_LIMITED);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $sender = $this->resolveSmsSender();
        if (!$sender->sendVerifyCode($phone, $code, ['scene' => $scene])) {
            return $this->error('验证码发送失败，请稍后重试', ErrorCode::THIRD_PARTY_ERROR);
        }

        // 发送成功后才写入 Redis（覆盖旧码）
        $redis->set(self::SMS_CODE_PREFIX . $phone, $code, self::SMS_CODE_TTL);
        $redis->set(self::SMS_LIMIT_PREFIX . $phone, 1, self::SMS_LIMIT_TTL);

        return $this->success([
            'expire_seconds' => self::SMS_CODE_TTL,
        ], '验证码发送成功');
    }

    /**
     * 微信登录（批次3新增）
     * POST /api/v1/auth/wechat-login
     * Body: { "code": "wx.login() 返回的 code" }
     *
     * 流程：code → openid → 查 lj_account.wechat_openid 绑定 → 签发 JWT
     * （复用密码登录的 token + 默认门店逻辑）；未绑定返回业务错误码
     * WECHAT_NOT_BOUND，提示联系总部绑定。
     */
    public function wechatLogin(): \think\Response
    {
        try {
            validate(AuthValidate::class)->scene('wechat')
                ->check($this->app->request->post());
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $code = (string) $this->app->request->post('code', '');

        try {
            $session = $this->resolveWechatLoginAdapter()->codeToSession($code);
        } catch (BusinessException $e) {
            return $this->error($e->getErrorMessage(), $e->getErrorCode());
        }

        $account = Account::where('wechat_openid', $session['openid'])
            ->where('status', 1)
            ->find();

        if (!$account) {
            // 未绑定：业务错误码提示联系总部（api_part2 契约）
            return $this->error('该微信尚未绑定门店账号，请联系总部绑定', ErrorCode::WECHAT_NOT_BOUND);
        }

        // 已绑定：复用现有登录的 token 签发逻辑
        $account->save(['last_login_at' => date('Y-m-d H:i:s')]);

        $token = AuthService::generateToken($account);

        $defaultStoreId = $account->getDefaultStoreId();
        if ($defaultStoreId > 0) {
            AuthMiddleware::updateCurrentStore($account->id, $defaultStoreId);
        }

        $stores = $this->buildStoreList($account->getStoreIds());

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
     * 登出（批次3新增）
     * POST /api/v1/auth/logout
     *
     * 采用 JWT 黑名单方案：将当前 Token 写入 Redis 黑名单
     * （键 jwt:blacklist:sha1(token)，TTL = Token 剩余有效期），
     * AuthMiddleware 在鉴权时先查黑名单，命中即拒绝。
     * 同时清除当前门店缓存。
     */
    public function logout(): \think\Response
    {
        $accountId = $this->getAccountId();

        $token = $this->app->request->header('Authorization', '');
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if ($token !== '') {
            $decoded = AuthService::verifyToken($token);
            if ($decoded && isset($decoded->exp)) {
                $remaining = (int) $decoded->exp - time();
                AuthMiddleware::blacklistToken($token, $remaining);
            }
        }

        AuthMiddleware::clearCurrentStore($accountId);

        return $this->success(null, '登出成功');
    }

    /**
     * 当前登录账号详情（批次3新增）
     * GET /api/v1/auth/profile
     *
     * 返回账号信息 + 关联主体（门店列表、当前门店、微信绑定状态）。
     */
    public function profile(): \think\Response
    {
        $accountId = $this->getAccountId();
        $account = Account::find($accountId);

        if (!$account) {
            return $this->unauthorized();
        }

        $storeId = $this->getStoreId();
        $stores = $this->buildStoreList($account->getStoreIds());

        // wechat_openid 在模型 hidden 中，用 getData 读取原始值判断绑定状态
        $wechatBound = !empty($account->getData('wechat_openid'));

        $phone = (string) $account->phone;
        $maskedPhone = strlen($phone) === 11
            ? substr($phone, 0, 3) . '****' . substr($phone, 7)
            : $phone;

        return $this->success([
            'id'            => $account->id,
            'phone'         => $maskedPhone,
            'real_name'     => $account->real_name,
            'role'          => $account->account_role,
            'role_text'     => self::ROLE_TEXT[(int) $account->account_role] ?? '未知',
            'verify_status' => $account->verify_status,
            'wechat_bound'  => $wechatBound,
            'current_store_id' => $storeId,
            'stores'        => $stores,
            'last_login_at' => $account->last_login_at,
        ]);
    }

    // ────────────────────────────────────────────────
    // 私有辅助（适配器选择/数据组装，不含业务逻辑）
    // ────────────────────────────────────────────────

    /**
     * 选择短信发送器：env('sms.driver') = 'aliyun' 时用真实通道，否则 Mock
     */
    private function resolveSmsSender(): SmsSenderInterface
    {
        if (env('sms.driver', 'mock') === 'aliyun') {
            return new AliyunSmsSender();
        }

        return new MockSmsSender();
    }

    /**
     * 选择微信登录适配器：env('wechat.login_driver') = 'miniprogram' 时用真实通道，否则 Mock
     */
    private function resolveWechatLoginAdapter(): WechatLoginAdapter
    {
        if (env('wechat.login_driver', 'mock') === 'miniprogram') {
            return new WechatMiniProgramLoginAdapter();
        }

        return new MockWechatLoginAdapter();
    }

    /**
     * 组装关联门店列表（id => store_name）
     * @param array $storeIds
     * @return array
     */
    private function buildStoreList(array $storeIds): array
    {
        if (empty($storeIds)) {
            return [];
        }

        return \app\common\model\Store::whereIn('id', $storeIds)
            ->where('status', 1)
            ->column('store_name', 'id');
    }
}
