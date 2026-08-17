<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use app\common\model\Admin;
use app\common\model\AdminRole;
use app\common\model\AdminPermission;
use app\common\model\AdminRolePermission;
use app\common\service\AuthService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 后台认证控制器
 * 基于 lj_admin 表 + RBAC 权限体系
 */
class AdminAuthController extends BaseController
{
    /**
     * 管理员登录
     * POST /api/v1/admin/auth/login
     */
    public function login(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('login')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        // 查询管理员
        $admin = Admin::where('username', $data['username'])
            ->where('status', 1)
            ->find();

        if (!$admin || !$admin->verifyPassword($data['password'])) {
            return $this->error('用户名或密码错误', 2005);
        }

        // 加载角色信息
        $role = AdminRole::where('id', $admin->role_id)
            ->where('status', 1)
            ->find();

        if (!$role) {
            return $this->error('角色已停用或不存在', 2003);
        }

        // 获取权限编码列表
        $permissions = $role->getPermissionCodes();

        // 更新登录信息
        $admin->updateLoginInfo($this->app->request->ip());

        // 生成 JWT Token
        $payload = [
            'admin_id'    => $admin->id,
            'username'    => $admin->username,
            'role_id'     => $admin->role_id,
            'permissions' => $permissions,
        ];
        $token = AuthService::generateAdminToken($payload, 28800); // 8小时

        return $this->success([
            'token'       => $token,
            'expires_in'  => 28800,
            'admin_id'    => $admin->id,
            'username'    => $admin->username,
            'real_name'   => $admin->real_name,
            'role_id'     => $admin->role_id,
            'role_name'   => $role->role_name,
            'role_code'   => $role->role_code,
            'permissions' => $permissions,
        ], '登录成功');
    }

    /**
     * 退出登录
     * POST /api/v1/admin/auth/logout
     */
    public function logout(): \think\Response
    {
        // TODO: 使 Token 失效（Redis 黑名单）
        return $this->success(null, '退出成功');
    }

    /**
     * 获取当前管理员信息
     * GET /api/v1/admin/auth/profile
     */
    public function profile(): \think\Response
    {
        $adminId = $this->app->request->adminId ?? 0;

        $admin = Admin::alias('a')
            ->leftJoin('lj_admin_role r', 'r.id = a.role_id')
            ->where('a.id', $adminId)
            ->field([
                'a.id as admin_id',
                'a.username',
                'a.real_name',
                'a.phone',
                'a.email',
                'a.avatar',
                'a.role_id',
                'r.role_name',
                'r.role_code',
                'a.last_login_at',
                'a.last_login_ip',
                'a.login_count',
            ])
            ->find();

        if (!$admin) {
            return $this->unauthorized('管理员不存在');
        }

        // 获取权限列表
        $role = AdminRole::find($admin['role_id']);
        $admin['permissions'] = $role ? $role->getPermissionCodes() : [];

        return $this->success($admin);
    }

    /**
     * 修改密码
     * PUT /api/v1/admin/auth/password
     */
    public function changePassword(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('change_password')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $adminId = $this->app->request->adminId ?? 0;
        $admin = Admin::find($adminId);

        if (!$admin || !$admin->verifyPassword($data['old_password'])) {
            return $this->error('原密码错误', 2005);
        }

        $admin->password_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $admin->save();

        return $this->success(null, '密码修改成功');
    }
}
