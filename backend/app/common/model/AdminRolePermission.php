<?php
declare(strict_types=1);

namespace app\common\model;

use think\model\Pivot;

/**
 * 角色权限关联模型（中间表）
 * @property int $id
 * @property int $role_id 角色ID
 * @property int $permission_id 权限ID
 */
class AdminRolePermission extends Pivot
{
    protected $table = 'lj_admin_role_permission';

    // 中间表不需要软删除和时间戳（只有 created_at）
    protected $deleteTime = false;
    protected $autoWriteTimestamp = 'datetime';
    protected $updateTime = false;

    /**
     * 关联角色
     */
    public function role()
    {
        return $this->belongsTo(AdminRole::class, 'role_id', 'id');
    }

    /**
     * 关联权限
     */
    public function permission()
    {
        return $this->belongsTo(AdminPermission::class, 'permission_id', 'id');
    }

    /**
     * 批量绑定权限到角色
     */
    public static function syncPermissions(int $roleId, array $permissionIds): bool
    {
        // 先删除旧的
        self::where('role_id', $roleId)->delete();

        // 批量插入新的
        $data = [];
        foreach ($permissionIds as $permissionId) {
            $data[] = [
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
                'created_at'    => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($data)) {
            return (new self())->saveAll($data) !== false;
        }

        return true;
    }
}
