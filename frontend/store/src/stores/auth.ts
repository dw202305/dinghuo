import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { getStorage, setStorage, removeStorage, STORAGE_KEYS } from '@/utils/storage';
import { getAccountProfile, logout as logoutApi, switchCurrentStore } from '@/api/auth';
import type { AccountProfile, StoreBrief } from '@/types/user';

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string>('');
  const accountId = ref<number>(0);
  const realName = ref<string>('');
  const accountRole = ref<number>(0);
  const currentStoreId = ref<number>(0);
  const stores = ref<StoreBrief[]>([]);
  const profile = ref<AccountProfile | null>(null);

  const isLoggedIn = computed(() => !!token.value);
  const currentStore = computed(() => stores.value.find((s) => s.store_id === currentStoreId.value) ?? null);

  /** 初始化认证状态（App启动时调用） */
  function initAuth() {
    const savedToken = getStorage<string>(STORAGE_KEYS.TOKEN);
    const savedStoreId = getStorage<number>(STORAGE_KEYS.CURRENT_STORE_ID);
    if (savedToken) {
      token.value = savedToken;
      accountId.value = getStorage<number>(STORAGE_KEYS.ACCOUNT_INFO + '_id') ?? 0;
      realName.value = getStorage<string>(STORAGE_KEYS.ACCOUNT_INFO + '_name') ?? '';
      accountRole.value = getStorage<number>(STORAGE_KEYS.ACCOUNT_INFO + '_role') ?? 0;
      currentStoreId.value = savedStoreId ?? 0;
      const savedStores = getStorage<StoreBrief[]>(STORAGE_KEYS.ACCOUNT_INFO + '_stores');
      if (savedStores) stores.value = savedStores;
    }
  }

  /** 设置登录信息 */
  function setLoginInfo(data: {
    token: string;
    account_id: number;
    real_name: string;
    account_role: number;
    stores: StoreBrief[];
  }) {
    token.value = data.token;
    accountId.value = data.account_id;
    realName.value = data.real_name;
    accountRole.value = data.account_role;
    stores.value = data.stores;

    const defaultStore = data.stores.find((s) => s.is_default) ?? data.stores[0];
    if (defaultStore) {
      currentStoreId.value = defaultStore.store_id;
    }

    setStorage(STORAGE_KEYS.TOKEN, data.token);
    setStorage(STORAGE_KEYS.ACCOUNT_INFO + '_id', data.account_id);
    setStorage(STORAGE_KEYS.ACCOUNT_INFO + '_name', data.real_name);
    setStorage(STORAGE_KEYS.ACCOUNT_INFO + '_role', data.account_role);
    setStorage(STORAGE_KEYS.ACCOUNT_INFO + '_stores', data.stores);
    setStorage(STORAGE_KEYS.CURRENT_STORE_ID, currentStoreId.value);
  }

  /** 切换当前门店（调后端接口写 Redis current_store:{account_id}） */
  async function switchStore(storeId: number) {
    if (storeId === currentStoreId.value) return;
    try {
      await switchCurrentStore(storeId);
      currentStoreId.value = storeId;
      setStorage(STORAGE_KEYS.CURRENT_STORE_ID, storeId);
    } catch {
      uni.showToast({ title: '切换门店失败', icon: 'none' });
    }
  }

  /** 获取最新账号信息 */
  async function fetchProfile() {
    try {
      const data = await getAccountProfile();
      profile.value = data;
    } catch {
      // 获取失败静默处理
    }
  }

  /** 退出登录 */
  async function logout() {
    try {
      await logoutApi();
    } catch {
      // 即使接口失败也清除本地状态
    } finally {
      clearAuth();
    }
  }

  /** 清除认证信息 */
  function clearAuth() {
    token.value = '';
    accountId.value = 0;
    realName.value = '';
    accountRole.value = 0;
    currentStoreId.value = 0;
    stores.value = [];
    profile.value = null;
    removeStorage(STORAGE_KEYS.TOKEN);
    removeStorage(STORAGE_KEYS.CURRENT_STORE_ID);
    removeStorage(STORAGE_KEYS.ACCOUNT_INFO + '_id');
    removeStorage(STORAGE_KEYS.ACCOUNT_INFO + '_name');
    removeStorage(STORAGE_KEYS.ACCOUNT_INFO + '_role');
    removeStorage(STORAGE_KEYS.ACCOUNT_INFO + '_stores');
  }

  return {
    token, accountId, realName, accountRole, currentStoreId, stores, profile,
    isLoggedIn, currentStore,
    initAuth, setLoginInfo, switchStore, fetchProfile, logout, clearAuth,
  };
});
