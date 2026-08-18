<?php
declare(strict_types=1);

namespace app\common\service\pay;

use think\facade\Log;

/**
 * 支付回调验签器抽象基类
 *
 * 统一实现"严格模式开关 + 凭证检查"策略（规范 12.2）：
 * - env 开关 PAY_VERIFY_STRICT（默认 false，支持写成 [PAY] VERIFY_STRICT）；
 * - 凭证未齐备 或 开关关闭：记 Log::warning 并放行；
 * - 开关开启且凭证齐备：调用子类 doVerify() 强校验，失败即拒绝。
 *
 * 真实渠道凭证一律从 env 读取，禁止硬编码。
 */
abstract class AbstractNotifyVerifier implements NotifyVerifierInterface
{
    /**
     * 渠道标识（用于日志）
     *
     * @return string
     */
    abstract protected function channel(): string;

    /**
     * 该渠道必须具备的 env 凭证键列表（如 ['WECHAT.MCH_ID', ...]）
     *
     * @return array<int, string>
     */
    abstract protected function requiredEnvKeys(): array;

    /**
     * 渠道真实验签实现（仅在严格模式开启且凭证齐备时被调用）
     *
     * @param array $headers 请求头（键名统一小写）
     * @param string $rawBody 原始请求体
     * @return bool
     */
    abstract protected function doVerify(array $headers, string $rawBody): bool;

    /**
     * {@inheritdoc}
     */
    public function verify(array $headers, string $rawBody): bool
    {
        if (!$this->isConfigured() || !$this->isStrict()) {
            Log::warning('支付回调验签跳过（凭证未齐备或严格模式关闭），骨架期放行', [
                'channel'    => $this->channel(),
                'configured' => $this->isConfigured(),
                'strict'     => $this->isStrict(),
            ]);
            return true;
        }

        $passed = $this->doVerify($headers, $rawBody);
        if (!$passed) {
            Log::error('支付回调验签失败，已拒绝入账', [
                'channel' => $this->channel(),
            ]);
        }
        return $passed;
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        foreach ($this->requiredEnvKeys() as $key) {
            $value = env($key);
            if ($value === null || $value === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * 严格模式是否开启
     *
     * 兼容两种 .env 写法：顶层 PAY_VERIFY_STRICT=1 或 [PAY] 段 VERIFY_STRICT=1。
     *
     * @return bool
     */
    public function isStrict(): bool
    {
        $value = env('PAY_VERIFY_STRICT');
        if ($value === null || $value === '') {
            $value = env('PAY.VERIFY_STRICT', false);
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 读取单个请求头（键名不区分大小写）
     *
     * @param array $headers 请求头
     * @param string $name 头名称
     * @return string
     */
    protected function header(array $headers, string $name): string
    {
        $name = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }
        return '';
    }

    /**
     * 按 JSON 解析回调体
     *
     * @param string $rawBody 原始请求体
     * @return array
     */
    protected function parseJson(string $rawBody): array
    {
        $data = json_decode($rawBody, true);
        return is_array($data) ? $data : [];
    }
}
