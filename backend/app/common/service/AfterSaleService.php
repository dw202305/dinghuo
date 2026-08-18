<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\CustomerType;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 售后服务
 * 创建售后/列表/详情/处理
 */
class AfterSaleService extends BaseService
{
    /**
     * 创建售后申请
     * @param int $storeId 门店ID
     * @param int $accountId 申请人ID
     * @param array $data 申请数据
     * @return array 售后单信息
     */
    public function createAfterSale(int $storeId, int $accountId, array $data): array
    {
        // 校验订单归属（transaction_type=1 门店 / transaction_id=门店ID）
        $order = Db::name('order')
            ->where('id', $data['order_id'])
            ->where('transaction_type', CustomerType::STORE->value)
            ->where('transaction_id', $storeId)
            ->find();

        if (!$order) {
            throw new ValidateException('订单不存在');
        }

        // 生成售后单号
        $afterSaleNo = $this->generateAfterSaleNo();

        $afterSaleId = $this->transaction(function () use ($storeId, $accountId, $data, $afterSaleNo, $order) {
            return Db::name('after_sale')->insertGetId([
                'after_sale_no'     => $afterSaleNo,
                'store_id'          => $storeId,
                'order_id'          => $data['order_id'],
                'order_no'          => $order['order_no'] ?? '',
                'item_id'           => $data['item_id'] ?? null,
                'problem_type'      => $data['problem_type'],
                'problem_desc'      => $data['problem_desc'],
                'images'            => json_encode($data['images'] ?? [], JSON_UNESCAPED_UNICODE),
                'videos'            => json_encode($data['videos'] ?? [], JSON_UNESCAPED_UNICODE),
                'install_date'      => $data['install_date'] ?? null,
                'affect_usage'      => $data['affect_usage'] ?? 0,
                'contact_name'      => $data['contact_name'],
                'contact_phone'     => $data['contact_phone'],
                'expected_solution' => $data['expected_solution'] ?? null,
                'status'            => 1, // 待处理
                'created_by'        => $accountId,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        });

        $this->logOperation(
            module: 'after_sale',
            action: 'create',
            targetType: 'after_sale',
            targetId: $afterSaleId,
            targetNo: $afterSaleNo,
            operatorId: $accountId,
            remark: '门店申请售后',
        );

        return [
            'after_sale_id' => $afterSaleId,
            'after_sale_no' => $afterSaleNo,
        ];
    }

    /**
     * 获取售后列表（门店端）
     * @param int $storeId 门店ID
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getStoreAfterSaleList(int $storeId, array $filters, int $page, int $pageSize): array
    {
        $query = Db::name('after_sale')
            ->alias('a')
            ->leftJoin('order o', 'o.id = a.order_id')
            ->where('a.store_id', $storeId);

        if (!empty($filters['status'])) {
            $query->where('a.status', (int) $filters['status']);
        }

        $total = $query->count();
        $list  = $query->field([
                'a.id as after_sale_id',
                'a.after_sale_no',
                'o.order_no',
                'a.item_id',
                'a.problem_type',
                'a.problem_desc',
                'a.status',
                'a.created_at',
            ])
            ->order('a.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $problemTypeMap = $this->getProblemTypeMap();
        $statusMap      = $this->getStatusMap();

        foreach ($list as &$item) {
            $item['problem_type_text'] = $problemTypeMap[$item['problem_type']] ?? '未知';
            $item['status_text']       = $statusMap[$item['status']] ?? '未知';
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /**
     * 获取售后详情（门店端）
     * @param int $storeId 门店ID
     * @param int $afterSaleId 售后单ID
     * @return array
     * @throws ValidateException
     */
    public function getStoreAfterSaleDetail(int $storeId, int $afterSaleId): array
    {
        $detail = Db::name('after_sale')
            ->alias('a')
            ->leftJoin('order o', 'o.id = a.order_id')
            ->leftJoin('order_item oi', 'oi.id = a.item_id')
            ->where('a.store_id', $storeId)
            ->where('a.id', $afterSaleId)
            ->field([
                'a.*',
                'o.order_no',
                'oi.item_no',
            ])
            ->find();

        if (!$detail) {
            throw new ValidateException('售后单不存在');
        }

        $detail['images'] = json_decode($detail['images'] ?? '[]', true);
        $detail['videos'] = json_decode($detail['videos'] ?? '[]', true);

        $problemTypeMap = $this->getProblemTypeMap();
        $statusMap      = $this->getStatusMap();

        $detail['problem_type_text'] = $problemTypeMap[$detail['problem_type']] ?? '未知';
        $detail['status_text']       = $statusMap[$detail['status']] ?? '未知';

        return $detail;
    }

    /**
     * 补充售后信息
     * @param int $storeId 门店ID
     * @param int $afterSaleId 售后单ID
     * @param array $data 补充数据
     * @return bool
     */
    public function supplementAfterSale(int $storeId, int $afterSaleId, array $data): bool
    {
        $afterSale = Db::name('after_sale')
            ->where('id', $afterSaleId)
            ->where('store_id', $storeId)
            ->where('status', 'in', [1, 2]) // 待处理或处理中
            ->find();

        if (!$afterSale) {
            throw new ValidateException('售后单不存在或当前状态不允许修改');
        }

        $updateData = [];
        if (!empty($data['problem_desc'])) {
            $updateData['problem_desc'] = $data['problem_desc'];
        }
        if (!empty($data['images'])) {
            $existing = json_decode($afterSale['images'] ?? '[]', true);
            $updateData['images'] = json_encode(array_merge($existing, $data['images']), JSON_UNESCAPED_UNICODE);
        }
        if (!empty($data['videos'])) {
            $existing = json_decode($afterSale['videos'] ?? '[]', true);
            $updateData['videos'] = json_encode(array_merge($existing, $data['videos']), JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['expected_solution'])) {
            $updateData['expected_solution'] = $data['expected_solution'];
        }

        if (!empty($updateData)) {
            Db::name('after_sale')->where('id', $afterSaleId)->update($updateData);
        }

        return true;
    }

    /**
     * 处理售后（后台）
     * @param int $afterSaleId 售后单ID
     * @param array $data 处理数据
     * @param int $adminId 管理员ID
     * @param string $adminName 管理员名称
     * @return bool
     */
    public function processAfterSale(int $afterSaleId, array $data, int $adminId, string $adminName): bool
    {
        $afterSale = Db::name('after_sale')
            ->where('id', $afterSaleId)
            ->find();

        if (!$afterSale) {
            throw new ValidateException('售后单不存在');
        }

        if (!in_array($afterSale['status'], [1, 2])) {
            throw new ValidateException('当前状态不允许处理');
        }

        $updateData = [
            'status'        => (int) $data['status'],
            'diagnosis'     => $data['diagnosis'] ?? null,
            'responsibility' => $data['responsibility'] ?? null,
            'solution'      => $data['solution'] ?? null,
            'accessory_cost_cent' => (int) ($data['accessory_cost'] ?? 0),
            'labor_cost_cent'     => (int) ($data['labor_cost'] ?? 0),
            'logistics_cost_cent' => (int) ($data['logistics_cost'] ?? 0),
            'handler_id'    => $adminId,
            'handler_name'  => $adminName,
            'handled_at'    => date('Y-m-d H:i:s'),
        ];

        Db::name('after_sale')->where('id', $afterSaleId)->update($updateData);

        $this->logOperation(
            module: 'after_sale',
            action: 'process',
            targetType: 'after_sale',
            targetId: $afterSaleId,
            targetNo: $afterSale['after_sale_no'],
            beforeData: ['status' => $afterSale['status']],
            afterData: $updateData,
            operatorId: $adminId,
            operatorName: $adminName,
            remark: '处理售后申请',
        );

        return true;
    }

    /**
     * 关闭售后（后台）
     * @param int $afterSaleId 售后单ID
     * @param string $closeReason 关闭原因
     * @param int $adminId 管理员ID
     * @param string $adminName 管理员名称
     * @return bool
     */
    public function closeAfterSale(int $afterSaleId, string $closeReason, int $adminId, string $adminName): bool
    {
        $afterSale = Db::name('after_sale')->where('id', $afterSaleId)->find();

        if (!$afterSale) {
            throw new ValidateException('售后单不存在');
        }

        if (in_array($afterSale['status'], [3, 4])) {
            throw new ValidateException('售后单已关闭或已完成');
        }

        Db::name('after_sale')->where('id', $afterSaleId)->update([
            'status'      => 4,
            'close_reason' => $closeReason,
            'handler_id'  => $adminId,
            'handler_name' => $adminName,
            'handled_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->logOperation(
            module: 'after_sale',
            action: 'close',
            targetType: 'after_sale',
            targetId: $afterSaleId,
            targetNo: $afterSale['after_sale_no'],
            operatorId: $adminId,
            operatorName: $adminName,
            remark: '关闭售后：' . $closeReason,
        );

        return true;
    }

    /**
     * 获取后台全局售后列表
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getAdminAfterSaleList(array $filters, int $page, int $pageSize): array
    {
        $query = Db::name('after_sale')
            ->alias('a')
            ->leftJoin('order o', 'o.id = a.order_id')
            ->leftJoin('store s', 's.id = a.store_id');

        if (!empty($filters['keyword'])) {
            $query->where('a.after_sale_no|o.order_no', 'like', '%' . $filters['keyword'] . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('a.status', (int) $filters['status']);
        }
        if (!empty($filters['problem_type'])) {
            $query->where('a.problem_type', (int) $filters['problem_type']);
        }
        if (!empty($filters['store_id'])) {
            $query->where('a.store_id', (int) $filters['store_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('a.created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('a.created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->field([
                'a.id as after_sale_id',
                'a.after_sale_no',
                'o.order_no',
                's.store_name',
                'a.problem_type',
                'a.status',
                'a.handler_name',
                'a.created_at',
            ])
            ->order('a.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $problemTypeMap = $this->getProblemTypeMap();
        $statusMap      = $this->getStatusMap();

        foreach ($list as &$item) {
            $item['problem_type_text'] = $problemTypeMap[$item['problem_type']] ?? '未知';
            $item['status_text']       = $statusMap[$item['status']] ?? '未知';
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /**
     * 获取售后详情（后台）
     * @param int $afterSaleId 售后单ID
     * @return array
     */
    public function getAdminAfterSaleDetail(int $afterSaleId): array
    {
        $detail = Db::name('after_sale')
            ->alias('a')
            ->leftJoin('order o', 'o.id = a.order_id')
            ->leftJoin('store s', 's.id = a.store_id')
            ->leftJoin('order_item oi', 'oi.id = a.item_id')
            ->where('a.id', $afterSaleId)
            ->field([
                'a.*',
                'o.order_no',
                's.store_name',
                's.store_no',
                'oi.item_no',
            ])
            ->find();

        if (!$detail) {
            throw new ValidateException('售后单不存在');
        }

        $detail['images'] = json_decode($detail['images'] ?? '[]', true);
        $detail['videos'] = json_decode($detail['videos'] ?? '[]', true);

        $problemTypeMap = $this->getProblemTypeMap();
        $statusMap      = $this->getStatusMap();
        $responsibilityMap = [1 => '世尚', 2 => '门店', 3 => '物流', 4 => '其他'];

        $detail['problem_type_text']    = $problemTypeMap[$detail['problem_type']] ?? '未知';
        $detail['status_text']          = $statusMap[$detail['status']] ?? '未知';
        $detail['responsibility_text']  = $detail['responsibility'] ? ($responsibilityMap[$detail['responsibility']] ?? null) : null;

        return $detail;
    }

    /**
     * 生成售后单号 AS{日期}{3位流水号}
     * @return string
     */
    private function generateAfterSaleNo(): string
    {
        $date   = date('Ymd');
        $prefix = 'AS' . $date;

        $last = Db::name('after_sale')
            ->where('after_sale_no', 'like', $prefix . '%')
            ->order('id', 'desc')
            ->value('after_sale_no');

        $seq = $last ? (int) substr($last, -3) + 1 : 1;

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 获取问题类型映射
     * @return array
     */
    private function getProblemTypeMap(): array
    {
        return [
            1 => '电机', 2 => '电源', 3 => '遥控器', 4 => '墙控',
            5 => '轨道', 6 => '面料', 7 => '结构件', 8 => '安装',
            9 => '初始化', 10 => '运输破损', 11 => '其他',
        ];
    }

    /**
     * 获取售后状态映射
     * @return array
     */
    private function getStatusMap(): array
    {
        return [
            1 => '待处理',
            2 => '处理中',
            3 => '已完成',
            4 => '已关闭',
        ];
    }
}
