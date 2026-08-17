import { post, get } from './index';
import type { LoginParams, WechatLoginParams, SendCodeParams, LoginResult, WechatBindPhoneResult, AccountProfile } from '@/types/user';

/** 发送验证码 */
export function sendVerifyCode(data: SendCodeParams) {
  return post<{ expire_seconds: number }>('/store/auth/send-code', data as unknown as Record<string, unknown>);
}

/** 手机号+验证码登录 */
export function loginByPhone(data: LoginParams) {
  return post<LoginResult>('/store/auth/login', data as unknown as Record<string, unknown>);
}

/** 微信授权登录 */
export function loginByWechat(data: WechatLoginParams) {
  return post<LoginResult | WechatBindPhoneResult>('/store/auth/wechat-login', data as unknown as Record<string, unknown>);
}

/** 退出登录 */
export function logout() {
  return post<null>('/store/auth/logout');
}

/** 获取当前账号信息 */
export function getAccountProfile() {
  return get<AccountProfile>('/store/auth/profile');
}

/**
 * 切换当前门店（后端写 Redis current_store:{account_id}）
 * @param storeId 目标门店ID
 */
export function switchCurrentStore(storeId: number) {
  return post<{ store_id: number; store_name: string }>('/store/auth/switch-store', { store_id: storeId } as unknown as Record<string, unknown>);
}
