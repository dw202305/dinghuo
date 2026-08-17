<?php

declare(strict_types=1);

namespace app\common\enum;

/**
 * 发票状态枚举
 * 对应 lj_invoice_request 表 status 字段
 */
enum InvoiceStatus: int
{
    /** 待审核 */
    case PENDING_REVIEW = 1;

    /** 已审核待开票 */
    case APPROVED = 2;

    /** 已开票 */
    case INVOICED = 3;

    /** 已驳回 */
    case REJECTED = 4;

    /**
     * 获取状态标签
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => '待审核',
            self::APPROVED       => '已审核待开票',
            self::INVOICED       => '已开票',
            self::REJECTED       => '已驳回',
        };
    }

    /**
     * 是否可审核
     * @return bool
     */
    public function canReview(): bool
    {
        return $this === self::PENDING_REVIEW;
    }

    /**
     * 是否可开票
     * @return bool
     */
    public function canInvoice(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * 是否已终态
     * @return bool
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::INVOICED, self::REJECTED]);
    }

    /**
     * 是否可驳回
     * @return bool
     */
    public function canReject(): bool
    {
        return in_array($this, [self::PENDING_REVIEW, self::APPROVED]);
    }

    /**
     * 获取所有状态选项
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
