<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 储值方式枚举
 *
 * 对应 deploy lj_recharge_order.recharge_method 字段注释：
 * '储值方式：1微信 2支付宝 3线下 4测试'（批次2a逐项核对 deploy/mysql/init.sql）。
 *
 * @see deploy/mysql/init.sql lj_recharge_order
 */
enum RechargeMethod: int
{
    /** 微信 */
    case WECHAT = 1;

    /** 支付宝 */
    case ALIPAY = 2;

    /** 线下 */
    case OFFLINE = 3;

    /** 测试 */
    case TEST = 4;

    /**
     * 获取方式标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::WECHAT  => '微信',
            self::ALIPAY  => '支付宝',
            self::OFFLINE => '线下',
            self::TEST    => '测试',
        };
    }

    /**
     * 是否第三方支付储值（以支付平台回调为准）
     * @return bool
     */
    public function isThirdParty(): bool
    {
        return in_array($this, [self::WECHAT, self::ALIPAY], true);
    }

    /**
     * 是否需要财务审核凭证（线下储值必须关联凭证并审核，PRD 4.9.3）
     * @return bool
     */
    public function requiresVoucherReview(): bool
    {
        return $this === self::OFFLINE;
    }

    /**
     * 获取所有方式选项
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
