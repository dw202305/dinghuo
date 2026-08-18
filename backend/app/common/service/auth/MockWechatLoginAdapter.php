<?php
declare(strict_types=1);

namespace app\common\service\auth;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;

/**
 * Mock 微信登录适配器（批次3新增）
 *
 * 本地开发/测试环境使用，不调用微信 code2session：
 * - 约定 code = 'mock_bound_code'   → 返回固定测试 openid（用于已绑定账号联调）；
 * - 约定 code = 'mock_invalid_code' → 模拟 code 无效，抛业务异常；
 * - 其他 code → 派生稳定 openid（sha1），方便多账号联调。
 *
 * 生产环境禁止使用本实现（env wechat.login_driver != 'miniprogram' 时才生效）。
 */
class MockWechatLoginAdapter implements WechatLoginAdapter
{
    /** 约定测试 code → openid 映射 */
    private const MOCK_CODE_MAP = [
        'mock_bound_code' => 'mock_openid_bound_001',
    ];

    /** 模拟无效 code */
    private const INVALID_CODE = 'mock_invalid_code';

    public function codeToSession(string $code): array
    {
        if ($code === self::INVALID_CODE) {
            throw new BusinessException(ErrorCode::THIRD_PARTY_ERROR, '微信授权凭证无效或已过期');
        }

        $openid = self::MOCK_CODE_MAP[$code]
            ?? ('mock_openid_' . substr(sha1($code), 0, 16));

        return [
            'openid'      => $openid,
            'unionid'     => null,
            'session_key' => null,
        ];
    }
}
