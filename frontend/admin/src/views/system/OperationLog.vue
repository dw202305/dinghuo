<template>
  <div class="operation-log-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">操作日志</h2>
    </div>

    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="模块">
        <el-select v-model="queryParams.module" placeholder="全部模块" clearable style="width: 150px">
          <el-option
            v-for="item in MODULE_OPTIONS"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="操作人">
        <el-input
          v-model="queryParams.operator_name"
          placeholder="操作人姓名"
          clearable
          style="width: 160px"
        />
      </el-form-item>
      <el-form-item label="时间范围">
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          range-separator="至"
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
        <span class="total-text">共 {{ total }} 条记录</span>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="log_id" label="日志ID" width="80" align="center" />
        <el-table-column label="模块" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="moduleTagType(row.module)" size="small" effect="light">
              {{ moduleText(row.module) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="action" label="操作动作" width="140" show-overflow-tooltip />
        <el-table-column label="操作对象" min-width="160">
          <template #default="{ row }">
            <div class="cell-target">
              <span class="cell-target__type">{{ row.target_type || "-" }}</span>
              <span class="cell-target__no">{{ row.target_no || (row.target_id ? `#${row.target_id}` : "-") }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="操作人" width="140">
          <template #default="{ row }">
            <div class="cell-operator">
              <span class="cell-operator__name">{{ row.operator_name || "-" }}</span>
              <span v-if="row.operator_role" class="cell-operator__role">{{ row.operator_role }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="ip_address" label="IP 地址" width="130">
          <template #default="{ row }">
            {{ row.ip_address || "-" }}
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.remark || "-" }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="操作时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="90" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="openDetailDialog(row as OperationLog)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <TablePagination
        :page="queryParams.page"
        :page-size="queryParams.page_size"
        :total="total"
        @page-change="handlePageChange"
        @size-change="handleSizeChange"
      />
    </el-card>

    <!-- 日志详情弹窗 -->
    <el-dialog v-model="detailVisible" title="日志详情" width="640px" destroy-on-close>
      <el-descriptions v-if="detailTarget" :column="2" border size="small">
        <el-descriptions-item label="日志ID">{{ detailTarget.log_id }}</el-descriptions-item>
        <el-descriptions-item label="模块">{{ moduleText(detailTarget.module) }}</el-descriptions-item>
        <el-descriptions-item label="操作动作">{{ detailTarget.action }}</el-descriptions-item>
        <el-descriptions-item label="操作对象">
          {{ detailTarget.target_type }}{{ detailTarget.target_no ? ` · ${detailTarget.target_no}` : "" }}
        </el-descriptions-item>
        <el-descriptions-item label="操作人">{{ detailTarget.operator_name }}</el-descriptions-item>
        <el-descriptions-item label="IP 地址">{{ detailTarget.ip_address || "-" }}</el-descriptions-item>
        <el-descriptions-item label="操作时间" :span="2">{{ formatDateTime(detailTarget.created_at) }}</el-descriptions-item>
        <el-descriptions-item v-if="detailTarget.remark" label="备注" :span="2">{{ detailTarget.remark }}</el-descriptions-item>
        <el-descriptions-item label="变更前数据" :span="2">
          <pre class="json-block">{{ prettyJson(detailTarget.before_data) }}</pre>
        </el-descriptions-item>
        <el-descriptions-item label="变更后数据" :span="2">
          <pre class="json-block">{{ prettyJson(detailTarget.after_data) }}</pre>
        </el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue"
import { getOperationLogs } from "@/api/system"
import type { OperationLog } from "@/types/admin"
import { useTable } from "@/composables/useTable"
import { formatDateTime } from "@/utils/format"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"

/** 模块选项（与后端 logOperation 写入的 module 值对齐） */
const MODULE_OPTIONS = [
  { value: "order", label: "订单" },
  { value: "order_state", label: "订单状态" },
  { value: "payment", label: "支付" },
  { value: "invoice", label: "发票" },
  { value: "after_sale", label: "售后" },
  { value: "inventory", label: "库存" },
  { value: "technical_audit", label: "技术审核" },
  { value: "ownership", label: "归属" },
  { value: "balance", label: "余额账户" },
  { value: "finance", label: "财务" },
  { value: "product", label: "商品" },
  { value: "system", label: "系统" }
]

const MODULE_LABEL_MAP: Record<string, string> = Object.fromEntries(
  MODULE_OPTIONS.map((item) => [item.value, item.label])
)

const MODULE_TAG_TYPE_MAP: Record<string, "primary" | "success" | "warning" | "info" | "danger"> = {
  order: "primary",
  order_state: "primary",
  payment: "success",
  invoice: "warning",
  after_sale: "danger",
  inventory: "info",
  technical_audit: "warning",
  ownership: "info",
  balance: "success",
  finance: "success",
  product: "primary",
  system: "info"
}

function moduleText(module: string): string {
  return MODULE_LABEL_MAP[module] || module || "-"
}

function moduleTagType(module: string): "primary" | "success" | "warning" | "info" | "danger" {
  return MODULE_TAG_TYPE_MAP[module] ?? "info"
}

/** 列表查询参数 */
interface LogQueryParams {
  module?: string
  operator_name?: string
  [key: string]: unknown
}

/** 时间范围（映射到 start_date / end_date） */
const dateRange = ref<[string, string] | null>(null)

const {
  loading,
  tableData,
  total,
  queryParams,
  handleSearch: searchTable,
  handleReset: resetTable,
  handlePageChange,
  handleSizeChange
} = useTable<OperationLog, LogQueryParams>({
  fetchApi: (params) =>
    getOperationLogs({
      ...params,
      start_date: dateRange.value?.[0] || undefined,
      end_date: dateRange.value?.[1] || undefined
    }),
  defaultParams: {
    module: undefined,
    operator_name: undefined
  }
})

/** 时间范围变化时回到第一页 */
watch(dateRange, () => {
  queryParams.page = 1
})

function handleSearch(): void {
  searchTable()
}

function handleReset(): void {
  dateRange.value = null
  resetTable()
}

/** 详情弹窗 */
const detailVisible = ref<boolean>(false)
const detailTarget = ref<OperationLog | null>(null)

function openDetailDialog(row: OperationLog): void {
  detailTarget.value = row
  detailVisible.value = true
}

/**
 * 格式化 JSON 数据用于展示
 */
function prettyJson(data: Record<string, unknown> | null | undefined): string {
  if (!data || Object.keys(data).length === 0) return "（无）"
  try {
    return JSON.stringify(data, null, 2)
  } catch {
    return String(data)
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

.table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.cell-target {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cell-target__type {
  font-size: 12px;
  color: var(--color-neutral-400);
}

.cell-target__no {
  font-weight: 500;
  color: var(--color-neutral-800);
  font-family: var(--font-family-mono);
}

.cell-operator {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cell-operator__name {
  font-weight: 500;
  color: var(--color-neutral-800);
}

.cell-operator__role {
  font-size: 12px;
  color: var(--color-neutral-400);
}

.json-block {
  margin: 0;
  padding: 8px 12px;
  background: var(--color-neutral-50);
  border-radius: var(--radius-sm);
  font-family: var(--font-family-mono);
  font-size: 12px;
  line-height: 1.6;
  color: var(--color-neutral-700);
  max-height: 220px;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
