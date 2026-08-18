<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 窗帘明细模型
 *
 * 每副窗帘为一条独立明细，拥有独立尺寸、配置、计价快照和生产/发货状态。
 * 所有金额字段以"分"为单位（规范 7.2）。
 *
 * @property int $id
 * @property string $item_no 窗帘明细编号（SS-xxx-C001）
 * @property int $order_id 订单ID
 * @property int $sequence 序号
 * @property string $install_position 安装位置
 * @property string $width_cm 宽度（厘米，DECIMAL(6,1)）
 * @property string $height_cm 高度（厘米，DECIMAL(6,1)）
 * @property string $area_m2 面积（平方米，DECIMAL(12,4)）
 * @property string $track_color 轨道颜色
 * @property string $track_horizontal_length_m 横轨长度（米，DECIMAL(6,2)）
 * @property string $track_vertical_length_m 竖轨总长度（米，DECIMAL(6,2)）
 * @property string $fabric_no 世尚面料编号
 * @property int $fabric_price_cent 面料单价（分/㎡）
 * @property int $power_type 电源类型 1标准 2锂电池
 * @property int $power_surcharge_cent 电源加价（分）
 * @property int $remote_type 遥控器类型 1标准 2Pro
 * @property int $remote_surcharge_cent 遥控器加价（分）
 * @property int $wall_control_type 墙控类型 0不配 1标准 2Pro
 * @property int $wall_control_quantity 墙控数量
 * @property int $wall_control_price_cent 墙控单价（分）
 * @property int $wall_control_amount_cent 墙控费用（分）
 * @property int $use_inventory 是否使用库存套件
 * @property int|null $kit_id 套件主数据ID
 * @property int $kit_price_cent 套件单价（分）
 * @property int $track_amount_cent 轨道合计（分）
 * @property int $fabric_amount_cent 面料费用（分）
 * @property int $accessory_amount_cent 选装费用（分）
 * @property int $kit_amount_cent 套件费用（分）
 * @property int $nonstandard_amount_cent 非标费用（分）
 * @property int $item_total_cent 单副合计（分）
 * @property int $technical_status 技术状态（0待审核 1通过 2需确认 3需补款 4无法生产）
 * @property int $production_status 生产状态（0待排产 1生产中 2质检中 3已完成）
 * @property int $qc_status 质检状态（0待质检 1合格 2不合格）
 * @property int $shipping_status 发货状态（0待发货 1已发货 2已签收）
 */
class OrderItem extends BaseModel
{
    protected $table = 'lj_order_item';

    /**
     * 关联订单
     */
    public function order(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 关联面料
     */
    public function fabric(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Fabric::class, 'fabric_no', 'fabric_no');
    }

    /**
     * 关联实际供应商
     */
    public function actualSupplier(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(FabricSupplier::class, 'actual_supplier_id', 'id');
    }

    /**
     * 关联售后记录
     */
    public function afterSales(): \think\model\relation\HasMany
    {
        return $this->hasMany(AfterSale::class, 'item_id', 'id');
    }

    /**
     * 技术状态筛选
     */
    public function scopeOfTechnicalStatus($query, int $status): void
    {
        $query->where('technical_status', $status);
    }

    /**
     * 生产状态筛选
     */
    public function scopeOfProductionStatus($query, int $status): void
    {
        $query->where('production_status', $status);
    }

    /**
     * 待审核筛选
     */
    public function scopePendingReview($query): void
    {
        $query->where('technical_status', 0);
    }

    /**
     * 是否已发货（deploy 列 shipping_status：1已发货 2已签收）
     */
    public function isShipped(): bool
    {
        return (int) $this->shipping_status >= 1;
    }

    /**
     * 获取生产状态标签（deploy 注释：0待排产 1生产中 2质检中 3已完成）
     */
    public function getProductionStatusLabel(): string
    {
        return match ((int) $this->production_status) {
            0 => '待排产',
            1 => '生产中',
            2 => '质检中',
            3 => '已完成',
            default => '未知',
        };
    }
}
