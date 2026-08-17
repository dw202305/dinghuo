<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\model\Store;
use app\common\model\Order;
use think\facade\Db;

/**
 * 门店首页控制器
 * 工作台数据
 */
class HomeController extends BaseController
{
    /**
     * 获取工作台数据
     * GET /api/v1/store/home/dashboard
     */
    public function dashboard(): \think\Response
    {
        $storeId = $this->getStoreId();
        $store = Store::find($storeId);

        if (!$store) {
            return $this->error('门店不存在', 1004);
        }

        // 门店基本信息
        $storeInfo = [
            'store_id'    => $store->id,
            'store_no'    => $store->store_no,
            'store_name'  => $store->store_name,
            'customer_level' => (int) $store->customer_level,
            'customer_level_text' => $this->getCustomerLevelText((int) $store->customer_level),
            'kit_price'   => $this->getKitPrice((int) $store->customer_level),
            'primary_contact' => $this->getPrimaryContact($storeId),
        ];

        // 库存概览
        $inventory = Db::name('store_inventory')
            ->where('store_id', $storeId)
            ->field([
                Db::raw('SUM(available) as kit_available'),
                Db::raw('SUM(locked) as kit_locked'),
            ])
            ->find();

        // 各状态订单统计
        $orderStats = $this->getOrderStats($storeId);

        // 待办通知
        $notices = $this->buildNotices($orderStats);

        return $this->success([
            'store_info' => $storeInfo,
            'inventory'  => [
                'kit_available' => (int) ($inventory['kit_available'] ?? 0),
                'kit_locked'    => (int) ($inventory['kit_locked'] ?? 0),
            ],
            'order_stats' => $orderStats,
            'notices'     => $notices,
        ]);
    }

    /**
     * 获取客户等级文本
     */
    private function getCustomerLevelText(int $level): string
    {
        $map = [
            1 => '认证合作门店',
            2 => '银牌合作门店',
            3 => '金牌合作门店',
            4 => '钻石合作门店',
            5 => '战略合伙人',
        ];
        return $map[$level] ?? '未认证';
    }

    /**
     * 获取套件价格
     */
    private function getKitPrice(int $customerLevel): float
    {
        // TODO: kit 套餐功能待 database.md 补充 lj_kit 表后启用
        return 0.00;
    }

    /**
     * 获取主联系人
     */
    private function getPrimaryContact(int $storeId): array
    {
        $contact = Db::name('store_contact')
            ->where('store_id', $storeId)
            ->where('is_primary', 1)
            ->find();

        return [
            'name'  => $contact['contact_name'] ?? '',
            'phone' => $contact['phone'] ?? '',
        ];
    }

    /**
     * 获取订单状态统计
     */
    private function getOrderStats(int $storeId): array
    {
        $baseQuery = Db::name('order')
            ->where('transaction_type', 1)
            ->where('transaction_id', $storeId)
            ->whereNull('deleted_at');

        return [
            'pending_payment'  => (int) (clone $baseQuery)->where('order_status', 2)->count(),
            'pending_confirm'  => (int) (clone $baseQuery)->where('order_status', 5)->count(),
            'in_production'    => (int) (clone $baseQuery)->where('order_status', 'in', [7, 8, 9])->count(),
            'pending_receive'  => (int) (clone $baseQuery)->where('order_status', 'in', [10, 11, 12])->count(),
            'completed'        => (int) (clone $baseQuery)->where('order_status', 14)->count(),
            'after_sale'       => (int) (clone $baseQuery)->where('order_status', 15)->count(),
        ];
    }

    /**
     * 构建待办通知
     */
    private function buildNotices(array $stats): array
    {
        $notices = [];

        if ($stats['pending_payment'] > 0) {
            $notices[] = [
                'type'    => 'payment_reminder',
                'message' => "您有{$stats['pending_payment']}笔订单待支付，请及时处理",
                'link'    => '/pages/order/list?status=pending_payment',
            ];
        }

        if ($stats['pending_confirm'] > 0) {
            $notices[] = [
                'type'    => 'confirm_reminder',
                'message' => "您有{$stats['pending_confirm']}笔订单需确认",
                'link'    => '/pages/order/list?status=pending_confirm',
            ];
        }

        return $notices;
    }
}
