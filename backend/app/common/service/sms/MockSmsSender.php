<?php
declare(strict_types=1);

namespace app\common\service\sms;

use think\facade\Log;

/**
 * Mock 短信发送器（批次3新增）
 *
 * 本地开发/测试环境使用：不真实下发短信，仅写日志。
 * 验证码直接输出到日志便于联调（生产环境必须使用真实通道）。
 */
class MockSmsSender implements SmsSenderInterface
{
    public function sendVerifyCode(string $phone, string $code, array $context = []): bool
    {
        Log::info('[MockSms] 模拟发送验证码', [
            'phone'   => $phone,
            'code'    => $code,
            'scene'   => $context['scene'] ?? '',
        ]);

        return true;
    }
}
