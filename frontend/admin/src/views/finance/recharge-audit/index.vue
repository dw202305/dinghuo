<template>
  <div class="recharge-audit-page">
    <!-- Tab 切换 -->
    <div class="tab-bar">
      <el-radio-group v-model="activeTab" @change="handleTabChange">
        <el-radio-button value="pending">待审核</el-radio-button>
        <el-radio-button value="approved">已通过</el-radio-button>
        <el-radio-button value="rejected">已拒绝</el-radio-button>
        <el-radio-button value="">全部</el-radio-button>
      </el-radio-group>
    </div>

    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="充值单号">
        <el-input
          v-model="queryParams.recharge_no"
          placeholder="充值单号"
          clearable
          style="width: 180px"
        />
      </el-form-item>
      <el-form-item label="客户名称">
        <el-input
          v-model="queryParams.customer_name"
          placeholder="客户名称"
          clearable
          style="width: 160px"
        />
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

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="recharge_no" label="充值单号" width="200" fixed show-overflow-tooltip />
        <el-table-column prop="customer_name" label="客户名称" width="140" show-overflow-tooltip />
        <el-table-column label="充值金额" width="130" align="right">
          <template #default="{ row }">
            <span class="amount-text text-primary">
              ¥{{ formatCentToYuan(row.amount_cent) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="payment_channel" label="支付方式" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="channelTagType(row.payment_channel)" size="small" effect="light">
              {{ row.payment_channel }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="充值时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" size="small" effect="light" round>
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="auditor_name" label="审核人" width="100" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.auditor_name || "-" }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right" align="center">
          <template #default="{ row }">
            <template v-if="row.status === 'pending'">
              <el-button type="success" link size="small" @click="handleApprove(row as RechargeAuditRecord)">
                通过
              </el-button>
              <el-button type="danger" link size="small" @click="handleReject(row as RechargeAuditRecord)">
                拒绝
              </el-button>
            </template>
            <el-button type="info" link size="small" @click="handleViewHistory(row as RechargeAuditRecord)">
              审核记录
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

    <!-- 审核记录弹窗 -->
    <el-dialog v-model="historyVisible" title="审核记录" width="600px" destroy-on-close>
      <div class="history-header">
        <span>充值单号：{{ currentRechargeNo }}</span>
      </div>
      <el-timeline v-if="historyList.length > 0" class="audit-timeline">
        <el-timeline-item
          v-for="(item, idx) in historyList"
          :key="idx"
          :timestamp="formatDateTime(item.created_at)"
          placement="top"
          :type="timelineType(item.action)"
        >
          <div class="timeline-card">
            <div class="timeline-card__header">
              <span class="timeline-card__operator">{{ item.operator_name }}</span>
              <el-tag :type="timelineType(item.action)" size="small" effect="light">
                {{ item.action }}
              </el-tag>
            </div>
            <div v-if="item.remark" class="timeline-card__remark">
              {{ item.remark }}
            </div>
          </div>
        </el-timeline-item>
      </el-timeline>
      <el-empty v-else description="暂无审核记录" :image-size="80" />
      <template #footer>
        <el-button @click="historyVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 拒绝原因弹窗 -->
    <el-dialog v-model="rejectVisible" title="拒绝充值" width="440px" destroy-on-close>
      <el-form ref="rejectFormRef" :model="rejectForm" :rules="rejectRules" label-width="90px">
        <el-form-item label="充值单号">
          <span>{{ rejectForm.rechargeNo }}</span>
        </el-form-item>
        <el-form-item label="拒绝原因" prop="reason">
          <el-input
            v-model="rejectForm.reason"
            type="textarea"
            :rows="3"
            maxlength="200"
            show-word-limit
            placeholder="请填写拒绝原因（必填）"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rejectVisible = false">取消</el-button>
        <el-button type="danger" :loading="rejectLoading" @click="confirmReject">确认拒绝</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from "vue"
import { Download } from "@element-plus/icons-vue"
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from "element-plus"
import {
  getRechargeAuditList,
  approveRecharge,
  rejectRecharge,
  getAuditHistory,
  exportReconciliation
} from "@/api/finance"
import { useTable } from "@/composables/useTable"
import { formatMoney, formatDateTime } from "@/utils/format"
import type { RechargeAuditRecord, AuditHistoryItem } from "@/types/finance"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"

/** 分转元 */
function formatCentToYuan(cent: number): string {
  return formatMoney(cent / 100)
}

/** 当前 Tab */
const activeTab = ref<string>("pending")

/** 日期范围 */
const dateRange = ref<[string, string] | null>(null)

/** 导出 loading */
const exporting = ref<boolean>(false)

/** 状态标签类型 */
function statusTagType(status: string): "success" | "warning" | "danger" | "info" {
  const map: Record<string, "success" | "warning" | "danger" | "info"> = {
    pending: "warning",
    approved: "success",
    rejected: "danger"
  }
  return map[status] || "info"
}

/** 状态文本 */
function statusLabel(status: string): string {
  const map: Record<string, string> = {
    pending: "待审核",
    approved: "已通过",
    rejected: "已拒绝"
  }
  return map[status] || status
}

/** 支付渠道标签类型 */
function channelTagType(channel: string): "success" | "primary" | "warning" | "info" {
  if (channel.includes("微信")) return "success"
  if (channel.includes("支付宝")) return "primary"
  if (channel.includes("余额")) return "warning"
  return "info"
}

/** 时间线类型 */
function timelineType(action: string): "success" | "warning" | "danger" | "info" | "primary" {
  if (action.includes("通过")) return "success"
  if (action.includes("拒绝")) return "danger"
  if (action.includes("提交") || action.includes("申请")) return "primary"
  return "info"
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
  fetchApi: getRechargeAuditList,
  defaultParams: {
    recharge_no: undefined,
    customer_name: undefined,
    status: "pending",
    start_date: undefined,
    end_date: undefined
  }
})

/** Tab 切换 */
function handleTabChange(val: string | number | boolean | undefined): void {
  ;(queryParams as Record<string, unknown>).status = val || undefined
  handleSearch()
}

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

/* ===================== 审核通过 ===================== */

async function handleApprove(row: RechargeAuditRecord): Promise<void> {
  try {
    await ElMessageBox.confirm(
      `确认充值单「${row.recharge_no}」到账？充值金额 ¥${formatCentToYuan(row.amount_cent)}`,
      "审核通过",
      { confirmButtonText: "确认通过", cancelButtonText: "取消", type: "success" }
    )
    await approveRecharge(row.id)
    ElMessage.success("已通过")
    loadData()
  } catch {
    /* 用户取消或请求失败 */
  }
}

/* ===================== 审核拒绝 ===================== */

const rejectVisible = ref<boolean>(false)
const rejectLoading = ref<boolean>(false)
const rejectFormRef = ref<FormInstance>()

const rejectForm = reactive({
  id: 0,
  rechargeNo: "",
  reason: ""
})

const rejectRules: FormRules = {
  reason: [
    { required: true, message: "请填写拒绝原因", trigger: "blur" },
    { min: 2, max: 200, message: "原因长度在 2 到 200 个字符", trigger: "blur" }
  ]
}

function handleReject(row: RechargeAuditRecord): void {
  rejectForm.id = row.id
  rejectForm.rechargeNo = row.recharge_no
  rejectForm.reason = ""
  rejectVisible.value = true
}

async function confirmReject(): Promise<void> {
  if (!rejectFormRef.value) return
  const valid = await rejectFormRef.value.validate().catch(() => false)
  if (!valid) return

  rejectLoading.value = true
  try {
    await rejectRecharge(rejectForm.id, rejectForm.reason)
    ElMessage.success("已拒绝")
    rejectVisible.value = false
    loadData()
  } catch {
    /* 错误已由拦截器处理 */
  } finally {
    rejectLoading.value = false
  }
}

/* ===================== 审核记录 ===================== */

const historyVisible = ref<boolean>(false)
const currentRechargeNo = ref<string>("")
const historyList = ref<AuditHistoryItem[]>([])

async function handleViewHistory(row: RechargeAuditRecord): Promise<void> {
  currentRechargeNo.value = row.recharge_no
  historyList.value = []
  historyVisible.value = true
  try {
    historyList.value = await getAuditHistory(row.recharge_no)
  } catch {
    /* 错误已由拦截器处理 */
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
.tab-bar {
  margin-bottom: 16px;
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

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.amount-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.text-primary {
  color: var(--color-primary-500);
}

.history-header {
  padding: 8px 14px;
  background: var(--color-neutral-50);
  border-radius: var(--radius-md);
  margin-bottom: 16px;
  font-size: 14px;
  color: var(--color-neutral-600);
}

.audit-timeline {
  padding-left: 8px;
}

.timeline-card {
  padding: 8px 0;
}

.timeline-card__header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.timeline-card__operator {
  font-weight: 500;
  color: var(--color-neutral-700);
}

.timeline-card__remark {
  margin-top: 6px;
  font-size: 13px;
  color: var(--color-neutral-500);
  line-height: 1.5;
}
</style>
