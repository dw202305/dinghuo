<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 支付渠道枚举（string backed）
 *
 * 对应 deploy lj_payment.payment_channel 字段注释：
 * '支付渠道：balance/wechat/alipay'（批次2a逐项核对 deploy/mysql/init.sql）。
 *
 * @see deploy/mysql/init.sql lj_payment
 */
enum PaymentChannel: string
{
    /** 余额支付 */
    case BALANCE = 'balance';

    /** 微信支付 */
    case WECHAT = 'wechat';

    /** 支付宝 */
    case ALIPAY = 'alipay';

    /**
     * 获取渠道标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::BALANCE => '余额支付',
            self::WECHAT  => '微信支付',
            self::ALIPAY  => '支付宝',
        };
    }

    /**
     * 是否第三方支付渠道（非余额）
     * @return bool
     */
    public function isThirdParty(): bool
    {
        return in_array($this, [self::WECHAT, self::ALIPAY], true);
    }

    /**
     * 获取所有渠道选项
     * @return array<string, string>
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
