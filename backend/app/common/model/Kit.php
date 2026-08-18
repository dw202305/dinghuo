<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 套件主数据模型
 *
 * 对应 deploy/mysql/init.sql 2.25 lj_kit（等级套件价，PRD 4.4）。
 * 金额以"分"为单位存储（BIGINT），禁止 float（规范 7.2）。
 *
 * @property int $id
 * @property string $kit_sku 套件SKU，如 KIT-STD-STORE
 * @property string $kit_name 套件名称
 * @property int $customer_level 适用客户等级：1认证合作门店 2城市合伙人
 * @property int $kit_price_cent 等级套件价（分，含税不含运费）
 * @property string|null $effective_from 价格生效日期
 * @property string|null $effective_to 价格失效日期
 * @property int $status 状态：1启用 0停用
 */
class Kit extends BaseModel
{
    protected $table = 'lj_kit';

    /**
     * 是否启用中
     */
    public function isEnabled(): bool
    {
        return (int) $this->status === 1;
    }

    /**
     * 当日是否在有效期内（effective_from/to 为空视为不限）
     */
    public function isEffectiveOn(?string $date = null): bool
    {
        $date = $date ?? date('Y-m-d');
        if ($this->effective_from !== null && $this->effective_from > $date) {
            return false;
        }
        if ($this->effective_to !== null && $this->effective_to < $date) {
            return false;
        }
        return true;
    }
}
