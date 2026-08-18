<?php

declare(strict_types=1);

namespace app\common\support;

use InvalidArgumentException;

/**
 * 金额工具类（规范 7.2）
 *
 * 金额统一以"分"为最小单位（整数），禁止 float 参与结算：
 * - 乘法走 bcmath，中间结果保留 6 位小数；
 * - 最终按普通四舍五入 ROUND_HALF_UP 取整到分；
 * - 全程字符串运算，禁用 PHP float 运算路径。
 *
 * @see docs/dev_specification_v1.md 7.2 金额和尺寸
 */
final class Money
{
    /** 金额中间计算精度（bcmath scale，多保留位数防截断误差） */
    public const SCALE = 6;

    /**
     * a × b 高精度相乘后按 ROUND_HALF_UP 取整到分
     *
     * 典型场景：长度(米) × 单价(分/米)、面积(㎡) × 单价(分/㎡)。
     * 内部使用 bcmul(scale=6) + 字符串半进位取整，全程无 float 运算；
     * 若入参为 float（仅限兼容旧调用），先转定点字符串再进入 bcmath 路径。
     *
     * @param string|float $a 乘数a（推荐字符串）
     * @param string|float $b 乘数b（推荐字符串）
     * @return int 金额（分）
     */
    public static function roundHalfUpCent(string|float $a, string|float $b): int
    {
        $product = bcmul(self::toNumericString($a), self::toNumericString($b), self::SCALE);

        return self::halfUpToInt($product);
    }

    /**
     * 金额乘法辅助（数量 × 单价(分) 等），ROUND_HALF_UP 到分
     *
     * @param string|int|float $a 乘数a
     * @param string|int|float $b 乘数b
     * @return int 金额（分）
     */
    public static function mulCent(string|int|float $a, string|int|float $b): int
    {
        return self::roundHalfUpCent(self::toNumericString($a), self::toNumericString($b));
    }

    /**
     * 金额求和（整数分相加，无精度损失）
     *
     * @param int|string ...$values 若干金额（分）
     * @return int 合计（分）
     */
    public static function sum(int|string ...$values): int
    {
        $total = '0';
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '' || !is_numeric($value)) {
                throw new InvalidArgumentException("非法金额参数：{$value}");
            }
            $total = bcadd($total, $value, 0);
        }

        return (int) $total;
    }

    /**
     * ROUND_HALF_UP 取整到整数（字符串实现，支持负数）
     *
     * @param string $value bcmath 数值字符串
     * @return int
     */
    private static function halfUpToInt(string $value): int
    {
        $isNegative = bccomp($value, '0', self::SCALE) < 0;
        $abs = $isNegative ? bcmul($value, '-1', self::SCALE) : $value;

        // |x| + 0.5 后向零截断 == 对 |x| 做半进位（ROUND_HALF_UP）
        $rounded = bcadd($abs, '0.5', 0);

        return (int) ($isNegative ? '-' . $rounded : $rounded);
    }

    /**
     * 数值规范化为定点字符串（禁止 float 直接参与运算）
     *
     * @param string|int|float $value
     * @return string
     */
    private static function toNumericString(string|int|float $value): string
    {
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException('金额参数非法（INF/NAN）');
            }
            // float 仅为兼容入口：转定点字符串后立即进入 bcmath 路径
            return sprintf('%.8F', $value);
        }

        $str = trim((string) $value);
        if ($str === '' || !is_numeric($str)) {
            throw new InvalidArgumentException("非法金额参数：{$str}");
        }

        return $str;
    }
}
