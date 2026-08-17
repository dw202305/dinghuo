<?php
// +----------------------------------------------------------------------
// | 中间件配置
// |
// | 全局中间件执行顺序（按数组顺序依次执行）：
// | 1. CrossDomain       — 跨域支持
// | 2. RequestIdMiddleware — 请求唯一标识（规范 §14.1 / §18.1）
// | 3. IdempotentMiddleware — 幂等校验（规范 §14.5）
// +----------------------------------------------------------------------

return [
    // 全局中间件
    'global' => [
        // 1. 跨域请求支持
        \app\common\middleware\CrossDomain::class,
        // 2. 请求唯一标识（必须在业务中间件之前执行）
        \app\common\middleware\RequestIdMiddleware::class,
        // 3. 接口幂等校验（在跨域之后）
        \app\common\middleware\IdempotentMiddleware::class,
    ],

    // 路由中间件（按路由配置加载）
    'alias' => [
        'auth'       => \app\common\middleware\AuthMiddleware::class,
        'admin_auth' => \app\common\middleware\AdminAuthMiddleware::class,
    ],
];
