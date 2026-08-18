import type { ApiResponse } from '@/types/api';
import { useAuthStore } from '@/stores/auth';

/** 基础URL（优先读取环境变量 VITE_API_BASE_URL） */
const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://api.shengshikunyuan.com/api/v1';

/**
 * 业务错误码 → 用户友好提示映射
 */
const ERROR_CODE_MESSAGES: Record<number, string> = {
  4001: '套件库存不足',
  4002: '订单价格已失效，请重新提交',
  4003: '订单状态已变更',
  4004: '订单已进入生产，无法取消',
  4101: '支付订单不存在',
  4102: '支付金额不匹配',
  4103: '支付已过期',
  4104: '支付渠道暂不可用',
  4105: '支付重复提交',
  4106: '支付结果确认中，请稍后查询',
  4201: '面料已下架',
  4202: '尺寸超出范围',
};

/** 请求配置 */
interface RequestConfig {
  url: string;
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
  data?: Record<string, unknown>;
  header?: Record<string, string>;
  showLoading?: boolean;
  showError?: boolean;
  /** 幂等键，传入后自动附加到请求头 */
  idempotencyKey?: string;
}

/**
 * 统一网络请求封装
 * 基于 uni.request，兼容 H5 和微信小程序
 * 支持 409/422 错误码处理、幂等键、业务错误码提示
 */
export async function request<T>(config: RequestConfig): Promise<T> {
  const {
    url,
    method = 'GET',
    data,
    header = {},
    showLoading = false,
    showError = true,
    idempotencyKey,
  } = config;

  // 注入 Token
  const authStore = useAuthStore();
  const token = authStore.token;
  if (token) {
    header['Authorization'] = `Bearer ${token}`;
  }
  header['Content-Type'] = header['Content-Type'] || 'application/json';

  // 注入幂等键
  if (idempotencyKey) {
    header['X-Idempotency-Key'] = idempotencyKey;
  }

  if (showLoading) {
    uni.showLoading({ title: '加载中...', mask: true });
  }

  return new Promise<T>((resolve, reject) => {
    uni.request({
      url: `${BASE_URL}${url}`,
      method,
      data,
      header,
      timeout: 15000,
      success: (res) => {
        const statusCode = res.statusCode;

        // ── 409 Conflict ──
        if (statusCode === 409) {
          if (showError) {
            uni.showToast({ title: '操作冲突，请刷新页面重试', icon: 'none' });
          }
          // 触发数据刷新事件（供页面监听）
          uni.$emit('data-conflict');
          reject(new Error('操作冲突'));
          return;
        }

        // ── 422 Unprocessable Entity ──
        if (statusCode === 422) {
          const body = res.data as ApiResponse<unknown> & {
            errors?: Record<string, string[]>;
          };
          if (body.errors) {
            const messages = Object.entries(body.errors)
              .map(([field, errs]) => `${field}: ${errs.join(', ')}`)
              .join('; ');
            if (showError) {
              uni.showToast({ title: messages, icon: 'none', duration: 3000 });
            }
            reject(new Error(messages));
          } else {
            const msg = body.message || '请求参数有误';
            if (showError) {
              uni.showToast({ title: msg, icon: 'none' });
            }
            reject(new Error(msg));
          }
          return;
        }

        if (statusCode < 200 || statusCode >= 300) {
          const errMsg = `请求失败(${statusCode})`;
          if (showError) {
            uni.showToast({ title: errMsg, icon: 'none' });
          }
          reject(new Error(errMsg));
          return;
        }

        const body = res.data as ApiResponse<T>;

        // 成功
        if (body.code === 0) {
          resolve(body.data);
          return;
        }

        // Token 过期或无效
        if (body.code === 2001 || body.code === 2002) {
          authStore.clearAuth();
          uni.showToast({ title: '登录已过期，请重新登录', icon: 'none' });
          setTimeout(() => {
            uni.navigateTo({ url: '/pages/login/index' });
          }, 1500);
          reject(new Error(body.message));
          return;
        }

        // ── 业务错误码精确提示 ──
        const codeMsg = ERROR_CODE_MESSAGES[body.code];
        if (codeMsg) {
          if (showError) {
            uni.showToast({ title: codeMsg, icon: 'none', duration: 3000 });
          }
          reject(new Error(codeMsg));
          return;
        }

        // 其他业务错误
        if (showError) {
          uni.showToast({ title: body.message || '操作失败', icon: 'none' });
        }
        reject(new Error(body.message));
      },
      fail: (err) => {
        const errMsg = '网络连接失败，请检查网络';
        if (showError) {
          uni.showToast({ title: errMsg, icon: 'none' });
        }
        reject(new Error(errMsg));
      },
      complete: () => {
        if (showLoading) {
          uni.hideLoading();
        }
      },
    });
  });
}

/** GET 请求 */
export function get<T>(url: string, data?: Record<string, unknown>, config?: Partial<RequestConfig>): Promise<T> {
  return request<T>({ url, method: 'GET', data, ...config });
}

/** POST 请求 */
export function post<T>(url: string, data?: Record<string, unknown>, config?: Partial<RequestConfig>): Promise<T> {
  return request<T>({ url, method: 'POST', data, ...config });
}

/** PUT 请求 */
export function put<T>(url: string, data?: Record<string, unknown>, config?: Partial<RequestConfig>): Promise<T> {
  return request<T>({ url, method: 'PUT', data, ...config });
}

/** DELETE 请求 */
export function del<T>(url: string, data?: Record<string, unknown>, config?: Partial<RequestConfig>): Promise<T> {
  return request<T>({ url, method: 'DELETE', data, ...config });
}
