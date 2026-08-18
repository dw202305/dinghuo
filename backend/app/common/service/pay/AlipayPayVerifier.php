<?php
declare(strict_types=1);

namespace app\common\service\pay;

/**
 * 支付宝支付回调验签器
 *
 * 凭证从 env [ALIPAY] 段读取，禁止硬编码：
 * - ALIPAY.APP_ID            应用APPID
 * - ALIPAY.APP_PRIVATE_KEY   应用私钥（发起请求用）
 * - ALIPAY.ALIPAY_PUBLIC_KEY 支付宝公钥（回调验签用）
 *
 * 真实验签流程（TODO，批次后续实现）：
 * 1. 取回调 POST 参数（去掉 sign、sign_type）按 key 升序拼成 k=v&k=v 待验签串；
 * 2. 使用 ALIPAY.ALIPAY_PUBLIC_KEY 按 RSA2(SHA256withRSA) 验证 sign；
 * 3. 同时校验 app_id 与 ALIPAY.APP_ID 一致。
 *
 * 在真实验签落地前：
 * - PAY_VERIFY_STRICT 关闭或凭证未齐备 → 基类记 warning 放行（骨架期行为）；
 * - PAY_VERIFY_STRICT 开启且凭证齐备 → doVerify() 因尚未实现而返回 false（宁可拒绝不可误放）。
 */
class AlipayPayVerifier extends AbstractNotifyVerifier
{
    /**
     * {@inheritdoc}
     */
    protected function channel(): string
    {
        return 'alipay';
    }

    /**
     * {@inheritdoc}
     */
    protected function requiredEnvKeys(): array
    {
        return [
            'ALIPAY.APP_ID',
            'ALIPAY.APP_PRIVATE_KEY',
            'ALIPAY.ALIPAY_PUBLIC_KEY',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doVerify(array $headers, string $rawBody): bool
    {
        $params = $this->parse($rawBody);
        if (empty($params['sign']) || empty($params['app_id'])) {
            return false;
        }

        // app_id 必须与本地配置一致
        if (!hash_equals((string) env('ALIPAY.APP_ID', ''), (string) $params['app_id'])) {
            return false;
        }

        // TODO: 真实 RSA2 验签
        // $data = $params;
        // unset($data['sign'], $data['sign_type']);
        // ksort($data);
        // $message = urldecode(http_build_query($data));
        // $publicKey = 组装 PEM(env('ALIPAY.ALIPAY_PUBLIC_KEY'));
        // return openssl_verify($message, base64_decode((string) $params['sign']), $publicKey, OPENSSL_ALGO_SHA256) === 1;
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * 支付宝异步回调通常为 application/x-www-form-urlencoded，兼容 JSON 格式。
     */
    public function parse(string $rawBody): array
    {
        $trimmed = ltrim($rawBody);
        if ($trimmed !== '' && $trimmed[0] === '{') {
            return $this->parseJson($rawBody);
        }

        $data = [];
        parse_str($rawBody, $data);
        return is_array($data) ? $data : [];
    }
}
