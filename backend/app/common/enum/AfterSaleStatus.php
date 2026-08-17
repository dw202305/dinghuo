<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 售后状态枚举
 * 对应 lj_after_sale 表 status 字段
 */
enum AfterSaleStatus: int
{
    /** 待处理 */
    case PENDING = 1;

    /** 处理中 */
    case PROCESSING = 2;

    /** 已完成 */
    case COMPLETED = 3;

    /** 已关闭 */
    case CLOSED = 4;

    /**
     * 获取状态标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING    => '待处理',
            self::PROCESSING => '处理中',
            self::COMPLETED  => '已完成',
            self::CLOSED     => '已关闭',
        };
    }

    /**
     * 是否可处理
     * @return bool
     */
    public function canProcess(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING]);
    }

    /**
     * 是否已终态
     * @return bool
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CLOSED]);
    }

    /**
     * 是否可关闭
     * @return bool
     */
    public function canClose(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING]);
    }

    /**
     * 获取所有状态选项
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
