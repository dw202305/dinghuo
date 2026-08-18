<?php
declare(strict_types=1);

namespace app\common\service\pay;

/**
 * 微信支付回调验签器（V3）
 *
 * 凭证从 env [WECHAT] 段读取，禁止硬编码：
 * - WECHAT.MCH_ID               商户号
 * - WECHAT.API_V3_KEY           APIv3 密钥（解密 resource.ciphertext 用）
 * - WECHAT.PLATFORM_CERT_SERIAL 平台证书序列号
 * - WECHAT.PLATFORM_CERT_PATH   平台证书/公钥文件路径（验签用）
 *
 * 真实验签流程（TODO，批次后续实现）：
 * 1. 取请求头 Wechatpay-Timestamp / Wechatpay-Nonce / Wechatpay-Signature / Wechatpay-Serial；
 * 2. 构造验签名串：timestamp\n nonce\n rawBody\n；
 * 3. 使用微信平台公钥（按 Serial 匹配）做 SHA256withRSA 验签；
 * 4. 验签通过后再用 API_V3_KEY AES-256-GCM 解密 resource.ciphertext 得到明文回调。
 *
 * 在真实验签落地前：
 * - PAY_VERIFY_STRICT 关闭或凭证未齐备 → 基类记 warning 放行（骨架期行为）；
 * - PAY_VERIFY_STRICT 开启且凭证齐备 → doVerify() 因尚未实现而返回 false（宁可拒绝不可误放）。
 */
class WechatPayVerifier extends AbstractNotifyVerifier
{
    /**
     * {@inheritdoc}
     */
    protected function channel(): string
    {
        return 'wechat';
    }

    /**
     * {@inheritdoc}
     */
    protected function requiredEnvKeys(): array
    {
        return [
            'WECHAT.MCH_ID',
            'WECHAT.API_V3_KEY',
            'WECHAT.PLATFORM_CERT_SERIAL',
            'WECHAT.PLATFORM_CERT_PATH',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doVerify(array $headers, string $rawBody): bool
    {
        $timestamp = $this->header($headers, 'Wechatpay-Timestamp');
        $nonce     = $this->header($headers, 'Wechatpay-Nonce');
        $signature = $this->header($headers, 'Wechatpay-Signature');
        $serial    = $this->header($headers, 'Wechatpay-Serial');

        if ($timestamp === '' || $nonce === '' || $signature === '' || $serial === '') {
            return false;
        }

        // 防重放：回调时间戳偏差超过5分钟直接拒绝
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        // 平台证书序列号必须与本地配置一致
        if (!hash_equals((string) env('WECHAT.PLATFORM_CERT_SERIAL', ''), $serial)) {
            return false;
        }

        // TODO: 真实 SHA256withRSA 验签
        // $message = "{$timestamp}\n{$nonce}\n{$rawBody}\n";
        // $publicKey = 从 WECHAT.PLATFORM_CERT_PATH 加载平台证书公钥;
        // return openssl_verify($message, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256) === 1;
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * 微信 V3 回调体为 JSON（验签骨架期不解密 resource.ciphertext，
     * 解密依赖 API_V3_KEY，TODO 与真实验签同批实现）。
     */
    public function parse(string $rawBody): array
    {
        return $this->parseJson($rawBody);
    }
}
