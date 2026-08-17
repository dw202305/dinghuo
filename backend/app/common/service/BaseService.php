<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

/**
 * 基础 Service
 * 提供事务、日志等通用能力
 */
abstract class BaseService
{
    /**
     * 开启事务执行
     * @param callable $callback 事务回调
     * @return mixed
     */
    protected function transaction(callable $callback): mixed
    {
        try {
            return Db::transaction($callback);
        } catch (\Throwable $e) {
            Log::error('事务执行失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 记录操作日志
     * @param string $module 模块
     * @param string $action 操作
     * @param string $targetType 目标类型
     * @param int $targetId 目标ID
     * @param string $targetNo 目标编号
     * @param array|null $beforeData 变更前数据
     * @param array|null $afterData 变更后数据
     * @param int $operatorId 操作人ID
     * @param string $operatorName 操作人姓名
     * @param string $operatorRole 操作人角色
     * @param string $remark 备注
     * @return bool
     */
    protected function logOperation(
        string $module,
        string $action,
        string $targetType,
        int $targetId,
        string $targetNo = '',
        ?array $beforeData = null,
        ?array $afterData = null,
        int $operatorId = 0,
        string $operatorName = '',
        string $operatorRole = '',
        string $remark = '',
    ): bool {
        try {
            return Db::name('operation_log')->insert([
                'module'        => $module,
                'action'        => $action,
                'target_type'   => $targetType,
                'target_id'     => $targetId,
                'target_no'     => $targetNo,
                'before_data'   => $beforeData ? json_encode($beforeData, JSON_UNESCAPED_UNICODE) : null,
                'after_data'    => $afterData ? json_encode($afterData, JSON_UNESCAPED_UNICODE) : null,
                'operator_id'   => $operatorId,
                'operator_name' => $operatorName,
                'operator_role' => $operatorRole,
                'ip_address'    => request()->ip(),
                'user_agent'    => request()->header('User-Agent', ''),
                'remark'        => $remark,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::error('操作日志写入失败: ' . $e->getMessage());
            return false;
        }
    }
}
