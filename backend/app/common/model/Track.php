<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 轨道产品模型
 * @property int $id
 * @property string $sku 轨道SKU
 * @property int $track_type 类型：1横轨 2竖轨
 * @property string $color 颜色
 * @property string $standard_length 标准原料长度(米，DECIMAL(8,2))
 * @property int $price_per_meter_cent 门店单价(分/米)
 * @property int|null $partner_price_cent 合伙人价格(分)
 * @property int $enabled 是否启用
 * @property int $price_version 价格版本
 */
class Track extends BaseModel
{
    protected $table = 'lj_track';

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'track_type' => 'integer',
        'price_per_meter_cent' => 'integer',
        'partner_price_cent' => 'integer',
        'enabled' => 'integer',
        'price_version' => 'integer',
    ];

    /**
     * 启用状态筛选
     */
    public function scopeEnabled($query): void
    {
        $query->where('enabled', 1);
    }

    /**
     * 按轨道类型筛选
     */
    public function scopeOfType($query, int $type): void
    {
        $query->where('track_type', $type);
    }

    /**
     * 按颜色筛选
     */
    public function scopeOfColor($query, string $color): void
    {
        $query->where('color', $color);
    }

    /**
     * 根据 SKU 和颜色查找
     */
    public function scopeOfSkuColor($query, string $sku, string $color): void
    {
        $query->where('sku', $sku)->where('color', $color);
    }
}
