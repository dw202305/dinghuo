<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 后台系统管理控制器
 * 操作日志列表 + 管理员/角色/权限/归属变更/销售转交
 */
class AdminSystemController extends BaseController
{
    // ==================== 管理员管理 ====================

    /**
     * 管理员列表
     * GET /api/v1/admin/system/admin/list
     */
    public function adminList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('admin')
            ->alias('a')
            ->leftJoin('role r', 'r.id = a.role_id');

        if ($keyword = $request->param('keyword', '')) {
            $query->where('a.username|a.real_name|a.phone', 'like', '%' . $keyword . '%');
        }
        if ($roleId = $request->param('role_id/d')) {
            $query->where('a.role_id', $roleId);
        }
        if ($status = $request->param('status/d')) {
            $query->where('a.status', $status);
        }

        $total = $query->count();
        $list  = $query->field([
                'a.id as admin_id', 'a.username', 'a.real_name', 'a.phone',
                'a.email', 'a.role_id', 'r.role_name', 'a.status',
                'a.last_login_at', 'a.login_count',
            ])
            ->order('a.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = [1 => '正常', 0 => '停用'];
        foreach ($list as &$item) {
            $item['status_text'] = $statusMap[$item['status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 管理员新增/编辑
     * POST /api/v1/admin/system/admin/save
     */
    public function adminSave(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('admin_save')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $adminId = (int) ($data['admin_id'] ?? 0);

        if ($adminId > 0) {
            unset($data['admin_id']);
            if (!empty($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('admin')->where('id', $adminId)->update($data);
            return $this->success(['admin_id' => $adminId]);
        }

        // 新增
        if (empty($data['password'])) {
            return $this->paramError('新增管理员密码不能为空');
        }
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['status'] = $data['status'] ?? 1;
        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('admin')->insertGetId($data);

        return $this->success(['admin_id' => $newId]);
    }

    /**
     * 管理员删除（软删除）
     * DELETE /api/v1/admin/system/admin/delete
     */
    public function adminDelete(): \think\Response
    {
        $adminId = (int) $this->app->request->post('admin_id', 0);
        if ($adminId <= 0) {
            return $this->paramError('管理员ID不能为空');
        }

        Db::name('admin')->where('id', $adminId)->update([
            'status'     => 0,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success(null, '删除成功');
    }

    // ==================== 角色管理 ====================

    /**
     * 角色列表
     * GET /api/v1/admin/system/role/list
     */
    public function roleList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();

        $total = Db::name('role')->count();
        $list  = Db::name('role')
            ->order('sort_order', 'asc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['role_id'] = $item['id'];
            $item['admin_count'] = Db::name('admin')->where('role_id', $item['id'])->where('status', 1)->count();
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 角色新增/编辑
     * POST /api/v1/admin/system/role/save
     */
    public function roleSave(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('role_save')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $roleId = (int) ($data['role_id'] ?? 0);

        if (isset($data['permission_ids']) && is_array($data['permission_ids'])) {
            $data['permissions'] = json_encode($data['permission_ids'], JSON_UNESCAPED_UNICODE);
            unset($data['permission_ids']);
        }

        if ($roleId > 0) {
            unset($data['role_id']);
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('role')->where('id', $roleId)->update($data);
            return $this->success(['role_id' => $roleId]);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('role')->insertGetId($data);
        return $this->success(['role_id' => $newId]);
    }

    /**
     * 角色删除
     * DELETE /api/v1/admin/system/role/delete
     */
    public function roleDelete(): \think\Response
    {
        $roleId = (int) $this->app->request->post('role_id', 0);
        if ($roleId <= 0) {
            return $this->paramError('角色ID不能为空');
        }

        // 检查是否有管理员使用该角色
        $adminCount = Db::name('admin')->where('role_id', $roleId)->where('status', 1)->count();
        if ($adminCount > 0) {
            return $this->error('该角色下仍有管理员使用，无法删除', 1006);
        }

        Db::name('role')->where('id', $roleId)->delete();

        return $this->success(null, '删除成功');
    }

    /**
     * 权限树
     * GET /api/v1/admin/system/permission/tree
     */
    public function permissionTree(): \think\Response
    {
        $permissions = Db::name('permission')
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        // 构建树形结构
        $tree = $this->buildTree($permissions);

        return $this->success(['tree' => $tree]);
    }

    /**
     * 构建权限树
     */
    private function buildTree(array $items, int $parentId = 0): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $item['permission_id'] = $item['id'];
                $item['children'] = $this->buildTree($items, (int) $item['id']);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    // ==================== 操作日志 ====================

    /**
     * 操作日志查询
     * GET /api/v1/admin/system/operation-log
     */
    public function operationLog(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('operation_log');

        if ($module = $request->param('module', '')) {
            $query->where('module', $module);
        }
        if ($action = $request->param('action', '')) {
            $query->where('action', $action);
        }
        if ($targetType = $request->param('target_type', '')) {
            $query->where('target_type', $targetType);
        }
        if ($targetId = $request->param('target_id/d')) {
            $query->where('target_id', $targetId);
        }
        if ($operatorName = $request->param('operator_name', '')) {
            $query->where('operator_name', 'like', '%' . $operatorName . '%');
        }
        if ($startDate = $request->param('start_date', '')) {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate = $request->param('end_date', '')) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['log_id'] = $item['id'];
            $item['before_data'] = json_decode($item['before_data'] ?? '{}', true);
            $item['after_data']  = json_decode($item['after_data'] ?? '{}', true);
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    // ==================== 归属变更/销售转交 ====================

    /**
     * 客户归属变更
     * POST /api/v1/admin/system/attribution/change
     */
    public function attributionChange(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['customer_type']) || empty($data['customer_id']) || empty($data['primary_sales_id'])) {
            return $this->paramError('必填项缺失');
        }

        // TODO: 实现归属变更逻辑 + 历史记录 + 级联更新
        $attributionId = Db::name('customer_attribution_history')->insertGetId([
            'customer_type'    => $data['customer_type'],
            'customer_id'      => $data['customer_id'],
            'channel_mode'     => $data['channel_mode'] ?? null,
            'partner_id'       => $data['partner_id'] ?? null,
            'primary_sales_id' => $data['primary_sales_id'],
            'secondary_sales_id' => $data['secondary_sales_id'] ?? null,
            'attribution_source' => $data['attribution_source'],
            'effective_time'   => $data['effective_time'],
            'change_reason'    => $data['change_reason'],
            'applicant'        => $data['applicant'] ?? '',
            'approver'         => $data['approver'] ?? '',
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        $cascadedCount = 0;
        if (!empty($data['cascade_stores']) && (int) $data['customer_type'] === 2) {
            // 级联更新合伙人下属门店
            $cascadedCount = Db::name('store')
                ->where('partner_id', $data['customer_id'])
                ->update([
                    'primary_sales_id' => $data['primary_sales_id'],
                    'secondary_sales_id' => $data['secondary_sales_id'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        return $this->success([
            'attribution_id'       => $attributionId,
            'cascaded_store_count' => $cascadedCount,
        ]);
    }

    /**
     * 销售转交
     * POST /api/v1/admin/system/sales/transfer
     */
    public function salesTransfer(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['from_sales_id']) || empty($data['to_sales_id']) || empty($data['partner_ids'])) {
            return $this->paramError('必填项缺失');
        }

        $partnerIds = (array) $data['partner_ids'];
        $toSalesId  = (int) $data['to_sales_id'];

        $transferredPartnerCount = 0;
        $transferredStoreCount   = 0;

        Db::transaction(function () use ($partnerIds, $toSalesId, &$transferredPartnerCount, &$transferredStoreCount) {
            // 更新合伙人归属
            $transferredPartnerCount = Db::name('partner')
                ->where('id', 'in', $partnerIds)
                ->update([
                    'primary_sales_id' => $toSalesId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            // 级联更新下属门店
            $transferredStoreCount = Db::name('store')
                ->where('partner_id', 'in', $partnerIds)
                ->update([
                    'primary_sales_id' => $toSalesId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        });

        return $this->success([
            'transferred_partner_count' => $transferredPartnerCount,
            'transferred_store_count'   => $transferredStoreCount,
        ]);
    }
}
