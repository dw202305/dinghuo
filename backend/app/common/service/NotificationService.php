<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Log;

/**
 * 通知服务
 * 当前为 stub 实现（通知渠道未对接），仅记录日志
 *
 * @see docs/prd_v3.2.md 第十章 通知与消息
 * @see docs/dev_specification_v1.md 17节 异步任务与WebSocket
 */
class NotificationService extends BaseService
{
    /**
     * 通知门店
     *
     * @param int $storeId 门店ID
     * @param string $event 事件标识（如 order_shipped / audit_passed）
     * @param array $payload 事件负载数据
     * @return void
     */
    public function notifyStore(int $storeId, string $event, array $payload = []): void
    {
        // TODO: 对接微信订阅消息/站内通知
        Log::info('NotificationService::notifyStore', [
            'store_id' => $storeId,
            'event' => $event,
            'payload' => $payload,
        ]);
    }

    /**
     * 通知管理员
     *
     * @param string $event 事件标识（如 order_created / after_sale_applied）
     * @param array $payload 事件负载数据
     * @return void
     */
    public function notifyAdmin(string $event, array $payload = []): void
    {
        // TODO: 对接后台WebSocket/站内通知
        Log::info('NotificationService::notifyAdmin', [
            'event' => $event,
            'payload' => $payload,
        ]);
    }
}
