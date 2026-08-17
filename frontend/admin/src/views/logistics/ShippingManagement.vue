<template>
  <div class="shipping-management-page">
    <!-- ═══════════ 搜索区 ═══════════ -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleCustomReset">
      <el-form-item label="订单号">
        <el-input
          v-model="queryParams.order_no"
          placeholder="请输入订单号"
          clearable
          style="width: 200px"
          @keyup.enter="handleSearch"
        />
      </el-form-item>
      <el-form-item label="发货状态">
        <el-select
          v-model="queryParams.status"
          placeholder="全部状态"
          clearable
          style="width: 180px"
        >
          <el-option
            v-for="(label, value) in ShippingFilterStatusMap"
            :key="value"
            :label="label"
            :value="value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="下单时间">
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

    <!-- ═══════════ 表格区域 ═══════════ -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <div class="table-toolbar__left">
          <el-tag type="info" effect="plain">
            待处理 {{ pendingCount }} 单
          </el-tag>
        </div>
        <div class="table-toolbar__right">
          <span class="total-text">共 {{ total }} 条记录</span>
        </div>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe>
        <!-- 订单号 -->
        <el-table-column prop="order_no" label="订单号" width="230" fixed>
          <template #default="{ row }">
            <el-button type="primary" link @click="goOrderDetail(row.id)">
              {{ row.order_no }}
            </el-button>
          </template>
        </el-table-column>

        <!-- 门店名称 -->
        <el-table-column prop="store_name" label="门店名称" min-width="150" show-overflow-tooltip />

        <!-- 窗帘数量 -->
        <el-table-column label="窗帘数量" width="110" align="center">
          <template #default="{ row }">
            <span>{{ row.total_items }} 副</span>
          </template>
        </el-table-column>

        <!-- 已发货数量 -->
        <el-table-column label="已发货" width="100" align="center">
          <template #default="{ row }">
            <span class="shipped-count">{{ row.shipped_items }} / {{ row.total_items }}</span>
          </template>
        </el-table-column>

        <!-- 下单时间 -->
        <el-table-column prop="order_time" label="下单时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.order_time) }}
          </template>
        </el-table-column>

        <!-- 期望交期 -->
        <el-table-column prop="expected_delivery_date" label="期望交期" width="130">
          <template #default="{ row }">
            <span :class="{ 'date-overdue': isOverdue(row.expected_delivery_date, row.shipping_status) }">
              {{ formatDate(row.expected_delivery_date) }}
            </span>
          </template>
        </el-table-column>

        <!-- 发货状态 -->
        <el-table-column prop="shipping_status_text" label="发货状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="getShippingStatusTagType(row.shipping_status)" effect="plain" size="small">
              {{ row.shipping_status_text }}
            </el-tag>
          </template>
        </el-table-column>

        <!-- 操作列 -->
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="{ row }">
            <el-button
              v-if="row.shipping_status === 9 || row.shipping_status === 10"
              type="primary"
              link
              size="small"
              @click="openShipDialog(row)"
            >
              <el-icon><Promotion /></el-icon>
              发货
            </el-button>
            <el-button
              v-if="row.shipping_status !== 9"
              type="info"
              link
              size="small"
              @click="openLogisticsDialog(row)"
            >
              <el-icon><Van /></el-icon>
              查看物流
            </el-button>
            <span v-if="row.shipping_status === 9" class="placeholder-text">暂无物流信息</span>
          </template>
        </el-table-column>

        <!-- 空状态 -->
        <template #empty>
          <div class="empty-state">
            <el-empty description="暂无待发货订单" :image-size="100" />
          </div>
        </template>
      </el-table>

      <!-- 分页 -->
      <TablePagination
        :page="queryParams.page"
        :page-size="queryParams.page_size"
        :total="total"
        @page-change="handlePageChange"
        @size-change="handleSizeChange"
      />
    </el-card>

    <!-- ═══════════ 发货操作弹窗 ═══════════ -->
    <el-dialog
      v-model="shipDialogVisible"
      title="订单发货"
      width="720px"
      :close-on-click-modal="false"
      destroy-on-close
    >
      <div v-loading="shipDialogLoading">
        <!-- 订单基本信息 -->
        <div class="ship-order-info">
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item label="订单号">
              <el-text type="primary">{{ currentOrder?.order_no }}</el-text>
            </el-descriptions-item>
            <el-descriptions-item label="门店名称">
              {{ currentOrder?.store_name }}
            </el-descriptions-item>
            <el-descriptions-item label="窗帘总数">
              {{ currentOrder?.total_items }} 副
            </el-descriptions-item>
            <el-descriptions-item label="已发货数">
              <el-text type="success">{{ currentOrder?.shipped_items }} 副</el-text>
            </el-descriptions-item>
          </el-descriptions>
        </div>

        <!-- 窗帘明细选择 -->
        <div class="ship-items-section">
          <div class="section-header">
            <span class="section-title">选择本次发货的窗帘明细</span>
            <el-checkbox
              v-model="selectAllItems"
              :indeterminate="isIndeterminate"
              :disabled="availableItems.length === 0"
              @change="handleSelectAll"
            >
              全选待发货项
            </el-checkbox>
          </div>

          <el-table
            ref="itemsTableRef"
            :data="orderItems"
            row-key="item_id"
            stripe
            size="small"
            max-height="300"
            class="items-table"
          >
            <el-table-column width="50" align="center">
              <template #default="{ row }">
                <el-checkbox
                  v-model="selectedItemIds"
                  :label="row.item_id"
                  :disabled="row.shipping_status === 1"
                  @change="handleItemCheck"
                />
              </template>
            </el-table-column>
            <el-table-column prop="item_no" label="明细编号" width="120" />
            <el-table-column prop="position" label="安装位置" width="120" show-overflow-tooltip />
            <el-table-column prop="size" label="尺寸" width="140">
              <template #default="{ row }">
                {{ row.size }}
              </template>
            </el-table-column>
            <el-table-column prop="fabric_name" label="面料" min-width="140" show-overflow-tooltip />
            <el-table-column label="状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.shipping_status === 1" type="success" size="small" effect="plain">
                  已发货
                </el-tag>
                <el-tag v-else type="warning" size="small" effect="plain">
                  待发货
                </el-tag>
              </template>
            </el-table-column>
          </el-table>

          <div v-if="availableItems.length === 0" class="no-available-items">
            <el-text type="info">所有明细均已发货，无需再发</el-text>
          </div>
        </div>

        <!-- 物流信息填写 -->
        <el-form
          ref="shipFormRef"
          :model="shipForm"
          :rules="shipFormRules"
          label-width="90px"
          class="ship-logistics-form"
        >
          <el-divider content-position="left">物流信息</el-divider>
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="承运商" prop="carrier">
                <el-select v-model="shipForm.carrier" placeholder="请选择承运商" style="width: 100%">
                  <el-option
                    v-for="item in CARRIER_OPTIONS"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="物流单号" prop="tracking_no">
                <el-input
                  v-model="shipForm.tracking_no"
                  placeholder="请输入物流单号"
                  clearable
                  @keyup.enter="handleShipSubmit"
                />
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>

        <!-- 已选汇总 -->
        <div class="ship-summary">
          <el-text type="info" size="small">
            已选择 <el-text type="primary" strong>{{ selectedItemIds.length }}</el-text> 项待发货，
            承运商：<el-text strong>{{ shipForm.carrier || '未选择' }}</el-text>，
            单号：<el-text strong>{{ shipForm.tracking_no || '未填写' }}</el-text>
          </el-text>
        </div>
      </div>

      <template #footer>
        <el-button @click="shipDialogVisible = false">取消</el-button>
        <el-button
          type="primary"
          :loading="shipSubmitting"
          :disabled="selectedItemIds.length === 0"
          @click="handleShipSubmit"
        >
          确认发货（{{ selectedItemIds.length }} 项）
        </el-button>
      </template>
    </el-dialog>

    <!-- ═══════════ 物流信息查看弹窗 ═══════════ -->
    <el-dialog
      v-model="logisticsDialogVisible"
      title="物流信息"
      width="680px"
      destroy-on-close
    >
      <div v-loading="logisticsDialogLoading">
        <!-- 订单信息摘要 -->
        <el-descriptions :column="2" border size="small" class="logistics-order-info">
          <el-descriptions-item label="订单号">
            <el-text type="primary">{{ currentLogisticsOrder?.order_no }}</el-text>
          </el-descriptions-item>
          <el-descriptions-item label="门店名称">
            {{ currentLogisticsOrder?.store_name }}
          </el-descriptions-item>
        </el-descriptions>

        <!-- 发货批次列表 -->
        <div v-if="shippingInfoList.length > 0" class="shipping-batches">
          <div
            v-for="(batch, batchIndex) in shippingInfoList"
            :key="batch.id"
            class="shipping-batch-card"
          >
            <div class="batch-header">
              <el-tag type="primary" effect="dark" size="small">
                第 {{ batchIndex + 1 }} 批
              </el-tag>
              <span class="batch-time">{{ formatDateTime(batch.shipped_at) }}</span>
            </div>
            <el-descriptions :column="2" border size="small">
              <el-descriptions-item label="承运商">
                <el-tag effect="plain" size="small">{{ batch.carrier }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="运单号">
                <el-text class="tracking-no-text" copyable>
                  {{ batch.tracking_no }}
                </el-text>
              </el-descriptions-item>
            </el-descriptions>
            <!-- 本批次窗帘明细 -->
            <div class="batch-items">
              <div class="batch-items-title">本批次发货明细</div>
              <el-table :data="batch.shipped_items" stripe size="small" max-height="200">
                <el-table-column prop="item_no" label="明细编号" width="120" />
                <el-table-column prop="position" label="安装位置" width="120" show-overflow-tooltip />
                <el-table-column prop="size" label="尺寸" min-width="160" />
              </el-table>
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <div v-else-if="!logisticsDialogLoading" class="empty-state">
          <el-empty description="暂无物流信息" :image-size="80" />
        </div>
      </div>

      <template #footer>
        <el-button @click="logisticsDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
/**
 * 发货管理页面
 * @description 管理待发货/部分发货/已发货订单，支持发货操作和物流信息查看
 */

import { ref, reactive, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Promotion, Van } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'

import {
  getPendingShipments,
  getOrderItems,
  shipOrder,
  getOrderShippingInfo
} from '@/api/logistics'
import { useTable } from '@/composables/useTable'
import { formatDateTime, formatDate } from '@/utils/format'
import {
  ShippingFilterStatus,
  ShippingFilterStatusMap,
  CARRIER_OPTIONS
} from '@/types/logistics'
import type {
  PendingShipmentOrder,
  ShipmentListParams,
  ShipmentOrderItem,
  ShipOrderParams,
  ShippingInfo
} from '@/types/logistics'

import SearchForm from '@/components/SearchForm.vue'
import TablePagination from '@/components/TablePagination.vue'

const router = useRouter()

// ═══════════════════════════════════════════════════════════════
// 列表区域
// ═══════════════════════════════════════════════════════════════

/** 日期范围（搜索用） */
const dateRange = ref<[string, string] | null>(null)

/**
 * 发货状态 → el-tag type 映射
 */
function getShippingStatusTagType(status: number): 'warning' | 'success' | 'info' | 'primary' {
  switch (status) {
    case 9:  return 'warning'  // 待发货
    case 10: return 'primary'  // 部分发货
    case 11: return 'success'  // 已发货
    default: return 'info'
  }
}

/**
 * 判断是否超期
 */
function isOverdue(dateStr: string | null | undefined, status: number): boolean {
  if (!dateStr || status === 11) return false
  const expected = new Date(dateStr)
  const now = new Date()
  return now > expected
}

/**
 * 待发货订单数量（status=9 的订单）
 */
const pendingCount = computed<number>(() => {
  return tableData.value.filter((row: PendingShipmentOrder) => row.shipping_status === 9).length
})

/** 表格逻辑（复用 useTable） */
const {
  loading,
  tableData,
  total,
  queryParams,
  loadData,
  handleSearch,
  handleReset: originalReset,
  handlePageChange,
  handleSizeChange
} = useTable<PendingShipmentOrder, ShipmentListParams & Record<string, unknown>>({
  fetchApi: getPendingShipments as (params: ShipmentListParams & Record<string, unknown>) => Promise<{ list: PendingShipmentOrder[]; total: number }>,
  defaultParams: {
    order_no: undefined,
    status: undefined,
    start_date: undefined,
    end_date: undefined
  },
  pageSize: 20
})

/** 监听日期范围，同步到查询参数 */
watch(dateRange, (val) => {
  if (val) {
    (queryParams as Record<string, unknown>).start_date = val[0]
    (queryParams as Record<string, unknown>).end_date = val[1]
  } else {
    (queryParams as Record<string, unknown>).start_date = undefined
    (queryParams as Record<string, unknown>).end_date = undefined
  }
})

/** 自定义重置（同步清空日期） */
function handleCustomReset(): void {
  dateRange.value = null
  ;(queryParams as Record<string, unknown>).start_date = undefined
  ;(queryParams as Record<string, unknown>).end_date = undefined
  originalReset()
}

/**
 * 跳转订单详情
 */
function goOrderDetail(orderId: number): void {
  router.push(`/order/detail/${orderId}`)
}

// ═══════════════════════════════════════════════════════════════
// 发货弹窗
// ═══════════════════════════════════════════════════════════════

/** 发货弹窗可见状态 */
const shipDialogVisible = ref<boolean>(false)

/** 发货弹窗加载状态 */
const shipDialogLoading = ref<boolean>(false)

/** 发货提交中状态 */
const shipSubmitting = ref<boolean>(false)

/** 当前选中操作的订单 */
const currentOrder = ref<PendingShipmentOrder | null>(null)

/** 订单窗帘明细列表 */
const orderItems = ref<ShipmentOrderItem[]>([])

/** 已勾选的明细 ID 列表 */
const selectedItemIds = ref<number[]>([])

/** 全选 checkbox 状态 */
const selectAllItems = ref<boolean>(false)

/** 全选是否为不确定状态 */
const isIndeterminate = computed<boolean>(() => {
  return selectedItemIds.value.length > 0 && selectedItemIds.value.length < availableItems.value.length
})

/** 可发货的明细列表（尚未发货的） */
const availableItems = computed<ShipmentOrderItem[]>(() => {
  return orderItems.value.filter((item) => item.shipping_status !== 1)
})

/** 发货表单 */
const shipForm = reactive<{
  carrier: string
  tracking_no: string
}>({
  carrier: '',
  tracking_no: ''
})

/** 发货表单 ref */
const shipFormRef = ref<FormInstance>()

/** 发货表单校验规则 */
const shipFormRules: FormRules = {
  carrier: [
    { required: true, message: '请选择承运商', trigger: 'change' }
  ],
  tracking_no: [
    { required: true, message: '请输入物流单号', trigger: 'blur' },
    { pattern: /^[A-Za-z0-9\-]+$/, message: '单号只能包含字母、数字和横杠', trigger: 'blur' }
  ]
}

/**
 * 打开发货弹窗
 * @description 加载订单窗帘明细，初始化表单状态
 */
async function openShipDialog(row: PendingShipmentOrder): Promise<void> {
  currentOrder.value = row
  selectedItemIds.value = []
  selectAllItems.value = false
  shipForm.carrier = ''
  shipForm.tracking_no = ''
  shipDialogVisible.value = true
  shipDialogLoading.value = true

  try {
    const items = await getOrderItems(row.id)
    orderItems.value = items
  } catch {
    ElMessage.error('获取窗帘明细失败')
    orderItems.value = []
  } finally {
    shipDialogLoading.value = false
  }
}

/**
 * 全选 / 全不选 待发货明细
 */
function handleSelectAll(val: boolean | string | number): void {
  if (val) {
    // 全选：将所有待发货的 item_id 加入选中列表
    const ids = availableItems.value.map((item) => item.item_id)
    selectedItemIds.value = ids
  } else {
    selectedItemIds.value = []
  }
}

/**
 * 单条勾选变化时，同步全选 checkbox 状态
 */
function handleItemCheck(): void {
  const allAvailableIds = availableItems.value.map((item) => item.item_id)
  const allChecked = allAvailableIds.length > 0 &&
    allAvailableIds.every((id) => selectedItemIds.value.includes(id))
  selectAllItems.value = allChecked
}

/**
 * 提交发货
 * @description 校验表单后，调用发货接口，成功后刷新列表并关闭弹窗
 */
async function handleShipSubmit(): Promise<void> {
  if (!shipFormRef.value) return
  await shipFormRef.value.validate()

  if (selectedItemIds.value.length === 0) {
    ElMessage.warning('请至少勾选一项待发货的窗帘明细')
    return
  }

  if (!currentOrder.value) return

  shipSubmitting.value = true
  try {
    const params: ShipOrderParams = {
      order_id: currentOrder.value.id,
      item_ids: selectedItemIds.value,
      carrier: shipForm.carrier,
      tracking_no: shipForm.tracking_no
    }
    await shipOrder(params)
    ElMessage.success('发货成功！')
    shipDialogVisible.value = false
    // 刷新列表
    loadData()
  } catch {
    // 错误已由 axios 拦截器统一提示
  } finally {
    shipSubmitting.value = false
  }
}

// ═══════════════════════════════════════════════════════════════
// 物流信息查看弹窗
// ═══════════════════════════════════════════════════════════════

/** 物流弹窗可见状态 */
const logisticsDialogVisible = ref<boolean>(false)

/** 物流弹窗加载状态 */
const logisticsDialogLoading = ref<boolean>(false)

/** 当前查看物流的订单 */
const currentLogisticsOrder = ref<PendingShipmentOrder | null>(null)

/** 物流批次信息列表 */
const shippingInfoList = ref<ShippingInfo[]>([])

/**
 * 打开物流信息弹窗
 * @description 加载订单的所有物流批次信息
 */
async function openLogisticsDialog(row: PendingShipmentOrder): Promise<void> {
  currentLogisticsOrder.value = row
  logisticsDialogVisible.value = true
  logisticsDialogLoading.value = true
  shippingInfoList.value = []

  try {
    const info = await getOrderShippingInfo(row.id)
    shippingInfoList.value = info
  } catch {
    ElMessage.error('获取物流信息失败')
  } finally {
    logisticsDialogLoading.value = false
  }
}
</script>

<style scoped>
.shipping-management-page {
  padding: 0;
}

/* ── 表格工具栏 ── */
.table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.table-toolbar__left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.table-toolbar__right {
  display: flex;
  align-items: center;
}

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

/* ── 已发货数量 ── */
.shipped-count {
  font-variant-numeric: tabular-nums;
  font-weight: 500;
}

/* ── 超期日期 ── */
.date-overdue {
  color: var(--color-error, #dc2626);
  font-weight: 600;
}

/* ── 空状态 ── */
.empty-state {
  padding: 24px 0;
}

/* ── 暂无物流占位 ── */
.placeholder-text {
  font-size: 12px;
  color: var(--color-neutral-400);
}

/* ── 发货弹窗：订单信息 ── */
.ship-order-info {
  margin-bottom: 20px;
}

/* ── 发货弹窗：明细选择 ── */
.ship-items-section {
  margin-bottom: 8px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-neutral-800);
}

.items-table {
  border-radius: 6px;
  overflow: hidden;
}

.no-available-items {
  padding: 16px 0;
  text-align: center;
  border: 1px dashed var(--color-neutral-300);
  border-radius: 6px;
  margin-top: 8px;
}

/* ── 发货弹窗：物流表单 ── */
.ship-logistics-form {
  margin-top: 4px;
}

/* ── 发货弹窗：汇总 ── */
.ship-summary {
  padding: 12px 16px;
  background: var(--color-neutral-50, #f9fafb);
  border-radius: 6px;
  margin-top: 12px;
}

/* ── 物流弹窗 ── */
.logistics-order-info {
  margin-bottom: 20px;
}

.shipping-batches {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.shipping-batch-card {
  border: 1px solid var(--color-neutral-200, #e5e7eb);
  border-radius: 8px;
  padding: 16px;
  background: var(--color-neutral-25, #fcfcfd);
}

.batch-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.batch-time {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.batch-items {
  margin-top: 12px;
}

.batch-items-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-neutral-600);
  margin-bottom: 8px;
}

.tracking-no-text {
  font-family: 'Courier New', Courier, monospace;
  font-size: 13px;
  letter-spacing: 0.5px;
}
</style>
