<template>
  <div class="order-list-page">
    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="customReset">
      <el-form-item label="订单号">
        <el-input
          v-model="queryParams.keyword"
          placeholder="订单号/项目名称"
          clearable
          style="width: 200px"
        />
      </el-form-item>
      <el-form-item label="订单状态">
        <el-select
          v-model="queryParams.order_status"
          placeholder="全部状态"
          clearable
          style="width: 180px"
        >
          <el-option
            v-for="(label, value) in OrderStatusMap"
            :key="value"
            :label="label"
            :value="Number(value)"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="门店名称">
        <el-input
          v-model="storeKeyword"
          placeholder="请输入门店名称"
          clearable
          style="width: 160px"
        />
      </el-form-item>
      <el-form-item label="客户名称">
        <el-input
          v-model="customerKeyword"
          placeholder="请输入客户名称"
          clearable
          style="width: 160px"
        />
      </el-form-item>
      <el-form-item label="下单日期">
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

    <!-- 操作栏 + 表格 -->
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

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="order_no" label="订单号" width="220" fixed>
          <template #default="{ row }">
            <el-button type="primary" link @click="goDetail(row.order_id)">
              {{ row.order_no }}
            </el-button>
          </template>
        </el-table-column>
        <el-table-column prop="store_name" label="门店名称" width="150" show-overflow-tooltip />
        <el-table-column label="项目/客户" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span>{{ row.project_name || "-" }}</span>
            <span v-if="row.project_name && row.end_customer" class="cell-sub"> / </span>
            <span class="cell-sub">{{ row.end_customer || "" }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="item_count" label="窗帘副数" width="100" align="center" />
        <el-table-column prop="total_amount" label="订单金额" width="120" align="right">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatMoney(row.total_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="paid_amount" label="已付金额" width="120" align="right">
          <template #default="{ row }">
            <span class="amount-text paid">¥{{ formatMoney(row.paid_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="order_status_text" label="订单状态" width="140">
          <template #default="{ row }">
            <StatusTag :status="row.order_status" :label="row.order_status_text" :type-map="statusTypeMap" />
          </template>
        </el-table-column>
        <el-table-column prop="payment_status_text" label="支付状态" width="110">
          <template #default="{ row }">
            <StatusTag
              :status="row.payment_status"
              :label="row.payment_status_text"
              :type-map="paymentTypeMap"
            />
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="下单时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="goDetail(row.order_id)">
              查看详情
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
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue"
import { useRouter } from "vue-router"
import { ElMessage } from "element-plus"
import { Download } from "@element-plus/icons-vue"
import { getOrderList, exportOrders } from "@/api/order"
import { useTable } from "@/composables/useTable"
import { formatMoney, formatDateTime } from "@/utils/format"
import { OrderStatusMap } from "@/types/common"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"
import StatusTag from "@/components/StatusTag.vue"

const router = useRouter()

/** 日期范围 */
const dateRange = ref<[string, string] | null>(null)
/** 门店关键词 */
const storeKeyword = ref<string>("")
/** 客户关键词 */
const customerKeyword = ref<string>("")
/** 导出loading */
const exporting = ref<boolean>(false)

/** 订单状态颜色映射 */
const statusTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "1": "info",    // 草稿
  "2": "danger",  // 待支付
  "3": "warning", // 支付处理中
  "4": "warning", // 已支付待审核
  "5": "warning", // 需门店确认
  "6": "warning", // 待补款
  "7": "primary", // 审核通过待排产
  "8": "primary", // 生产中
  "9": "primary", // 质检中
  "10": "warning", // 待发货
  "11": "warning", // 部分发货
  "12": "success", // 已发货
  "13": "success", // 已签收
  "14": "success", // 已完成
  "15": "danger",  // 售后处理中
  "16": "info",    // 已取消
  "17": "danger",  // 退款中
  "18": "info"     // 已退款
}

/** 支付状态颜色映射 */
const paymentTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "0": "danger",  // 未支付
  "1": "warning", // 部分支付
  "2": "success"  // 已支付
}

const {
  loading,
  tableData,
  total,
  queryParams,
  handleSearch,
  handleReset: originalReset,
  handlePageChange,
  handleSizeChange
} = useTable({
  fetchApi: getOrderList,
  defaultParams: {
    keyword: undefined,
    order_status: undefined,
    start_date: undefined,
    end_date: undefined
  }
})

/** 搜索时同步额外参数 */
watch([dateRange, storeKeyword, customerKeyword], () => {
  if (dateRange.value) {
    ;(queryParams as Record<string, unknown>).start_date = dateRange.value[0]
    ;(queryParams as Record<string, unknown>).end_date = dateRange.value[1]
  } else {
    ;(queryParams as Record<string, unknown>).start_date = undefined
    ;(queryParams as Record<string, unknown>).end_date = undefined
  }
  ;(queryParams as Record<string, unknown>).store_name = storeKeyword.value || undefined
  ;(queryParams as Record<string, unknown>).customer_name = customerKeyword.value || undefined
})

/** 重置时清空额外参数（模板 @reset 直接绑定此函数，避免重赋 const 绑定） */
function customReset(): void {
  dateRange.value = null
  storeKeyword.value = ""
  customerKeyword.value = ""
  ;(queryParams as Record<string, unknown>).start_date = undefined
  ;(queryParams as Record<string, unknown>).end_date = undefined
  ;(queryParams as Record<string, unknown>).store_name = undefined
  ;(queryParams as Record<string, unknown>).customer_name = undefined
  originalReset()
}

/** 跳转订单详情 */
function goDetail(orderId: number): void {
  router.push(`/order/detail/${orderId}`)
}

/** 导出订单 */
async function handleExport(): Promise<void> {
  exporting.value = true
  try {
    const result = await exportOrders(queryParams as Record<string, unknown>)
    if (result && typeof result === "object" && "file_url" in result) {
      window.open((result as { file_url: string }).file_url, "_blank")
      ElMessage.success("导出成功，正在下载")
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

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.cell-sub {
  color: var(--color-neutral-400);
}

.amount-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.amount-text.paid {
  color: var(--color-success);
}
</style>
