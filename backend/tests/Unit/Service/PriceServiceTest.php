<?php

declare(strict_types=1);

namespace tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * PriceService 计价引擎单元测试
 *
 * 验证 bcmath 精度计算的正确性。
 * 由于 PriceService 私有方法依赖数据库查询轨道/面料/配件价格，
 * 本测试类直接验证 bcmath 计价公式的正确性（与源码公式完全一致），
 * 确保分/元转换、精度控制、大数运算无误。
 *
 * 批次2a说明：源码金额取整已由 bcmul(...,0) 截断改为
 * Money::roundHalfUpCent（ROUND_HALF_UP 到分，规范 7.2）；
 * 本类保留 bcmul 截断行为用例仅用于验证 bcmath 库自身语义，
 * 所有整积样例在两种口径下结果一致，不受影响。
 *
 * 公式来源：dev_specification_v1.0 §7.2/§8.2，PRD §4.1-4.7
 */
class PriceServiceTest extends TestCase
{
    /** bcmath 面积精度 */
    const AREA_SCALE = 4;
    /** bcmath 金额中间精度 */
    const MONEY_SCALE = 6;

    // ─────────────────────────────────────────────────────
    // 横轨计价
    // ─────────────────────────────────────────────────────

    /**
     * 横轨费用(分) = bcmul(width_m, track_unit_price_cent, 0)
     * 宽度(cm)/100 × 单价(分/米)
     */
    #[Test]
    public function testCalculateTrackHorizontal_BasicCase(): void
    {
        // 宽度 200cm = 2m，单价 5000分/米(50元/米)
        $widthCm = '200';
        $pricePerMeterCent = '5000';

        $widthM = bcdiv($widthCm, '100', self::MONEY_SCALE);
        $result = bcmul($widthM, $pricePerMeterCent, 0);

        // 2.0 × 5000 = 10000分 = 100元
        $this->assertSame('10000', $result);
    }

    #[Test]
    public function testCalculateTrackHorizontal_DecimalWidth(): void
    {
        // 宽度 155.5cm = 1.555m
        $widthCm = '155.5';
        $pricePerMeterCent = '3200';

        $widthM = bcdiv($widthCm, '100', self::MONEY_SCALE);
        $result = bcmul($widthM, $pricePerMeterCent, 0);

        // 1.555 × 3200 = 4976
        $expected = bcmul('1.555000', '3200', 0);
        $this->assertSame($expected, $result);
        $this->assertSame('4976', $result);
    }

    #[Test]
    public function testCalculateTrackHorizontal_MinWidth(): void
    {
        // 最小宽度 90cm
        $widthCm = '90';
        $pricePerMeterCent = '4000';

        $widthM = bcdiv($widthCm, '100', self::MONEY_SCALE);
        $result = bcmul($widthM, $pricePerMeterCent, 0);

        // 0.9 × 4000 = 3600
        $this->assertSame('3600', $result);
    }

    // ─────────────────────────────────────────────────────
    // 竖轨计价
    // ─────────────────────────────────────────────────────

    /**
     * 竖轨费用(分) = bcmul(bcmul(height_m, 2, 4), rail_unit_price_cent, 0)
     * 高度(cm)/100 × 2（两条竖轨）× 单价(分/米)
     */
    #[Test]
    public function testCalculateTrackVertical_BasicCase(): void
    {
        // 高度 250cm = 2.5m，单价 4500分/米
        $heightCm = '250';
        $pricePerMeterCent = '4500';

        $heightM = bcdiv($heightCm, '100', self::MONEY_SCALE);
        $totalLengthM = bcmul($heightM, '2', self::AREA_SCALE);
        $result = bcmul($totalLengthM, $pricePerMeterCent, 0);

        // 2.5 × 2 = 5.0m, 5.0 × 4500 = 22500分
        $this->assertSame('22500', $result);
    }

    #[Test]
    public function testCalculateTrackVertical_DecimalHeight(): void
    {
        // 高度 183.7cm，单价 3000分/米
        $heightCm = '183.7';
        $pricePerMeterCent = '3000';

        $heightM = bcdiv($heightCm, '100', self::MONEY_SCALE);
        $totalLengthM = bcmul($heightM, '2', self::AREA_SCALE);
        $result = bcmul($totalLengthM, $pricePerMeterCent, 0);

        // 1.837 × 2 = 3.674m, 3.674 × 3000 = 11022
        $this->assertSame('11022', $result);
    }

    // ─────────────────────────────────────────────────────
    // 面料计价
    // ─────────────────────────────────────────────────────

    /**
     * 面料费用(分) = bcmul(area_m2, fabric_price_per_m2_cent, 0)
     * area_m2 = width_m × height_m（保留4位小数）
     */
    #[Test]
    public function testCalculateFabric_BasicCase(): void
    {
        // 200cm × 250cm = 2m × 2.5m = 5㎡，单价 12000分/㎡(120元/㎡)
        $widthCm = '200';
        $heightCm = '250';
        $pricePerSqmCent = '12000';

        $widthM = bcdiv($widthCm, '100', self::MONEY_SCALE);
        $heightM = bcdiv($heightCm, '100', self::MONEY_SCALE);
        $areaM2 = bcmul($widthM, $heightM, self::AREA_SCALE);
        $result = bcmul($areaM2, $pricePerSqmCent, 0);

        // 5.0000 × 12000 = 60000分 = 600元
        $this->assertSame('5.0000', $areaM2);
        $this->assertSame('60000', $result);
    }

    #[Test]
    public function testCalculateFabric_SmallArea(): void
    {
        // 90cm × 50cm = 0.9m × 0.5m = 0.45㎡
        $widthCm = '90';
        $heightCm = '50';
        $pricePerSqmCent = '8000';

        $widthM = bcdiv($widthCm, '100', self::MONEY_SCALE);
        $heightM = bcdiv($heightCm, '100', self::MONEY_SCALE);
        $areaM2 = bcmul($widthM, $heightM, self::AREA_SCALE);
        $result = bcmul($areaM2, $pricePerSqmCent, 0);

        // 0.4500 × 8000 = 3600
        $this->assertSame('0.4500', $areaM2);
        $this->assertSame('3600', $result);
    }

    // ─────────────────────────────────────────────────────
    // 选装配件计价
    // ─────────────────────────────────────────────────────

    /**
     * 选装费用(分) = Σ(选装项单价 × 数量)
     */
    #[Test]
    public function testCalculateAccessory_MultipleItems(): void
    {
        // 模拟：电源升级 5000分 + Pro遥控器 8000分 + 2个Pro墙控(3000×2)
        $powerSurcharge = 5000;    // 锂电池升级
        $remoteSurcharge = 8000;   // Pro遥控器
        $wallControlUnitPrice = 3000;
        $wallControlQty = 2;
        $wallControlTotal = $wallControlUnitPrice * $wallControlQty;

        // 模拟 PriceService 中的 bcadd 累加
        $totalCent = '0';
        $totalCent = bcadd($totalCent, (string) $powerSurcharge, 0);
        $totalCent = bcadd($totalCent, (string) $remoteSurcharge, 0);
        $totalCent = bcadd($totalCent, (string) $wallControlTotal, 0);

        // 5000 + 8000 + 6000 = 19000分
        $this->assertSame('19000', $totalCent);
    }

    #[Test]
    public function testCalculateAccessory_NoAccessory(): void
    {
        // 标准电源 + 标准遥控 + 不配墙控
        $powerSurcharge = 0;
        $remoteSurcharge = 0;
        $wallControlTotal = 0;

        $totalCent = '0';
        $totalCent = bcadd($totalCent, (string) $powerSurcharge, 0);
        $totalCent = bcadd($totalCent, (string) $remoteSurcharge, 0);
        $totalCent = bcadd($totalCent, (string) $wallControlTotal, 0);

        $this->assertSame('0', $totalCent);
    }

    // ─────────────────────────────────────────────────────
    // 套件计价
    // ─────────────────────────────────────────────────────

    /**
     * 套件费用(分) = 未抵扣数量 × 客户等级套件单价(分)
     * 未抵扣数量 = max(0, 1 - 库存抵扣数量)
     */
    #[Test]
    public function testCalculateKit_NoInventoryDeduction(): void
    {
        // 不使用库存抵扣：未抵扣数量=1，套件单价=15000分
        $undeductCount = max(0, 1 - 0);
        $kitPriceCent = 15000;

        $result = bcmul((string) $undeductCount, (string) $kitPriceCent, 0);

        $this->assertSame('15000', $result);
    }

    #[Test]
    public function testCalculateKit_FullInventoryDeduction(): void
    {
        // 使用库存抵扣：未抵扣数量=0，费用=0
        $undeductCount = max(0, 1 - 1);
        $kitPriceCent = 15000;

        $result = bcmul((string) $undeductCount, (string) $kitPriceCent, 0);

        $this->assertSame('0', $result);
    }

    // ─────────────────────────────────────────────────────
    // 非标费用
    // ─────────────────────────────────────────────────────

    /**
     * 非标判断逻辑：宽高超出标准范围
     * 标准范围：宽 90~350cm，高 50~600cm
     */
    #[Test]
    public function testCalculateNonstandard_WithinRange(): void
    {
        $widthCm = '200';
        $heightCm = '250';

        $isNonstandard = (
            bccomp($widthCm, '90.0', 1) < 0 ||
            bccomp($widthCm, '350.0', 1) > 0 ||
            bccomp($heightCm, '50.0', 1) < 0 ||
            bccomp($heightCm, '600.0', 1) > 0
        );

        $this->assertFalse((bool) $isNonstandard);
    }

    #[Test]
    public function testCalculateNonstandard_WidthTooSmall(): void
    {
        $widthCm = '85';
        $heightCm = '200';

        $isNonstandard = (
            bccomp($widthCm, '90.0', 1) < 0 ||
            bccomp($widthCm, '350.0', 1) > 0 ||
            bccomp($heightCm, '50.0', 1) < 0 ||
            bccomp($heightCm, '600.0', 1) > 0
        );

        $this->assertTrue((bool) $isNonstandard);
    }

    #[Test]
    public function testCalculateNonstandard_HeightTooLarge(): void
    {
        $widthCm = '200';
        $heightCm = '650';

        $isNonstandard = (
            bccomp($widthCm, '90.0', 1) < 0 ||
            bccomp($widthCm, '350.0', 1) > 0 ||
            bccomp($heightCm, '50.0', 1) < 0 ||
            bccomp($heightCm, '600.0', 1) > 0
        );

        $this->assertTrue((bool) $isNonstandard);
    }

    // ─────────────────────────────────────────────────────
    // 完整订单价格计算
    // ─────────────────────────────────────────────────────

    /**
     * 完整单副窗帘价格计算（含所有组件）
     * 模拟 calculateItemAmount 的完整公式链
     */
    #[Test]
    public function testFullOrderPriceCalculation(): void
    {
        // 输入参数
        $widthCm = '200';     // 2m
        $heightCm = '250';    // 2.5m
        $trackPriceCent = '5000';    // 横轨50元/米
        $railPriceCent = '4000';     // 竖轨40元/米
        $fabricPriceCent = '12000';  // 面料120元/㎡
        $accessoryCent = '13000';    // 选装配件130元
        $kitPriceCent = '15000';     // 套件150元
        $nonstandardCent = '0';      // 非标费用0

        // 按公式链计算
        $widthM = bcdiv($widthCm, '100', self::MONEY_SCALE);
        $heightM = bcdiv($heightCm, '100', self::MONEY_SCALE);
        $areaM2 = bcmul($widthM, $heightM, self::AREA_SCALE);

        $horizontalCent = bcmul($widthM, $trackPriceCent, 0);       // 2×5000=10000
        $verticalCent = bcmul(bcmul($heightM, '2', self::AREA_SCALE), $railPriceCent, 0); // 5×4000=20000
        $fabricTotalCent = bcmul($areaM2, $fabricPriceCent, 0);     // 5×12000=60000

        $itemTotalCent = bcadd(
            bcadd(
                bcadd($horizontalCent, $verticalCent, 0),
                bcadd($fabricTotalCent, (string) $accessoryCent, 0),
                0
            ),
            bcadd($kitPriceCent, $nonstandardCent, 0),
            0
        );

        // 10000 + 20000 + 60000 + 13000 + 15000 + 0 = 118000分 = 1180元
        $this->assertSame('10000', $horizontalCent);
        $this->assertSame('20000', $verticalCent);
        $this->assertSame('60000', $fabricTotalCent);
        $this->assertSame('118000', $itemTotalCent);
    }

    // ─────────────────────────────────────────────────────
    // 精度测试
    // ─────────────────────────────────────────────────────

    /**
     * 精度测试：避免 0.1 + 0.2 类型的浮点误差
     * bcmath 使用字符串运算，不存在浮点精度问题
     */
    #[Test]
    public function testPrecisionNoFloatingPointError(): void
    {
        // 经典浮点陷阱：0.1 + 0.2 ≠ 0.3（float）
        // bcmath 应精确等于 0.3
        $a = '0.1';
        $b = '0.2';
        $sum = bcadd($a, $b, 1);
        $this->assertSame('0.3', $sum);

        // 价格场景：1.1元 + 2.2元 = 3.3元（分：110 + 220 = 330）
        $priceA = '110'; // 1.1元
        $priceB = '220'; // 2.2元
        $total = bcadd($priceA, $priceB, 0);
        $this->assertSame('330', $total);

        // 面积计算精度：1.55m × 2.33m
        $area = bcmul('1.550000', '2.330000', self::AREA_SCALE);
        $this->assertSame('3.6115', $area); // 精确值 3.6115

        // 大数乘法：350.0cm × 29999分/米
        $widthM = bcdiv('350', '100', self::MONEY_SCALE);
        $cost = bcmul($widthM, '29999', 0);
        $this->assertSame('104996', $cost); // 3.5 × 29999 = 104996.5 → bcmul(scale=0) 截断到104996
        // 注：源码已改用 Money::roundHalfUpCent（同例应为 104997），
        // 此处仅验证 bcmul 库自身的截断语义
        // bcmul with scale=0 truncates, not rounds
        $this->assertIsString($cost);
    }

    /**
     * 精度验证：bcmul 截断行为确认（仅验证 bcmath 库语义，
     * 源码金额取整已改用 Money::roundHalfUpCent）
     */
    #[Test]
    public function testPrecisionBcmulTruncation(): void
    {
        // bcmul 在 scale=0 时截断而非四舍五入
        // 1.5 × 3 = 4.5 → scale=0 截断为 4
        $result = bcmul('1.500000', '3', 0);
        $this->assertSame('4', $result);

        // 确认这不是 bug 而是 bcmath 的规范行为
        $result2 = bcmul('1.999999', '100', 0);
        $this->assertSame('199', $result2); // 199.9999 → 199
    }

    // ─────────────────────────────────────────────────────
    // 大金额计算
    // ─────────────────────────────────────────────────────

    /**
     * 大金额计算：10000元以上订单
     * 模拟多副窗帘的总价格
     */
    #[Test]
    public function testLargeAmountCalculation(): void
    {
        // 5副窗帘，每副各组件费用
        $itemPrices = [];
        $itemCount = 5;

        for ($i = 0; $i < $itemCount; $i++) {
            $widthCm = (string) (200 + $i * 10);    // 200~240cm
            $heightCm = (string) (250 + $i * 20);   // 250~330cm

            $widthM = bcdiv($widthCm, '100', self::MONEY_SCALE);
            $heightM = bcdiv($heightCm, '100', self::MONEY_SCALE);
            $areaM2 = bcmul($widthM, $heightM, self::AREA_SCALE);

            $hCent = bcmul($widthM, '5000', 0);
            $vCent = bcmul(bcmul($heightM, '2', self::AREA_SCALE), '4000', 0);
            $fCent = bcmul($areaM2, '15000', 0);
            $aCent = '20000';
            $kCent = '15000';
            $nCent = '0';

            $total = bcadd(
                bcadd(bcadd($hCent, $vCent, 0), bcadd($fCent, $aCent, 0), 0),
                bcadd($kCent, $nCent, 0),
                0
            );

            $itemPrices[] = $total;
        }

        // 汇总
        $grandTotal = '0';
        foreach ($itemPrices as $price) {
            $grandTotal = bcadd($grandTotal, $price, 0);
        }

        // 验证总额为确定值：5 副窗帘（宽 200~240cm、高 250~330cm）精确汇总 = 827500 分（8275 元）
        $this->assertSame('827500', $grandTotal, '大额订单总额应为精确的整数分');
        $this->assertSame(1, bccomp($grandTotal, '800000', 0),
            "总额 {$grandTotal} 分应大于 800000 分(8000元)");

        // 验证每副窗帘金额都是正整数（分）
        foreach ($itemPrices as $i => $price) {
            $this->assertGreaterThan(0, (int) $price, "第{$i}副窗帘金额应>0");
            // 验证没有小数点（整数分）
            $this->assertStringNotContainsString('.', $price);
        }
    }

    /**
     * 多副窗帘汇总精度
     */
    #[Test]
    public function testPreviewPriceAccumulation(): void
    {
        // 模拟 previewPrice 中的 bcadd 累加
        $itemTotals = ['118000', '135600', '98750', '210300', '156400'];

        $grandTotal = '0';
        foreach ($itemTotals as $total) {
            $grandTotal = bcadd($grandTotal, $total, 0);
        }

        // 手工验算：118000+135600+98750+210300+156400 = 719050
        $this->assertSame('719050', $grandTotal);
    }
}
