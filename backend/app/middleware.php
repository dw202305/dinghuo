<?php
// +----------------------------------------------------------------------
// | 应用全局中间件定义（ThinkPHP 8 规范位置）
// |
// | 注意：think\Http::loadMiddleware() 只加载 basePath（app/）下的
// | middleware.php 作为全局中间件；config/middleware.php 仅提供 alias 别名，
// | 其 'global' 键不会被框架自动加载。故全局中间件必须在此文件注册。
// |
// | 执行顺序（按数组顺序依次执行）：
// | 1. CrossDomain         — 跨域支持（含 OPTIONS 预检 204 短路）
// | 2. RequestIdMiddleware — 请求唯一标识（规范 §14.1 / §18.1）
// | 3. IdempotentMiddleware — 幂等校验（规范 §14.5）
// +----------------------------------------------------------------------

return [
    // 1. 跨域请求支持（必须最先执行，保证 OPTIONS 预检也带 CORS 头）
    \app\common\middleware\CrossDomain::class,
    // 2. 请求唯一标识（必须在业务中间件之前执行）
    \app\common\middleware\RequestIdMiddleware::class,
    // 3. 接口幂等校验（在跨域之后）
    \app\common\middleware\IdempotentMiddleware::class,
];
