<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AdminValidate;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 后台门店管理控制器
 * 门店CRUD/状态/联系人/账号管理
 */
class AdminStoreController extends BaseController
{
    /**
     * 门店列表
     * GET /api/v1/admin/store/list
     */
    public function list(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('store')
            ->alias('s')
            ->leftJoin('partner p', 'p.id = s.partner_id')
            ->leftJoin('sales_person sp', 'sp.id = s.primary_sales_id')
            ->leftJoin('store_contact sc', 'sc.id = s.primary_contact_id');

        if ($keyword = $request->param('keyword', '')) {
            $query->where('s.store_no|s.store_name|sc.contact_name', 'like', '%' . $keyword . '%');
        }
        if ($storeType = $request->param('store_type/d')) {
            $query->where('s.store_type', $storeType);
        }
        if ($customerLevel = $request->param('customer_level/d')) {
            $query->where('s.customer_level', $customerLevel);
        }
        if ($channelMode = $request->param('channel_mode/d')) {
            $query->where('s.channel_mode', $channelMode);
        }
        if ($partnerId = $request->param('partner_id/d')) {
            $query->where('s.partner_id', $partnerId);
        }
        if ($primarySalesId = $request->param('primary_sales_id/d')) {
            $query->where('s.primary_sales_id', $primarySalesId);
        }
        if ($status = $request->param('status/d')) {
            $query->where('s.status', $status);
        }
        if ($province = $request->param('province', '')) {
            $query->where('s.province', $province);
        }
        if ($city = $request->param('city', '')) {
            $query->where('s.city', $city);
        }

        $total = $query->count();
        $list  = $query->field([
                's.id as store_id', 's.store_no', 's.store_name', 's.business_entity',
                's.customer_level', 's.channel_mode', 'p.business_entity as partner_name',
                'sp.name as primary_sales_name', 's.province', 's.city',
                's.contact_phone', 'sc.contact_name as primary_contact_name', 's.status',
                's.cooperation_start_date',
            ])
            ->order('s.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $levelMap  = [1 => '认证合作门店', 2 => '银牌合作门店', 3 => '金牌合作门店', 4 => '钻石合作门店', 5 => '战略合伙人'];
        $channelMap = [1 => '城市合伙人渠道', 2 => '公司直营'];
        $statusMap = [1 => '正常', 2 => '停用', 3 => '待审核'];

        foreach ($list as &$item) {
            $item['customer_level_text'] = $levelMap[$item['customer_level']] ?? '';
            $item['channel_mode_text']   = $channelMap[$item['channel_mode']] ?? '';
            $item['status_text']         = $statusMap[$item['status']] ?? '';
        }

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    /**
     * 门店详情
     * GET /api/v1/admin/store/detail
     */
    public function detail(): \think\Response
    {
        $storeId = (int) $this->app->request->param('store_id', 0);
        if ($storeId <= 0) {
            return $this->paramError('门店ID不能为空');
        }

        $store = Db::name('store')->where('id', $storeId)->find();
        if (!$store) {
            return $this->error('门店不存在', 1004);
        }

        // 联系人列表
        $contacts = Db::name('store_contact')
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->select()->toArray();

        $contactTypeMap = [1 => '负责人', 2 => '采购', 3 => '下单', 4 => '财务', 5 => '安装', 6 => '售后', 7 => '收货人'];
        foreach ($contacts as &$c) {
            $c['contact_id'] = $c['id'];
            $c['contact_type_text'] = $contactTypeMap[$c['contact_type']] ?? '未知';
        }

        // 账号列表
        $accounts = Db::name('account')
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->field('id as account_id, phone, real_name, account_role, verify_status, status, last_login_at')
            ->select()->toArray();

        $roleMap = [1 => '门店管理员', 2 => '下单员', 3 => '财务', 4 => '安装售后', 5 => '只读'];
        foreach ($accounts as &$a) {
            $a['account_role_text'] = $roleMap[$a['account_role']] ?? '未知';
        }

        $store['contacts'] = $contacts;
        $store['accounts'] = $accounts;

        return $this->success($store);
    }

    /**
     * 新增门店
     * POST /api/v1/admin/store/create
     */
    public function create(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AdminValidate::class)->scene('store_create')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        // 检查编号唯一
        if (Db::name('store')->where('store_no', $data['store_no'])->find()) {
            return $this->error('门店编号已存在', 1005);
        }

        $storeId = Db::transaction(function () use ($data) {
            $storeData = [
                'store_no' => $data['store_no'], 'store_name' => $data['store_name'],
                'business_entity' => $data['business_entity'] ?? '', 'credit_code' => $data['credit_code'] ?? '',
                'store_type' => $data['store_type'], 'customer_level' => $data['customer_level'],
                'channel_mode' => $data['channel_mode'], 'partner_id' => $data['partner_id'] ?? null,
                'primary_sales_id' => $data['primary_sales_id'], 'secondary_sales_id' => $data['secondary_sales_id'] ?? null,
                'province' => $data['province'] ?? '', 'city' => $data['city'] ?? '',
                'district' => $data['district'] ?? '', 'address' => $data['address'] ?? '',
                'contact_phone' => $data['contact_phone'] ?? '', 'wechat' => $data['wechat'] ?? '',
                'showroom_photos' => json_encode($data['showroom_photos'] ?? [], JSON_UNESCAPED_UNICODE),
                'invoice_title' => $data['invoice_title'] ?? '', 'tax_no' => $data['tax_no'] ?? '',
                'primary_contact_name' => $data['primary_contact_name'],
                'primary_contact_phone' => $data['primary_contact_phone'],
                'cooperation_start_date' => $data['cooperation_start_date'] ?? null,
                'status' => 1,
            ];
            // 注：lj_store 无 primary_contact_name/phone/created_at 列，主联系人写入联系人表
            unset($storeData['primary_contact_name'], $storeData['primary_contact_phone']);
            $storeId = Db::name('store')->insertGetId($storeData);

            // 创建默认联系人
            Db::name('store_contact')->insert([
                'store_id' => $storeId, 'contact_name' => $data['primary_contact_name'],
                'phone' => $data['primary_contact_phone'], 'contact_type' => 1,
                'is_primary' => 1, 'receive_order_notify' => 1, 'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $storeId;
        });

        return $this->success(['store_id' => $storeId, 'store_no' => $data['store_no']]);
    }

    /**
     * 更新门店
     * PUT /api/v1/admin/store/update
     */
    public function update(): \think\Response
    {
        $data = $this->app->request->post();
        $storeId = (int) ($data['store_id'] ?? 0);

        if ($storeId <= 0) {
            return $this->paramError('门店ID不能为空');
        }

        $store = Db::name('store')->where('id', $storeId)->find();
        if (!$store) {
            return $this->error('门店不存在', 1004);
        }

        unset($data['store_id']);
        // JSON字段编码
        if (isset($data['showroom_photos']) && is_array($data['showroom_photos'])) {
            $data['showroom_photos'] = json_encode($data['showroom_photos'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['invoice_info']) && is_array($data['invoice_info'])) {
            $data['invoice_info'] = json_encode($data['invoice_info'], JSON_UNESCAPED_UNICODE);
        }
        // lj_store 无 updated_at 列，不写入时间戳

        Db::name('store')->where('id', $storeId)->update($data);

        return $this->success(null, '更新成功');
    }

    /**
     * 停用/启用门店
     * PUT /api/v1/admin/store/status
     */
    public function status(): \think\Response
    {
        $storeId = (int) $this->app->request->post('store_id', 0);
        $status  = (int) $this->app->request->post('status', 0);

        if ($storeId <= 0 || !in_array($status, [1, 2])) {
            return $this->paramError('参数错误');
        }

        $store = Db::name('store')->where('id', $storeId)->find();
        if (!$store) {
            return $this->error('门店不存在', 1004);
        }

        Db::name('store')->where('id', $storeId)->update([
            'status' => $status, 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success(null, $status === 1 ? '已启用' : '已停用');
    }

    /**
     * 管理门店联系人
     * POST /api/v1/admin/store/contact/save
     */
    public function contactSave(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['store_id']) || empty($data['contact_name']) || empty($data['phone']) || empty($data['contact_type'])) {
            return $this->paramError('必填项缺失');
        }

        $contactId = (int) ($data['contact_id'] ?? 0);

        if ($contactId > 0) {
            // 更新
            unset($data['contact_id']);
            Db::name('store_contact')->where('id', $contactId)->update($data);
            return $this->success(['contact_id' => $contactId]);
        }

        // 新增
        // 检查手机号重复
        $exists = Db::name('store_contact')
            ->where('store_id', $data['store_id'])
            ->where('phone', $data['phone'])
            ->where('status', 1)
            ->find();

        if ($exists) {
            return $this->error('该手机号已是该门店联系人', 1005);
        }

        $data['status'] = 1;
        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('store_contact')->insertGetId($data);

        return $this->success(['contact_id' => $newId]);
    }

    /**
     * 管理门店账号
     * POST /api/v1/admin/store/account/save
     */
    public function accountSave(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['store_id']) || empty($data['phone']) || empty($data['account_role'])) {
            return $this->paramError('必填项缺失');
        }

        $accountId = (int) ($data['account_id'] ?? 0);

        if ($accountId > 0) {
            // 更新
            unset($data['account_id']);
            Db::name('account')->where('id', $accountId)->update($data);
            return $this->success(['account_id' => $accountId]);
        }

        // 新增 - 检查手机号
        if (Db::name('account')->where('phone', $data['phone'])->find()) {
            return $this->error('该手机号已被注册', 1005);
        }

        $data['status'] = 1;
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = Db::name('account')->insertGetId($data);

        return $this->success(['account_id' => $newId]);
    }


    /**
     * 客户等级列表（基于store表的customer_level字段）
     * GET /api/v1/admin/customer-levels
     */
    public function customerLevelList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('store')->where('status', 1);

        if ($level = $request->param('customer_level/d')) {
            $query->where('customer_level', $level);
        }
        if ($keyword = $request->param('keyword', '')) {
            $query->where('store_name|store_no', 'like', '%' . $keyword . '%');
        }

        $total = $query->count();
        $list = $query->field([
                'id as store_id', 'store_no', 'store_name',
                'customer_level', 'channel_mode', 'partner_id',
                'contact_phone', 'cooperation_start_date',
            ])
            ->order('customer_level', 'asc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $levelMap = [1 => '认证合作门店', 2 => '城市合伙人', 3 => '产品体验客户', 4 => '特殊合同客户', 5 => '大B客户'];
        foreach ($list as &$item) {
            $item['customer_level_text'] = $levelMap[$item['customer_level']] ?? '未知';
        }

        return $this->success([
            'list' => $list, 'total' => $total,
            'page' => $page, 'page_size' => $pageSize,
        ]);
    }

    /**
     * 修改客户等级
     * PUT /api/v1/admin/customer-levels/:id
     */
    public function customerLevelUpdate(int $id): \think\Response
    {
        $data = $this->app->request->post();
        $newLevel = (int)($data['customer_level'] ?? 0);

        if ($newLevel < 1 || $newLevel > 5) {
            return $this->paramError('客户等级值无效（1-5）');
        }

        $store = Db::name('store')->where('id', $id)->find();
        if (!$store) {
            return $this->error('门店不存在', 1004);
        }

        $oldLevel = $store['customer_level'];
        Db::name('store')->where('id', $id)->update([
            'customer_level' => $newLevel,
        ]);

        // 记录归属变更日志
        $levelMap = [1 => '认证合作门店', 2 => '城市合伙人', 3 => '产品体验客户', 4 => '特殊合同客户', 5 => '大B客户'];
        Db::name('operation_log')->insert([
            'operator_type' => 'admin',
            'operator_id' => $this->getAccountId(),
            'operator_name' => '管理员',
            'action' => 'customer_level_change',
            'target_type' => 'store',
            'target_id' => $id,
            'target_no' => $store['store_no'],
            'detail' => json_encode([
                'old_level' => $levelMap[$oldLevel] ?? $oldLevel,
                'new_level' => $levelMap[$newLevel] ?? $newLevel,
            ], JSON_UNESCAPED_UNICODE),
            'ip' => $this->app->request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success(null, '等级更新成功');
    }

}
