import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

/**
 * 认证相关组合式函数
 * 提供登录态判断、权限检查等通用逻辑
 */
export function useAuth() {
  const authStore = useAuthStore();

  const isLoggedIn = computed(() => authStore.isLoggedIn);
  const userName = computed(() => authStore.realName);
  const storeName = computed(() => authStore.currentStore?.store_name ?? '');

  /** 检查是否已登录，未登录则跳转登录页 */
  function checkLogin(): boolean {
    if (!authStore.isLoggedIn) {
      uni.navigateTo({ url: '/pages/login/index' });
      return false;
    }
    return true;
  }

  /**
   * 检查是否有下单权限（下单员及以上）
   * account_role: 1-门店管理员 2-下单员 3-财务 4-安装售后 5-只读
   */
  function canCreateOrder(): boolean {
    return authStore.accountRole <= 2;
  }

  return { isLoggedIn, userName, storeName, checkLogin, canCreateOrder };
}
