<?php
declare(strict_types=1);

namespace app\common\service;

/**
 * 审计服务
 * 负责关键操作的审计日志记录
 *
 * 内部复用 BaseService::logOperation 写入 operation_log 表，
 * 为上层提供统一的审计日志入口。
 *
 * @see docs/dev_specification_v1.md 18.2节
 */
class AuditService extends BaseService
{
    /**
     * 记录审计日志
     *
     * @param string $module 模块
     * @param string $action 操作
     * @param string $targetType 目标类型
     * @param int|string $targetId 目标ID
     * @param string|null $beforeData 变更前数据（JSON 字符串）
     * @param string|null $afterData 变更后数据（JSON 字符串）
     * @param string|null $remark 备注
     * @param int $operatorId 操作人ID（缺省时自动读取当前请求上下文）
     * @param string $operatorName 操作人姓名
     * @param string $operatorRole 操作人角色
     * @return bool 是否写入成功
     */
    public function log(
        string $module,
        string $action,
        string $targetType,
        int|string $targetId,
        ?string $beforeData = null,
        ?string $afterData = null,
        ?string $remark = null,
        int $operatorId = 0,
        string $operatorName = '',
        string $operatorRole = '',
    ): bool {
        return $this->logOperation(
            module: $module,
            action: $action,
            targetType: $targetType,
            targetId: (int) $targetId,
            beforeData: $beforeData !== null ? $this->decodeJson($beforeData) : null,
            afterData: $afterData !== null ? $this->decodeJson($afterData) : null,
            operatorId: $operatorId,
            operatorName: $operatorName,
            operatorRole: $operatorRole,
            remark: $remark ?? '',
        );
    }

    /**
     * 将 JSON 字符串解码为数组，非法 JSON 时原样包装保留
     *
     * @param string $raw 原始字符串
     * @return array
     */
    private function decodeJson(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return ['raw' => $raw];
    }
}
