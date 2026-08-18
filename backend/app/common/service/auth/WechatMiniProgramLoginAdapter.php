<?php
declare(strict_types=1);

namespace app\common\service\auth;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use think\facade\Log;

/**
 * 微信小程序真实登录适配器（批次3新增 — TODO 骨架）
 *
 * 调用微信官方 code2session：
 * GET https://api.weixin.qq.com/sns/jscode2session
 *   ?appid={appid}&secret={secret}&js_code={code}&grant_type=authorization_code
 *
 * 凭证一律从 env 读取（禁止硬编码）：
 * - wechat.mini_appid     小程序 AppID
 * - wechat.mini_secret    小程序 AppSecret
 *
 * TODO(批次5/上线前)：
 * 1. 实现 HTTP 调用（建议 guzzle 或 think\facade\HttpClient 不存在时用 curl）；
 * 2. 处理微信错误码：40029(code无效)/45011(频率限制)/40226(高风险用户)；
 * 3. session_key 不得下发前端，仅服务端加密存储（如需解密手机号）。
 */
class WechatMiniProgramLoginAdapter implements WechatLoginAdapter
{
    private string $appId;
    private string $secret;

    public function __construct()
    {
        $this->appId  = (string) env('wechat.mini_appid', '');
        $this->secret = (string) env('wechat.mini_secret', '');
    }

    public function codeToSession(string $code): array
    {
        if ($this->appId === '' || $this->secret === '') {
            Log::error('[WechatLogin] 小程序凭证未配置（wechat.mini_appid / wechat.mini_secret）');
            throw new BusinessException(ErrorCode::THIRD_PARTY_ERROR, '微信登录配置缺失');
        }

        // TODO(批次5)：真实调用 code2session，示例：
        // $url = 'https://api.weixin.qq.com/sns/jscode2session?' . http_build_query([
        //     'appid'      => $this->appId,
        //     'secret'     => $this->secret,
        //     'js_code'    => $code,
        //     'grant_type' => 'authorization_code',
        // ]);
        // $resp = json_decode((string) file_get_contents($url), true);
        // if (empty($resp['openid'])) {
        //     Log::error('[WechatLogin] code2session 失败', ['errcode' => $resp['errcode'] ?? null]);
        //     throw new BusinessException(ErrorCode::THIRD_PARTY_ERROR, '微信授权凭证无效或已过期');
        // }
        // return [
        //     'openid'      => (string) $resp['openid'],
        //     'unionid'     => isset($resp['unionid']) ? (string) $resp['unionid'] : null,
        //     'session_key' => isset($resp['session_key']) ? (string) $resp['session_key'] : null,
        // ];

        Log::warning('[WechatLogin] code2session 真实通道尚未接入（TODO 骨架）');
        throw new BusinessException(ErrorCode::THIRD_PARTY_ERROR, '微信登录通道尚未接入');
    }
}
