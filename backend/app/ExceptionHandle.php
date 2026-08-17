<?php
declare(strict_types=1);

namespace app;

use app\common\exception\ExceptionHandler;

/**
 * 应用异常处理类
 *
 * ThinkPHP 8 自动加载此文件作为全局异常处理器。
 * 继承自自定义的 ExceptionHandler，实现统一 JSON 异常响应。
 *
 * 对齐规范 §14.1-14.3。
 */
class ExceptionHandle extends ExceptionHandler
{
    // 如需自定义 report / render 逻辑，在此扩展即可。
    // 当前全部逻辑由父类 app\common\exception\ExceptionHandler 提供。
}
