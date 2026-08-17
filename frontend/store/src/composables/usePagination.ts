import { ref, computed } from 'vue';

/**
 * 分页加载组合式函数
 * 适用于列表页面的下拉刷新和上拉加载更多
 */
export function usePagination<T>(
  fetchFn: (params: { page: number; page_size: number }) => Promise<{ list: T[]; total: number }>,
  pageSize = 20
) {
  const list = ref<T[]>([]) as { value: T[] };
  const page = ref(1);
  const total = ref(0);
  const loading = ref(false);
  const refreshing = ref(false);

  const hasMore = computed(() => list.value.length < total.value);
  const isEmpty = computed(() => !loading.value && list.value.length === 0);

  async function loadData(isRefresh = false) {
    if (loading.value) return;
    if (!isRefresh && !hasMore.value) return;

    loading.value = true;
    if (isRefresh) {
      page.value = 1;
      refreshing.value = true;
    }

    try {
      const result = await fetchFn({ page: page.value, page_size: pageSize });
      if (isRefresh) {
        list.value = result.list;
      } else {
        list.value = [...list.value, ...result.list];
      }
      total.value = result.total;
    } catch (e) {
      console.error('分页加载失败:', e);
    } finally {
      loading.value = false;
      refreshing.value = false;
    }
  }

  function onRefresh() {
    return loadData(true);
  }

  function onLoadMore() {
    if (hasMore.value) {
      page.value += 1;
      return loadData(false);
    }
  }

  function init() {
    return loadData(true);
  }

  return {
    list, page, total, loading, refreshing, hasMore, isEmpty,
    loadData, onRefresh, onLoadMore, init,
  };
}
