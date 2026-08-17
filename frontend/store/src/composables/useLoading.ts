import { ref } from 'vue';

/**
 * Loading 状态组合式函数
 */
export function useLoading() {
  const loading = ref(false);

  /** 执行异步操作并自动管理loading状态 */
  async function withLoading(fn: () => Promise<void>) {
    loading.value = true;
    try {
      await fn();
    } finally {
      loading.value = false;
    }
  }

  function showLoading(title = '加载中...') {
    uni.showLoading({ title, mask: true });
  }

  function hideLoading() {
    uni.hideLoading();
  }

  return { loading, withLoading, showLoading, hideLoading };
}
