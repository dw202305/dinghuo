<template>
  <div class="balance-log-page">
    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="客户">
        <el-select
          v-model="queryParams.customer_id"
          placeholder="搜索客户"
          filterable
          clearable
          :loading="customerSearchLoading"
          :remote-method="searchCustomers"
          style="width: 200px"
        >
          <el-option
            v-for="item in customerOptions"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="流水类型">
        <el-select v-model="queryParams.type" placeholder="全部" clearable style="width: 150px">
          <el-option label="充值" value="recharge" />
          <el-option label="消费" value="payment" />
          <el-option label="退款" value="refund" />
          <el-option label="冲正" value="reversal" />
          <el-option label="余额调整" value="adjustment" />
        </el-select>
      </el-form-item>
      <el-form-item label="时间范围">
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
            导出
          </el-button>
        </div>
        <div class="table-toolbar__right">
          <span class="total-text">共 {{ total }} 条记录</span>
        </div>
      </div>

      <el-table
        :data="tableData"
        v-loading="loading"
        stripe
        :row-class-name="tableRowClassName"
      >
        <el-table-column prop="log_no" label="流水号" width="200" fixed show-overflow-tooltip />
        <el-table-column prop="customer_name" label="客户名称" width="130" show-overflow-tooltip />
        <el-table-column label="类型" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="typeTagType(row.type)" size="small" effect="light">
              {{ typeLabel(row.type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="140" align="right">
          <template #default="{ row }">
            <span :class="row.amount_cent >= 0 ? 'amount-positive' : 'amount-negative'">
              {{ row.amount_cent >= 0 ? '+' : '' }}¥{{ formatCentToYuan(row.amount_cent) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="余额变化" width="200" align="right">
          <template #default="{ row }">
            <span class="balance-change">
              ¥{{ formatCentToYuan(row.balance_before_cent) }}
              <el-icon class="arrow-icon"><Right /></el-icon>
              ¥{{ formatCentToYuan(row.balance_after_cent) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="related_order_no" label="关联订单号" width="200" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.related_order_no || "-" }}
          </template>
        </el-table-column>
        <el-table-column prop="operator_name" label="操作人" width="100" show-overflow-tooltip />
        <el-table-column label="时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <template v-if="row.type === 'adjustment' && row.approval_status">
              <el-tag :type="approvalTagType(row.approval_status)" size="small" effect="light" style="margin-right: 4px">
                {{ approvalLabel(row.approval_status) }}
              </el-tag>
            </template>
            {{ row.remark || "-" }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right" align="center">
          <template #default="{ row }">
            <template v-if="row.type === 'adjustment'">
              <el-button type="primary" link size="small" @click="handleViewApproval(row as BalanceLog)">
                审批详情
              </el-button>
            </template>
            <template v-else-if="row.type !== 'reversal'">
              <el-button type="warning" link size="small" @click="handleReversal(row as BalanceLog)">
                冲正
              </el-button>
            </template>
            <template v-else>
              <span class="text-muted">-</span>
            </template>
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

    <!-- 调整审批详情弹窗 -->
    <el-dialog v-model="approvalVisible" title="调整审批详情" width="520px" destroy-on-close>
      <el-descriptions :column="1" border>
        <el-descriptions-item label="流水号">{{ approvalDetail.logNo }}</el-descriptions-item>
        <el-descriptions-item label="申请人">{{ approvalDetail.operatorName }}</el-descriptions-item>
        <el-descriptions-item label="申请金额">
          <span :class="approvalDetail.amountCent >= 0 ? 'amount-positive' : 'amount-negative'">
            {{ approvalDetail.amountCent >= 0 ? '+' : '' }}¥{{ formatCentToYuan(approvalDetail.amountCent) }}
          </span>
        </el-descriptions-item>
        <el-descriptions-item label="调整原因">{{ approvalDetail.remark || "-" }}</el-descriptions-item>
        <el-descriptions-item label="审批状态">
          <el-tag :type="approvalTagType(approvalDetail.approvalStatus)" size="small" effect="light" round>
            {{ approvalLabel(approvalDetail.approvalStatus) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="审批人">{{ approvalDetail.auditorName || "-" }}</el-descriptions-item>
        <el-descriptions-item label="审批时间">{{ formatDateTime(approvalDetail.auditedAt) }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="approvalVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 冲正弹窗 -->
    <el-dialog v-model="reversalVisible" title="发起冲正" width="480px" destroy-on-close>
      <el-alert type="warning" :closable="false" show-icon style="margin-bottom: 16px">
        <template #title>
          冲正将创建一笔反向流水来抵消原交易，此操作不可撤销。
        </template>
      </el-alert>
      <div class="reversal-original">
        <div>原流水号：<strong>{{ reversalForm.originalLogNo }}</strong></div>
        <div>原金额：<strong :class="reversalForm.originalAmountCent >= 0 ? 'amount-positive' : 'amount-negative'">
          ¥{{ formatCentToYuan(Math.abs(reversalForm.originalAmountCent)) }}
        </strong></div>
      </div>
      <el-form ref="reversalFormRef" :model="reversalForm" :rules="reversalRules" label-width="90px" style="margin-top: 16px">
        <el-form-item label="冲正原因" prop="reason">
          <el-input
            v-model="reversalForm.reason"
            type="textarea"
            :rows="3"
            maxlength="200"
            show-word-limit
            placeholder="请说明冲正原因（必填）"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="reversalVisible = false">取消</el-button>
        <el-button type="warning" :loading="reversalLoading" @click="confirmReversal">确认冲正</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted } from "vue"
import { useRoute } from "vue-router"
import { Download, Right } from "@element-plus/icons-vue"
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from "element-plus"
import { getBalanceLogList, getCustomerAccounts, createReversal, exportReconciliation } from "@/api/finance"
import { useTable } from "@/composables/useTable"
import { formatMoney, formatDateTime } from "@/utils/format"
import type { BalanceLog } from "@/types/finance"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"

const route = useRoute()

/** 分转元 */
function formatCentToYuan(cent: number): string {
  return formatMoney(cent / 100)
}

/** 日期范围 */
const dateRange = ref<[string, string] | null>(null)

/** 导出 loading */
const exporting = ref<boolean>(false)

/** 类型标签 */
function typeTagType(type: string): "success" | "warning" | "danger" | "info" | "primary" {
  const map: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
    recharge: "success",
    payment: "primary",
    refund: "warning",
    reversal: "danger",
    adjustment: "info"
  }
  return map[type] || "info"
}

/** 类型文本 */
function typeLabel(type: string): string {
  const map: Record<string, string> = {
    recharge: "充值",
    payment: "消费",
    refund: "退款",
    reversal: "冲正",
    adjustment: "余额调整"
  }
  return map[type] || type
}

/** 审批状态标签类型 */
function approvalTagType(status: string): "success" | "warning" | "danger" | "info" {
  const map: Record<string, "success" | "warning" | "danger" | "info"> = {
    pending: "warning",
    approved: "success",
    rejected: "danger"
  }
  return map[status] || "info"
}

/** 审批状态文本 */
function approvalLabel(status: string): string {
  const map: Record<string, string> = {
    pending: "待审批",
    approved: "已通过",
    rejected: "已拒绝"
  }
  return map[status] || status
}

/** 行样式类名——余额调整行加金色背景 */
function tableRowClassName({ row }: { row: BalanceLog }): string {
  if (row.type === "adjustment") return "adjustment-row"
  return ""
}

const {
  loading,
  tableData,
  total,
  queryParams,
  loadData,
  handleSearch,
  handleReset,
  handlePageChange,
  handleSizeChange
} = useTable({
  fetchApi: getBalanceLogList,
  defaultParams: {
    customer_id: undefined,
    keyword: undefined,
    type: undefined,
    start_date: undefined,
    end_date: undefined
  }
})

/** 从 URL query 中读取客户信息（从账户页跳转过来） */
onMounted(() => {
  const qCustomerId = route.query.customer_id
  const qCustomerName = route.query.customer_name
  if (qCustomerId) {
    ;(queryParams as Record<string, unknown>).customer_id = Number(qCustomerId)
    if (qCustomerName) {
      customerOptions.value = [{ id: Number(qCustomerId), name: String(qCustomerName) }]
    }
    loadData()
  }
})

/** 同步日期范围参数 */
watch(dateRange, () => {
  const q = queryParams as Record<string, unknown>
  if (dateRange.value) {
    q.start_date = dateRange.value[0]
    q.end_date = dateRange.value[1]
  } else {
    q.start_date = undefined
    q.end_date = undefined
  }
})

/* ===================== 客户搜索下拉 ===================== */

interface CustomerOption {
  id: number
  name: string
}

const customerOptions = ref<CustomerOption[]>([])
const customerSearchLoading = ref<boolean>(false)

async function searchCustomers(query: string): Promise<void> {
  if (!query || query.length < 1) {
    customerOptions.value = []
    return
  }
  customerSearchLoading.value = true
  try {
    const res = await getCustomerAccounts({ keyword: query, page: 1, page_size: 20 })
    customerOptions.value = res.list.map((item) => ({
      id: item.customer_id,
      name: `${item.customer_name}（${item.customer_no}）`
    }))
  } catch {
    customerOptions.value = []
  } finally {
    customerSearchLoading.value = false
  }
}

/* ===================== 审批详情弹窗 ===================== */

const approvalVisible = ref<boolean>(false)
const approvalDetail = reactive({
  logNo: "",
  operatorName: "",
  amountCent: 0,
  remark: "",
  approvalStatus: "",
  auditorName: "",
  auditedAt: ""
})

function handleViewApproval(row: BalanceLog): void {
  approvalDetail.logNo = row.log_no
  approvalDetail.operatorName = row.operator_name
  approvalDetail.amountCent = row.amount_cent
  approvalDetail.remark = row.remark
  approvalDetail.approvalStatus = row.approval_status || ""
  approvalDetail.auditorName = ""
  approvalDetail.auditedAt = ""
  approvalVisible.value = true
}

/* ===================== 冲正操作 ===================== */

const reversalVisible = ref<boolean>(false)
const reversalLoading = ref<boolean>(false)
const reversalFormRef = ref<FormInstance>()

const reversalForm = reactive({
  originalLogId: 0,
  originalLogNo: "",
  originalAmountCent: 0,
  reason: ""
})

const reversalRules: FormRules = {
  reason: [
    { required: true, message: "请填写冲正原因", trigger: "blur" },
    { min: 2, max: 200, message: "原因长度在 2 到 200 个字符", trigger: "blur" }
  ]
}

async function handleReversal(row: BalanceLog): Promise<void> {
  try {
    await ElMessageBox.confirm(
      `确定要对流水「${row.log_no}」发起冲正吗？冲正将创建一笔反向流水。`,
      "冲正确认",
      { confirmButtonText: "继续", cancelButtonText: "取消", type: "warning" }
    )
    reversalForm.originalLogId = row.id
    reversalForm.originalLogNo = row.log_no
    reversalForm.originalAmountCent = row.amount_cent
    reversalForm.reason = ""
    reversalVisible.value = true
  } catch {
    /* 用户取消 */
  }
}

async function confirmReversal(): Promise<void> {
  if (!reversalFormRef.value) return
  const valid = await reversalFormRef.value.validate().catch(() => false)
  if (!valid) return

  reversalLoading.value = true
  try {
    await createReversal({
      original_log_id: reversalForm.originalLogId,
      reason: reversalForm.reason
    })
    ElMessage.success("冲正流水已创建")
    reversalVisible.value = false
    loadData()
  } catch {
    /* 错误已由拦截器处理 */
  } finally {
    reversalLoading.value = false
  }
}

/* ===================== 导出 ===================== */

async function handleExport(): Promise<void> {
  exporting.value = true
  try {
    const params: Record<string, unknown> = {}
    if (dateRange.value) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }
    if (queryParams.type) params.type = queryParams.type
    if (queryParams.customer_id) params.customer_id = queryParams.customer_id
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

.amount-positive {
  color: var(--color-success);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.amount-negative {
  color: var(--color-error);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.balance-change {
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  color: var(--color-neutral-500);
  white-space: nowrap;
}

.arrow-icon {
  margin: 0 4px;
  font-size: 12px;
  vertical-align: middle;
}

.text-muted {
  color: var(--color-neutral-400);
}

.reversal-original {
  padding: 10px 14px;
  background: var(--color-neutral-50);
  border-radius: var(--radius-md);
  font-size: 14px;
  line-height: 1.8;
}

.reversal-original strong {
  font-variant-numeric: tabular-nums;
}

/* 余额调整行金色背景标记 */
:deep(.adjustment-row) {
  background-color: var(--color-accent-50) !important;
}

:deep(.adjustment-row:hover > td) {
  background-color: var(--color-accent-100) !important;
}
</style>
