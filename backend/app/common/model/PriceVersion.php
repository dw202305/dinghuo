<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * @deprecated database.md v1.2 中不存在 lj_price_versions 表
 * price_version 仅为面料/轨道/配件表的整型字段，无需独立版本表。
 * 此模型文件保留待后续确认是否删除，当前不应被任何代码引用。
 *
 * 价格版本模型（规范 8.3）
 * @property int $id
 * @property string $version_no 版本号
 * @property string $effective_at 生效时间
 * @property string|null $expires_at 失效时间
 * @property int $status 状态：1生效中 2已过期 3草稿
 * @property int|null $created_by 创建人ID
 */
class PriceVersion extends BaseModel
{
    protected $table = 'lj_price_versions';

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * 生效中筛选
     */
    public function scopeEffective($query): void
    {
        $query->where('status', 1)
              ->where('effective_at', '<=', date('Y-m-d H:i:s'))
              ->where(function ($q) {
                  $q->whereNull('expires_at')
                    ->whereOr('expires_at', '>', date('Y-m-d H:i:s'));
              });
    }

    /**
     * 草稿筛选
     */
    public function scopeDraft($query): void
    {
        $query->where('status', 3);
    }

    /**
     * 获取当前有效价格版本
     */
    public static function currentEffective(): ?self
    {
        return self::effective()->order('effective_at', 'desc')->find();
    }
}
