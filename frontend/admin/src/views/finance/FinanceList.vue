<template>
  <div class="finance-list-page">
    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="订单号">
        <el-input
          v-model="queryParams.keyword"
          placeholder="收款编号/订单号"
          clearable
          style="width: 200px"
        />
      </el-form-item>
      <el-form-item label="支付方式">
        <el-select
          v-model="queryParams.pay_channel"
          placeholder="全部"
          clearable
          style="width: 140px"
        >
          <el-option label="微信" :value="1" />
          <el-option label="支付宝" :value="2" />
          <el-option label="银行转账" :value="3" />
        </el-select>
      </el-form-item>
      <el-form-item label="收款状态">
        <el-select
          v-model="queryParams.pay_status"
          placeholder="全部"
          clearable
          style="width: 140px"
        >
          <el-option label="已收" :value="1" />
          <el-option label="待收" :value="0" />
          <el-option label="部分收" :value="2" />
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
          <el-button type="success" :icon="Download" @click="handleExport" :loading="exporting">
            导出 Excel
          </el-button>
        </div>
        <div class="table-toolbar__right">
          <span class="total-text">共 {{ total }} 条记录</span>
        </div>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe show-summary :summary-method="getSummary">
        <el-table-column prop="payment_no" label="收款编号" width="200" fixed show-overflow-tooltip />
        <el-table-column prop="order_no" label="关联订单号" width="220" show-overflow-tooltip />
        <el-table-column prop="store_name" label="门店名称" width="140" show-overflow-tooltip />
        <el-table-column prop="receivable_amount" label="应收金额" width="120" align="right">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatMoney(row.receivable_amount || row.pay_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="actual_amount" label="实收金额" width="120" align="right">
          <template #default="{ row }">
            <span class="amount-text paid">¥{{ formatMoney(row.actual_amount || row.pay_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="支付方式" width="110" align="center">
          <template #default="{ row }">
            <el-tag
              :type="payChannelTag(row.pay_channel)"
              size="small"
              effect="light"
            >
              {{ row.pay_channel_text || channelLabel(row.pay_channel) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="收款状态" width="100" align="center">
          <template #default="{ row }">
            <StatusTag
              :status="row.pay_status"
              :label="row.pay_status_text || statusLabel(row.pay_status)"
              :type-map="receiptStatusTypeMap"
            />
          </template>
        </el-table-column>
        <el-table-column prop="paid_at" label="收款时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.paid_at) }}
          </template>
        </el-table-column>
        <el-table-column prop="operator_name" label="操作人" width="100" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.operator_name || "-" }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleViewDetail(row)">
              详情
            </el-button>
            <el-button
              v-if="row.pay_status !== 1"
              type="success"
              link
              size="small"
              @click="handleMarkReceived(row)"
            >
              标记已收
            </el-button>
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

    <!-- 收款详情弹窗 -->
    <el-dialog v-model="detailVisible" title="收款详情" width="560px" destroy-on-close>
      <el-descriptions :column="2" border v-loading="detailLoading">
        <el-descriptions-item label="收款编号" :span="2">{{ detailData?.payment_no }}</el-descriptions-item>
        <el-descriptions-item label="关联订单号">{{ detailData?.order_no }}</el-descriptions-item>
        <el-descriptions-item label="门店名称">{{ detailData?.store_name }}</el-descriptions-item>
        <el-descriptions-item label="应收金额">
          <span class="amount-text">¥{{ formatMoney(detailData?.receivable_amount || detailData?.pay_amount) }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="实收金额">
          <span class="amount-text paid">¥{{ formatMoney(detailData?.actual_amount || detailData?.pay_amount) }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="支付方式">
          <el-tag
            :type="payChannelTag(detailData?.pay_channel ?? 0)"
            size="small"
            effect="light"
          >
            {{ detailData?.pay_channel_text || channelLabel(detailData?.pay_channel ?? 0) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="收款状态">
          <StatusTag
            :status="detailData?.pay_status ?? 0"
            :label="detailData?.pay_status_text || statusLabel(detailData?.pay_status ?? 0)"
            :type-map="receiptStatusTypeMap"
          />
        </el-descriptions-item>
        <el-descriptions-item label="支付流水号" :span="2">{{ detailData?.transaction_id || "-" }}</el-descriptions-item>
        <el-descriptions-item label="支付方式">{{ detailData?.pay_method || "-" }}</el-descriptions-item>
        <el-descriptions-item label="收款时间">{{ formatDateTime(detailData?.paid_at) }}</el-descriptions-item>
        <el-descriptions-item label="退款金额">{{ detailData?.refund_amount ? `¥${formatMoney(detailData.refund_amount)}` : "-" }}</el-descriptions-item>
        <el-descriptions-item label="操作人">{{ detailData?.operator_name || "-" }}</el-descriptions-item>
        <el-descriptions-item label="备注" :span="2">{{ detailData?.remark || "-" }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 标记已收款弹窗 -->
    <el-dialog v-model="markVisible" title="标记已收款" width="440px" destroy-on-close>
      <el-form :model="markForm" label-width="90px">
        <el-form-item label="收款编号">
          <span>{{ markForm.payment_no }}</span>
        </el-form-item>
        <el-form-item label="实收金额">
          <el-input-number
            v-model="markForm.actual_amount"
            :min="0"
            :precision="2"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="markForm.remark" type="textarea" :rows="3" placeholder="请输入备注信息" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="markVisible = false">取消</el-button>
        <el-button type="primary" :loading="markLoading" @click="confirmMarkReceived">确认</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive } from "vue"
import { Download } from "@element-plus/icons-vue"
import { ElMessage, type TableColumnCtx } from "element-plus"
import { getPaymentList, getPaymentDetail, markPaymentReceived, exportReconciliation } from "@/api/finance"
import { useTable } from "@/composables/useTable"
import { formatMoney, formatDateTime } from "@/utils/format"
import type { ReceiptRecord, ReceiptDetail } from "@/types/admin"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"
import StatusTag from "@/components/StatusTag.vue"

/** 日期范围 */
const dateRange = ref<[string, string] | null>(null)
/** 导出loading */
const exporting = ref<boolean>(false)

/** 收款状态颜色映射 */
const receiptStatusTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "0": "warning",
  "1": "success",
  "2": "info"
}

/**
 * 支付方式标签类型
 */
function payChannelTag(channel: number): "success" | "warning" | "primary" | "info" {
  if (channel === 1) return "success"
  if (channel === 2) return "primary"
  if (channel === 3) return "warning"
  return "info"
}

/**
 * 支付方式文本
 */
function channelLabel(channel: number): string {
  const map: Record<number, string> = { 1: "微信", 2: "支付宝", 3: "银行转账" }
  return map[channel] || "未知"
}

/**
 * 收款状态文本
 */
function statusLabel(status: number): string {
  const map: Record<number, string> = { 0: "待收", 1: "已收", 2: "部分收" }
  return map[status] || "未知"
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
} = useTable({
  fetchApi: getPaymentList,
  defaultParams: {
    keyword: undefined,
    pay_channel: undefined,
    pay_status: undefined,
    start_date: undefined,
    end_date: undefined
  }
})

/** 搜索时同步日期参数 */
watch(dateRange, () => {
  if (dateRange.value) {
    (queryParams as Record<string, unknown>).start_date = dateRange.value[0]
    (queryParams as Record<string, unknown>).end_date = dateRange.value[1]
  } else {
    (queryParams as Record<string, unknown>).start_date = undefined
    (queryParams as Record<string, unknown>).end_date = undefined
  }
})

/** 汇总栏计算 */
function getSummary({ columns, data }: { columns: TableColumnCtx<ReceiptRecord>[]; data: ReceiptRecord[] }): string[] {
  const sums: string[] = []
  columns.forEach((col, idx) => {
    if (idx === 0) {
      sums[idx] = "合计"
      return
    }
    if (col.property === "receivable_amount" || col.property === "actual_amount") {
      const values = data.map((item) => {
        const key = col.property === "receivable_amount" ? (item.receivable_amount || item.pay_amount) : (item.actual_amount || item.pay_amount)
        return Number(key)
      })
      sums[idx] = `¥${formatMoney(values.reduce((prev, curr) => prev + curr, 0))}`
    } else {
      sums[idx] = ""
    }
  })
  return sums
}

/** 导出 */
async function handleExport(): Promise<void> {
  exporting.value = true
  try {
    const params: Record<string, unknown> = {}
    if (dateRange.value) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }
    if (queryParams.pay_channel) params.pay_channel = queryParams.pay_channel
    const result = await exportReconciliation(params)
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

/** 详情弹窗 */
const detailVisible = ref<boolean>(false)
const detailLoading = ref<boolean>(false)
const detailData = ref<ReceiptDetail | null>(null)

/**
 * 查看收款详情
 */
async function handleViewDetail(row: ReceiptRecord): Promise<void> {
  detailVisible.value = true
  detailLoading.value = true
  try {
    detailData.value = await getPaymentDetail(row.id)
  } catch {
    ElMessage.error("获取详情失败")
  } finally {
    detailLoading.value = false
  }
}

/** 标记已收款弹窗 */
const markVisible = ref<boolean>(false)
const markLoading = ref<boolean>(false)
const markForm = reactive<{ payment_id: number; payment_no: string; actual_amount: number; remark: string }>({
  payment_id: 0,
  payment_no: "",
  actual_amount: 0,
  remark: ""
})

/**
 * 打开标记已收款弹窗
 */
function handleMarkReceived(row: ReceiptRecord): void {
  markForm.payment_id = row.id
  markForm.payment_no = row.payment_no
  markForm.actual_amount = parseFloat(row.pay_amount) || 0
  markForm.remark = ""
  markVisible.value = true
}

/**
 * 确认标记已收款
 */
async function confirmMarkReceived(): Promise<void> {
  if (markForm.actual_amount <= 0) {
    ElMessage.warning("请输入实收金额")
    return
  }
  markLoading.value = true
  try {
    await markPaymentReceived({
      payment_id: markForm.payment_id,
      actual_amount: markForm.actual_amount,
      remark: markForm.remark
    })
    ElMessage.success("标记成功")
    markVisible.value = false
    handleSearch()
  } catch {
    ElMessage.error("标记失败")
  } finally {
    markLoading.value = false
  }
}
</script>

<style scoped>
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

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.amount-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.amount-text.paid {
  color: var(--color-success);
}
</style>
