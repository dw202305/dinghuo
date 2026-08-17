<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 操作日志模型
 * @property int $id
 * @property string $module 模块
 * @property string $action 操作
 * @property string $target_type 目标类型
 * @property int $target_id 目标ID
 * @property string $target_no 目标编号
 * @property int $operator_id 操作人ID
 * @property string $operator_name 操作人姓名
 * @property string $operator_role 操作人角色
 */
class OperationLog extends BaseModel
{
    protected $table = 'lj_operation_log';

    // 该表不使用软删除，日志类表
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // JSON 字段
    protected $json = ['before_data', 'after_data'];

    // 仅创建，不更新
    protected $update = [];

    /**
     * 按模块筛选
     * @param \think\db\Query $query
     * @param string $module
     * @return void
     */
    public function scopeOfModule($query, string $module): void
    {
        $query->where('module', $module);
    }

    /**
     * 按目标筛选
     * @param \think\db\Query $query
     * @param string $targetType
     * @param int $targetId
     * @return void
     */
    public function scopeOfTarget($query, string $targetType, int $targetId): void
    {
        $query->where('target_type', $targetType)
              ->where('target_id', $targetId);
    }

    /**
     * 按操作人筛选
     * @param \think\db\Query $query
     * @param int $operatorId
     * @return void
     */
    public function scopeOfOperator($query, int $operatorId): void
    {
        $query->where('operator_id', $operatorId);
    }

    /**
     * 按模块和操作筛选
     * @param \think\db\Query $query
     * @param string $module
     * @param string $action
     * @return void
     */
    public function scopeOfModuleAction($query, string $module, string $action): void
    {
        $query->where('module', $module)->where('action', $action);
    }
}
