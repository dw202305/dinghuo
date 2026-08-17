<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\Order;
use app\common\model\Store;
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
        $channelMode = 2; // 默认公司直营
        $rule = 'direct';

        // 判断渠道归属
        if ($partnerId) {
            $partner = Db::name('partner')
                ->where('id', $partnerId)
                ->where('status', 1)
                ->find();

            if ($partner) {
                $channelMode = 1; // 城市合伙人渠道
                $rule = 'inherited_from_partner';

                // 继承合伙人的公司销售
                $salesId = $partner['sales_id'] ?? null;
                if ($salesId) {
                    $sales = Db::name('sales_staff')->where('id', $salesId)->find();
                    $salesName = $sales['name'] ?? null;
                }
            }
        }

        // 无合伙人时，门店直接归公司销售
        if (!$partnerId || !$partner) {
            $salesId = $store['sales_id'] ?? null;
            if ($salesId) {
                $sales = Db::name('sales_staff')->where('id', $salesId)->find();
                $salesName = $sales['name'] ?? null;
            }
            $rule = 'direct_store_sales';
        }

        return [
            'transaction_type' => 1, // 门店
            'transaction_id'   => $storeId,
            'service_store_id' => $storeId,
            'partner_id'       => $partnerId,
            'partner_name'     => $partner['name'] ?? null,
            'sales_id'         => $salesId,
            'sales_name'       => $salesName,
            'channel_mode'     => $channelMode,
            'rule'             => $rule,
            'determined_at'    => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 订单创建时填充归属快照字段
     *
     * 将当前归属关系写入订单快照字段，确保后续归属变更不影响历史订单（PRD 4.0.4）。
     * 快照字段包括：交易主体、服务门店、合伙人、成交销售、当前服务销售、
     * 协同销售、CRM客户及商机ID、归属确定时间和规则。
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

        // 填充归属快照字段
        $snapshotData = [
            // 交易归属
            'transaction_type'       => $ownership['transaction_type'],
            'transaction_id'         => $ownership['transaction_id'],
            // 实际服务门店
            'service_store_id'       => $ownership['service_store_id'],
            // 渠道归属快照
            'partner_id_snapshot'    => $ownership['partner_id'],
            'partner_name_snapshot'  => $ownership['partner_name'],
            'channel_mode_snapshot'  => $ownership['channel_mode'],
            // 公司销售归属快照
            'sales_id_snapshot'      => $ownership['sales_id'],
            'sales_name_snapshot'    => $ownership['sales_name'],
            // 当前服务销售（初始等于成交销售）
            'current_service_sales_id' => $ownership['sales_id'],
            // 协同销售（初始为空）
            'collaborating_sales_id' => null,
            // CRM 快照
            'crm_customer_id'        => $this->getPartnerCrmCustomerId($ownership['partner_id']),
            'crm_opportunity_id'     => null,
            // 归属元信息
            'ownership_rule'         => $ownership['rule'],
            'ownership_determined_at' => $ownership['determined_at'],
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
        // 校验销售人员存在性
        $fromSales = Db::name('sales_staff')->where('id', $fromSalesId)->find();
        $toSales = Db::name('sales_staff')->where('id', $toSalesId)->find();

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

            // 1. 查找原销售负责的城市合伙人
            $partnerQuery = Db::name('partner')
                ->where('sales_id', $fromSalesId)
                ->where('status', 1);

            if (!empty($partnerIds)) {
                $partnerQuery->whereIn('id', $partnerIds);
            }

            $partners = $partnerQuery->select()->toArray();

            foreach ($partners as $partner) {
                // 2. 更新合伙人的当前维护销售
                Db::name('partner')
                    ->where('id', $partner['id'])
                    ->update(['sales_id' => $toSalesId]);

                // 写入合伙人归属历史
                $this->writeOwnershipHistory(
                    'partner',
                    $partner['id'],
                    $fromSalesId,
                    $toSalesId,
                    $reason,
                    $operatorId,
                    $reviewerId,
                    true // 由合伙人销售变更级联
                );

                $stats['partners_transferred']++;

                // 3. 同步更新下属门店的当前维护销售
                $stores = Db::name('store')
                    ->where('partner_id', $partner['id'])
                    ->where('status', 1)
                    ->select()
                    ->toArray();

                foreach ($stores as $store) {
                    Db::name('store')
                        ->where('id', $store['id'])
                        ->update(['sales_id' => $toSalesId]);

                    // 写入门店归属历史
                    $this->writeOwnershipHistory(
                        'store',
                        $store['id'],
                        $fromSalesId,
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
                    $affectedStores[] = $partner['id']; // 合伙人自营订单

                    // 更新未完成订单的当前服务销售
                    $orderAffected = Db::name('order')
                        ->whereIn('service_store_id', $affectedStores)
                        ->whereIn('order_status', [
                            // 非终态的订单
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
                targetType: 'sales_staff',
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
            ->leftJoin('sales_staff ss', 'ss.id = p.sales_id')
            ->where('p.id', $partnerId)
            ->field([
                'p.id as partner_id',
                'p.name as partner_name',
                'p.sales_id',
                'ss.name as sales_name',
                'ss.id as sales_staff_id',
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
                'sales_id',
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
     * 记录归属关系变更的完整信息，包括变更前后、原因、审批等。
     *
     * @param string $subjectType 主体类型（partner/store）
     * @param int $subjectId 主体ID
     * @param int $fromSalesId 原销售ID
     * @param int $toSalesId 新销售ID
     * @param string $reason 变更原因
     * @param int $operatorId 操作人ID
     * @param int $reviewerId 审批人ID
     * @param bool $isCascade 是否级联更新
     * @return void
     */
    private function writeOwnershipHistory(
        string $subjectType,
        int $subjectId,
        int $fromSalesId,
        int $toSalesId,
        string $reason,
        int $operatorId,
        int $reviewerId,
        bool $isCascade = false
    ): void {
        // 获取销售姓名
        $fromSales = Db::name('sales_staff')->where('id', $fromSalesId)->find();
        $toSales = Db::name('sales_staff')->where('id', $toSalesId)->find();

        // 确定归属来源
        $source = $isCascade ? 'cascade_from_partner' : 'manual_transfer';

        Db::name('customer_attribution_history')->insert([
            'subject_type'    => $subjectType,
            'subject_id'      => $subjectId,
            'from_sales_id'   => $fromSalesId,
            'from_sales_name' => $fromSales['name'] ?? '',
            'to_sales_id'     => $toSalesId,
            'to_sales_name'   => $toSales['name'] ?? '',
            'source'          => $source,
            'reason'          => $reason,
            'operator_id'     => $operatorId,
            'reviewer_id'     => $reviewerId,
            'is_cascade'      => $isCascade ? 1 : 0,
            'effective_at'    => date('Y-m-d H:i:s'),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 获取合伙人的 CRM 客户 ID
     *
     * @param int|null $partnerId 合伙人ID
     * @return int|null
     */
    private function getPartnerCrmCustomerId(?int $partnerId): ?int
    {
        if (!$partnerId) {
            return null;
        }

        $partner = Db::name('partner')->where('id', $partnerId)->find();
        return $partner ? ($partner['crm_customer_id'] ?? null) : null;
    }
}
