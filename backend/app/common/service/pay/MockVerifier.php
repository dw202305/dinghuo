<?php
declare(strict_types=1);

namespace app\common\service\pay;

/**
 * Mock 验签器（仅测试用）
 *
 * 按约定签名串校验：请求头 X-Mock-Sign = HMAC-SHA256(rawBody, secret)。
 * 不读取 env、不依赖框架运行时，可在纯 PHPUnit 环境独立运行。
 *
 * 禁止在生产路由中装配本验签器。
 */
class MockVerifier implements NotifyVerifierInterface
{
    /**
     * 默认测试密钥（仅测试环境使用）
     */
    public const DEFAULT_SECRET = 'mock-notify-secret';

    /**
     * 签名请求头名称
     */
    public const SIGN_HEADER = 'X-Mock-Sign';

    private string $secret;

    public function __construct(string $secret = self::DEFAULT_SECRET)
    {
        $this->secret = $secret;
    }

    /**
     * 生成约定签名（测试构造请求时使用）
     *
     * @param string $rawBody 原始请求体
     * @return string
     */
    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, $this->secret);
    }

    /**
     * {@inheritdoc}
     */
    public function verify(array $headers, string $rawBody): bool
    {
        $signature = $this->findHeader($headers, self::SIGN_HEADER);
        if ($signature === '') {
            return false;
        }

        $expected = $this->sign($rawBody);
        return hash_equals($expected, $signature);
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $rawBody): array
    {
        $data = json_decode($rawBody, true);
        return is_array($data) ? $data : [];
    }

    /**
     * {@inheritdoc}
     *
     * Mock 验签器密钥由构造函数注入，始终视为齐备。
     */
    public function isConfigured(): bool
    {
        return $this->secret !== '';
    }

    /**
     * 大小写不敏感读取请求头
     *
     * @param array $headers 请求头
     * @param string $name 头名称
     * @return string
     */
    private function findHeader(array $headers, string $name): string
    {
        $name = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }
        return '';
    }
}
