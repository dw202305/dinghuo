<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 选装配件模型
 * @property int $id
 * @property string $sku 配件SKU
 * @property string $name 配件名称
 * @property string $config_group 配置组：power/remote/wall_control
 * @property int $option_type 类型：1标准 2升级 3新增
 * @property int $surcharge_cent 加价或补差价(分)
 * @property int|null $upgrade_price_cent 升级价格(分)
 * @property int|null $partner_surcharge_cent 合伙人加价(分)
 * @property int $required 是否必选
 * @property int $enabled 是否启用
 * @property int $price_version 价格版本
 */
class Accessory extends BaseModel
{
    protected $table = 'lj_accessory';

    // JSON 字段
    protected $json = ['applicable_products', 'compatibility_rules'];

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'surcharge_cent' => 'integer',
        'upgrade_price_cent' => 'integer',
        'partner_surcharge_cent' => 'integer',
        'option_type' => 'integer',
        'required' => 'integer',
        'enabled' => 'integer',
        'select_mode' => 'integer',
        'allow_quantity' => 'integer',
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
     * 按配置组筛选
     */
    public function scopeOfGroup($query, string $group): void
    {
        $query->where('config_group', $group);
    }

    /**
     * 必选配件筛选
     */
    public function scopeRequired($query): void
    {
        $query->where('required', 1);
    }

    /**
     * 根据 SKU 查找
     */
    public function scopeOfSku($query, string $sku): void
    {
        $query->where('sku', $sku);
    }
}
