<?php
declare(strict_types=1);

namespace app\common\enum;

/**
 * 统一业务错误码常量
 *
 * 错误码分段规范（对齐 dev_specification v1.0 §14.3）：
 *   0    : 成功
 *   1xxx : 参数和格式
 *   2xxx : 认证和令牌
 *   3xxx : 权限和数据访问
 *   4xxx : 业务冲突（库存/状态/价格/支付/面料）
 *   5xxx : 系统/数据库/第三方服务
 */
class ErrorCode
{
    // ── 成功 ──────────────────────────────────────────
    public const SUCCESS = 0;

    // ── 1xxx: 参数和格式 ──────────────────────────────
    /** 参数无效（格式、范围、类型） */
    public const PARAM_INVALID = 1001;
    /** 必填参数缺失 */
    public const PARAM_MISSING = 1002;
    /** 请求体格式错误（非合法 JSON 等） */
    public const FORMAT_ERROR = 1003;

    // ── 2xxx: 认证和令牌 ──────────────────────────────
    /** 未认证（未携带 Token 或 Token 无效） */
    public const UNAUTHENTICATED = 2001;
    /** Token 已过期 */
    public const TOKEN_EXPIRED = 2002;
    /** Token 无效（签名错误、payload 不合法） */
    public const TOKEN_INVALID = 2003;
    /** 微信未绑定门店账号（需联系总部绑定，批次3新增） */
    public const WECHAT_NOT_BOUND = 2004;

    // ── 3xxx: 权限和数据访问 ──────────────────────────
    /** 无权限（角色不足 / 数据越权） */
    public const FORBIDDEN = 3001;
    /** 资源不存在 */
    public const DATA_NOT_FOUND = 3002;

    // ── 4xxx: 业务冲突 ────────────────────────────────
    // 40xx: 库存 & 订单状态
    /** 套件库存不足（PRD §4.4，规范 §14.3） */
    public const INVENTORY_INSUFFICIENT = 4001;
    /** 订单价格已失效（价格锁定 30 天，规范 §8.3） */
    public const PRICE_EXPIRED = 4002;
    /** 非法订单状态转换（规范 §10） */
    public const ILLEGAL_STATUS_TRANSITION = 4003;
    /** 订单已进入生产，不可取消/修改（PRD §11.3） */
    public const ORDER_IN_PRODUCTION = 4004;
    /** 请求过于频繁（短信验证码 60 秒防刷等限流场景，批次5新增；HTTP 映射 429） */
    public const RATE_LIMITED = 4029;

    // 41xx: 支付 & 资金
    /** 支付回调已处理（幂等拦截，规范 §12.2） */
    public const PAYMENT_CALLBACK_PROCESSED = 4101;
    /** 支付金额与订单金额不一致（规范 §12.2） */
    public const PAYMENT_AMOUNT_MISMATCH = 4102;
    /** 余额不足（PRD §4.9.5，规范 §12.0） */
    public const BALANCE_INSUFFICIENT = 4103;
    /** 不支持混合支付（PRD §4.9.4，规范 §12.0） */
    public const MIXED_PAYMENT_NOT_SUPPORTED = 4104;
    /** 储值回调已处理（幂等拦截） */
    public const BALANCE_CALLBACK_PROCESSED = 4105;
    /** 资金账户已冻结 */
    public const ACCOUNT_FROZEN = 4106;

    // 42xx: 面料 & 尺寸
    /** 面料已下架（规范 §8.2，只有上架且允许订货的面料可被选择） */
    public const FABRIC_OFF_SHELF = 4201;
    /** 尺寸超出标准范围（PRD §4.1.1，宽 90~350 cm / 高 50~600 cm） */
    public const SIZE_OUT_OF_RANGE = 4202;

    // ── 5xxx: 系统 ────────────────────────────────────
    /** 服务器内部错误 */
    public const SYSTEM_ERROR = 5000;
    /** 数据库异常 */
    public const DATABASE_ERROR = 5001;
    /** 第三方服务调用失败（微信/支付宝/短信/CRM 等） */
    public const THIRD_PARTY_ERROR = 5002;

    // ── 错误码 → HTTP 状态码映射表 ──────────────────────
    /**
     * 将业务错误码映射为 HTTP 状态码
     * 规范 §14.2
     *
     * @var array<int, int>
     */
    public const HTTP_STATUS_MAP = [
        // 成功
        self::SUCCESS                     => 200,

        // 1xxx 参数 → 400
        self::PARAM_INVALID               => 400,
        self::PARAM_MISSING               => 400,
        self::FORMAT_ERROR                => 400,

        // 2xxx 认证 → 401
        self::UNAUTHENTICATED             => 401,
        self::TOKEN_EXPIRED               => 401,
        self::TOKEN_INVALID               => 401,
        self::WECHAT_NOT_BOUND            => 401,

        // 3xxx 权限 → 403/404
        self::FORBIDDEN                   => 403,
        self::DATA_NOT_FOUND              => 404,

        // 400x 库存/状态冲突 → 409
        self::INVENTORY_INSUFFICIENT      => 409,
        self::ILLEGAL_STATUS_TRANSITION   => 409,
        self::ORDER_IN_PRODUCTION         => 409,

        // 40xx 限流 → 429（规范 §14.2）
        self::RATE_LIMITED                => 429,

        // 400x 价格 → 422
        self::PRICE_EXPIRED               => 422,

        // 41xx 支付/资金 → 409 或 422
        self::PAYMENT_CALLBACK_PROCESSED  => 409,
        self::PAYMENT_AMOUNT_MISMATCH     => 422,
        self::BALANCE_INSUFFICIENT        => 422,
        self::MIXED_PAYMENT_NOT_SUPPORTED => 422,
        self::BALANCE_CALLBACK_PROCESSED  => 409,
        self::ACCOUNT_FROZEN              => 422,

        // 42xx 面料/尺寸 → 422
        self::FABRIC_OFF_SHELF            => 422,
        self::SIZE_OUT_OF_RANGE           => 422,

        // 5xxx 系统 → 500
        self::SYSTEM_ERROR                => 500,
        self::DATABASE_ERROR              => 500,
        self::THIRD_PARTY_ERROR           => 502,
    ];

    /**
     * 根据错误码获取对应的 HTTP 状态码
     *
     * @param int $errorCode 业务错误码
     * @return int HTTP 状态码
     */
    public static function toHttpStatus(int $errorCode): int
    {
        if (isset(self::HTTP_STATUS_MAP[$errorCode])) {
            return self::HTTP_STATUS_MAP[$errorCode];
        }

        // 按段推断
        return match (true) {
            $errorCode >= 1000 && $errorCode < 2000 => 400,
            $errorCode >= 2000 && $errorCode < 3000 => 401,
            $errorCode >= 3000 && $errorCode < 4000 => ($errorCode === self::DATA_NOT_FOUND ? 404 : 403),
            $errorCode >= 4000 && $errorCode < 5000 => 422,
            default => 500,
        };
    }
}
