<?php
declare(strict_types=1);

namespace app\common\middleware;

use app\common\ApiResponse;
use app\common\model\Admin;
use app\common\model\AdminPermission;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\Request;
use think\Response;
use think\facade\Cache;

/**
 * 后台管理端认证中间件
 * 基于 lj_admin 表 + RBAC 权限体系
 *
 * 权限获取策略（对齐架构师方案）：
 * 1. 角色为超级管理员（lj_admin.role_id=1，对应 lj_admin_role.role_code=super_admin）
 *    → 硬编码跳过权限检查，直接放行
 * 2. 普通管理员 → 优先读 Redis 缓存（key=rbac:admin:{admin_id}:permissions，TTL 2h）
 * 3. 缓存未命中 → 查 DB 并写入缓存
 * 4. 权限变更时主动清除对应管理员的缓存 Key
 */
class AdminAuthMiddleware
{
    use ApiResponse;

    /** Redis Key 前缀 */
    private const CACHE_PREFIX = 'rbac:admin:';
    /** Redis Key 后缀 */
    private const CACHE_SUFFIX = ':permissions';
    /** 缓存 TTL（秒）：2 小时 */
    private const CACHE_TTL = 7200;
    /** 超级管理员角色 ID（admin_seed.sql 约定：role_id=1 / role_code=super_admin） */
    private const SUPER_ADMIN_ROLE_ID = 1;

    /**
     * 处理请求
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $token = $request->header('Authorization', '');

        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return $this->unauthorized('请先登录');
        }

        try {
            $secret = env('jwt.secret', 'lj_shishang_jwt_secret_2026');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            // JWT payload: admin_id, admin_type='admin'
            $adminType = $decoded->admin_type ?? '';
            if ($adminType !== 'admin') {
                return $this->forbidden('无权访问后台');
            }

            $adminId = $decoded->admin_id ?? 0;
            if ($adminId <= 0) {
                return $this->unauthorized('登录已过期，请重新登录');
            }

            // 查询管理员是否存在且正常
            $admin = Admin::where('id', $adminId)
                ->where('status', 1)
                ->find();

            if (!$admin) {
                return $this->unauthorized('账号已停用或不存在');
            }

            // 注入管理员信息到请求对象
            $request->adminId   = $adminId;
            $request->adminInfo = $admin;

            // 权限加载：超管跳过 → Redis 缓存 → DB 回源
            // 注：lj_admin 表无 is_super_admin 列，超管由角色表约定（role_id=1）标识
            if ((int) $admin->role_id === self::SUPER_ADMIN_ROLE_ID) {
                // 超管：硬编码跳过权限检查，标记为全权限
                $request->adminPermissions  = ['*'];
                $request->isSuperAdmin      = true;
            } else {
                $request->isSuperAdmin = false;
                $permissions = $this->loadPermissions($adminId);
                $request->adminPermissions = $permissions;
            }

        } catch (\Firebase\JWT\ExpiredException $e) {
            return $this->unauthorized('登录已过期，请重新登录');
        } catch (\Exception $e) {
            return $this->unauthorized('认证失败，请重新登录');
        }

        return $next($request);
    }

    /**
     * 加载管理员权限（Redis 缓存优先）
     * @param int $adminId
     * @return array<string> 权限编码列表
     */
    private function loadPermissions(int $adminId): array
    {
        $cacheKey = self::CACHE_PREFIX . $adminId . self::CACHE_SUFFIX;

        // 1. 尝试读 Redis
        $cached = Cache::store('redis')->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        // 2. 缓存未命中 → 查 DB
        $permissions = AdminPermission::alias('p')
            ->join('lj_admin_role_permission rp', 'rp.permission_id = p.id')
            ->join('lj_admin a', 'a.role_id = rp.role_id')
            ->where('a.id', $adminId)
            ->where('p.status', 1)
            ->column('p.permission_code');

        // 3. 写入 Redis，TTL 2h
        Cache::store('redis')->set($cacheKey, $permissions, self::CACHE_TTL);

        return $permissions;
    }

    /**
     * 清除指定管理员的权限缓存
     * 供 AdminSystemController / AdminRoleController 等权限变更时调用
     * @param int $adminId
     * @return void
     */
    public static function clearPermissionCache(int $adminId): void
    {
        $cacheKey = self::CACHE_PREFIX . $adminId . self::CACHE_SUFFIX;
        Cache::store('redis')->delete($cacheKey);
    }

    /**
     * 批量清除角色下所有管理员的权限缓存
     * 当角色权限变更时调用
     * @param int $roleId
     * @return void
     */
    public static function clearRolePermissionCache(int $roleId): void
    {
        $adminIds = Admin::where('role_id', $roleId)->column('id');
        foreach ($adminIds as $adminId) {
            self::clearPermissionCache($adminId);
        }
    }
}
