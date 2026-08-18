<?php
declare(strict_types=1);

namespace app\common\service\pay;

/**
 * 支付回调验签接口
 *
 * 所有第三方支付回调必须先经过验签器校验，验签通过才允许进入业务处理（规范 12.2）。
 *
 * 行为约定：
 * - verify() 返回 true 表示放行（允许进入业务处理），false 表示拒绝；
 * - env 开关 PAY_VERIFY_STRICT（默认 false）：
 *   - 关闭或未配置渠道凭证时：记 Log::warning 并放行（骨架期兼容）；
 *   - 开启且凭证齐备时：执行强校验，校验失败即拒绝。
 *
 * @see docs/dev_specification_v1.md 12.2
 */
interface NotifyVerifierInterface
{
    /**
     * 校验回调签名
     *
     * @param array $headers 请求头（键名统一小写）
     * @param string $rawBody 原始请求体
     * @return bool true=放行 false=拒绝
     */
    public function verify(array $headers, string $rawBody): bool;

    /**
     * 解析回调参数为数组
     *
     * @param string $rawBody 原始请求体
     * @return array 回调参数（解析失败返回空数组）
     */
    public function parse(string $rawBody): array;

    /**
     * 渠道凭证是否齐备
     *
     * @return bool
     */
    public function isConfigured(): bool;
}
