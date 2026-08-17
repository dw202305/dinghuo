<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\Account;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * 认证服务
 * 处理 JWT Token 生成与验证（门店端 + 后台端）
 */
class AuthService
{
    /**
     * 为门店端账号生成 JWT Token
     * 注意：Token 不包含 store_id，门店切换通过 Redis 控制（对齐架构师方案）
     * @param Account $account 账号模型
     * @param int $expireSeconds 过期时间（秒），默认 2 小时
     * @return string
     */
    public static function generateToken(Account $account, int $expireSeconds = 0): string
    {
        $secret = env('jwt.secret', 'lj_shishang_jwt_secret_2026');
        $expire = $expireSeconds > 0 ? $expireSeconds : (int) env('jwt.expire', 7200);

        $payload = [
            'iss'          => 'shishang-order-system',
            'iat'          => time(),
            'exp'          => time() + $expire,
            'account_id'   => $account->id,
            'account_type' => 'store',
            'role'         => $account->account_role,
            'phone'        => $account->phone,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * 为后台管理员生成 JWT Token
     * @param array $adminData 管理员数据（必须含 admin_id, admin_type='admin'）
     * @param int $expireSeconds 过期时间（秒），默认 8 小时
     * @return string
     */
    public static function generateAdminToken(array $adminData, int $expireSeconds = 0): string
    {
        $secret = env('jwt.secret', 'lj_shishang_jwt_secret_2026');
        $expire = $expireSeconds > 0 ? $expireSeconds : (int) env('jwt.admin_expire', 28800);

        $payload = array_merge([
            'iss'        => 'shishang-order-system',
            'iat'        => time(),
            'exp'        => time() + $expire,
            'admin_type' => 'admin',
        ], $adminData);

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * 验证 JWT Token
     * @param string $token
     * @return object|null
     */
    public static function verifyToken(string $token): ?object
    {
        try {
            $secret = env('jwt.secret', 'lj_shishang_jwt_secret_2026');
            return JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Exception $e) {
            return null;
        }
    }
}
