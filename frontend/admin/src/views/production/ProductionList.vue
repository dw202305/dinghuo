<template>
  <div class="production-list-page">
    <!-- 页面标题 + 操作按钮 -->
    <div class="page-header">
      <h2 class="page-title">生产单管理</h2>
      <div class="page-header__actions">
        <el-button
          type="success"
          :disabled="selectedIds.length === 0"
          @click="handleBatchComplete"
        >
          批量标记完成
        </el-button>
      </div>
    </div>

    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="关键词">
        <el-input
          v-model="queryParams.keyword"
          placeholder="生产单号/订单号"
          clearable
          style="width: 200px"
        />
      </el-form-item>
      <el-form-item label="门店名称">
        <el-input
          v-model="storeKeyword"
          placeholder="请输入门店名称"
          clearable
          style="width: 160px"
        />
      </el-form-item>
      <el-form-item label="生产状态">
        <el-select v-model="queryParams.production_status" placeholder="全部" clearable style="width: 130px">
          <el-option label="待排产" :value="0" />
          <el-option label="生产中" :value="1" />
          <el-option label="质检中" :value="2" />
          <el-option label="已完成" :value="3" />
        </el-select>
      </el-form-item>
      <el-form-item label="发货状态">
        <el-select v-model="queryParams.shipping_status" placeholder="全部" clearable style="width: 120px">
          <el-option label="待发货" :value="0" />
          <el-option label="已发货" :value="1" />
          <el-option label="已签收" :value="2" />
        </el-select>
      </el-form-item>
      <el-form-item label="日期范围">
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          style="width: 260px"
        />
      </el-form-item>
    </SearchForm>

    <!-- 表格 -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <div class="table-toolbar__left">
          <span class="total-text">共 {{ total }} 条记录</span>
        </div>
      </div>

      <el-table
        :data="tableData"
        v-loading="loading"
        stripe
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="45" align="center" />
        <el-table-column prop="item_no" label="生产单号" width="250" fixed show-overflow-tooltip>
          <template #default="{ row }">
            <el-button type="primary" link @click="openDetailDialog(row)">
              {{ row.item_no }}
            </el-button>
          </template>
        </el-table-column>
        <el-table-column prop="order_no" label="关联订单号" width="230" show-overflow-tooltip />
        <el-table-column prop="store_name" label="门店名称" width="150" show-overflow-tooltip />
        <el-table-column prop="fabric_name" label="面料" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <div class="cell-fabric">
              <span class="cell-fabric__name">{{ row.fabric_name }}</span>
              <span class="cell-fabric__no">{{ row.fabric_no }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="尺寸(宽×高)" width="130">
          <template #default="{ row }">
            <span class="size-text">{{ row.width }} × {{ row.height }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="production_status" label="生产状态" width="110" align="center">
          <template #default="{ row }">
            <StatusTag
              :status="row.production_status"
              :label="row.production_status_text"
              :type-map="productionStatusTypeMap"
            />
          </template>
        </el-table-column>
        <el-table-column prop="shipping_status_text" label="发货状态" width="100" align="center">
          <template #default="{ row }">
            <StatusTag
              :status="row.shipping_status"
              :label="row.shipping_status_text"
              :type-map="shippingStatusTypeMap"
            />
          </template>
        </el-table-column>
        <el-table-column prop="planned_date" label="计划完成日期" width="130">
          <template #default="{ row }">
            {{ row.planned_date || "-" }}
          </template>
        </el-table-column>
        <el-table-column prop="completed_at" label="实际完成日期" width="130">
          <template #default="{ row }">
            {{ row.completed_at || "-" }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="openDetailDialog(row)">
              详情
            </el-button>
            <el-button
              v-if="row.production_status < 3"
              type="success"
              link
              size="small"
              @click="handleMarkComplete(row)"
            >
              标记完成
            </el-button>
            <el-button type="info" link size="small" @click="handlePrint(row)">
              打印
            </el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty description="暂无生产单数据" />
        </template>
      </el-table>

      <TablePagination
        :page="queryParams.page"
        :page-size="queryParams.page_size"
        :total="total"
        @page-change="handlePageChange"
        @size-change="handleSizeChange"
      />
    </el-card>

    <!-- 详情弹窗 -->
    <el-dialog
      v-model="detailDialogVisible"
      title="生产单详情"
      width="600px"
      destroy-on-close
    >
      <div v-loading="detailLoading">
        <template v-if="detailData">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="生产单号" :span="2">{{ detailData.item_no }}</el-descriptions-item>
            <el-descriptions-item label="关联订单号">{{ detailData.order_no }}</el-descriptions-item>
            <el-descriptions-item label="门店名称">{{ detailData.store_name }}</el-descriptions-item>
            <el-descriptions-item label="安装位置">{{ detailData.install_position || "-" }}</el-descriptions-item>
            <el-descriptions-item label="面料">
              {{ detailData.fabric_name }}（{{ detailData.fabric_no }}）
            </el-descriptions-item>
            <el-descriptions-item label="尺寸">{{ detailData.width }} × {{ detailData.height }}</el-descriptions-item>
            <el-descriptions-item label="面积">{{ detailData.area }} ㎡</el-descriptions-item>
            <el-descriptions-item label="生产状态">
              <StatusTag
                :status="detailData.production_status"
                :label="detailData.production_status_text"
                :type-map="productionStatusTypeMap"
              />
            </el-descriptions-item>
            <el-descriptions-item label="发货状态">
              <StatusTag
                :status="detailData.shipping_status"
                :label="detailData.shipping_status_text"
                :type-map="shippingStatusTypeMap"
              />
            </el-descriptions-item>
            <el-descriptions-item label="物流单号">{{ detailData.tracking_no || "-" }}</el-descriptions-item>
            <el-descriptions-item label="承运商">{{ detailData.carrier || "-" }}</el-descriptions-item>
            <el-descriptions-item label="计划完成日期">{{ detailData.planned_date || "-" }}</el-descriptions-item>
            <el-descriptions-item label="实际完成日期">{{ detailData.completed_at || "-" }}</el-descriptions-item>
            <el-descriptions-item label="创建时间" :span="2">{{ formatDateTime(detailData.created_at) }}</el-descriptions-item>
          </el-descriptions>
        </template>
      </div>
    </el-dialog>

    <!-- 标记完成确认弹窗 -->
    <ConfirmDialog
      v-model:visible="completeDialogVisible"
      title="标记完成"
      :message="completeMessage"
      type="warning"
      @confirm="confirmMarkComplete"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from "vue"
import { ElMessage } from "element-plus"
import {
  getProductionList,
  getProductionDetail,
  updateProductionStatus,
  batchUpdateProductionStatus,
  printProductionOrder
} from "@/api/production"
import type { ProductionListItem } from "@/types/production"
import { formatDateTime } from "@/utils/format"
import { useTable } from "@/composables/useTable"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"
import StatusTag from "@/components/StatusTag.vue"
import ConfirmDialog from "@/components/ConfirmDialog.vue"

/** 日期范围 */
const dateRange = ref<[string, string] | null>(null)
/** 门店关键词 */
const storeKeyword = ref<string>("")
/** 多选ID */
const selectedIds = ref<number[]>([])

/** 生产状态颜色映射 */
const productionStatusTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "0": "info",      // 待排产
  "1": "primary",   // 生产中
  "2": "warning",   // 质检中
  "3": "success"    // 已完成
}

/** 发货状态颜色映射 */
const shippingStatusTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "0": "info",      // 待发货
  "1": "primary",   // 已发货
  "2": "success"    // 已签收
}

const {
  loading,
  tableData,
  total,
  queryParams,
  handleSearch,
  handleReset,
  handlePageChange,
  handleSizeChange
} = useTable<ProductionListItem, Record<string, unknown>>({
  fetchApi: getProductionList as unknown as (params: Record<string, unknown>) => Promise<{ list: ProductionListItem[]; total: number }>,
  defaultParams: {
    keyword: undefined,
    production_status: undefined,
    shipping_status: undefined,
    store_name: undefined,
    start_date: undefined,
    end_date: undefined
  }
})

/** 搜索时同步额外参数 */
watch([dateRange, storeKeyword], () => {
  if (dateRange.value) {
    (queryParams as Record<string, unknown>).start_date = dateRange.value[0]
    (queryParams as Record<string, unknown>).end_date = dateRange.value[1]
  } else {
    (queryParams as Record<string, unknown>).start_date = undefined
    (queryParams as Record<string, unknown>).end_date = undefined
  }
  ;(queryParams as Record<string, unknown>).store_name = storeKeyword.value || undefined
})

/** 多选变化 */
function handleSelectionChange(rows: ProductionListItem[]): void {
  selectedIds.value = rows.map((r) => r.item_id)
}

/* ========== 详情弹窗 ========== */

const detailDialogVisible = ref<boolean>(false)
const detailLoading = ref<boolean>(false)
const detailData = ref<ProductionListItem | null>(null)

/**
 * 打开详情弹窗
 */
async function openDetailDialog(row: ProductionListItem): Promise<void> {
  detailDialogVisible.value = true
  detailLoading.value = true
  detailData.value = null
  try {
    detailData.value = await getProductionDetail(row.item_id)
  } catch {
    // 错误已由拦截器处理
  } finally {
    detailLoading.value = false
  }
}

/* ========== 标记完成 ========== */

const completeDialogVisible = ref<boolean>(false)
const completeTargetId = ref<number | null>(null)
const completeTargetNo = ref<string>("")

/** 确认弹窗提示文案 */
const completeMessage = computed(() => {
  return `确定要将生产单「${completeTargetNo.value}」标记为已完成吗？`
})

/**
 * 标记单个完成
 */
function handleMarkComplete(row: ProductionListItem): void {
  completeTargetId.value = row.item_id
  completeTargetNo.value = row.item_no
  completeDialogVisible.value = true
}

/**
 * 确认标记完成
 */
async function confirmMarkComplete(): Promise<void> {
  if (completeTargetId.value === null) return
  try {
    await updateProductionStatus(completeTargetId.value, 3)
    ElMessage.success("已标记为完成")
    await handleSearch()
  } catch {
    // 错误已由拦截器处理
  }
}

/**
 * 批量标记完成
 */
async function handleBatchComplete(): Promise<void> {
  if (selectedIds.value.length === 0) return
  try {
    await batchUpdateProductionStatus({
      item_ids: selectedIds.value,
      status: 3
    })
    ElMessage.success(`已批量标记 ${selectedIds.value.length} 条为完成`)
    await handleSearch()
  } catch {
    // 错误已由拦截器处理
  }
}

/**
 * 打印生产单
 */
async function handlePrint(row: ProductionListItem): Promise<void> {
  try {
    const result = await printProductionOrder(row.item_id)
    if (result?.file_url) {
      window.open(result.file_url, "_blank")
    }
  } catch {
    ElMessage.error("获取打印文件失败")
  }
}
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-neutral-800);
  margin: 0;
}

.page-header__actions {
  display: flex;
  gap: 8px;
}

.table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.table-toolbar__left {
  display: flex;
  gap: 8px;
}

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.cell-fabric {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cell-fabric__name {
  font-weight: 500;
  color: var(--color-neutral-800);
}

.cell-fabric__no {
  font-size: 12px;
  color: var(--color-neutral-400);
  font-family: var(--font-family-mono, monospace);
}

.size-text {
  font-family: var(--font-family-mono, monospace);
  font-size: 13px;
  color: var(--color-neutral-600);
}
</style>
