<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\TrackType;
use app\common\model\Store;
use app\common\support\Money;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 价格计算服务（重构版）
 *
 * 核心计价引擎，严格遵循开发规范第七节（7.2）和第八节（8.2）。
 * 所有金额以"分"（整数）为单位进行计算和传递，禁止使用 PHP float 做结算。
 * 内部使用 bcmath 系列函数保证精度。
 *
 * 计价公式来源：
 * - 规范 8.2：单副窗帘标准公式
 * - PRD 4.1-4.7：轨道、面料、套件、选装配件计价规则
 *
 * @see docs/dev_specification_v1.0.md 第七节 & 第八节
 * @see docs/prd_v3.2.md 4.1-4.7
 */
class PriceService extends BaseService
{
    /** 宽度下限(cm) */
    const WIDTH_MIN_CM = 90.0;
    /** 宽度上限(cm) */
    const WIDTH_MAX_CM = 350.0;
    /** 高度下限(cm) */
    const HEIGHT_MIN_CM = 50.0;
    /** 高度上限(cm) */
    const HEIGHT_MAX_CM = 600.0;

    // ── 绝对硬限（批次5：两级尺寸管控，超出即拒绝下单）───────────────
    // 取值依据：在 PRD §4.1.1 标准推荐范围（宽 90~350 / 高 50~600）之外预留
    // 工程安全裕量——宽向两侧各外扩至 30/500cm（轨道可加工范围），
    // 高向外扩至 20/800cm（卷管可覆盖范围）；区间内的非标尺寸不拦截，
    // 仅标记 is_nonstandard 提示联系总部评估。具体硬限待总部工艺确认后
    // 可在此集中调整（建议后续移入后台配置表）。
    /** 宽度绝对下限(cm) */
    const WIDTH_HARD_MIN_CM = 30.0;
    /** 宽度绝对上限(cm) */
    const WIDTH_HARD_MAX_CM = 500.0;
    /** 高度绝对下限(cm) */
    const HEIGHT_HARD_MIN_CM = 20.0;
    /** 高度绝对上限(cm) */
    const HEIGHT_HARD_MAX_CM = 800.0;

    /** bcmath 面积计算精度（保留4位小数） */
    const AREA_SCALE = 4;
    /** bcmath 金额中间计算精度（多保留2位防止截断） */
    const MONEY_SCALE = 6;

    /**
     * 计算单副窗帘明细的全部费用（核心方法）
     *
     * 所有金额输入输出均以"分"为单位（int），宽高输入单位为厘米。
     * 内部将厘米转为米（÷100）后按公式计算。
     *
     * 公式：
     *   横轨费用(分) = bcmul(width_m, track_unit_price_cent, 0)
     *   竖轨费用(分) = bcmul(bcmul(height_m, 2, 4), rail_unit_price_cent, 0)
     *   面料费用(分) = bcmul(area_m2, fabric_price_per_m2_cent, 0)
     *   选装费用(分) = Σ(选装项单价 × 数量)
     *   套件费用(分) = 未抵扣数量 × 客户等级套件单价(分)
     *   单副金额(分) = 横轨 + 竖轨 + 面料 + 选装 + 套件 + 非标
     *
     * @param int $storeId 门店ID（用于获取客户等级和套件价格）
     * @param array $itemData 明细数据，包含：
     *   - width_cm: string 宽度（厘米，字符串防精度丢失）
     *   - height_cm: string 高度（厘米）
     *   - track_color: string 轨道颜色
     *   - fabric_no: string 世尚面料编号
     *   - power_type: int 电源类型 1标准 2锂电池
     *   - remote_type: int 遥控器类型 1标准 2Pro
     *   - wall_control_type: int 墙面控制类型 0不配 1标准 2Pro
     *   - wall_control_quantity: int 墙面控制数量
     *   - use_inventory: int 是否使用库存套件 0|1
     *   - inventory_deduct_count: int 使用库存抵扣数量（默认0或1）
     * @return array 各项费用明细（均为整数字符串或整数，单位分）
     * @throws ValidateException
     */
    public function calculateItemAmount(int $storeId, array $itemData): array
    {
        $widthCm  = $itemData['width_cm'];
        $heightCm = $itemData['height_cm'];

        // 参数校验
        $this->validateDimensionRange($widthCm, $heightCm);

        // 厘米转米：width_m = width_cm / 100，保留足够精度
        $widthM  = bcdiv((string) $widthCm, '100', self::MONEY_SCALE);
        $heightM = bcdiv((string) $heightCm, '100', self::MONEY_SCALE);

        // 轨道长度（米）：横轨=宽度，竖轨总长=高度×2（两条竖轨），
        // 落库 lj_order_item.track_horizontal_length_m / track_vertical_length_m（DECIMAL(6,2)）
        $horizontalLengthM = bcadd($widthM, '0', 2);
        $verticalLengthM   = bcadd(bcmul($heightM, '2', 4), '0', 2);

        // 面积 = width_m × height_m，保留4位小数（规范 7.2）
        $areaM2 = bcmul($widthM, $heightM, self::AREA_SCALE);

        // 1. 横轨费用（分）
        $horizontalTrackCent = $this->calculateHorizontalTrackCent($widthM, $itemData['track_color'] ?? '黑色');

        // 2. 竖轨费用（分）
        $verticalTrackCent = $this->calculateVerticalTrackCent($heightM, $itemData['track_color'] ?? '黑色');

        // 3. 面料费用（分）
        $fabricCent = $this->calculateFabricCent($areaM2, $itemData['fabric_no'] ?? '');

        // 4. 选装配件费用（分）
        $accessoryCent = $this->calculateAccessoryCent(
            (int) ($itemData['power_type'] ?? 1),
            (int) ($itemData['remote_type'] ?? 1),
            (int) ($itemData['wall_control_type'] ?? 0),
            (int) ($itemData['wall_control_quantity'] ?? 0),
        );

        // 5. 套件费用（分）：新购按等级套件价，库存抵扣为0
        $kit = $this->resolveLevelKit($storeId);
        $undeductCount = max(0, 1 - (int) ($itemData['inventory_deduct_count'] ?? 0));
        $kitCent = $undeductCount === 0
            ? '0'
            : (string) Money::mulCent($undeductCount, (int) $kit['kit_price_cent']);

        // 6. 非标费用（门店下单时为0，由后台审核后填写）
        $nonstandardCent = '0';

        // 7. 单副合计（分）= 横轨 + 竖轨 + 面料 + 选装 + 套件 + 非标
        $itemTotalCent = bcadd(
            bcadd(
                bcadd($horizontalTrackCent, $verticalTrackCent, 0),
                bcadd($fabricCent, $accessoryCent, 0),
                0
            ),
            bcadd($kitCent, $nonstandardCent, 0),
            0
        );

        // 非标判断（使用 bccomp 比较，不使用 float）
        $isNonstandard = (
            bccomp($widthCm, (string) self::WIDTH_MIN_CM, 1) < 0 ||
            bccomp($widthCm, (string) self::WIDTH_MAX_CM, 1) > 0 ||
            bccomp($heightCm, (string) self::HEIGHT_MIN_CM, 1) < 0 ||
            bccomp($heightCm, (string) self::HEIGHT_MAX_CM, 1) > 0
        );

        // 墙面控制单价与费用（分）：落库 lj_order_item.wall_control_price_cent / wall_control_amount_cent
        $wallControlType = (int) ($itemData['wall_control_type'] ?? 0);
        $wallControlQuantity = (int) ($itemData['wall_control_quantity'] ?? 0);
        $wallControlPriceCent = ($wallControlType > 0 && $wallControlQuantity > 0)
            ? $this->getAccessorySurchargeCent('wall_control', $wallControlType)
            : 0;
        $wallControlAmountCent = $wallControlPriceCent * $wallControlQuantity;

        return [
            'width_cm'              => $widthCm,
            'height_cm'             => $heightCm,
            'width_m'               => $widthM,
            'height_m'              => $heightM,
            // 轨道长度（米，2位小数，deploy lj_order_item NOT NULL 列）
            'track_horizontal_length_m' => $horizontalLengthM,
            'track_vertical_length_m'   => $verticalLengthM,
            'area_m2'               => $areaM2,
            // 各项费用（分，整数字符串）
            'horizontal_track_cent' => $horizontalTrackCent,
            'vertical_track_cent'   => $verticalTrackCent,
            'track_cent'            => bcadd($horizontalTrackCent, $verticalTrackCent, 0),
            'fabric_cent'           => $fabricCent,
            'accessory_cent'        => $accessoryCent,
            'kit_cent'              => $kitCent,
            'nonstandard_cent'      => $nonstandardCent,
            'item_total_cent'       => $itemTotalCent,
            // 状态
            'is_nonstandard'        => $isNonstandard,
            'nonstandard_hint'      => $isNonstandard ? $this->buildNonstandardHint($widthCm, $heightCm) : null,
            // 选装子项明细（分）
            'power_surcharge_cent'  => (string) $this->getPowerSurchargeCent((int) ($itemData['power_type'] ?? 1)),
            'remote_surcharge_cent' => (string) $this->getRemoteSurchargeCent((int) ($itemData['remote_type'] ?? 1)),
            'wall_control_cent'     => (string) $wallControlAmountCent,
            'wall_control_price_cent'  => (string) $wallControlPriceCent,
            'wall_control_amount_cent' => (string) $wallControlAmountCent,
            // 套件主数据快照（deploy lj_order_item.kit_id / kit_price_cent）
            'kit_id'         => (int) $kit['id'],
            'kit_sku'        => (string) $kit['kit_sku'],
            'kit_price_cent' => (int) $kit['kit_price_cent'],
        ];
    }

    /**
     * 预览订单价格（供前端调用）
     *
     * 计算整张订单所有明细的价格汇总，返回各项费用小计和订单总额。
     * 所有价格从后台实时读取，不信任前端传入金额（规范 8.1）。
     *
     * @param int $storeId 门店ID
     * @param array $items 明细数据数组，每个元素包含 calculateItemAmount 所需字段
     * @return array 订单级价格汇总（分）
     */
    public function previewPrice(int $storeId, array $items): array
    {
        $trackTotalCent     = '0';
        $fabricTotalCent    = '0';
        $accessoryTotalCent = '0';
        $kitTotalCent       = '0';
        $nonstandardTotalCent = '0';
        $areaTotalM2        = '0';
        $itemResults        = [];
        $grandTotalCent     = '0';

        foreach ($items as $index => $itemData) {
            $result = $this->calculateItemAmount($storeId, $itemData);

            $trackTotalCent       = bcadd($trackTotalCent, $result['track_cent'], 0);
            $fabricTotalCent      = bcadd($fabricTotalCent, $result['fabric_cent'], 0);
            $accessoryTotalCent   = bcadd($accessoryTotalCent, $result['accessory_cent'], 0);
            $kitTotalCent         = bcadd($kitTotalCent, $result['kit_cent'], 0);
            $nonstandardTotalCent = bcadd($nonstandardTotalCent, $result['nonstandard_cent'], 0);
            $areaTotalM2          = bcadd($areaTotalM2, $result['area_m2'], self::AREA_SCALE);
            $grandTotalCent       = bcadd($grandTotalCent, $result['item_total_cent'], 0);

            $itemResults[] = $result;
        }

        return [
            'item_count'              => count($items),
            'track_total_cent'        => $trackTotalCent,
            'fabric_total_cent'       => $fabricTotalCent,
            'fabric_area_total_m2'    => $areaTotalM2,
            'accessory_total_cent'    => $accessoryTotalCent,
            'kit_total_cent'          => $kitTotalCent,
            'nonstandard_total_cent'  => $nonstandardTotalCent,
            'subtotal_cent'           => $grandTotalCent,
            'discount_cent'           => '0',
            'payable_cent'            => $grandTotalCent,
            'items'                   => $itemResults,
        ];
    }

    /**
     * 计算横轨费用（分）
     *
     * 公式：横轨费用(分) = bcmul(width_m, track_unit_price_cent, 0)
     * 价格从后台 track 表读取，禁止硬编码（规范 8.2）
     *
     * @param string $widthM 宽度（米，bcmath 字符串）
     * @param string $trackColor 轨道颜色
     * @return string 费用（分，整数字符串）
     * @throws ValidateException
     */
    private function calculateHorizontalTrackCent(string $widthM, string $trackColor): string
    {
        // 批次2c：deploy lj_track.track_type 为 TINYINT（1横轨 2竖轨），
        // 单价列名 price_per_meter_cent（非 unit_price_cent）
        $track = Db::name('track')
            ->where('color', $trackColor)
            ->where('track_type', TrackType::HORIZONTAL->value)
            ->where('enabled', 1)
            ->find();

        if (!$track) {
            throw new ValidateException("轨道配置不存在或已停用(颜色:{$trackColor}, 类型:横轨)");
        }

        // 单价以分存储（BIGINT），规范 5.1/7.2
        $pricePerMeterCent = (string) $track['price_per_meter_cent'];

        // 横轨费用 = 宽度(米) × 单价(分/米)，四舍五入到分（ROUND_HALF_UP，规范 7.2）
        return (string) Money::roundHalfUpCent($widthM, $pricePerMeterCent);
    }

    /**
     * 计算竖轨费用（分）
     *
     * 公式：竖轨费用(分) = bcmul(bcmul(height_m, 2, 4), rail_unit_price_cent, 0)
     * 每副窗帘有两条竖轨，所以高度×2
     *
     * @param string $heightM 高度（米，bcmath 字符串）
     * @param string $trackColor 轨道颜色
     * @return string 费用（分，整数字符串）
     * @throws ValidateException
     */
    private function calculateVerticalTrackCent(string $heightM, string $trackColor): string
    {
        $track = Db::name('track')
            ->where('color', $trackColor)
            ->where('track_type', TrackType::VERTICAL->value)
            ->where('enabled', 1)
            ->find();

        if (!$track) {
            throw new ValidateException("轨道配置不存在或已停用(颜色:{$trackColor}, 类型:竖轨)");
        }

        $pricePerMeterCent = (string) $track['price_per_meter_cent'];

        // 竖轨总长度 = 高度 × 2（两条竖轨）
        $totalLengthM = bcmul($heightM, '2', self::AREA_SCALE);

        // 竖轨费用 = 总长度 × 单价（ROUND_HALF_UP 到分，规范 7.2）
        return (string) Money::roundHalfUpCent($totalLengthM, $pricePerMeterCent);
    }

    /**
     * 计算面料费用（分）
     *
     * 公式：面料费用(分) = bcmul(area_m2, fabric_price_per_m2_cent, 0)
     * 面料价格从后台 fabric 表按世尚面料编号读取（规范 8.2 & PRD 4.3）
     *
     * @param string $areaM2 面积（平方米，bcmath 字符串，4位小数）
     * @param string $fabricNo 世尚面料编号
     * @return string 费用（分，整数字符串）
     * @throws ValidateException
     */
    private function calculateFabricCent(string $areaM2, string $fabricNo): string
    {
        if (empty($fabricNo)) {
            throw new ValidateException('面料编号不能为空');
        }

        $fabric = Db::name('fabric')
            ->where('fabric_no', $fabricNo)
            ->where('listing_status', 1)
            ->where('orderable', 1)
            ->find();

        if (!$fabric) {
            throw new ValidateException('面料不存在或已下架');
        }

        // 单价以分存储（BIGINT）
        $pricePerSqmCent = (string) $fabric['price_per_sqm_cent'];

        // 最小计费面积（如有配置）
        $minBillingArea = $fabric['min_billing_area'] ?? null;
        if ($minBillingArea !== null && bccomp($areaM2, (string) $minBillingArea, self::AREA_SCALE) < 0) {
            $areaM2 = (string) $minBillingArea;
        }

        // 损耗系数（如有配置，默认1.0即无加成）
        $lossCoefficient = $fabric['loss_coefficient'] ?? '1.0';
        $effectiveArea = bcmul($areaM2, (string) $lossCoefficient, self::AREA_SCALE);

        // 面料费用 = 有效面积 × 单价(分/㎡)（ROUND_HALF_UP 到分，规范 7.2）
        return (string) Money::roundHalfUpCent($effectiveArea, $pricePerSqmCent);
    }

    /**
     * 计算选装配件费用（分）
     *
     * 公式：选装费用(分) = Σ(选装项单价 × 数量)
     * 各选装项价格从后台 accessory 表读取
     *
     * @param int $powerType 电源类型 1标准 2锂电池
     * @param int $remoteType 遥控器类型 1标准 2Pro
     * @param int $wallControlType 墙面控制类型 0不配 1标准 2Pro
     * @param int $wallControlQuantity 墙面控制数量
     * @return string 费用（分，整数字符串）
     */
    private function calculateAccessoryCent(
        int $powerType,
        int $remoteType,
        int $wallControlType,
        int $wallControlQuantity,
    ): string {
        $totalCent = '0';

        // 电源加价
        $totalCent = bcadd($totalCent, (string) $this->getPowerSurchargeCent($powerType), 0);

        // 遥控器加价
        $totalCent = bcadd($totalCent, (string) $this->getRemoteSurchargeCent($remoteType), 0);

        // 墙面控制费用
        $totalCent = bcadd($totalCent, (string) $this->getWallControlCent($wallControlType, $wallControlQuantity), 0);

        return $totalCent;
    }

    /**
     * 查询门店客户等级对应的生效中套件主数据（PRD 4.4）
     *
     * 批次2c：套件计价落地。按 lj_store.customer_level 从 deploy lj_kit
     * 查询 status=1 且有效期覆盖当日的等级套件价；无匹配时抛业务异常，
     * 禁止静默返回 0 元。
     *
     * @param int $storeId 门店ID
     * @return array lj_kit 记录（含 id/kit_sku/kit_price_cent）
     * @throws ValidateException
     */
    public function resolveLevelKit(int $storeId): array
    {
        $store = Store::find($storeId);
        if (!$store) {
            throw new ValidateException('门店不存在，无法确定套件等级价');
        }

        $today = date('Y-m-d');
        $kit = Db::name('kit')
            ->where('customer_level', (int) $store->customer_level)
            ->where('status', 1)
            ->where(function ($query) use ($today) {
                $query->where(function ($q) use ($today) {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today);
                })->where(function ($q) use ($today) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
                });
            })
            ->order('id', 'desc')
            ->find();

        if (!$kit) {
            throw new ValidateException('套件价未配置：该客户等级无生效中的等级套件价，请先在后台配置套件主数据');
        }

        return $kit;
    }

    /**
     * 获取门店等级对应套件价格（分）
     *
     * 价格从后台 lj_kit 表读取，禁止硬编码（规范 8.2）。
     *
     * @param int $storeId 门店ID
     * @return int 套件单价（分）
     * @throws ValidateException
     */
    public function getKitPriceCent(int $storeId): int
    {
        return (int) $this->resolveLevelKit($storeId)['kit_price_cent'];
    }

    /**
     * 获取电源加价（分）
     *
     * @param int $powerType 1标准 2锂电池
     * @return int 加价（分）
     */
    private function getPowerSurchargeCent(int $powerType): int
    {
        return $this->getAccessorySurchargeCent('power', $powerType);
    }

    /**
     * 获取遥控器加价（分）
     *
     * @param int $remoteType 1标准 2Pro
     * @return int 加价（分）
     */
    private function getRemoteSurchargeCent(int $remoteType): int
    {
        return $this->getAccessorySurchargeCent('remote', $remoteType);
    }

    /**
     * 获取选装配件加价（分）
     *
     * @param string $configGroup 配置组：power/remote/wall_control
     * @param int $optionType 选项类型
     * @return int 加价（分），未配置时返回 0
     */
    private function getAccessorySurchargeCent(string $configGroup, int $optionType): int
    {
        $accessory = Db::name('accessory')
            ->where('config_group', $configGroup)
            ->where('option_type', $optionType)
            ->where('enabled', 1)
            ->find();

        return $accessory ? (int) $accessory['surcharge_cent'] : 0;
    }

    /**
     * 获取墙面控制费用（分）
     *
     * 公式：墙面控制费用 = 单价(分) × 数量
     *
     * @param int $wallControlType 0不配 1标准 2Pro
     * @param int $quantity 数量
     * @return int 费用（分）
     */
    private function getWallControlCent(int $wallControlType, int $quantity): int
    {
        if ($wallControlType === 0 || $quantity <= 0) {
            return 0;
        }

        $accessory = Db::name('accessory')
            ->where('config_group', 'wall_control')
            ->where('option_type', $wallControlType)
            ->where('enabled', 1)
            ->find();

        if (!$accessory) {
            return 0;
        }

        return (int) $accessory['surcharge_cent'] * $quantity;
    }

    /**
     * 校验尺寸是否超出绝对硬限（批次5：两级判断）
     *
     * 第一级（标准推荐范围，宽 90~350 / 高 50~600，PRD 4.1.1）：
     *   超出【不拦截】，由 calculateItemAmount 标记 is_nonstandard=true
     *   并返回 nonstandard_hint，供前端提示“联系总部非标评估”；
     * 第二级（绝对硬限）：超出直接拒绝下单。
     *
     * @param string $widthCm 宽度（厘米）
     * @param string $heightCm 高度（厘米）
     * @throws ValidateException
     */
    private function validateDimensionRange(string $widthCm, string $heightCm): void
    {
        if (bccomp($widthCm, (string) self::WIDTH_HARD_MIN_CM, 1) < 0 || bccomp($widthCm, (string) self::WIDTH_HARD_MAX_CM, 1) > 0) {
            throw new ValidateException(sprintf('宽度超出允许范围(%.1f-%.1f cm)，无法下单', self::WIDTH_HARD_MIN_CM, self::WIDTH_HARD_MAX_CM));
        }
        if (bccomp($heightCm, (string) self::HEIGHT_HARD_MIN_CM, 1) < 0 || bccomp($heightCm, (string) self::HEIGHT_HARD_MAX_CM, 1) > 0) {
            throw new ValidateException(sprintf('高度超出允许范围(%.1f-%.1f cm)，无法下单', self::HEIGHT_HARD_MIN_CM, self::HEIGHT_HARD_MAX_CM));
        }
    }

    /**
     * 生成非标提示
     *
     * 仅用于构建可读提示文本，不参与任何金额计算。
     * 批次5：尾部追加“联系总部非标评估”引导文案，由 OrderService
     * 拼接 [非标] 前缀后落库 lj_order_item.remark（批次2c约定）。
     *
     * @param string $widthCm 宽度(cm)
     * @param string $heightCm 高度(cm)
     * @return string
     */
    private function buildNonstandardHint(string $widthCm, string $heightCm): string
    {
        $hints = [];
        if (bccomp($widthCm, (string) self::WIDTH_MIN_CM, 1) < 0 || bccomp($widthCm, (string) self::WIDTH_MAX_CM, 1) > 0) {
            $hints[] = "宽度{$widthCm}cm超出标准推荐范围(" . self::WIDTH_MIN_CM . '-' . self::WIDTH_MAX_CM . "cm)";
        }
        if (bccomp($heightCm, (string) self::HEIGHT_MIN_CM, 1) < 0 || bccomp($heightCm, (string) self::HEIGHT_MAX_CM, 1) > 0) {
            $hints[] = "高度{$heightCm}cm超出标准推荐范围(" . self::HEIGHT_MIN_CM . '-' . self::HEIGHT_MAX_CM . "cm)";
        }
        $hints[] = '请联系总部进行非标评估';
        return implode('；', $hints);
    }
}
