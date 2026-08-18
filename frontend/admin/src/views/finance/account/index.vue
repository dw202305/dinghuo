<template>
  <div class="customer-account-page">
    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="客户">
        <el-input
          v-model="queryParams.keyword"
          placeholder="客户名称 / 编号"
          clearable
          style="width: 200px"
        />
      </el-form-item>
      <el-form-item label="余额范围">
        <el-input-number
          v-model="minBalanceYuan"
          :min="0"
          :precision="2"
          :controls="false"
          placeholder="最低"
          style="width: 120px"
        />
        <span class="range-separator">—</span>
        <el-input-number
          v-model="maxBalanceYuan"
          :min="0"
          :precision="2"
          :controls="false"
          placeholder="最高"
          style="width: 120px"
        />
      </el-form-item>
    </SearchForm>

    <!-- 表格 -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <div class="table-toolbar__left">
          <span class="summary-badge">
            总客户数 <strong>{{ summary.totalCustomerCount }}</strong>
          </span>
          <span class="summary-badge">
            总可用余额 <strong class="text-success">¥{{ formatMoney(summary.totalAvailableYuan) }}</strong>
          </span>
          <span class="summary-badge">
            总冻结余额 <strong class="text-warning">¥{{ formatMoney(summary.totalFrozenYuan) }}</strong>
          </span>
        </div>
        <div class="table-toolbar__right">
          <span class="total-text">共 {{ total }} 条记录</span>
        </div>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="customer_no" label="客户编号" width="140" fixed show-overflow-tooltip />
        <el-table-column prop="customer_name" label="客户名称" width="140" show-overflow-tooltip />
        <el-table-column label="可用余额" width="130" align="right">
          <template #default="{ row }">
            <span class="amount-text text-success">
              ¥{{ formatCentToYuan(row.available_balance_cent) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="冻结余额" width="130" align="right">
          <template #default="{ row }">
            <span class="amount-text">
              ¥{{ formatCentToYuan(row.frozen_balance_cent) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="累计充值" width="130" align="right">
          <template #default="{ row }">
            <span class="amount-text">
              ¥{{ formatCentToYuan(row.total_recharge_cent) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="累计消费" width="130" align="right">
          <template #default="{ row }">
            <span class="amount-text">
              ¥{{ formatCentToYuan(row.total_consumption_cent) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="最后操作时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.last_operation_at) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_frozen ? 'danger' : 'success'" size="small" effect="light" round>
              {{ row.is_frozen ? '已冻结' : '正常' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleAdjustBalance(row as CustomerAccount)">
              调整余额
            </el-button>
            <el-button type="info" link size="small" @click="handleViewLog(row as CustomerAccount)">
              查看流水
            </el-button>
            <el-button
              :type="row.is_frozen ? 'success' : 'warning'"
              link
              size="small"
              @click="handleToggleFreeze(row as CustomerAccount)"
            >
              {{ row.is_frozen ? '解冻' : '冻结' }}
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

    <!-- 调整余额弹窗 -->
    <el-dialog v-model="adjustVisible" title="调整客户余额" width="480px" destroy-on-close>
      <div class="adjust-customer-info">
        <span>{{ adjustForm.customerName }}（{{ adjustForm.customerNo }}）</span>
        <span class="adjust-current-balance">
          当前余额：<strong>¥{{ formatCentToYuan(adjustForm.currentBalanceCent) }}</strong>
        </span>
      </div>
      <el-form ref="adjustFormRef" :model="adjustForm" :rules="adjustRules" label-width="100px" style="margin-top: 16px">
        <el-form-item label="调整金额" prop="amountYuan">
          <el-input-number
            v-model="adjustForm.amountYuan"
            :precision="2"
            :step="10"
            style="width: 100%"
            placeholder="正数增加余额，负数减少余额"
          />
          <div class="form-tip">正数增加余额，负数减少余额</div>
        </el-form-item>
        <el-form-item label="调整原因" prop="reason">
          <el-input
            v-model="adjustForm.reason"
            type="textarea"
            :rows="3"
            maxlength="200"
            show-word-limit
            placeholder="请详细说明调整原因（必填）"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adjustVisible = false">取消</el-button>
        <el-button type="primary" :loading="adjustLoading" @click="confirmAdjust">提交审批</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from "vue"
import { useRouter } from "vue-router"
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from "element-plus"
import { getCustomerAccounts, adjustBalance, toggleAccountFreeze } from "@/api/finance"
import { useTable } from "@/composables/useTable"
import { formatMoney, formatDateTime } from "@/utils/format"
import type { CustomerAccount, AccountListParams } from "@/types/finance"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"

const router = useRouter()

/** 分转元 */
function formatCentToYuan(cent: number): string {
  return formatMoney(cent / 100)
}

/** 余额范围（以元为单位，界面上输入） */
const minBalanceYuan = ref<number | undefined>(undefined)
const maxBalanceYuan = ref<number | undefined>(undefined)

/** 汇总数据 */
const summary = reactive({
  totalCustomerCount: 0,
  totalAvailableYuan: 0,
  totalFrozenYuan: 0
})

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
  fetchApi: (params: AccountListParams) =>
    getCustomerAccounts(params).then((res) => {
      /** 同步汇总数据 */
      summary.totalCustomerCount = res.total_customer_count
      summary.totalAvailableYuan = res.total_available_cent / 100
      summary.totalFrozenYuan = res.total_frozen_cent / 100
      return { list: res.list, total: res.total }
    }),
  defaultParams: {
    keyword: undefined,
    min_balance: undefined,
    max_balance: undefined
  }
})

/** 搜索时同步余额范围参数（元 → 分） */
watch([minBalanceYuan, maxBalanceYuan], () => {
  const q = queryParams as Record<string, unknown>
  q.min_balance = minBalanceYuan.value != null ? Math.round(minBalanceYuan.value * 100) : undefined
  q.max_balance = maxBalanceYuan.value != null ? Math.round(maxBalanceYuan.value * 100) : undefined
})

/* ===================== 调整余额弹窗 ===================== */

const adjustVisible = ref<boolean>(false)
const adjustLoading = ref<boolean>(false)
const adjustFormRef = ref<FormInstance>()

const adjustForm = reactive({
  customerId: 0,
  customerName: "",
  customerNo: "",
  currentBalanceCent: 0,
  amountYuan: undefined as number | undefined,
  reason: ""
})

const adjustRules: FormRules = {
  amountYuan: [
    { required: true, message: "请输入调整金额", trigger: "blur" }
  ],
  reason: [
    { required: true, message: "请输入调整原因", trigger: "blur" },
    { min: 2, max: 200, message: "原因长度在 2 到 200 个字符", trigger: "blur" }
  ]
}

/** 打开调整余额弹窗 */
function handleAdjustBalance(row: CustomerAccount): void {
  adjustForm.customerId = row.customer_id
  adjustForm.customerName = row.customer_name
  adjustForm.customerNo = row.customer_no
  adjustForm.currentBalanceCent = row.available_balance_cent
  adjustForm.amountYuan = undefined
  adjustForm.reason = ""
  adjustVisible.value = true
}

/** 确认提交调整 */
async function confirmAdjust(): Promise<void> {
  if (!adjustFormRef.value) return
  const valid = await adjustFormRef.value.validate().catch(() => false)
  if (!valid) return

  adjustLoading.value = true
  try {
    const amountCent = Math.round((adjustForm.amountYuan ?? 0) * 100)
    if (amountCent === 0) {
      ElMessage.warning("调整金额不能为 0")
      adjustLoading.value = false
      return
    }
    await adjustBalance({
      customer_id: adjustForm.customerId,
      amount_cent: amountCent,
      reason: adjustForm.reason
    })
    ElMessage.success("余额调整已提交审批")
    adjustVisible.value = false
    loadData()
  } catch {
    /* 错误已被拦截器处理 */
  } finally {
    adjustLoading.value = false
  }
}

/* ===================== 查看流水 ===================== */

/** 跳转到余额流水详情页 */
function handleViewLog(row: CustomerAccount): void {
  router.push({ path: "/finance/balance-log", query: { customer_id: String(row.customer_id), customer_name: row.customer_name } })
}

/* ===================== 冻结/解冻 ===================== */

/** 冻结/解冻账户 */
async function handleToggleFreeze(row: CustomerAccount): Promise<void> {
  const action = row.is_frozen ? "解冻" : "冻结"
  try {
    await ElMessageBox.confirm(
      `确定要${action}客户「${row.customer_name}」的资金账户吗？`,
      `${action}确认`,
      { confirmButtonText: "确定", cancelButtonText: "取消", type: "warning" }
    )
    await toggleAccountFreeze(row.id, !row.is_frozen)
    ElMessage.success(`${action}成功`)
    loadData()
  } catch {
    /* 用户取消或请求失败（已由拦截器处理） */
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
  gap: 20px;
}

.table-toolbar__right {
  display: flex;
  gap: 8px;
}

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.summary-badge {
  font-size: 13px;
  color: var(--color-neutral-600);
}

.summary-badge strong {
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.text-success {
  color: var(--color-success);
}

.text-warning {
  color: var(--color-warning);
}

.range-separator {
  display: inline-block;
  margin: 0 6px;
  color: var(--color-neutral-400);
}

.amount-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.adjust-customer-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: var(--color-neutral-50);
  border-radius: var(--radius-md);
  font-size: 14px;
}

.adjust-current-balance {
  color: var(--color-neutral-500);
}

.adjust-current-balance strong {
  color: var(--color-primary-500);
}

.form-tip {
  font-size: 12px;
  color: var(--color-neutral-400);
  margin-top: 4px;
}
</style>
