<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 后台角色模型
 * @property int $id
 * @property string $role_name 角色名称
 * @property string $role_code 角色编码
 * @property string|null $description 角色描述
 * @property int $sort_order 排序
 * @property int $status 状态：1正常 0停用
 */
class AdminRole extends BaseModel
{
    protected $table = 'lj_admin_role';

    // 角色表不需要软删除
    protected $deleteTime = false;

    /**
     * 关联管理员
     */
    public function admins()
    {
        return $this->hasMany(Admin::class, 'role_id', 'id');
    }

    /**
     * 关联权限（多对多）
     */
    public function permissions()
    {
        return $this->belongsToMany(
            AdminPermission::class,
            'lj_admin_role_permission',
            'permission_id',
            'role_id'
        );
    }

    /**
     * 获取角色拥有的权限编码列表
     */
    public function getPermissionCodes(): array
    {
        return AdminPermission::alias('p')
            ->join('lj_admin_role_permission rp', 'rp.permission_id = p.id')
            ->where('rp.role_id', $this->id)
            ->where('p.status', 1)
            ->column('p.permission_code');
    }

    /**
     * 状态作用域：正常
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
