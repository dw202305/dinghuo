<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\FabricValidate;
use app\common\support\Money;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 面料控制器（门店端）
 * 面料列表/详情/收藏/常用/最近
 */
class FabricController extends BaseController
{
    /**
     * 面料列表
     * GET /api/v1/store/fabric/list
     */
    public function list(): \think\Response
    {
        [$page, $pageSize] = $this->getPageParams();
        $request = $this->app->request;

        $query = Db::name('fabric')
            ->where('listing_status', 1)
            ->where('orderable', 1);

        // 关键词搜索
        $keyword = $request->param('keyword', '');
        if ($keyword) {
            $query->where('fabric_no|name|color_name', 'like', '%' . $keyword . '%');
        }

        // 筛选
        if ($series = $request->param('series', '')) {
            $query->where('series', $series);
        }
        if ($material = $request->param('material', '')) {
            $query->where('material', $material);
        }
        if ($colorName = $request->param('color_name', '')) {
            $query->where('color_name', $colorName);
        }
        if ($functionTag = $request->param('function_tag', '')) {
            $query->where('function_tags', 'like', '%' . $functionTag . '%');
        }
        // 批次2c：deploy lj_fabric 价格列为 price_per_sqm_cent（整数分），
        // 入参 price_min/price_max 仍为元，内部转分比较（禁 float）
        if ($priceMin = $request->param('price_min')) {
            $query->where('price_per_sqm_cent', '>=', Money::mulCent((string) $priceMin, 100));
        }
        if ($priceMax = $request->param('price_max')) {
            $query->where('price_per_sqm_cent', '<=', Money::mulCent((string) $priceMax, 100));
        }
        if ($stockStatus = $request->param('stock_status/d')) {
            $query->where('stock_status', $stockStatus);
        }

        // 排序
        $sort = $request->param('sort', 'hot');
        switch ($sort) {
            case 'price_asc':
                $query->order('price_per_sqm_cent', 'asc');
                break;
            case 'price_desc':
                $query->order('price_per_sqm_cent', 'desc');
                break;
            case 'newest':
                $query->order('id', 'desc');
                break;
            default: // hot
                $query->order('sort_weight', 'desc');
        }

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        // 获取筛选选项
        $filterOptions = [
            'series_list'        => Db::name('fabric')->where('listing_status', 1)->distinct(true)->column('series'),
            'material_list'      => Db::name('fabric')->where('listing_status', 1)->distinct(true)->column('material'),
            'function_tag_list'  => ['遮光', '阻燃', '防水', '防霉'],
        ];

        // 处理列表数据
        $storeId = $this->getStoreId();
        foreach ($list as &$item) {
            $item['fabric_id'] = $item['id'];
            $item['function_tags'] = json_decode($item['function_tags'] ?? '[]', true);
            $item['stock_status_text'] = $this->getStockStatusText((int) $item['stock_status']);
            // API 兼容：额外输出元显示价（仅展示用，不参与结算）
            $item['price_per_sqm'] = number_format(((int) ($item['price_per_sqm_cent'] ?? 0)) / 100, 2, '.', '');
            // 收藏状态（查收藏表）
            $item['is_favorited'] = false; // TODO: fabric_favorite 表待启用; if(false)
        }

        return $this->success([
            'list'           => $list,
            'total'          => $total,
            'page'           => $page,
            'page_size'      => $pageSize,
            'filter_options' => $filterOptions,
        ]);
    }

    /**
     * 面料详情
     * GET /api/v1/store/fabric/detail
     */
    public function detail(): \think\Response
    {
        $fabricNo = $this->app->request->param('fabric_no', '');

        try {
            validate(FabricValidate::class)->scene('detail')->check(['fabric_no' => $fabricNo]);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $fabric = Db::name('fabric')
            ->where('fabric_no', $fabricNo)
            ->where('listing_status', 1)
            ->find();

        if (!$fabric) {
            return $this->error('面料不存在', 1004);
        }

        $fabric['fabric_id']      = $fabric['id'];
        $fabric['function_tags']  = json_decode($fabric['function_tags'] ?? '[]', true);
        $fabric['texture_tags']   = json_decode($fabric['texture_tags'] ?? '[]', true);
        $fabric['detail_images']  = json_decode($fabric['detail_images'] ?? '[]', true);
        $fabric['stock_status_text'] = $this->getStockStatusText((int) $fabric['stock_status']);
        // API 兼容：额外输出元显示价（仅展示用）
        $fabric['price_per_sqm']  = number_format(((int) ($fabric['price_per_sqm_cent'] ?? 0)) / 100, 2, '.', '');


        return $this->success($fabric);
    }

    /**
     * 收藏面料
     * POST /api/v1/store/fabric/favorite
     */
    public function favorite(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(FabricValidate::class)->scene('favorite')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        $storeId  = $this->getStoreId();
        $fabricNo = $data['fabric_no'];
        $action   = (int) $data['action'];

        if ($action === 1) {
            // TODO: fabric_favorite 表待 database.md 补充后启用
        } else {
            // TODO: fabric_favorite 表待 database.md 补充后启用
        }

        return $this->success(null, '操作成功');
    }

    /**
     * 获取常用面料（收藏列表）
     * GET /api/v1/store/fabric/favorites
     */
    public function favorites(): \think\Response
    {
        // TODO: fabric_favorite 表待 database.md 补充后启用
        [$page, $pageSize] = $this->getPageParams();
        return $this->success([
            'list'      => [],
            'total'     => 0,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 获取最近使用面料
     * GET /api/v1/store/fabric/recent
     */
    public function recent(): \think\Response
    {
        $limit = min(50, max(1, (int) $this->app->request->param('limit', 10)));
        $storeId = $this->getStoreId();

        // 从订单明细中获取最近使用的面料
        $list = Db::name('order_item')
            ->alias('oi')
            ->leftJoin('order o', 'o.id = oi.order_id')
            ->leftJoin('fabric f', 'f.fabric_no = oi.fabric_no')
            ->where('o.transaction_id', $storeId)
            ->where('o.transaction_type', 1)
            ->whereNull('o.deleted_at')
            ->group('oi.fabric_no')
            ->field([
                'oi.fabric_no',
                'f.name',
                'f.series',
                'f.price_per_sqm_cent',
                'f.main_image',
                'f.stock_status',
                Db::raw('MAX(o.created_at) as last_used_at'),
            ])
            ->order('last_used_at', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        // API 兼容：额外输出元显示价（仅展示用）
        foreach ($list as &$item) {
            $item['price_per_sqm'] = number_format(((int) ($item['price_per_sqm_cent'] ?? 0)) / 100, 2, '.', '');
        }

        return $this->success(['list' => $list]);
    }

    /**
     * 库存状态文本
     */
    private function getStockStatusText(int $status): string
    {
        return [1 => '充足', 2 => '紧张', 3 => '缺货'][$status] ?? '未知';
    }


    /**
     * 面料系列列表
     * GET /api/v1/fabrics/series
     */
    public function series(): \think\Response
    {
        $seriesList = Db::name('fabric')
            ->where('listing_status', 1)
            ->where('series', '<>', '')
            ->distinct(true)
            ->column('series');

        return $this->success(['list' => $seriesList]);
    }

    /**
     * 面料筛选选项
     * GET /api/v1/fabrics/filter-options
     */
    public function filterOptions(): \think\Response
    {
        $options = [
            'series_list' => Db::name('fabric')
                ->where('listing_status', 1)
                ->where('series', '<>', '')
                ->distinct(true)
                ->column('series'),
            'material_list' => Db::name('fabric')
                ->where('listing_status', 1)
                ->where('material', '<>', '')
                ->distinct(true)
                ->column('material'),
            'function_tag_list' => ['遮光', '阻燃', '防水', '防霉', '隔音', '隔热'],
            'stock_status_list' => [
                ['value' => 1, 'label' => '充足'],
                ['value' => 2, 'label' => '紧张'],
                ['value' => 3, 'label' => '缺货'],
            ],
        ];

        return $this->success($options);
    }

}
