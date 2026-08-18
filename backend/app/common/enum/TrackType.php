<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 轨道类型枚举
 *
 * 对应 deploy/mysql/init.sql lj_track.track_type 字段注释：
 * '类型：1横轨 2竖轨'（批次2c 逐表对齐，替换旧代码中的
 * 'horizontal'/'vertical' 字符串魔法值）。
 *
 * @see deploy/mysql/init.sql 2.16 轨道表 lj_track
 */
enum TrackType: int
{
    /** 横轨 */
    case HORIZONTAL = 1;

    /** 竖轨 */
    case VERTICAL = 2;

    /**
     * 获取类型标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::HORIZONTAL => '横轨',
            self::VERTICAL   => '竖轨',
        };
    }

    /**
     * 获取所有类型选项
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
