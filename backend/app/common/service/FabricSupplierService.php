<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\FabricSupplier;
use app\common\model\FabricSupplierMapping;
use think\exception\ValidateException;

/**
 * 面料供应商映射服务
 * 负责供应商CRUD和面料-供应商映射管理
 *
 * 逻辑自 AdminProductController::supplierList / supplierSave / supplierMappingSave 迁移而来，
 * 统一使用 FabricSupplier / FabricSupplierMapping 模型（对应 lj_fabric_supplier / lj_fabric_supplier_mapping 表）。
 *
 * @see docs/dev_specification_v1.md 4.2节
 */
class FabricSupplierService extends BaseService
{
    /**
     * 经营状态文案映射
     */
    const BUSINESS_STATUS_MAP = [
        1 => '正常',
        2 => '停用',
    ];

    /**
     * 供应商分页列表
     *
     * @param int $page 页码
     * @param int $pageSize 每页条数
     * @param string $keyword 供应商名称关键词
     * @param int $businessStatus 经营状态：1正常 2停用，0不限
     * @return array{list: array, total: int, page: int, page_size: int}
     */
    public function supplierList(int $page = 1, int $pageSize = 10, string $keyword = '', int $businessStatus = 0): array
    {
        $query = FabricSupplier::where([]);

        if ($keyword !== '') {
            $query->where('supplier_name', 'like', '%' . $keyword . '%');
        }
        if ($businessStatus > 0) {
            $query->where('business_status', $businessStatus);
        }

        $total = $query->count();
        $list  = $query->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['business_status_text'] = self::BUSINESS_STATUS_MAP[$item['business_status']] ?? '';
        }
        unset($item);

        return [
            'list'      => $list,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 供应商新增/编辑
     *
     * @param array $data 供应商数据（可含 id 表示编辑）
     * @return int 供应商ID
     * @throws ValidateException
     */
    public function supplierSave(array $data): int
    {
        if (empty($data['supplier_name'])) {
            throw new ValidateException('供应商名称不能为空');
        }

        $id = (int) ($data['id'] ?? 0);
        unset($data['id']);

        if ($id > 0) {
            $supplier = FabricSupplier::find($id);
            if (!$supplier) {
                throw new ValidateException('供应商不存在');
            }

            $data['updated_at'] = date('Y-m-d H:i:s');
            $supplier->save($data);

            return $id;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $supplier = FabricSupplier::create($data);

        return (int) $supplier->id;
    }

    /**
     * 供应商面料映射新增/编辑
     *
     * @param array $data 映射数据（可含 id 表示编辑）
     * @return int 映射ID
     * @throws ValidateException
     */
    public function supplierMappingSave(array $data): int
    {
        if (empty($data['fabric_no']) || empty($data['supplier_id']) || empty($data['supplier_fabric_no'])) {
            throw new ValidateException('必填项缺失');
        }

        $id = (int) ($data['id'] ?? 0);
        unset($data['id']);

        if ($id > 0) {
            $mapping = FabricSupplierMapping::find($id);
            if (!$mapping) {
                throw new ValidateException('供应商映射不存在');
            }

            $data['updated_at'] = date('Y-m-d H:i:s');
            $mapping->save($data);

            return $id;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $mapping = FabricSupplierMapping::create($data);

        return (int) $mapping->id;
    }
}
