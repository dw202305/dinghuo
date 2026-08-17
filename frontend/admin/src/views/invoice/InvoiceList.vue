<template>
  <div class="invoice-list-page">
    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="关键词">
        <el-input
          v-model="queryParams.keyword"
          placeholder="申请编号/订单号/门店"
          clearable
          style="width: 200px"
        />
      </el-form-item>
      <el-form-item label="发票类型">
        <el-select
          v-model="queryParams.invoice_type"
          placeholder="全部"
          clearable
          style="width: 130px"
        >
          <el-option label="普票" :value="1" />
          <el-option label="专票" :value="2" />
        </el-select>
      </el-form-item>
      <el-form-item label="开票状态">
        <el-select
          v-model="queryParams.status"
          placeholder="全部"
          clearable
          style="width: 130px"
        >
          <el-option label="待审核" :value="1" />
          <el-option label="已审核待开票" :value="2" />
          <el-option label="已开票" :value="3" />
          <el-option label="已驳回" :value="4" />
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
        <div class="table-toolbar__left"></div>
        <div class="table-toolbar__right">
          <span class="total-text">共 {{ total }} 条记录</span>
        </div>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="request_no" label="申请编号" width="160" fixed show-overflow-tooltip />
        <el-table-column prop="store_name" label="门店名称" width="140" show-overflow-tooltip />
        <el-table-column label="发票类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag
              :type="row.invoice_type === 2 ? 'danger' : 'primary'"
              size="small"
              effect="light"
            >
              {{ row.invoice_type_text || (row.invoice_type === 2 ? "专票" : "普票") }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="发票抬头" min-width="180" show-overflow-tooltip />
        <el-table-column prop="invoice_amount" label="开票金额" width="120" align="right">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatMoney(row.invoice_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="开票状态" width="120" align="center">
          <template #default="{ row }">
            <StatusTag
              :status="row.status"
              :label="row.status_text || invoiceStatusLabel(row.status)"
              :type-map="invoiceStatusTypeMap"
            />
          </template>
        </el-table-column>
        <el-table-column prop="order_no" label="关联订单号" width="200" show-overflow-tooltip />
        <el-table-column prop="created_at" label="申请时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleViewDetail(row)">
              详情
            </el-button>
            <el-button
              v-if="row.status === 1"
              type="success"
              link
              size="small"
              @click="handleApprove(row)"
            >
              审核通过
            </el-button>
            <el-button
              v-if="row.status === 2"
              type="warning"
              link
              size="small"
              @click="handleIssue(row)"
            >
              开具发票
            </el-button>
            <el-button
              v-if="row.status === 1"
              type="danger"
              link
              size="small"
              @click="handleReject(row)"
            >
              驳回
            </el-button>
            <el-button
              v-if="row.status === 3"
              type="danger"
              link
              size="small"
              @click="handleInvalidate(row)"
            >
              作废
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

    <!-- 发票详情弹窗 -->
    <el-dialog v-model="detailVisible" title="发票详情" width="580px" destroy-on-close>
      <el-descriptions :column="2" border v-loading="detailLoading">
        <el-descriptions-item label="申请编号" :span="2">{{ detailData?.request_no }}</el-descriptions-item>
        <el-descriptions-item label="门店名称">{{ detailData?.store_name }}</el-descriptions-item>
        <el-descriptions-item label="关联订单号">{{ detailData?.order_no }}</el-descriptions-item>
        <el-descriptions-item label="发票类型">
          <el-tag
            :type="detailData?.invoice_type === 2 ? 'danger' : 'primary'"
            size="small"
            effect="light"
          >
            {{ detailData?.invoice_type_text || (detailData?.invoice_type === 2 ? "专票" : "普票") }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="开票状态">
          <StatusTag
            :status="detailData?.status ?? 0"
            :label="detailData?.status_text || invoiceStatusLabel(detailData?.status ?? 0)"
            :type-map="invoiceStatusTypeMap"
          />
        </el-descriptions-item>
        <el-descriptions-item label="发票抬头" :span="2">{{ detailData?.title }}</el-descriptions-item>
        <el-descriptions-item label="税号" :span="2">{{ detailData?.tax_no || "-" }}</el-descriptions-item>
        <el-descriptions-item label="开票金额">
          <span class="amount-text">¥{{ formatMoney(detailData?.invoice_amount) }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="发票内容">{{ detailData?.invoice_content || "-" }}</el-descriptions-item>
        <el-descriptions-item label="关联订单数">{{ detailData?.related_order_count || 1 }}</el-descriptions-item>
        <el-descriptions-item label="申请时间">{{ formatDateTime(detailData?.created_at) }}</el-descriptions-item>
        <el-descriptions-item label="发票号码" v-if="detailData?.invoice_no">{{ detailData.invoice_no }}</el-descriptions-item>
        <el-descriptions-item label="发票代码" v-if="detailData?.invoice_code">{{ detailData.invoice_code }}</el-descriptions-item>
        <el-descriptions-item label="开票时间" v-if="detailData?.invoiced_at">{{ formatDateTime(detailData.invoiced_at) }}</el-descriptions-item>
        <el-descriptions-item label="驳回原因" v-if="detailData?.reject_reason" :span="2">
          <span class="reject-reason">{{ detailData.reject_reason }}</span>
        </el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 开具发票弹窗 -->
    <el-dialog v-model="issueVisible" title="开具发票" width="460px" destroy-on-close>
      <el-form :model="issueForm" label-width="90px">
        <el-form-item label="申请编号">
          <span>{{ issueForm.request_no }}</span>
        </el-form-item>
        <el-form-item label="发票号码">
          <el-input v-model="issueForm.invoice_no" placeholder="请输入发票号码" />
        </el-form-item>
        <el-form-item label="发票代码">
          <el-input v-model="issueForm.invoice_code" placeholder="请输入发票代码" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="issueVisible = false">取消</el-button>
        <el-button type="primary" :loading="issueLoading" @click="confirmIssue">确认开具</el-button>
      </template>
    </el-dialog>

    <!-- 驳回弹窗 -->
    <el-dialog v-model="rejectVisible" title="驳回申请" width="440px" destroy-on-close>
      <el-form :model="rejectForm" label-width="90px">
        <el-form-item label="申请编号">
          <span>{{ rejectForm.request_no }}</span>
        </el-form-item>
        <el-form-item label="驳回原因">
          <el-input v-model="rejectForm.reject_reason" type="textarea" :rows="3" placeholder="请输入驳回原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rejectVisible = false">取消</el-button>
        <el-button type="danger" :loading="rejectLoading" @click="confirmReject">确认驳回</el-button>
      </template>
    </el-dialog>

    <!-- 作废弹窗 -->
    <el-dialog v-model="invalidateVisible" title="作废发票" width="440px" destroy-on-close>
      <el-form :model="invalidateForm" label-width="90px">
        <el-form-item label="申请编号">
          <span>{{ invalidateForm.request_no }}</span>
        </el-form-item>
        <el-form-item label="作废原因">
          <el-input v-model="invalidateForm.reason" type="textarea" :rows="3" placeholder="请输入作废原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="invalidateVisible = false">取消</el-button>
        <el-button type="danger" :loading="invalidateLoading" @click="confirmInvalidate">确认作废</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive } from "vue"
import { ElMessage } from "element-plus"
import { getInvoiceList, getInvoiceDetail, reviewInvoice, issueInvoice, invalidateInvoice } from "@/api/finance"
import { useTable } from "@/composables/useTable"
import { formatMoney, formatDateTime } from "@/utils/format"
import type { InvoiceItem } from "@/api/finance"
import type { InvoiceDetail } from "@/types/admin"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"
import StatusTag from "@/components/StatusTag.vue"

/** 日期范围 */
const dateRange = ref<[string, string] | null>(null)

/** 发票状态颜色映射 */
const invoiceStatusTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "1": "warning",
  "2": "primary",
  "3": "success",
  "4": "danger"
}

/**
 * 发票状态文本
 */
function invoiceStatusLabel(status: number): string {
  const map: Record<number, string> = { 1: "待审核", 2: "已审核待开票", 3: "已开票", 4: "已驳回" }
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
  fetchApi: (params: Record<string, unknown>) => getInvoiceList(params) as unknown as Promise<{ list: InvoiceItem[]; total: number }>,
  defaultParams: {
    keyword: undefined,
    invoice_type: undefined,
    status: undefined,
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

/** 详情弹窗 */
const detailVisible = ref<boolean>(false)
const detailLoading = ref<boolean>(false)
const detailData = ref<InvoiceDetail | null>(null)

/**
 * 查看发票详情
 */
async function handleViewDetail(row: InvoiceItem): Promise<void> {
  detailVisible.value = true
  detailLoading.value = true
  try {
    detailData.value = await getInvoiceDetail(row.id)
  } catch {
    ElMessage.error("获取详情失败")
  } finally {
    detailLoading.value = false
  }
}

/** 审核通过 */
async function handleApprove(row: InvoiceItem): Promise<void> {
  try {
    await reviewInvoice({ request_id: row.id, action: 1 })
    ElMessage.success("审核通过")
    handleSearch()
  } catch {
    ElMessage.error("审核失败")
  }
}

/** 驳回弹窗 */
const rejectVisible = ref<boolean>(false)
const rejectLoading = ref<boolean>(false)
const rejectForm = reactive<{ request_id: number; request_no: string; reject_reason: string }>({
  request_id: 0,
  request_no: "",
  reject_reason: ""
})

/**
 * 打开驳回弹窗
 */
function handleReject(row: InvoiceItem): void {
  rejectForm.request_id = row.id
  rejectForm.request_no = row.request_no
  rejectForm.reject_reason = ""
  rejectVisible.value = true
}

/**
 * 确认驳回
 */
async function confirmReject(): Promise<void> {
  if (!rejectForm.reject_reason.trim()) {
    ElMessage.warning("请输入驳回原因")
    return
  }
  rejectLoading.value = true
  try {
    await reviewInvoice({ request_id: rejectForm.request_id, action: 4, reject_reason: rejectForm.reject_reason })
    ElMessage.success("已驳回")
    rejectVisible.value = false
    handleSearch()
  } catch {
    ElMessage.error("操作失败")
  } finally {
    rejectLoading.value = false
  }
}

/** 开具发票弹窗 */
const issueVisible = ref<boolean>(false)
const issueLoading = ref<boolean>(false)
const issueForm = reactive<{ request_id: number; request_no: string; invoice_no: string; invoice_code: string }>({
  request_id: 0,
  request_no: "",
  invoice_no: "",
  invoice_code: ""
})

/**
 * 打开开具发票弹窗
 */
function handleIssue(row: InvoiceItem): void {
  issueForm.request_id = row.id
  issueForm.request_no = row.request_no
  issueForm.invoice_no = ""
  issueForm.invoice_code = ""
  issueVisible.value = true
}

/**
 * 确认开具发票
 */
async function confirmIssue(): Promise<void> {
  if (!issueForm.invoice_no.trim()) {
    ElMessage.warning("请输入发票号码")
    return
  }
  issueLoading.value = true
  try {
    await issueInvoice(issueForm.request_id, issueForm.invoice_no, issueForm.invoice_code)
    ElMessage.success("开具成功")
    issueVisible.value = false
    handleSearch()
  } catch {
    ElMessage.error("开具失败")
  } finally {
    issueLoading.value = false
  }
}

/** 作废弹窗 */
const invalidateVisible = ref<boolean>(false)
const invalidateLoading = ref<boolean>(false)
const invalidateForm = reactive<{ request_id: number; request_no: string; reason: string }>({
  request_id: 0,
  request_no: "",
  reason: ""
})

/**
 * 打开作废弹窗
 */
function handleInvalidate(row: InvoiceItem): void {
  invalidateForm.request_id = row.id
  invalidateForm.request_no = row.request_no
  invalidateForm.reason = ""
  invalidateVisible.value = true
}

/**
 * 确认作废
 */
async function confirmInvalidate(): Promise<void> {
  if (!invalidateForm.reason.trim()) {
    ElMessage.warning("请输入作废原因")
    return
  }
  invalidateLoading.value = true
  try {
    await invalidateInvoice(invalidateForm.request_id, invalidateForm.reason)
    ElMessage.success("已作废")
    invalidateVisible.value = false
    handleSearch()
  } catch {
    ElMessage.error("操作失败")
  } finally {
    invalidateLoading.value = false
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

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.amount-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.reject-reason {
  color: var(--color-error);
}
</style>
