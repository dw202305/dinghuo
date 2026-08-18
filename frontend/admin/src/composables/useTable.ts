/**
 * 表格 CRUD 通用逻辑
 */

import { ref, reactive, onMounted } from "vue"
import type { Ref } from "vue"
import { ElMessage, ElMessageBox } from "element-plus"

/** 表格配置 */
interface UseTableOptions<T, P extends object> {
  /** 获取列表数据的 API 函数 */
  fetchApi: (params: P) => Promise<{ list: T[]; total: number }>
  /** 初始查询参数 */
  defaultParams?: Partial<P>
  /** 每页条数 */
  pageSize?: number
  /** 是否立即加载 */
  immediate?: boolean
}

/**
 * 通用表格逻辑 composable
 */
export function useTable<T, P extends object>(options: UseTableOptions<T, P>) {
  const { fetchApi, defaultParams = {}, pageSize = 20, immediate = true } = options

  const loading = ref<boolean>(false)
  const tableData = ref<T[]>([]) as Ref<T[]>
  const total = ref<number>(0)

  const queryParams = reactive<Record<string, unknown>>({
    page: 1,
    page_size: pageSize,
    ...defaultParams
  }) as P & { page: number; page_size: number }

  /**
   * 加载数据
   */
  async function loadData(): Promise<void> {
    loading.value = true
    try {
      const result = await fetchApi(queryParams as P)
      tableData.value = result.list
      total.value = result.total
    } catch (error: unknown) {
      const errMsg = error instanceof Error ? error.message : "加载数据失败"
      ElMessage.error(errMsg)
    } finally {
      loading.value = false
    }
  }

  /**
   * 搜索
   */
  function handleSearch(): void {
    queryParams.page = 1
    loadData()
  }

  /**
   * 重置搜索
   */
  function handleReset(): void {
    Object.keys(queryParams).forEach((key) => {
      if (key !== "page" && key !== "page_size") {
        ;(queryParams as Record<string, unknown>)[key] = (defaultParams as unknown as Record<string, unknown>)[key] ?? undefined
      }
    })
    queryParams.page = 1
    loadData()
  }

  /**
   * 分页变更
   */
  function handlePageChange(page: number): void {
    queryParams.page = page
    loadData()
  }

  /**
   * 每页条数变更
   */
  function handleSizeChange(size: number): void {
    queryParams.page_size = size
    queryParams.page = 1
    loadData()
  }

  /**
   * 删除确认
   */
  async function confirmDelete(message: string, deleteFn: () => Promise<unknown>): Promise<void> {
    try {
      await ElMessageBox.confirm(message, "确认删除", {
        confirmButtonText: "确定",
        cancelButtonText: "取消",
        type: "warning"
      })
      await deleteFn()
      ElMessage.success("删除成功")
      loadData()
    } catch {
      // 用户取消
    }
  }

  if (immediate) {
    onMounted(() => {
      loadData()
    })
  }

  return {
    loading,
    tableData,
    total,
    queryParams,
    loadData,
    handleSearch,
    handleReset,
    handlePageChange,
    handleSizeChange,
    confirmDelete
  }
}
