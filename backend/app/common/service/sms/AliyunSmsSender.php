<?php
declare(strict_types=1);

namespace app\common\service\sms;

use think\facade\Log;

/**
 * 阿里云短信发送器（批次3新增 — TODO 骨架）
 *
 * 凭证一律从 env 读取（禁止硬编码）：
 * - sms.access_key_id / sms.access_key_secret
 * - sms.sign_name     短信签名
 * - sms.template_code 验证码模板 Code
 *
 * TODO(批次5/上线前)：
 * 1. composer require alibabacloud/dysmsapi-20170525 接入官方 SDK；
 * 2. 实现 SendSms 调用与错误码处理（isv.BUSINESS_LIMIT_CONTROL 等）；
 * 3. 发送失败记录 Log::error 并返回 false，由控制器转统一错误响应。
 */
class AliyunSmsSender implements SmsSenderInterface
{
    private string $accessKeyId;
    private string $accessKeySecret;
    private string $signName;
    private string $templateCode;

    public function __construct()
    {
        $this->accessKeyId     = (string) env('sms.access_key_id', '');
        $this->accessKeySecret = (string) env('sms.access_key_secret', '');
        $this->signName        = (string) env('sms.sign_name', '');
        $this->templateCode    = (string) env('sms.template_code', '');
    }

    public function sendVerifyCode(string $phone, string $code, array $context = []): bool
    {
        if ($this->accessKeyId === '' || $this->accessKeySecret === '') {
            // 凭证未配置：记录日志，降级为发送失败（不泄漏内部错误）
            Log::error('[AliyunSms] 短信凭证未配置（sms.access_key_id / sms.access_key_secret）');
            return false;
        }

        // TODO(批次5)：接入阿里云 dysmsapi SDK 真实下发
        // $request = new SendSmsRequest([
        //     'PhoneNumbers'  => $phone,
        //     'SignName'      => $this->signName,
        //     'TemplateCode'  => $this->templateCode,
        //     'TemplateParam' => json_encode(['code' => $code]),
        // ]);

        Log::warning('[AliyunSms] 真实通道尚未接入（TODO 骨架），验证码未实际下发', [
            'phone' => $phone,
        ]);

        return false;
    }
}
