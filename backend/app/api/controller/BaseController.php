<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\ApiResponse;
use think\App;

/**
 * 基础控制器
 * 提供通用能力和请求上下文
 */
abstract class BaseController
{
    use ApiResponse;

    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->initialize();
    }

    /**
     * 初始化（子类可覆盖）
     */
    protected function initialize(): void
    {
    }

    /**
     * 获取当前登录账号ID
     * @return int
     */
    protected function getAccountId(): int
    {
        return (int) ($this->app->request->accountId ?? 0);
    }

    /**
     * 获取当前账号角色
     * @return int
     */
    protected function getAccountRole(): int
    {
        return (int) ($this->app->request->accountRole ?? 0);
    }

    /**
     * 获取当前门店ID（门店端）
     * @return int
     */
    protected function getStoreId(): int
    {
        return (int) ($this->app->request->storeId ?? 0);
    }

    /**
     * 获取分页参数
     * @return array [page, pageSize]
     */
    protected function getPageParams(): array
    {
        $page = max(1, (int) $this->app->request->param('page', 1));
        $pageSize = (int) $this->app->request->param('page_size', 20);
        $pageSize = min(100, max(1, $pageSize));
        return [$page, $pageSize];
    }
}
