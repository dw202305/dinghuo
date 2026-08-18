<?php
declare(strict_types=1);

namespace app\common\service\sms;

/**
 * 短信发送适配器接口（批次3新增）
 *
 * 实现选择由 env('sms.driver') 决定：
 * - 'aliyun' => AliyunSmsSender（真实通道，TODO 骨架）
 * - 其他     => MockSmsSender（本地/测试环境，Log 输出）
 */
interface SmsSenderInterface
{
    /**
     * 发送验证码短信
     *
     * @param string $phone 手机号
     * @param string $code 验证码（6位数字）
     * @param array $context 附加上下文（如 scene），实现方可忽略
     * @return bool 是否发送成功
     */
    public function sendVerifyCode(string $phone, string $code, array $context = []): bool;
}
