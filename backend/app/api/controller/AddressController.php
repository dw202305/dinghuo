<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\AddressValidate;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 收货地址控制器（门店端）
 * 地址CRUD+设默认
 */
class AddressController extends BaseController
{
    /**
     * 获取地址列表
     * GET /api/v1/store/address/list
     */
    public function list(): \think\Response
    {
        $storeId = $this->getStoreId();
        $addressType = $this->app->request->param('address_type/d');

        $query = Db::name('store_address')
            ->where('store_id', $storeId)
            ->where('status', 1);

        if ($addressType) {
            $query->where('address_type', $addressType);
        }

        $list = $query->order('is_default', 'desc')->order('id', 'desc')->select()->toArray();

        $addressTypeMap = [1 => '门店地址', 2 => '仓库地址', 3 => '终端客户地址'];
        foreach ($list as &$item) {
            $item['address_id'] = $item['id'];
            $item['address_type_text'] = $addressTypeMap[$item['address_type']] ?? '未知';
        }

        return $this->success(['list' => $list]);
    }

    /**
     * 新增地址
     * POST /api/v1/store/address/create
     */
    public function create(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(AddressValidate::class)->scene('create')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $data['store_id'] = $this->getStoreId();
        $data['status']   = 1;
        $data['created_at'] = date('Y-m-d H:i:s');

        // 如果设为默认，取消其他默认
        if (!empty($data['is_default'])) {
            Db::name('store_address')
                ->where('store_id', $data['store_id'])
                ->update(['is_default' => 0]);
        }

        $addressId = Db::name('store_address')->insertGetId($data);

        return $this->success(['address_id' => $addressId]);
    }

    /**
     * 更新地址
     * PUT /api/v1/store/address/update
     */
    public function update(): \think\Response
    {
        $data = $this->app->request->post();

        if (empty($data['address_id'])) {
            return $this->paramError('地址ID不能为空');
        }

        try {
            validate(AddressValidate::class)->scene('update')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $address = Db::name('store_address')
            ->where('id', $data['address_id'])
            ->where('store_id', $this->getStoreId())
            ->find();

        if (!$address) {
            return $this->error('地址不存在', 1004);
        }

        unset($data['address_id']);
        Db::name('store_address')->where('id', $address['id'])->update($data);

        return $this->success(null, '更新成功');
    }

    /**
     * 删除地址（软删除）
     * DELETE /api/v1/store/address/delete
     */
    public function delete(): \think\Response
    {
        $addressId = (int) $this->app->request->post('address_id', 0);

        if ($addressId <= 0) {
            return $this->paramError('地址ID不能为空');
        }

        $address = Db::name('store_address')
            ->where('id', $addressId)
            ->where('store_id', $this->getStoreId())
            ->find();

        if (!$address) {
            return $this->error('地址不存在', 1004);
        }

        Db::name('store_address')->where('id', $addressId)->update([
            'status'     => 0,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success(null, '删除成功');
    }

    /**
     * 设置默认地址
     * PUT /api/v1/store/address/set-default
     */
    public function setDefault(): \think\Response
    {
        $addressId = (int) $this->app->request->post('address_id', 0);

        if ($addressId <= 0) {
            return $this->paramError('地址ID不能为空');
        }

        $storeId = $this->getStoreId();

        $address = Db::name('store_address')
            ->where('id', $addressId)
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->find();

        if (!$address) {
            return $this->error('地址不存在', 1004);
        }

        // 事务：取消所有默认 → 设置当前为默认
        Db::transaction(function () use ($storeId, $addressId) {
            Db::name('store_address')
                ->where('store_id', $storeId)
                ->update(['is_default' => 0]);

            Db::name('store_address')
                ->where('id', $addressId)
                ->update(['is_default' => 1]);
        });

        return $this->success(null, '设置成功');
    }


    /**
     * 地址详情
     * GET /api/v1/addresses/:id
     */
    public function detail(int $id): \think\Response
    {
        $storeId = $this->getStoreId();
        $address = Db::name('store_address')
            ->where('id', $id)
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->find();

        if (!$address) {
            return $this->error('地址不存在', 1004);
        }

        $addressTypeMap = [1 => '门店地址', 2 => '仓库地址', 3 => '终端客户地址'];
        $address['address_id'] = $address['id'];
        $address['address_type_text'] = $addressTypeMap[$address['address_type']] ?? '未知';

        return $this->success($address);
    }

}
