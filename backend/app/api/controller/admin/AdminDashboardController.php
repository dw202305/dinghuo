<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\common\enum\OrderStatus;
use app\common\enum\AfterSaleStatus;
use think\facade\Db;
use think\facade\Cache;

/**
 * 管理端仪表盘统计控制器
 */
class AdminDashboardController extends BaseController
{
    /** 统计缓存时长（秒） */
    private const CACHE_TTL = 60;

    /**
     * 获取仪表盘统计数据
     * GET /api/v1/admin/dashboard/stats
     */
    public function stats(): \think\Response
    {
        // 尝试从缓存读取
        $cacheKey = 'dashboard:admin:stats';
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $this->success($cached);
        }

        // 单条 GROUP BY 查询替代多次 COUNT
        $statusCounts = Db::name('order')
            ->whereNull('deleted_at')
            ->field('order_status, COUNT(*) as count')
            ->group('order_status')
            ->select()
            ->toArray();

        // 构建统计结果
        $stats = [
            'total_orders'  => 0,
            'pending_payment' => 0,
            'in_production' => 0,
            'pending_ship'  => 0,
            'completed'     => 0,
        ];

        // 根据 OrderStatus 枚举映射到统计分类
        $statusMap = [
            OrderStatus::PENDING_PAY->value  => 'pending_payment',
            OrderStatus::APPROVED->value     => 'in_production',
            OrderStatus::PRODUCING->value    => 'in_production',
            OrderStatus::QC->value           => 'in_production',
            OrderStatus::PENDING_SHIP->value => 'pending_ship',
            OrderStatus::COMPLETED->value    => 'completed',
        ];

        foreach ($statusCounts as $row) {
            $count = (int) $row['count'];
            $stats['total_orders'] += $count;

            $status = (int) $row['order_status'];
            if (isset($statusMap[$status])) {
                $stats[$statusMap[$status]] += $count;
            }
        }

        // 待处理事项统计
        $pendingItems = [
            // 已支付待审核
            'pending_audit' => Db::name('order')
                ->where('order_status', OrderStatus::PAID_PENDING->value)
                ->whereNull('deleted_at')
                ->count(),
            // 待发货
            'pending_ship' => Db::name('order')
                ->where('order_status', OrderStatus::PENDING_SHIP->value)
                ->whereNull('deleted_at')
                ->count(),
            // 处理中售后（待处理 + 处理中）
            'after_sale' => Db::name('after_sale')
                ->whereIn('status', [
                    AfterSaleStatus::PENDING->value,
                    AfterSaleStatus::PROCESSING->value,
                ])
                ->count(),
        ];

        $result = array_merge($stats, ['pending_items' => $pendingItems]);

        // 缓存60秒
        Cache::set($cacheKey, $result, self::CACHE_TTL);

        return $this->success($result);
    }
}
