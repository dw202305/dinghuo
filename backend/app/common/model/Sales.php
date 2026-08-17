<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 销售人员模型
 * @property int $id
 * @property string $employee_no 员工编号
 * @property string $name 姓名
 * @property string $phone 手机号
 * @property string|null $department 所属部门
 * @property string|null $region 负责区域
 * @property int|null $supervisor_id 上级销售ID
 * @property string|null $crm_user_id CRM用户ID
 * @property int $employment_status 在职状态：1在职 2离职
 */
class Sales extends BaseModel
{
    protected $table = 'lj_sales';

    // 该表不使用软删除
    protected $deleteTime = null;
    protected $defaultSoftDelete = null;

    // 类型转换
    protected $casts = [
        'id' => 'integer',
        'supervisor_id' => 'integer',
        'employment_status' => 'integer',
    ];

    /**
     * 关联上级
     */
    public function superior(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Sales::class, 'supervisor_id', 'id');
    }

    /**
     * 关联下级
     */
    public function subordinates(): \think\model\relation\HasMany
    {
        return $this->hasMany(Sales::class, 'supervisor_id', 'id');
    }

    /**
     * 关联归属历史（作为主归属销售）
     */
    public function primaryAttributions(): \think\model\relation\HasMany
    {
        return $this->hasMany(CustomerOwnershipHistory::class, 'primary_sales_id', 'id');
    }

    /**
     * 在职状态筛选
     */
    public function scopeActive($query): void
    {
        $query->where('employment_status', 1);
    }

    /**
     * 根据员工编号查找
     */
    public function scopeOfEmployeeNo($query, string $employeeNo): void
    {
        $query->where('employee_no', $employeeNo);
    }
}
