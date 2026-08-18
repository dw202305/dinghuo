<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 基础模型
 * 所有业务模型继承此类
 */
class BaseModel extends Model
{
    // 开启自动时间戳（库表统一使用 created_at / updated_at，
    // 而非 ThinkPHP 默认的 create_time / update_time）
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 软删除
    protected $deleteTime = 'deleted_at';
    protected $defaultSoftDelete = null;

    // JSON 字段自动转换
    protected $json = [];

    /**
     * 获取分页列表
     * @param array $where 查询条件
     * @param string $order 排序
     * @param int $pageSize 每页条数
     * @return \think\Paginator
     */
    public function getList(array $where = [], string $order = 'id desc', int $pageSize = 20): \think\Paginator
    {
        return $this->where($where)
            ->order($order)
            ->paginate($pageSize);
    }
}
