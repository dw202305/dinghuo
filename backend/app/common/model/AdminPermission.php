<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 后台权限模型
 * @property int $id
 * @property int $parent_id 父级ID
 * @property string $permission_name 权限名称
 * @property string $permission_code 权限编码
 * @property int $permission_type 类型：1菜单 2按钮 3接口
 * @property string|null $path 路由路径
 * @property string|null $icon 图标
 * @property int $sort_order 排序
 * @property int $status 状态：1正常 0停用
 */
class AdminPermission extends BaseModel
{
    protected $table = 'lj_admin_permission';

    // 权限表不需要软删除
    protected $deleteTime = false;

    // 权限类型常量
    const TYPE_MENU      = 1;
    const TYPE_BUTTON    = 2;
    const TYPE_API       = 3;

    /**
     * 关联父级权限
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 关联子级权限
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    /**
     * 关联角色（多对多）
     */
    public function roles()
    {
        return $this->belongsToMany(
            AdminRole::class,
            'lj_admin_role_permission',
            'role_id',
            'permission_id'
        );
    }

    /**
     * 获取树形结构
     */
    public static function getTree(): array
    {
        $list = self::where('status', 1)
            ->order('sort_order asc, id asc')
            ->select()
            ->toArray();

        return self::buildTree($list);
    }

    /**
     * 构建树形结构
     */
    protected static function buildTree(array $list, int $parentId = 0): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int)$item['parent_id'] === $parentId) {
                $item['children'] = self::buildTree($list, (int)$item['id']);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /**
     * 状态作用域：正常
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 类型作用域
     */
    public function scopeOfType($query, int $type)
    {
        return $query->where('permission_type', $type);
    }
}
