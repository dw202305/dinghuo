import type { CustomerLevel } from './common';

/** 登录请求参数 */
export interface LoginParams {
  phone: string;
  verify_code: string;
}

/** 微信登录请求参数 */
export interface WechatLoginParams {
  code: string;
  encrypted_data?: string;
  iv?: string;
}

/** 发送验证码参数 */
export interface SendCodeParams {
  phone: string;
  scene: 'login' | 'bind-wechat' | 'change-phone';
}

/** 登录响应数据 */
export interface LoginResult {
  token: string;
  expires_in: number;
  account_id: number;
  real_name: string;
  account_role: number;
  verify_status: number;
  stores: StoreBrief[];
}

/** 微信登录需绑定手机号 */
export interface WechatBindPhoneResult {
  need_bindphone: true;
  temp_token: string;
  wechat_openid: string;
}

/** 门店简要信息 */
export interface StoreBrief {
  store_id: number;
  store_no: string;
  store_name: string;
  role_in_customer: number;
  is_default: boolean;
}

/** 账号详情 */
export interface AccountProfile {
  account_id: number;
  phone: string;
  real_name: string;
  account_role: number;
  account_role_text: string;
  verify_status: number;
  wechat_bound: boolean;
  current_store: StoreDetail;
  stores: StoreBrief[];
}

/** 门店详情 */
export interface StoreDetail {
  store_id: number;
  store_no: string;
  store_name: string;
  customer_level: CustomerLevel;
  customer_level_text: string;
  channel_mode: number;
  partner_name: string | null;
  primary_sales_name: string;
  contact_phone: string;
  province: string;
  city: string;
  district: string;
  address: string;
}

/** 收货地址 */
export interface StoreAddress {
  id: number;
  store_id: number;
  address_type: number;
  address_label: string | null;
  receiver_name: string;
  receiver_phone: string;
  province: string;
  city: string;
  district: string;
  detail_address: string;
  is_default: boolean;
}
