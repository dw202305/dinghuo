<?php
// +----------------------------------------------------------------------
// | 中间件配置（仅别名）
// |
// | 重要：ThinkPHP 8 中 config/middleware.php 只提供路由中间件别名（alias），
// | 'global' 键不会被框架加载。全局中间件请注册在 app/middleware.php
// | （think\Http::loadMiddleware() 加载 basePath 下的 middleware.php）。
// +----------------------------------------------------------------------

return [
    // 路由中间件别名（按路由配置加载）
    'alias' => [
        'auth'       => \app\common\middleware\AuthMiddleware::class,
        'admin_auth' => \app\common\middleware\AdminAuthMiddleware::class,
    ],
];
