<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\Order;
use app\common\model\Store;
use app\common\enum\CustomerType;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 客户与销售归属服务
 *
 * 管理门店、城市合伙人和公司销售之间的三层归属关系。
 * 订单创建时填充归属快照，确保历史订单不受后续归属变更影响。
 *
 * 三层归属模型（规范 11 & PRD 4.0）：
 * 1. 交易主体：实际下单付款的门店或城市合伙人
 * 2. 渠道归属：门店所属城市合伙人（可为空）
 * 3. 公司销售归属：公司内部主维护销售
 *
 * 归属规则：
 * - 门店归合伙人时，自动继承合伙人的公司销售
 * - 无合伙人时，门店直接归公司销售
 * - 合伙人销售变更时，下属门店当前维护销售同步更新
 * - 历史订单的成交销售快照不自动改变
 *
 * @see docs/dev_specification_v1.0.md 第十一节
 * @see docs/prd_v3.2.md 4.0
 */
class OwnershipService extends BaseService
{
    /**
     * 确定门店的三层归属关系
     *
     * 根据门店当前的渠道关系，返回完整的归属信息。
     * 门店归合伙人时，继承合伙人的公司销售；无合伙人时直接归公司销售。
     *
     * @param int $storeId 门店ID
     * @return array 归属信息数组：
     *   - transaction_type: int 交易主体类型（1=门店, 2=城市合伙人）
     *   - transaction_id: int 交易主体ID
     *   - service_store_id: int 实际服务门店ID
     *   - partner_id: int|null 城市合伙人ID（可为空）
     *   - partner_name: string|null 城市合伙人名称
     *   - sales_id: int|null 公司主归属销售ID
     *   - sales_name: string|null 公司主归属销售名称
     *   - channel_mode: int 渠道模式（1=城市合伙人渠道, 2=公司直营）
     *   - rule: string 归属规则来源描述
     */
    public function determineOwnership(int $storeId): array
    {
        $store = Db::name('store')->where('id', $storeId)->find();
        if (!$store) {
            throw new ValidateException('门店不存在');
        }

        $partnerId = $store['partner_id'] ?? null;
        $partner = null;
        $salesId = null;
        $salesName = null;
        // 批次2c：渠道模式读 deploy lj_store.channel_mode（1城市合伙人渠道 2公司直营）
        $channelMode = (int) ($store['channel_mode'] ?? 2);
        $rule = 'direct';

        // 判断渠道归属（deploy lj_partner 主归属列 primary_sales_id，名称列 business_entity）
        if ($partnerId) {
            $partner = Db::name('partner')
                ->where('id', $partnerId)
                ->where('status', 1)
                ->find();

            if ($partner) {
                $rule = 'inherited_from_partner';

                // 继承合伙人的公司销售（v1.3.1 统一使用 lj_sales_person）
                $salesId = $partner['primary_sales_id'] ?? null;
                if ($salesId) {
                    $sales = Db::name('sales_person')->where('id', $salesId)->find();
                    $salesName = $sales['name'] ?? null;
                }
            }
        }

        // 无合伙人时，门店直接归公司销售（deploy lj_store.primary_sales_id）
        if (!$partnerId || !$partner) {
            $salesId = $store['primary_sales_id'] ?? null;
            if ($salesId) {
                $sales = Db::name('sales_person')->where('id', $salesId)->find();
                $salesName = $sales['name'] ?? null;
            }
            $rule = 'direct_store_sales';
        }

        return [
            'transaction_type' => CustomerType::STORE->value,
            'transaction_id'   => $storeId,
            'service_store_id' => $storeId,
            'partner_id'       => $partnerId,
            'partner_name'     => $partner['business_entity'] ?? null,
            'sales_id'         => $salesId,
            'sales_name'       => $salesName,
            'channel_mode'     => $channelMode,
            'crm_customer_id'  => $store['crm_customer_id'] ?? null,
            'rule'             => $rule,
            'determined_at'    => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 订单创建时填充归属快照字段
     *
     * 将当前归属关系写入订单快照字段，确保后续归属变更不影响历史订单（PRD 4.0.4）。
     *
     * 批次2c：快照列对齐 deploy lj_order 实际列——partner_snapshot_id /
     * primary_sales_snapshot_id / current_service_sales_id / secondary_sales_snapshot_id /
     * crm_customer_snapshot_id(VARCHAR) / crm_opportunity_id；旧的名称类快照、
     * channel_mode_snapshot、ownership_rule/determined_at 等 deploy 无对应列，已删除。
     *
     * @param Order $order 订单模型（引用传递，直接修改）
     * @return void
     */
    public function fillOwnershipSnapshot(Order $order): void
    {
        $storeId = (int) ($order->transaction_id ?? 0);
        if ($storeId <= 0) {
            return;
        }

        $ownership = $this->determineOwnership($storeId);

        // 填充归属快照字段（deploy lj_order 实际列）
        $snapshotData = [
            // 渠道归属快照：城市合伙人ID
            'partner_snapshot_id'        => $ownership['partner_id'],
            // 公司销售归属快照：主归属销售
            'primary_sales_snapshot_id'  => $ownership['sales_id'],
            // 当前服务销售（初始等于成交销售）
            'current_service_sales_id'   => $ownership['sales_id'],
            // 协同销售（初始为空）
            'secondary_sales_snapshot_id' => null,
            // CRM 快照（VARCHAR(50)）：门店自身 CRM 客户ID
            'crm_customer_snapshot_id'   => $ownership['crm_customer_id'] !== null
                ? (string) $ownership['crm_customer_id']
                : null,
            'crm_opportunity_id'         => null,
        ];

        $order->save($snapshotData);
    }

    /**
     * 销售转交处理（PRD 4.0.3.1）
     *
     * 当公司销售离职或调岗时：
     * 1. 以城市合伙人为转交单位执行
     * 2. 同步更新合伙人及其下属门店的当前维护销售
     * 3. 新创建的订单使用新销售
     * 4. 历史订单成交销售快照不自动改变
     * 5. 未完成订单的当前服务负责人可更新为接任销售
     *
     * @param int $fromSalesId 原销售ID
     * @param int $toSalesId 接任销售ID
     * @param array $options 选项：
     *   - partner_ids: array 指定合伙人ID列表（空则转交所有由原销售负责的合伙人）
     *   - transfer_pending_orders: bool 是否转交未完成订单的当前服务销售（默认true）
     *   - reason: string 转交原因
     *   - operator_id: int 操作人ID
     *   - reviewer_id: int 审批人ID
     * @return array 转交结果统计
     * @throws ValidateException
     */
    public function handleSalesTransfer(int $fromSalesId, int $toSalesId, array $options = []): array
    {
        // 校验销售人员存在性（v1.3.1 统一使用 lj_sales_person）
        $fromSales = Db::name('sales_person')->where('id', $fromSalesId)->find();
        $toSales = Db::name('sales_person')->where('id', $toSalesId)->find();

        if (!$fromSales || !$toSales) {
            throw new ValidateException('销售人员不存在');
        }

        if ($fromSalesId === $toSalesId) {
            throw new ValidateException('原销售和接任销售不能是同一人');
        }

        $partnerIds = $options['partner_ids'] ?? [];
        $transferPendingOrders = $options['transfer_pending_orders'] ?? true;
        $reason = $options['reason'] ?? '';
        $operatorId = $options['operator_id'] ?? 0;
        $reviewerId = $options['reviewer_id'] ?? 0;

        return $this->transaction(function () use (
            $fromSalesId, $toSalesId, $partnerIds, $transferPendingOrders,
            $reason, $operatorId, $reviewerId, $fromSales, $toSales
        ) {
            $stats = [
                'partners_transferred' => 0,
                'stores_transferred'   => 0,
                'orders_transferred'   => 0,
            ];

            // 1. 查找原销售负责的城市合伙人（deploy lj_partner.primary_sales_id）
            $partnerQuery = Db::name('partner')
                ->where('primary_sales_id', $fromSalesId)
                ->where('status', 1);

            if (!empty($partnerIds)) {
                $partnerQuery->whereIn('id', $partnerIds);
            }

            $partners = $partnerQuery->select()->toArray();

            foreach ($partners as $partner) {
                // 2. 更新合伙人的当前维护销售
                Db::name('partner')
                    ->where('id', $partner['id'])
                    ->update(['primary_sales_id' => $toSalesId]);

                // 写入合伙人归属历史
                $this->writeOwnershipHistory(
                    CustomerType::PARTNER,
                    (int) $partner['id'],
                    null,
                    $toSalesId,
                    $reason,
                    $operatorId,
                    $reviewerId,
                    true // 由合伙人销售变更级联
                );

                $stats['partners_transferred']++;

                // 3. 同步更新下属门店的当前维护销售（deploy lj_store.primary_sales_id）
                $stores = Db::name('store')
                    ->where('partner_id', $partner['id'])
                    ->where('status', 1)
                    ->select()
                    ->toArray();

                foreach ($stores as $store) {
                    Db::name('store')
                        ->where('id', $store['id'])
                        ->update(['primary_sales_id' => $toSalesId]);

                    // 写入门店归属历史
                    $this->writeOwnershipHistory(
                        CustomerType::STORE,
                        (int) $store['id'],
                        (int) ($partner['id'] ?? 0),
                        $toSalesId,
                        $reason,
                        $operatorId,
                        $reviewerId,
                        true // 级联更新
                    );

                    $stats['stores_transferred']++;
                }

                // 4. 转交未完成订单的当前服务销售
                if ($transferPendingOrders) {
                    $affectedStores = array_column($stores, 'id');

                    // 更新未完成订单的当前服务销售（门店主体订单）
                    $orderAffected = 0;
                    if (!empty($affectedStores)) {
                        $orderAffected += Db::name('order')
                            ->whereIn('service_store_id', $affectedStores)
                            ->whereIn('order_status', [
                                // 非终态的订单
                                2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 15, 17,
                            ])
                            ->where('current_service_sales_id', $fromSalesId)
                            ->update(['current_service_sales_id' => $toSalesId]);
                    }

                    // 合伙人自营订单（transaction_type=2，service_store_id 可为空）
                    $orderAffected += Db::name('order')
                        ->where('transaction_type', CustomerType::PARTNER->value)
                        ->where('transaction_id', $partner['id'])
                        ->whereIn('order_status', [
                            2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 15, 17,
                        ])
                        ->where('current_service_sales_id', $fromSalesId)
                        ->update(['current_service_sales_id' => $toSalesId]);

                    $stats['orders_transferred'] += $orderAffected;
                }
            }

            // 5. 记录转交操作日志
            $this->logOperation(
                module: 'ownership',
                action: 'sales_transfer',
                targetType: 'sales',
                targetId: $fromSalesId,
                beforeData: ['sales_id' => $fromSalesId, 'sales_name' => $fromSales['name']],
                afterData: ['sales_id' => $toSalesId, 'sales_name' => $toSales['name']],
                operatorId: $operatorId,
                remark: "销售转交：{$fromSales['name']} → {$toSales['name']}，原因：{$reason}",
            );

            Log::info('销售转交完成', [
                'from' => $fromSales['name'],
                'to'   => $toSales['name'],
                'stats' => $stats,
            ]);

            return $stats;
        });
    }

    /**
     * 获取合伙人的销售归属链
     *
     * 返回：公司销售 → 城市合伙人 → 下属门店列表
     *
     * @param int $partnerId 合伙人ID
     * @return array 归属链信息
     */
    public function getPartnerSalesChain(int $partnerId): array
    {
        $partner = Db::name('partner')
            ->alias('p')
            ->leftJoin('sales_person ss', 'ss.id = p.primary_sales_id')
            ->where('p.id', $partnerId)
            ->field([
                'p.id as partner_id',
                'p.business_entity as partner_name',
                'p.primary_sales_id',
                'ss.name as sales_name',
                'ss.id as sales_id',
            ])
            ->find();

        if (!$partner) {
            throw new ValidateException('城市合伙人不存在');
        }

        // 获取下属门店
        $stores = Db::name('store')
            ->where('partner_id', $partnerId)
            ->where('status', 1)
            ->field([
                'id as store_id',
                'store_no',
                'store_name',
                'customer_level',
                'primary_sales_id',
            ])
            ->select()
            ->toArray();

        return [
            'partner' => $partner,
            'stores'  => $stores,
            'chain'   => [
                'company_sales' => $partner['sales_name'] ?? '未分配',
                'partner'       => $partner['partner_name'],
                'store_count'   => count($stores),
            ],
        ];
    }

    /**
     * 写入归属变更历史
     *
     * 批次2c：列对齐 deploy lj_customer_attribution_history（customer_type/
     * customer_id/channel_mode/partner_id/primary_sales_id/attribution_source/
     * effective_time/is_current/change_reason/cascade_from_partner）。
     * 归属来源：销售转交 = 4转移；级联继承 = 3继承。
     * 写入前将同主体旧记录的 is_current 置 0。
     *
     * @param CustomerType $customerType 客户主体类型
     * @param int $customerId 客户主体ID
     * @param int|null $partnerId 城市合伙人ID（门店主体时传入）
     * @param int $toSalesId 新主归属销售ID
     * @param string $reason 变更原因
     * @param int $operatorId 操作人ID（预留，当前表无对应列）
     * @param int $reviewerId 审批人ID（预留，当前表无对应列）
     * @param bool $isCascade 是否级联更新
     * @return void
     */
    private function writeOwnershipHistory(
        CustomerType $customerType,
        int $customerId,
        ?int $partnerId,
        int $toSalesId,
        string $reason,
        int $operatorId,
        int $reviewerId,
        bool $isCascade = false
    ): void {
        $now = date('Y-m-d H:i:s');

        // 门店主体补全渠道模式与合伙人；合伙人主体直营语义下 channel_mode=2
        $channelMode = 2;
        if ($customerType === CustomerType::STORE) {
            $store = Db::name('store')->where('id', $customerId)->find();
            $channelMode = (int) ($store['channel_mode'] ?? 2);
        }

        // 旧当前记录置失效
        Db::name('customer_attribution_history')
            ->where('customer_type', $customerType->value)
            ->where('customer_id', $customerId)
            ->where('is_current', 1)
            ->update(['is_current' => 0, 'expire_time' => $now]);

        Db::name('customer_attribution_history')->insert([
            'customer_type'        => $customerType->value,
            'customer_id'          => $customerId,
            'channel_mode'         => $channelMode,
            'partner_id'           => $partnerId,
            'primary_sales_id'     => $toSalesId,
            'secondary_sales_id'   => null,
            // 归属来源：级联=3继承，手动转交=4转移
            'attribution_source'   => $isCascade ? 3 : 4,
            'effective_time'       => $now,
            'expire_time'          => null,
            'is_current'           => 1,
            'change_reason'        => $reason !== '' ? $reason : null,
            'cascade_from_partner' => $isCascade ? 1 : 0,
            'created_at'           => $now,
        ]);
    }
}
