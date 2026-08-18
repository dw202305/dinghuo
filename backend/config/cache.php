<?php
// +----------------------------------------------------------------------
// | 缓存配置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动
    'default' => env('cache.driver', 'redis'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制
            'serialize'  => [],
        ],
        'redis' => [
            // 驱动方式
            'type'       => 'Redis',
            // 服务器地址
            'host'       => env('redis.host', '127.0.0.1'),
            // 端口
            'port'       => env('redis.port', '6379'),
            // 密码（兜底空串：缺配置时快速失败，避免静默连错口令）
            'password'   => env('redis.password', ''),
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存前缀
            'prefix'     => env('cache.prefix', 'shishang:'),
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 数据库
            'select'     => 0,
            // 超时时间
            'timeout'    => 0,
            // 持久化
            'persistent' => false,
            // 序列化
            'serialize'  => [],
        ],
    ],
];
