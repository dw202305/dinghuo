<?php

declare(strict_types=1);

namespace tests\Unit\Enum;

use app\common\enum\ErrorCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ErrorCode 错误码单元测试
 *
 * 验证错误码常量值及其与 HTTP 状态码的映射关系。
 * 纯静态测试，不依赖数据库。
 */
class ErrorCodeTest extends TestCase
{
    /**
     * 错误码常量值在预期范围内
     */
    #[Test]
    public function testErrorCodeRanges(): void
    {
        $this->assertSame(0, ErrorCode::SUCCESS);

        // 1xxx: 参数
        $this->assertGreaterThanOrEqual(1000, ErrorCode::PARAM_INVALID);
        $this->assertLessThan(2000, ErrorCode::PARAM_INVALID);

        // 2xxx: 认证
        $this->assertGreaterThanOrEqual(2000, ErrorCode::UNAUTHENTICATED);
        $this->assertLessThan(3000, ErrorCode::UNAUTHENTICATED);

        // 3xxx: 权限
        $this->assertGreaterThanOrEqual(3000, ErrorCode::FORBIDDEN);
        $this->assertLessThan(4000, ErrorCode::FORBIDDEN);

        // 4xxx: 业务冲突
        $this->assertGreaterThanOrEqual(4000, ErrorCode::INVENTORY_INSUFFICIENT);
        $this->assertLessThan(5000, ErrorCode::INVENTORY_INSUFFICIENT);

        // 5xxx: 系统
        $this->assertGreaterThanOrEqual(5000, ErrorCode::SYSTEM_ERROR);
        $this->assertLessThan(6000, ErrorCode::SYSTEM_ERROR);
    }

    /**
     * HTTP 状态码映射：显式定义的映射表检查
     */
    #[Test]
    public function testHttpStatusMapExplicitEntries(): void
    {
        $map = ErrorCode::HTTP_STATUS_MAP;

        // 成功 → 200
        $this->assertSame(200, $map[ErrorCode::SUCCESS]);

        // 1xxx → 400
        $this->assertSame(400, $map[ErrorCode::PARAM_INVALID]);
        $this->assertSame(400, $map[ErrorCode::PARAM_MISSING]);
        $this->assertSame(400, $map[ErrorCode::FORMAT_ERROR]);

        // 2xxx → 401
        $this->assertSame(401, $map[ErrorCode::UNAUTHENTICATED]);
        $this->assertSame(401, $map[ErrorCode::TOKEN_EXPIRED]);
        $this->assertSame(401, $map[ErrorCode::TOKEN_INVALID]);

        // 3xxx → 403/404
        $this->assertSame(403, $map[ErrorCode::FORBIDDEN]);
        $this->assertSame(404, $map[ErrorCode::DATA_NOT_FOUND]);

        // 5xxx → 500
        $this->assertSame(500, $map[ErrorCode::SYSTEM_ERROR]);
        $this->assertSame(500, $map[ErrorCode::DATABASE_ERROR]);
        $this->assertSame(502, $map[ErrorCode::THIRD_PARTY_ERROR]);
    }

    /**
     * 4xxx 段细分映射
     */
    #[Test]
    public function testHttpStatusMapBusinessErrors(): void
    {
        $map = ErrorCode::HTTP_STATUS_MAP;

        // 库存/状态冲突 → 409
        $this->assertSame(409, $map[ErrorCode::INVENTORY_INSUFFICIENT]);
        $this->assertSame(409, $map[ErrorCode::ILLEGAL_STATUS_TRANSITION]);
        $this->assertSame(409, $map[ErrorCode::ORDER_IN_PRODUCTION]);

        // 价格 → 422
        $this->assertSame(422, $map[ErrorCode::PRICE_EXPIRED]);

        // 支付/资金
        $this->assertSame(409, $map[ErrorCode::PAYMENT_CALLBACK_PROCESSED]);
        $this->assertSame(422, $map[ErrorCode::PAYMENT_AMOUNT_MISMATCH]);
        $this->assertSame(422, $map[ErrorCode::BALANCE_INSUFFICIENT]);
        $this->assertSame(422, $map[ErrorCode::MIXED_PAYMENT_NOT_SUPPORTED]);
        $this->assertSame(409, $map[ErrorCode::BALANCE_CALLBACK_PROCESSED]);
        $this->assertSame(422, $map[ErrorCode::ACCOUNT_FROZEN]);

        // 面料/尺寸 → 422
        $this->assertSame(422, $map[ErrorCode::FABRIC_OFF_SHELF]);
        $this->assertSame(422, $map[ErrorCode::SIZE_OUT_OF_RANGE]);
    }

    /**
     * toHttpStatus：静态方法按段推断
     */
    #[Test]
    #[DataProvider('httpStatusProvider')]
    public function testToHttpStatusMapping(int $errorCode, int $expectedHttpStatus): void
    {
        $this->assertSame(
            $expectedHttpStatus,
            ErrorCode::toHttpStatus($errorCode),
            "错误码 {$errorCode} 应映射到 HTTP {$expectedHttpStatus}"
        );
    }

    /**
     * 数据提供者：错误码 → HTTP 状态码
     */
    public static function httpStatusProvider(): array
    {
        return [
            'success'              => [ErrorCode::SUCCESS, 200],
            'param_invalid'        => [ErrorCode::PARAM_INVALID, 400],
            'param_missing'        => [ErrorCode::PARAM_MISSING, 400],
            'format_error'         => [ErrorCode::FORMAT_ERROR, 400],
            'unauthenticated'      => [ErrorCode::UNAUTHENTICATED, 401],
            'token_expired'        => [ErrorCode::TOKEN_EXPIRED, 401],
            'token_invalid'        => [ErrorCode::TOKEN_INVALID, 401],
            'forbidden'            => [ErrorCode::FORBIDDEN, 403],
            'data_not_found'       => [ErrorCode::DATA_NOT_FOUND, 404],
            'inventory'            => [ErrorCode::INVENTORY_INSUFFICIENT, 409],
            'status_transition'    => [ErrorCode::ILLEGAL_STATUS_TRANSITION, 409],
            'in_production'        => [ErrorCode::ORDER_IN_PRODUCTION, 409],
            'price_expired'        => [ErrorCode::PRICE_EXPIRED, 422],
            'balance_insufficient' => [ErrorCode::BALANCE_INSUFFICIENT, 422],
            'fabric_off_shelf'     => [ErrorCode::FABRIC_OFF_SHELF, 422],
            'system_error'         => [ErrorCode::SYSTEM_ERROR, 500],
            'database_error'       => [ErrorCode::DATABASE_ERROR, 500],
            'third_party'          => [ErrorCode::THIRD_PARTY_ERROR, 502],
        ];
    }

    /**
     * toHttpStatus：未知错误码按段推断
     */
    #[Test]
    public function testToHttpStatusUnknownCode_FallbackBySegment(): void
    {
        // 未定义的 1xxx 码 → 400
        $this->assertSame(400, ErrorCode::toHttpStatus(1999));

        // 未定义的 2xxx 码 → 401
        $this->assertSame(401, ErrorCode::toHttpStatus(2999));

        // 未定义的 4xxx 码 → 422
        $this->assertSame(422, ErrorCode::toHttpStatus(4999));

        // 未定义的超范围码 → 500
        $this->assertSame(500, ErrorCode::toHttpStatus(9999));
    }

    /**
     * 所有已定义错误码都在 HTTP_STATUS_MAP 中有对应条目
     */
    #[Test]
    public function testAllDefinedCodesHaveHttpMapping(): void
    {
        $reflection = new \ReflectionClass(ErrorCode::class);
        $constants = $reflection->getConstants();

        // 排除 HTTP_STATUS_MAP（它本身也是常量）
        // 注意：ARRAY_FILTER_USE_BOTH 回调签名为 (value, key)
        $errorCodeConstants = array_filter(
            $constants,
            fn($value, $key) => $key !== 'HTTP_STATUS_MAP' && is_int($value),
            ARRAY_FILTER_USE_BOTH
        );

        foreach ($errorCodeConstants as $name => $value) {
            $this->assertArrayHasKey(
                $value,
                ErrorCode::HTTP_STATUS_MAP,
                "错误码 {$name}({$value}) 缺少 HTTP_STATUS_MAP 映射"
            );
        }
    }
}
