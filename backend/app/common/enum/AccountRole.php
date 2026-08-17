<?php
declare(strict_types=1);

namespace app\common\enum;

/**
 * 门店端账号角色枚举
 * 门店端账号（lj_account）只使用 1-5，后台管理员使用独立的 lj_admin 表 + RBAC
 */
enum AccountRole: int
{
    case STORE_ADMIN    = 1; // 门店管理员
    case ORDER_STAFF    = 2; // 下单员
    case FINANCE        = 3; // 财务
    case INSTALLER      = 4; // 安装售后
    case READONLY       = 5; // 只读

    /**
     * 获取角色描述
     */
    public function label(): string
    {
        return match($this) {
            self::STORE_ADMIN => '门店管理员',
            self::ORDER_STAFF => '下单员',
            self::FINANCE     => '财务',
            self::INSTALLER   => '安装售后',
            self::READONLY    => '只读',
        };
    }

    /**
     * 获取所有选项（供下拉框使用）
     */
    public static function options(): array
    {
        return [
            ['value' => self::STORE_ADMIN->value, 'label' => '门店管理员'],
            ['value' => self::ORDER_STAFF->value, 'label' => '下单员'],
            ['value' => self::FINANCE->value,     'label' => '财务'],
            ['value' => self::INSTALLER->value,   'label' => '安装售后'],
            ['value' => self::READONLY->value,    'label' => '只读'],
        ];
    }
}
