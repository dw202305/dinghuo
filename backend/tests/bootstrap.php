<?php

declare(strict_types=1);

/**
 * PHPUnit 引导文件
 *
 * Unit 套件保持纯逻辑测试（不触库）；Feature 套件需要 ThinkPHP 应用容器
 * （Db/Cache/Log 门面），因此在此统一引导应用初始化。
 *
 * 安全约束：Feature 测试基类（tests\Feature\FeatureTestCase）在每个用例
 * 执行前强制校验当前连接库必须为 shishang_order_test，否则直接失败，
 * 杜绝误连生产库 shishang_order。
 */

require __DIR__ . '/../vendor/autoload.php';

$app = new \think\App(dirname(__DIR__));
$app->initialize();
