<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 面料模型
 * @property int $id
 * @property string $fabric_no 世尚面料编号
 * @property string $name 名称
 * @property string $series 系列
 * @property int $price_per_m2_cent 单价(分/㎡)
 * @property int $listing_status 上架状态
 */
class Fabric extends BaseModel
{
    protected $table = 'lj_fabric';

    // JSON 字段
    protected $json = ['texture_tags', 'function_tags', 'detail_images'];

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'price_per_m2_cent' => 'integer',
        'listing_status' => 'integer',
        'orderable' => 'integer',
        'stock_status' => 'integer',
        'sort_weight' => 'integer',
        'price_version' => 'integer',
    ];

    /**
     * 关联供应商映射
     */
    public function supplierMappings(): \think\model\relation\HasMany
    {
        return $this->hasMany(FabricSupplierMapping::class, 'fabric_id', 'id');
    }

    /**
     * 可下单面料查询
     */
    public static function orderable(): \think\db\Query
    {
        return self::where('listing_status', 1)
            ->where('orderable', 1)
            ->whereNull('deleted_at');
    }
}
