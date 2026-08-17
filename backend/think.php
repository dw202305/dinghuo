<?php
// +----------------------------------------------------------------------
// | 世尚门店订货系统 - ThinkPHP 入口文件（根目录）
// +----------------------------------------------------------------------

namespace think;

require __DIR__ . '/vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;
$response = $http->run();
$response->send();
$http->end($response);
