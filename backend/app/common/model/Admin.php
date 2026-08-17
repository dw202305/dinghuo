<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 后台管理员模型
 * @property int $id
 * @property string $username 登录用户名
 * @property string $password_hash 密码哈希
 * @property string $real_name 真实姓名
 * @property string|null $phone 手机号
 * @property string|null $email 邮箱
 * @property string|null $avatar 头像URL
 * @property int $is_super_admin 是否超级管理员：1是 0否
 * @property int $role_id 角色ID
 * @property int $status 状态：1正常 0停用
 * @property string|null $last_login_at 最近登录时间
 * @property string|null $last_login_ip 最近登录IP
 * @property int $login_count 登录次数
 */
class Admin extends BaseModel
{
    protected $table = 'lj_admin';

    // 隐藏敏感字段
    protected $hidden = ['password_hash', 'deleted_at'];

    /**
     * 关联角色
     */
    public function role()
    {
        return $this->belongsTo(AdminRole::class, 'role_id', 'id');
    }

    /**
     * 获取管理员的权限列表（通过角色）
     */
    public function getPermissions(): array
    {
        $roleId = $this->role_id;
        if (!$roleId) {
            return [];
        }

        return AdminPermission::alias('p')
            ->join('lj_admin_role_permission rp', 'rp.permission_id = p.id')
            ->where('rp.role_id', $roleId)
            ->where('p.status', 1)
            ->column('p.permission_code');
    }

    /**
     * 验证密码
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    /**
     * 更新登录信息
     */
    public function updateLoginInfo(string $ip): bool
    {
        return $this->save([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
            'login_count'   => $this->login_count + 1,
        ]);
    }

    /**
     * 状态作用域：正常
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
