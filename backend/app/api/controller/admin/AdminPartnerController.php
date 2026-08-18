<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 后台合伙人管理控制器
 * 合伙人CRUD/下属门店
 */
class AdminPartnerController extends BaseController
{
    /**
     * 合伙人列表
     * GET /api/v1/admin/partner/list
     */
    public function list(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('partner')->alias('p')
            ->leftJoin('sales_person sp', 'sp.id = p.primary_sales_id');

        if ($keyword = $request->param('keyword', '')) {
            $query->where('p.partner_no|p.business_entity', 'like', '%' . $keyword . '%');
        }
        if ($primarySalesId = $request->param('primary_sales_id/d')) {
            $query->where('p.primary_sales_id', $primarySalesId);
        }
        if ($status = $request->param('status/d')) {
            $query->where('p.status', $status);
        }

        $total = $query->count();
        $list  = $query->field([
                'p.id as partner_id', 'p.partner_no', 'p.business_entity',
                'p.authorized_city', 'p.cooperation_stage', 'p.partner_level',
                'sp.name as primary_sales_name', 'p.status',
                'p.cooperation_start_date', 'p.cooperation_end_date',
            ])
            ->order('p.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = [1 => '正常', 2 => '停用'];
        foreach ($list as &$item) {
            // Db 查询构造器无 withCount，逐行统计有效下属门店数
            $item['store_count']   = Db::name('store')
                ->where('partner_id', $item['partner_id'])
                ->where('status', 1)
                ->count();
            $item['status_text']   = $statusMap[$item['status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 合伙人详情
     * GET /api/v1/admin/partner/detail
     */
    public function detail(): \think\Response
    {
        $partnerId = (int) $this->app->request->param('partner_id', 0);
        if ($partnerId <= 0) {
            return $this->paramError('合伙人ID不能为空');
        }

        $partner = Db::name('partner')->where('id', $partnerId)->find();
        if (!$partner) {
            return $this->error('合伙人不存在', 1004);
        }

        $storeCount = Db::name('store')->where('partner_id', $partnerId)->where('status', 1)->count();
        $partner['store_count'] = $storeCount;

        return $this->success($partner);
    }

    /**
     * 新增/更新合伙人
     * POST /api/v1/admin/partner/save
     */
    public function save(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('partner_save')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $partnerId = (int) ($data['partner_id'] ?? 0);

        if ($partnerId > 0) {
            // 更新
            unset($data['partner_id']);
            Db::name('partner')->where('id', $partnerId)->update($data);
            return $this->success(['partner_id' => $partnerId]);
        }

        // 新增
        if (Db::name('partner')->where('partner_no', $data['partner_no'])->find()) {
            return $this->error('合伙人编号已存在', 1005);
        }

        $data['status'] = 1;
        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('partner')->insertGetId($data);

        return $this->success(['partner_id' => $newId]);
    }

    /**
     * 查看下属门店
     * GET /api/v1/admin/partner/stores
     */
    public function stores(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $partnerId = (int) $this->app->request->param('partner_id', 0);

        if ($partnerId <= 0) {
            return $this->paramError('合伙人ID不能为空');
        }

        $query = Db::name('store')
            ->where('partner_id', $partnerId)
            ->where('status', 1);

        $total = $query->count();
        $list  = $query->field([
                'id as store_id', 'store_no', 'store_name', 'customer_level', 'status',
            ])
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $statusMap = [1 => '正常', 2 => '停用', 3 => '待审核'];
        foreach ($list as &$item) {
            $item['status_text'] = $statusMap[$item['status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }
}
