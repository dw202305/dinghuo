<?php
declare(strict_types=1);

namespace app\common\service\auth;

/**
 * 微信登录适配器接口（批次3新增）
 *
 * 职责：用小程序 wx.login() 的 code 换取 openid（必要时含 unionid/session_key）。
 * 实现选择由 env('wechat.login_driver') 决定：
 * - 'miniprogram' => WechatMiniProgramLoginAdapter（真实 code2session，TODO 骨架）
 * - 其他          => MockWechatLoginAdapter（本地/测试环境，约定 code 返回测试 openid）
 */
interface WechatLoginAdapter
{
    /**
     * code 换取微信身份
     *
     * @param string $code 小程序 wx.login() 返回的临时登录凭证
     * @return array{openid: string, unionid: string|null, session_key: string|null}
     * @throws \app\common\exception\BusinessException code 无效或微信接口异常时抛出
     */
    public function codeToSession(string $code): array;
}
