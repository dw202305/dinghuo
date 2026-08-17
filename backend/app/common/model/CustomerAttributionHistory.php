<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * @deprecated 使用 CustomerOwnershipHistory 代替
 * 此类保留仅为向后兼容，实际映射到 lj_customer_attribution_history 表
 */
class CustomerAttributionHistory extends CustomerOwnershipHistory
{
    protected $table = 'lj_customer_attribution_history';
}
