<?php
declare(strict_types=1);

namespace app\api\controller;

use think\facade\Db;

/**
 * 商品控制器（门店端）
 * 轨道列表/配件列表/套件信息
 */
class ProductController extends BaseController
{
    /**
     * 获取轨道列表
     * GET /api/v1/store/product/track/list
     */
    public function trackList(): \think\Response
    {
        $request = $this->app->request;

        $query = Db::name('track')
            ->where('enabled', 1);

        if ($trackType = $request->param('track_type/d')) {
            $query->where('track_type', $trackType);
        }
        if ($color = $request->param('color', '')) {
            $query->where('color', $color);
        }

        $list = $query->order('track_type', 'asc')->select()->toArray();

        $trackTypeMap = [1 => '横轨', 2 => '竖轨'];
        foreach ($list as &$item) {
            $item['track_type_text'] = $trackTypeMap[$item['track_type']] ?? '未知';
        }

        return $this->success(['list' => $list]);
    }

    /**
     * 获取选装配件列表
     * GET /api/v1/store/product/accessory/list
     */
    public function accessoryList(): \think\Response
    {
        $request = $this->app->request;

        $query = Db::name('accessory')
            ->where('enabled', 1);

        if ($configGroup = $request->param('config_group', '')) {
            $query->where('config_group', $configGroup);
        }

        $list = $query->order('config_group', 'asc')->select()->toArray();

        $optionTypeMap = [1 => '标准', 2 => '升级', 3 => '新增'];
        foreach ($list as &$item) {
            $item['option_type_text'] = $optionTypeMap[$item['option_type']] ?? '未知';
        }

        return $this->success(['list' => $list]);
    }

    /**
     * 获取套件信息
     * GET /api/v1/store/product/kit/info
     */
    public function kitInfo(): \think\Response
    {
        $storeId = $this->getStoreId();
        $store   = Db::name('store')->where('id', $storeId)->find();
        $customerLevel = $store ? (int) $store['customer_level'] : 1;

        // TODO: kit 套餐功能待 database.md 补充 lj_kit 表后启用
        return $this->success([
            'kit_sku'             => null,
            'kit_name'            => null,
            'kit_price'           => '0.00',
            'customer_level'      => $customerLevel,
            'customer_level_text' => [1 => '认证合作门店', 2 => '银牌合作门店', 3 => '金牌合作门店', 4 => '钻石合作门店', 5 => '战略合伙人'][$customerLevel] ?? '未知',
            'includes'            => [],
        ]);
    }


    /**
     * 墙面控制器商品列表
     * GET /api/v1/products/wall-controller
     */
    public function wallControllerList(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();

        // 墙面控制器属于配件类型
        $list = Db::name('accessory')
            ->where('status', 1)
            ->where('category', 'like', '%墙面控制器%')
            ->page($page, $pageSize)
            ->order('sort_weight', 'desc')
            ->select()
            ->toArray();

        $total = Db::name('accessory')
            ->where('status', 1)
            ->where('category', 'like', '%墙面控制器%')
            ->count();

        return $this->success([
            'list' => $list, 'total' => $total,
            'page' => $page, 'page_size' => $pageSize,
        ]);
    }

}
