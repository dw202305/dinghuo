<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 后台商品管理控制器
 * 面料CRUD/批量导入/批量调价/批量上下架 + 轨道/配件/套件/供应商管理
 */
class AdminProductController extends BaseController
{
    // ==================== 面料管理 ====================

    /**
     * 面料列表（后台）
     * GET /api/v1/admin/product/fabric/list
     */
    public function fabricList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('fabric');

        if ($keyword = $request->param('keyword', '')) {
            $query->where('fabric_no|name|color_name|series', 'like', '%' . $keyword . '%');
        }
        if ($series = $request->param('series', '')) {
            $query->where('series', $series);
        }
        if ($listingStatus = $request->param('listing_status/d')) {
            $query->where('listing_status', $listingStatus);
        }
        if ($orderable = $request->param('orderable/d')) {
            $query->where('orderable', $orderable);
        }
        if ($stockStatus = $request->param('stock_status/d')) {
            $query->where('stock_status', $stockStatus);
        }

        $sort = $request->param('sort', '');
        if ($sort === 'price_asc') {
            $query->order('price_per_sqm', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->order('price_per_sqm', 'desc');
        } else {
            $query->order('sort_weight', 'desc')->order('id', 'desc');
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        $stockMap = [1 => '充足', 2 => '紧张', 3 => '缺货'];
        foreach ($list as &$item) {
            $item['fabric_id'] = $item['id'];
            $item['function_tags'] = json_decode($item['function_tags'] ?? '[]', true);
            $item['stock_status_text'] = $stockMap[$item['stock_status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 面料详情（后台）
     * GET /api/v1/admin/product/fabric/detail
     */
    public function fabricDetail(): \think\Response
    {
        $fabricId = (int) $this->app->request->param('fabric_id', 0);
        if ($fabricId <= 0) {
            return $this->paramError('面料ID不能为空');
        }

        $fabric = Db::name('fabric')->where('id', $fabricId)->find();
        if (!$fabric) {
            return $this->error('面料不存在', 1004);
        }

        $fabric['fabric_id'] = $fabric['id'];
        $fabric['function_tags'] = json_decode($fabric['function_tags'] ?? '[]', true);
        $fabric['texture_tags']  = json_decode($fabric['texture_tags'] ?? '[]', true);
        $fabric['detail_images'] = json_decode($fabric['detail_images'] ?? '[]', true);

        // 供应商映射
        $suppliers = Db::name('supplier_fabric_mapping')
            ->alias('m')
            ->leftJoin('supplier s', 's.id = m.supplier_id')
            ->where('m.fabric_no', $fabric['fabric_no'])
            ->field(['m.*', 's.supplier_name'])
            ->select()->toArray();

        $fabric['suppliers'] = $suppliers;

        return $this->success($fabric);
    }

    /**
     * 面料新增/编辑
     * POST /api/v1/admin/product/fabric/save
     */
    public function fabricSave(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('fabric_save')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $fabricId = (int) ($data['fabric_id'] ?? 0);

        // JSON字段
        foreach (['function_tags', 'texture_tags', 'detail_images'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
            }
        }

        if ($fabricId > 0) {
            unset($data['fabric_id']);
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('fabric')->where('id', $fabricId)->update($data);
            return $this->success(['fabric_id' => $fabricId]);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('fabric')->insertGetId($data);
        return $this->success(['fabric_id' => $newId]);
    }

    /**
     * 面料批量导入
     * POST /api/v1/admin/product/fabric/import
     */
    public function fabricImport(): \think\Response
    {
        // TODO: 处理 Excel 文件上传和解析
        $mode = $this->app->request->post('mode', 'append');

        return $this->success([
            'total_rows'    => 0,
            'success_count' => 0,
            'fail_count'    => 0,
            'fail_details'  => [],
        ]);
    }

    /**
     * 面料批量调价
     * POST /api/v1/admin/product/fabric/batch-price
     */
    public function fabricBatchPrice(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['fabric_ids']) || empty($data['adjust_type']) || !isset($data['adjust_value']) || empty($data['effective_date']) || empty($data['reason'])) {
            return $this->paramError('必填项缺失');
        }

        $fabricIds = (array) $data['fabric_ids'];
        $adjustType  = $data['adjust_type'];
        $adjustValue = (float) $data['adjust_value'];

        $affectedCount = 0;

        Db::transaction(function () use ($fabricIds, $adjustType, $adjustValue, &$affectedCount) {
            foreach ($fabricIds as $fabricId) {
                $fabric = Db::name('fabric')->where('id', $fabricId)->find();
                if (!$fabric) continue;

                $oldPrice = (float) $fabric['price_per_sqm'];
                if ($adjustType === 'fixed') {
                    $newPrice = max(0, $oldPrice + $adjustValue);
                } else {
                    $newPrice = max(0, $oldPrice * (1 + $adjustValue / 100));
                }

                Db::name('fabric')->where('id', $fabricId)->update([
                    'price_per_sqm' => round($newPrice, 2),
                    'price_version' => (int) $fabric['price_version'] + 1,
                    'effective_date' => $data['effective_date'],
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
                $affectedCount++;
            }
        });

        return $this->success([
            'affected_count'    => $affectedCount,
            'new_price_version' => 0, // TODO: 真实版本号
        ]);
    }

    /**
     * 面料批量上下架
     * POST /api/v1/admin/product/fabric/batch-status
     */
    public function fabricBatchStatus(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['fabric_ids']) || !isset($data['listing_status'])) {
            return $this->paramError('必填项缺失');
        }

        $fabricIds = (array) $data['fabric_ids'];
        $updateData = [
            'listing_status' => (int) $data['listing_status'],
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        if (isset($data['orderable'])) {
            $updateData['orderable'] = (int) $data['orderable'];
        }

        $affectedCount = Db::name('fabric')
            ->where('id', 'in', $fabricIds)
            ->update($updateData);

        return $this->success(['affected_count' => $affectedCount]);
    }

    // ==================== 轨道管理 ====================

    /**
     * 轨道列表
     * GET /api/v1/admin/product/track/list
     */
    public function trackList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('track');

        if ($trackType = $request->param('track_type/d')) {
            $query->where('track_type', $trackType);
        }
        if ($color = $request->param('color', '')) {
            $query->where('color', $color);
        }
        if ($enabled = $request->param('enabled/d')) {
            $query->where('enabled', $enabled);
        }

        $total = $query->count();
        $list  = $query->order('id', 'asc')->page($page, $pageSize)->select()->toArray();

        $typeMap = [1 => '横轨', 2 => '竖轨'];
        foreach ($list as &$item) {
            $item['track_type_text'] = $typeMap[$item['track_type']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 轨道新增/编辑
     * POST /api/v1/admin/product/track/save
     */
    public function trackSave(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('track_save')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $id = (int) ($data['id'] ?? 0);
        unset($data['id']);

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('track')->where('id', $id)->update($data);
            return $this->success(['id' => $id]);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('track')->insertGetId($data);
        return $this->success(['id' => $newId]);
    }

    // ==================== 配件管理 ====================

    /**
     * 配件列表
     * GET /api/v1/admin/product/accessory/list
     */
    public function accessoryList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('accessory');

        if ($configGroup = $request->param('config_group', '')) {
            $query->where('config_group', $configGroup);
        }
        if ($enabled = $request->param('enabled/d')) {
            $query->where('enabled', $enabled);
        }

        $total = $query->count();
        $list  = $query->order('id', 'asc')->page($page, $pageSize)->select()->toArray();

        $optionTypeMap = [1 => '标准', 2 => '升级', 3 => '新增'];
        foreach ($list as &$item) {
            $item['option_type_text'] = $optionTypeMap[$item['option_type']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 配件新增/编辑
     * POST /api/v1/admin/product/accessory/save
     */
    public function accessorySave(): \think\Response
    {
        $data = $this->app->request->post();

        $id = (int) ($data['id'] ?? 0);
        unset($data['id']);

        // JSON字段
        foreach (['applicable_products', 'compatibility_rules'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
            }
        }

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('accessory')->where('id', $id)->update($data);
            return $this->success(['id' => $id]);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('accessory')->insertGetId($data);
        return $this->success(['id' => $newId]);
    }

    // ==================== 套件管理 ====================

    /**
     * 套件列表
     * GET /api/v1/admin/product/kit/list
     */
    public function kitList(): \think\Response
    {
        // TODO: kit 套餐功能待 database.md 补充 lj_kit 表后启用
        [$page, $pageSize] = $this->getPageParams();
        return $this->success(['list' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 套件新增/编辑
     * POST /api/v1/admin/product/kit/save
     */
    public function kitSave(): \think\Response
    {
        // TODO: kit 套餐功能待 database.md 补充 lj_kit 表后启用
        return $this->error('套件功能暂未开放');
    }

    // ==================== 供应商管理 ====================

    /**
     * 供应商列表
     * GET /api/v1/admin/product/supplier/list
     */
    public function supplierList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('supplier');

        if ($keyword = $request->param('keyword', '')) {
            $query->where('supplier_name', 'like', '%' . $keyword . '%');
        }
        if ($businessStatus = $request->param('business_status/d')) {
            $query->where('business_status', $businessStatus);
        }

        $total = $query->count();
        $list  = $query->order('id', 'desc')->page($page, $pageSize)->select()->toArray();

        $statusMap = [1 => '正常', 2 => '停用'];
        foreach ($list as &$item) {
            $item['business_status_text'] = $statusMap[$item['business_status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 供应商新增/编辑
     * POST /api/v1/admin/product/supplier/save
     */
    public function supplierSave(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['supplier_name'])) {
            return $this->paramError('供应商名称不能为空');
        }

        $id = (int) ($data['id'] ?? 0);
        unset($data['id']);

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('supplier')->where('id', $id)->update($data);
            return $this->success(['id' => $id]);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('supplier')->insertGetId($data);
        return $this->success(['id' => $newId]);
    }

    /**
     * 供应商面料映射管理
     * POST /api/v1/admin/product/supplier/mapping/save
     */
    public function supplierMappingSave(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['fabric_no']) || empty($data['supplier_id']) || empty($data['supplier_fabric_no'])) {
            return $this->paramError('必填项缺失');
        }

        $id = (int) ($data['id'] ?? 0);
        unset($data['id']);

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::name('supplier_fabric_mapping')->where('id', $id)->update($data);
            return $this->success(['id' => $id]);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('supplier_fabric_mapping')->insertGetId($data);
        return $this->success(['id' => $newId]);
    }
}
