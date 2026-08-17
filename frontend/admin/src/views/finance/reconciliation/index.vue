<template>
  <div class="reconciliation-page">
    <!-- 顶部日期选择器 -->
    <div class="period-selector">
      <el-radio-group v-model="periodType" @change="handlePeriodChange">
        <el-radio-button value="day">按日</el-radio-button>
        <el-radio-button value="week">按周</el-radio-button>
        <el-radio-button value="month">按月</el-radio-button>
      </el-radio-group>

      <el-date-picker
        v-model="selectedDate"
        :type="datePickerType"
        :placeholder="datePickerPlaceholder"
        :format="dateFormat"
        :value-format="dateValueFormat"
        style="width: 220px; margin-left: 16px"
        @change="loadData"
      />

      <el-button
        type="success"
        :icon="Download"
        :loading="exporting"
        style="margin-left: 16px"
        @click="handleExport"
      >
        导出对账报告
      </el-button>
    </div>

    <!-- 汇总卡片 -->
    <div class="summary-cards" v-loading="summaryLoading">
      <div class="summary-card">
        <div class="summary-card__label">充值总额</div>
        <div class="summary-card__value text-success">
          ¥{{ formatCentToYuan(summary.total_recharge_cent) }}
        </div>
        <div class="summary-card__detail">
          <span>微信 ¥{{ formatCentToYuan(summary.wechat_received_cent) }}</span>
          <span>支付宝 ¥{{ formatCentToYuan(summary.alipay_received_cent) }}</span>
          <span>余额 ¥{{ formatCentToYuan(summary.balance_recharge_cent) }}</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-card__label">退款总额</div>
        <div class="summary-card__value text-error">
          ¥{{ formatCentToYuan(summary.total_refund_cent) }}
        </div>
        <div class="summary-card__detail">
          <span>本期退款金额合计</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-card__label">净收入</div>
        <div class="summary-card__value text-primary">
          ¥{{ formatCentToYuan(summary.net_income_cent) }}
        </div>
        <div class="summary-card__detail">
          <span>充值总额 - 退款总额</span>
        </div>
      </div>
      <div class="summary-card" :class="{ 'summary-card--abnormal': summary.difference_cent !== 0 }">
        <div class="summary-card__label">差异金额</div>
        <div class="summary-card__value" :class="summary.difference_cent === 0 ? 'text-success' : 'text-error'">
          ¥{{ formatCentToYuan(summary.difference_cent) }}
        </div>
        <div class="summary-card__detail">
          <span>应收 ¥{{ formatCentToYuan(summary.expected_cent) }}</span>
          <span>实收 ¥{{ formatCentToYuan(summary.actual_cent) }}</span>
        </div>
      </div>
    </div>

    <!-- 对账明细表格 -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <div class="table-toolbar__left">
          <span class="section-title">对账明细</span>
        </div>
        <div class="table-toolbar__right">
          <span class="total-text">共 {{ detailTotal }} 条记录</span>
        </div>
      </div>

      <el-table
        :data="detailList"
        v-loading="detailLoading"
        stripe
        :row-class-name="detailRowClassName"
        @row-click="handleRowClick"
      >
        <el-table-column prop="date" label="日期" width="120" />
        <el-table-column prop="channel" label="渠道" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="channelTagType(row.channel)" size="small" effect="light">
              {{ row.channel }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="order_count" label="订单数" width="100" align="right" />
        <el-table-column label="应收金额" width="130" align="right">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatCentToYuan(row.expected_amount_cent) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="实收金额" width="130" align="right">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatCentToYuan(row.actual_amount_cent) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="差异" width="130" align="right">
          <template #default="{ row }">
            <span :class="row.difference_cent === 0 ? 'text-success' : 'text-error'">
              {{ row.difference_cent === 0 ? '¥0.00' : `¥${formatCentToYuan(row.difference_cent)}` }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'normal' ? 'success' : 'danger'" size="small" effect="light" round>
              {{ row.status === 'normal' ? '正常' : '异常' }}
            </el-tag>
          </template>
        </el-table-column>
      </el-table>

      <TablePagination
        :page="detailPage"
        :page-size="detailPageSize"
        :total="detailTotal"
        @page-change="handleDetailPageChange"
        @size-change="handleDetailSizeChange"
      />
    </el-card>

    <!-- 异常详情弹窗 -->
    <el-dialog v-model="abnormalVisible" title="异常详情" width="500px" destroy-on-close>
      <el-descriptions :column="2" border v-if="currentRow">
        <el-descriptions-item label="日期" :span="2">{{ currentRow.date }}</el-descriptions-item>
        <el-descriptions-item label="渠道">
          <el-tag :type="channelTagType(currentRow.channel)" size="small" effect="light">
            {{ currentRow.channel }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="订单数">{{ currentRow.order_count }}</el-descriptions-item>
        <el-descriptions-item label="应收金额">
          <span class="amount-text">¥{{ formatCentToYuan(currentRow.expected_amount_cent) }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="实收金额">
          <span class="amount-text">¥{{ formatCentToYuan(currentRow.actual_amount_cent) }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="差异金额" :span="2">
          <span class="text-error" style="font-weight: 600">
            ¥{{ formatCentToYuan(currentRow.difference_cent) }}
          </span>
        </el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="abnormalVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue"
import { Download } from "@element-plus/icons-vue"
import { ElMessage } from "element-plus"
import {
  getReconciliationSummary,
  getReconciliationDetail,
  exportReconciliation
} from "@/api/finance"
import { formatMoney } from "@/utils/format"
import type {
  ReconciliationSummary as ReconciliationSummaryType,
  ReconciliationDetail as ReconciliationDetailType
} from "@/types/finance"
import TablePagination from "@/components/TablePagination.vue"
import dayjs from "dayjs"

/** 分转元 */
function formatCentToYuan(cent: number): string {
  return formatMoney(cent / 100)
}

/** 周期类型 */
const periodType = ref<"day" | "week" | "month">("day")

/** 选中日期 */
const selectedDate = ref<string>(dayjs().format("YYYY-MM-DD"))

/** 日期选择器类型 */
const datePickerType = computed(() => {
  const map: Record<string, "date" | "week" | "month"> = {
    day: "date",
    week: "week",
    month: "month"
  }
  return map[periodType.value]
})

/** 日期选择器占位符 */
const datePickerPlaceholder = computed(() => {
  const map: Record<string, string> = {
    day: "选择日期",
    week: "选择周",
    month: "选择月份"
  }
  return map[periodType.value]
})

/** 日期格式化 */
const dateFormat = computed(() => {
  const map: Record<string, string> = {
    day: "YYYY-MM-DD",
    week: "YYYY 第 ww 周",
    month: "YYYY-MM"
  }
  return map[periodType.value]
})

/** 日期值格式 */
const dateValueFormat = computed(() => {
  const map: Record<string, string> = {
    day: "YYYY-MM-DD",
    week: "YYYY-MM-DD",
    month: "YYYY-MM"
  }
  return map[periodType.value]
})

/** 渠道标签类型 */
function channelTagType(channel: string): "success" | "primary" | "warning" | "info" {
  if (channel.includes("微信")) return "success"
  if (channel.includes("支付宝")) return "primary"
  if (channel.includes("余额")) return "warning"
  return "info"
}

/* ===================== 汇总数据 ===================== */

const summaryLoading = ref<boolean>(false)
const summary = reactive<ReconciliationSummaryType>({
  period: "",
  total_recharge_cent: 0,
  total_refund_cent: 0,
  net_income_cent: 0,
  wechat_received_cent: 0,
  alipay_received_cent: 0,
  balance_recharge_cent: 0,
  expected_cent: 0,
  actual_cent: 0,
  difference_cent: 0
})

/* ===================== 明细数据 ===================== */

const detailLoading = ref<boolean>(false)
const detailList = ref<ReconciliationDetailType[]>([])
const detailTotal = ref<number>(0)
const detailPage = ref<number>(1)
const detailPageSize = ref<number>(20)

/** 行样式：异常行红色高亮 */
function detailRowClassName({ row }: { row: ReconciliationDetailType }): string {
  if (row.status === "abnormal") return "abnormal-row"
  return ""
}

/** 加载汇总和明细 */
async function loadData(): Promise<void> {
  loadSummary()
  loadDetail()
}

/** 加载汇总 */
async function loadSummary(): Promise<void> {
  summaryLoading.value = true
  try {
    const res = await getReconciliationSummary({
      period_type: periodType.value,
      date: selectedDate.value
    })
    Object.assign(summary, res)
  } catch {
    /* 错误已由拦截器处理 */
  } finally {
    summaryLoading.value = false
  }
}

/** 加载明细 */
async function loadDetail(): Promise<void> {
  detailLoading.value = true
  try {
    const { startDate, endDate } = computeDateRange()
    const res = await getReconciliationDetail({
      period_type: periodType.value,
      start_date: startDate,
      end_date: endDate,
      page: detailPage.value,
      page_size: detailPageSize.value
    })
    detailList.value = res.list
    detailTotal.value = res.total
  } catch {
    /* 错误已由拦截器处理 */
  } finally {
    detailLoading.value = false
  }
}

/** 根据周期类型计算起止日期 */
function computeDateRange(): { startDate: string; endDate: string } {
  const d = dayjs(selectedDate.value)
  if (periodType.value === "day") {
    return { startDate: d.format("YYYY-MM-DD"), endDate: d.format("YYYY-MM-DD") }
  }
  if (periodType.value === "week") {
    return { startDate: d.startOf("week").add(1, "day").format("YYYY-MM-DD"), endDate: d.endOf("week").add(1, "day").format("YYYY-MM-DD") }
  }
  /** month */
  return { startDate: d.startOf("month").format("YYYY-MM-DD"), endDate: d.endOf("month").format("YYYY-MM-DD") }
}

/** 切换周期 */
function handlePeriodChange(): void {
  /** 切换周期后重置日期为当天 */
  selectedDate.value = dayjs().format("YYYY-MM-DD")
  detailPage.value = 1
  loadData()
}

/** 明细分页 */
function handleDetailPageChange(page: number): void {
  detailPage.value = page
  loadDetail()
}

function handleDetailSizeChange(size: number): void {
  detailPageSize.value = size
  detailPage.value = 1
  loadDetail()
}

/* ===================== 异常详情弹窗 ===================== */

const abnormalVisible = ref<boolean>(false)
const currentRow = ref<ReconciliationDetailType | null>(null)

function handleRowClick(row: ReconciliationDetailType): void {
  if (row.status === "abnormal") {
    currentRow.value = row
    abnormalVisible.value = true
  }
}

/* ===================== 导出 ===================== */

const exporting = ref<boolean>(false)

async function handleExport(): Promise<void> {
  exporting.value = true
  try {
    const { startDate, endDate } = computeDateRange()
    const result = await exportReconciliation({
      start_date: startDate,
      end_date: endDate,
      period_type: periodType.value
    })
    if (result?.file_url) {
      window.open(result.file_url, "_blank")
      ElMessage.success("导出成功")
    }
  } catch {
    ElMessage.error("导出失败")
  } finally {
    exporting.value = false
  }
}

/** 初始化加载 */
loadData()
</script>

<style scoped>
.period-selector {
  display: flex;
  align-items: center;
  padding: 16px 24px;
  background: #fff;
  border-radius: var(--radius-md);
  margin-bottom: 16px;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.summary-card {
  background: #fff;
  border-radius: var(--radius-lg);
  padding: 20px 24px;
  box-shadow: var(--shadow-1);
  border-left: 3px solid var(--color-primary-200);
  transition: box-shadow 0.2s;
}

.summary-card:hover {
  box-shadow: var(--shadow-2);
}

.summary-card--abnormal {
  border-left-color: var(--color-error);
}

.summary-card__label {
  font-size: 13px;
  color: var(--color-neutral-500);
  margin-bottom: 8px;
}

.summary-card__value {
  font-size: 24px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  margin-bottom: 8px;
}

.summary-card__detail {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: var(--color-neutral-400);
}

.text-success {
  color: var(--color-success);
}

.text-error {
  color: var(--color-error);
}

.text-primary {
  color: var(--color-primary-500);
}

.table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.table-toolbar__left {
  display: flex;
  gap: 8px;
}

.table-toolbar__right {
  display: flex;
  gap: 8px;
}

.section-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-neutral-700);
}

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.amount-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

/* 异常行红色高亮 */
:deep(.abnormal-row) {
  background-color: var(--color-error-light) !important;
}

:deep(.abnormal-row:hover > td) {
  background-color: #fee2e2 !important;
}

:deep(.abnormal-row) {
  cursor: pointer;
}
</style>
