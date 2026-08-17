/**
 * 分页逻辑组合式函数
 */

import { ref, reactive } from "vue"

interface PaginationState {
  page: number
  pageSize: number
  total: number
}

/**
 * 分页 composable
 */
export function usePagination(defaultPageSize = 20) {
  const state = reactive<PaginationState>({
    page: 1,
    pageSize: defaultPageSize,
    total: 0
  })

  const loading = ref<boolean>(false)

  /**
   * 设置总数
   */
  function setTotal(value: number): void {
    state.total = value
  }

  /**
   * 设置页码
   */
  function setPage(value: number): void {
    state.page = value
  }

  /**
   * 重置分页
   */
  function resetPagination(): void {
    state.page = 1
    state.total = 0
  }

  /**
   * 获取分页参数
   */
  function getParams(): { page: number; page_size: number } {
    return {
      page: state.page,
      page_size: state.pageSize
    }
  }

  return {
    state,
    loading,
    setTotal,
    setPage,
    resetPagination,
    getParams
  }
}
