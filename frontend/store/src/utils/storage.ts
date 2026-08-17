/** Storage key 常量 */
const STORAGE_KEYS = {
  TOKEN: 'ss_token',
  TOKEN_EXPIRE: 'ss_token_expire',
  ACCOUNT_INFO: 'ss_account_info',
  CURRENT_STORE_ID: 'ss_current_store_id',
  ORDER_DRAFT: 'ss_order_draft',
  FAVORITE_FABRICS: 'ss_favorite_fabrics',
  SEARCH_HISTORY: 'ss_search_history',
} as const;

/**
 * 安全获取 Storage
 * @param key 存储键名
 * @returns 存储值，不存在返回 null
 */
export function getStorage<T>(key: string): T | null {
  try {
    const value = uni.getStorageSync(key);
    if (value === '' || value === undefined || value === null) {
      return null;
    }
    return value as T;
  } catch {
    return null;
  }
}

/**
 * 安全设置 Storage
 * @param key 存储键名
 * @param value 存储值
 */
export function setStorage<T>(key: string, value: T): void {
  try {
    uni.setStorageSync(key, value);
  } catch (e) {
    console.error(`Storage set failed [${key}]:`, e);
  }
}

/**
 * 移除指定 Storage
 * @param key 存储键名
 */
export function removeStorage(key: string): void {
  try {
    uni.removeStorageSync(key);
  } catch (e) {
    console.error(`Storage remove failed [${key}]:`, e);
  }
}

/**
 * 清空所有 Storage
 */
export function clearStorage(): void {
  try {
    uni.clearStorageSync();
  } catch (e) {
    console.error('Storage clear failed:', e);
  }
}

export { STORAGE_KEYS };
