<template>
  <div class="audit-workbench-page">
    <div class="page-header">
      <h2 class="page-title">技术审核工作台</h2>
    </div>

    <div class="workbench-layout">
      <!-- 左侧待审核列表 -->
      <div class="workbench-left">
        <div class="left-search">
          <el-input
            v-model="searchKeyword"
            placeholder="订单号/门店名"
            clearable
            :prefix-icon="Search"
            @input="debouncedSearch"
          />
        </div>

        <!-- 筛选Tab -->
        <div class="left-tabs">
          <div
            v-for="tab in filterTabs"
            :key="tab.value ?? 'all'"
            :class="['tab-item', { active: currentTab === tab.value }]"
            @click="switchTab(tab.value)"
          >
            {{ tab.label }}
            <span v-if="tab.count > 0" class="tab-badge">{{ tab.count }}</span>
          </div>
        </div>

        <!-- 列表 -->
        <div class="left-list" v-loading="listLoading">
          <div
            v-for="item in auditList"
            :key="item.order_id"
            :class="['list-item', { active: selectedOrder?.order_id === item.order_id }]"
            @click="selectOrder(item)"
          >
            <div class="list-item__header">
              <span class="list-item__no">{{ item.order_no }}</span>
              <StatusTag
                :status="item.order_status"
                :label="item.order_status_text"
                :type-map="auditStatusTypeMap"
                size="small"
              />
            </div>
            <div class="list-item__meta">
              <span>{{ item.store_name }}</span>
              <span v-if="item.project_name" class="list-item__project">{{ item.project_name }}</span>
            </div>
            <div class="list-item__footer">
              <span class="list-item__count">{{ item.pending_count }} 项待审核</span>
              <span class="list-item__time">{{ formatDateTime(item.created_at) }}</span>
            </div>
          </div>
          <el-empty v-if="auditList.length === 0 && !listLoading" description="暂无待审核订单" :image-size="60" />
        </div>

        <!-- 分页 -->
        <div class="left-pagination" v-if="total > 20">
          <el-pagination
            v-model:current-page="listPage"
            :page-size="20"
            :total="total"
            layout="prev, pager, next"
            small
            @current-change="loadAuditList"
          />
        </div>
      </div>

      <!-- 右侧审核详情 -->
      <div class="workbench-right" v-loading="detailLoading">
        <template v-if="selectedOrder">
          <!-- 订单技术信息 -->
          <el-card class="detail-section">
            <template #header>
              <div class="section-header">
                <span class="card-title">{{ selectedOrder.order_no }} - 技术信息</span>
                <span class="section-sub">{{ selectedOrder.store_name }} | {{ selectedOrder.project_name || "无项目名" }}</span>
              </div>
            </template>

            <!-- 窗帘明细技术参数 -->
            <el-table :data="orderItems" stripe border>
              <el-table-column type="index" label="序号" width="60" align="center" />
              <el-table-column prop="install_position" label="安装位置" width="100" />
              <el-table-column label="尺寸(cm)" width="130">
                <template #default="{ row }">{{ row.width }} × {{ row.height }}</template>
              </el-table-column>
              <el-table-column prop="area" label="面积(㎡)" width="90" align="right">
                <template #default="{ row }">{{ formatArea(row.area) }}</template>
              </el-table-column>
              <el-table-column prop="track_color" label="轨道颜色" width="100" />
              <el-table-column label="面料" min-width="160">
                <template #default="{ row }">
                  <div>{{ row.fabric_name }}</div>
                  <div class="cell-sub">编号：{{ row.fabric_no }} | ¥{{ formatMoney(row.fabric_price) }}/㎡</div>
                </template>
              </el-table-column>
              <el-table-column prop="power_type_text" label="电源" width="80" align="center" />
              <el-table-column prop="remote_type_text" label="遥控" width="80" align="center" />
              <el-table-column prop="wall_control_type_text" label="墙控" width="80" align="center" />
              <el-table-column prop="technical_status_text" label="审核状态" width="110">
                <template #default="{ row }">
                  <StatusTag
                    :status="row.technical_status"
                    :label="row.technical_status_text"
                    :type-map="itemStatusMap"
                    size="small"
                  />
                </template>
              </el-table-column>
              <el-table-column label="非标" width="80" align="center">
                <template #default="{ row }">
                  <el-tag
                    v-if="isNonstandard(row as OrderItemDetail)"
                    type="warning"
                    size="small"
                    effect="dark"
                    class="nonstandard-tag"
                  >
                    非标
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
            </el-table>
          </el-card>

          <!-- 非标项提示 -->
          <el-card v-if="nonstandardItems.length > 0" class="detail-section nonstandard-section">
            <template #header>
              <span class="card-title warning-text">⚠ 非标项提示（{{ nonstandardItems.length }} 项）</span>
            </template>
            <div class="nonstandard-list">
              <div
                v-for="item in nonstandardItems"
                :key="item.item_id"
                class="nonstandard-item"
              >
                <div class="nonstandard-item__header">
                  <span>{{ item.install_position }} - {{ item.width }}×{{ item.height }}cm</span>
                  <span class="nonstandard-item__amount">非标加价：¥{{ formatMoney(item.nonstandard_amount) }}</span>
                </div>
                <div v-if="item.remark" class="nonstandard-item__remark">{{ item.remark }}</div>
              </div>
            </div>
          </el-card>

          <!-- 面料供应商映射 -->
          <el-card v-if="supplierMappings.length > 0" class="detail-section">
            <template #header>
              <span class="card-title">面料供应商映射</span>
            </template>
            <el-table :data="supplierMappings" stripe size="small">
              <el-table-column prop="fabric_name" label="面料名称" width="160" />
              <el-table-column prop="supplier_name" label="供应商" width="140" />
              <el-table-column prop="supplier_fabric_no" label="供应商面料编号" width="150" />
              <el-table-column prop="supplier_color_desc" label="颜色描述" width="120" />
              <el-table-column prop="delivery_days" label="交期(天)" width="80" align="center" />
              <el-table-column prop="purchase_price" label="采购价" width="100" align="right">
                <template #default="{ row }">
                  {{ row.purchase_price ? `¥${formatMoney(row.purchase_price)}` : "-" }}
                </template>
              </el-table-column>
              <el-table-column label="默认" width="60" align="center">
                <template #default="{ row }">
                  <el-tag v-if="row.is_default_supplier" type="primary" size="small">默认</el-tag>
                </template>
              </el-table-column>
            </el-table>
          </el-card>

          <!-- 审核操作区 -->
          <el-card class="detail-section audit-action-section">
            <template #header>
              <span class="card-title">审核操作</span>
            </template>
            <el-form ref="auditFormRef" :model="auditForm" :rules="auditRules" label-width="110px">
              <el-form-item label="审核结果" prop="action">
                <el-radio-group v-model="auditForm.action">
                  <el-radio-button value="pass">
                    <el-icon color="var(--color-success)"><CircleCheck /></el-icon>
                    通过
                  </el-radio-button>
                  <el-radio-button value="need_confirm">
                    <el-icon color="var(--color-warning)"><Warning /></el-icon>
                    需门店确认
                  </el-radio-button>
                  <el-radio-button value="need_supplement">
                    <el-icon color="var(--color-warning)"><Money /></el-icon>
                    需补款
                  </el-radio-button>
                  <el-radio-button value="cannot_produce">
                    <el-icon color="var(--color-danger)"><CircleClose /></el-icon>
                    无法生产
                  </el-radio-button>
                </el-radio-group>
              </el-form-item>
              <el-form-item label="审核意见" prop="remark">
                <el-input
                  v-model="auditForm.remark"
                  type="textarea"
                  :rows="4"
                  placeholder="请输入审核意见（必填）"
                  maxlength="500"
                  show-word-limit
                />
              </el-form-item>
              <el-form-item v-if="auditForm.action === 'need_supplement'" label="补充金额" prop="supplementAmount">
                <el-input-number
                  v-model="auditForm.supplementAmount"
                  :min="0"
                  :precision="2"
                  style="width: 200px"
                  placeholder="请输入补充金额"
                />
              </el-form-item>
              <el-form-item v-if="auditForm.action === 'need_confirm'" label="确认说明" prop="confirmMessage">
                <el-input
                  v-model="auditForm.confirmMessage"
                  type="textarea"
                  :rows="3"
                  placeholder="请说明需要门店确认的内容"
                />
              </el-form-item>
              <el-form-item label="附件上传">
                <el-upload
                  :auto-upload="false"
                  :limit="5"
                  accept="image/*,.pdf,.doc,.docx"
                  list-type="text"
                >
                  <el-button size="small">选择附件</el-button>
                  <template #tip>
                    <div class="el-upload__tip">支持图片/PDF/Word，最多5个文件</div>
                  </template>
                </el-upload>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" :loading="submitting" @click="handleSubmitAudit">
                  提交审核
                </el-button>
                <el-button @click="resetAuditForm">重置</el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <!-- 历史审核记录 -->
          <el-card class="detail-section">
            <template #header>
              <span class="card-title">历史审核记录</span>
            </template>
            <el-timeline v-if="auditHistory.length > 0">
              <el-timeline-item
                v-for="item in auditHistory"
                :key="item.log_id"
                :timestamp="formatDateTime(item.created_at)"
                placement="top"
                :type="getHistoryType(item.action)"
              >
                <div class="history-item">
                  <span class="history-action">{{ item.action_text }}</span>
                  <span class="history-operator">操作人：{{ item.operator_name }}</span>
                </div>
                <div v-if="item.remark" class="history-remark">{{ item.remark }}</div>
              </el-timeline-item>
            </el-timeline>
            <el-empty v-else description="暂无历史审核记录" :image-size="60" />
          </el-card>
        </template>

        <!-- 空状态 -->
        <div v-else class="workbench-empty">
          <el-icon :size="48" color="var(--color-neutral-300)"><Document /></el-icon>
          <p>请从左侧选择一个订单进行审核</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from "vue"
import { ElMessage } from "element-plus"
import { Search, CircleCheck, Warning, Money, CircleClose, Document } from "@element-plus/icons-vue"
import type { FormInstance, FormRules } from "element-plus"
import {
  getAuditOrderList,
  getAuditHistory
} from "@/api/audit"
import type { AuditOrderGroup, AuditHistoryItem } from "@/api/audit"
import { getOrderDetail } from "@/api/order"
import type { OrderDetail, OrderItemDetail } from "@/types/order"
import { batchAuditOrder } from "@/api/audit"
import { formatMoney, formatDateTime, formatArea } from "@/utils/format"
import StatusTag from "@/components/StatusTag.vue"

/** 搜索 */
const searchKeyword = ref<string>("")
let searchTimer: ReturnType<typeof setTimeout> | null = null

function debouncedSearch(): void {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    listPage.value = 1
    loadAuditList()
  }, 300)
}

/** 筛选Tab */
const filterTabs = reactive<{ label: string; value: number | null; count: number }[]>([
  { label: "全部", value: null, count: 0 },
  { label: "待审核", value: 4, count: 0 },
  { label: "需确认", value: 5, count: 0 },
  { label: "需补款", value: 6, count: 0 }
])

const currentTab = ref<number | null>(null)

function switchTab(tab: number | null): void {
  currentTab.value = tab
  listPage.value = 1
  loadAuditList()
}

/** 列表 */
const listLoading = ref<boolean>(false)
const listPage = ref<number>(1)
const total = ref<number>(0)
const auditList = ref<AuditOrderGroup[]>([])

async function loadAuditList(): Promise<void> {
  listLoading.value = true
  try {
    const result = await getAuditOrderList({
      keyword: searchKeyword.value || undefined,
      status: currentTab.value ?? undefined,
      page: listPage.value,
      page_size: 20
    })
    auditList.value = (result as unknown as { list: AuditOrderGroup[] }).list || []
    total.value = (result as unknown as { total: number }).total || 0
  } catch {
    auditList.value = []
  } finally {
    listLoading.value = false
  }
}

/** 选中订单 */
const selectedOrder = ref<AuditOrderGroup | null>(null)
const detailLoading = ref<boolean>(false)
const orderItems = ref<OrderItemDetail[]>([])
const supplierMappings = ref<Record<string, unknown>[]>([])
const auditHistory = ref<AuditHistoryItem[]>([])

/** 非标项 */
const nonstandardItems = computed<OrderItemDetail[]>(() => {
  return orderItems.value.filter((item) => isNonstandard(item))
})

/** 判断是否非标 */
function isNonstandard(item: OrderItemDetail): boolean {
  const w = parseFloat(item.width)
  const h = parseFloat(item.height)
  // 超常规尺寸：宽度>300cm 或 高度>260cm
  return w > 300 || h > 260 || parseFloat(item.nonstandard_amount) > 0
}

/** 选中订单 */
async function selectOrder(item: AuditOrderGroup): Promise<void> {
  selectedOrder.value = item
  detailLoading.value = true
  try {
    const detail = await getOrderDetail(item.order_id)
    orderItems.value = (detail as unknown as OrderDetail).items || []
    // Load audit history for first item
    if (orderItems.value.length > 0) {
      try {
        const history = await getAuditHistory(orderItems.value[0].item_id)
        auditHistory.value = (history as unknown as AuditHistoryItem[]) || []
      } catch {
        auditHistory.value = []
      }
    }
  } catch {
    orderItems.value = []
    ElMessage.error("加载订单详情失败")
  } finally {
    detailLoading.value = false
  }
}

/** 审核状态颜色 */
const auditStatusTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "4": "warning", // 已支付待审核
  "5": "warning", // 需门店确认
  "6": "warning", // 待补款
  "7": "success", // 审核通过
  "8": "primary", // 生产中
  "14": "success"  // 已完成
}

const itemStatusMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "0": "warning",
  "1": "success",
  "2": "warning",
  "3": "warning",
  "4": "danger"
}

/** 审核表单 */
const auditFormRef = ref<FormInstance>()
const submitting = ref<boolean>(false)
const auditForm = reactive<{
  action: string
  remark: string
  supplementAmount: number
  confirmMessage: string
}>({
  action: "pass",
  remark: "",
  supplementAmount: 0,
  confirmMessage: ""
})

const auditRules = computed<FormRules>(() => ({
  action: [{ required: true, message: "请选择审核结果", trigger: "change" }],
  remark: [{ required: true, message: "请输入审核意见", trigger: "blur" }],
  supplementAmount: auditForm.action === "need_supplement"
    ? [{ required: true, message: "请输入补充金额", trigger: "blur" }]
    : [],
  confirmMessage: auditForm.action === "need_confirm"
    ? [{ required: true, message: "请输入确认说明", trigger: "blur" }]
    : []
}))

/** 提交审核 */
async function handleSubmitAudit(): Promise<void> {
  if (!selectedOrder.value) return
  const valid = await auditFormRef.value?.validate().catch(() => false)
  if (!valid) return

  submitting.value = true
  try {
    await batchAuditOrder(
      selectedOrder.value.order_id,
      auditForm.action,
      auditForm.remark,
      auditForm.action === "need_supplement" ? auditForm.supplementAmount : undefined,
      []
    )
    ElMessage.success("审核提交成功")
    resetAuditForm()
    await loadAuditList()
    // Reload detail
    if (selectedOrder.value) {
      await selectOrder(selectedOrder.value)
    }
  } catch {
    // handled by interceptor
  } finally {
    submitting.value = false
  }
}

/** 重置审核表单 */
function resetAuditForm(): void {
  auditForm.action = "pass"
  auditForm.remark = ""
  auditForm.supplementAmount = 0
  auditForm.confirmMessage = ""
  auditFormRef.value?.clearValidate()
}

/** 历史记录类型 */
function getHistoryType(action: string): "primary" | "success" | "warning" | "danger" | "info" {
  if (action === "pass") return "success"
  if (action === "need_confirm" || action === "need_supplement") return "warning"
  if (action === "cannot_produce") return "danger"
  return "info"
}

onMounted(() => {
  loadAuditList()
})

watch(currentTab, () => {
  loadAuditList()
})
</script>

<style scoped>
.page-header {
  margin-bottom: 16px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-neutral-800);
  margin: 0;
}

.workbench-layout {
  display: flex;
  gap: 16px;
  height: calc(100vh - 160px);
  min-height: 600px;
}

/* 左侧 */
.workbench-left {
  width: 340px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: var(--radius-md);
  overflow: hidden;
}

.left-search {
  padding: 12px;
  border-bottom: 1px solid var(--color-neutral-100);
}

.left-tabs {
  display: flex;
  padding: 0 8px;
  border-bottom: 1px solid var(--color-neutral-100);
}

.tab-item {
  flex: 1;
  text-align: center;
  padding: 10px 4px;
  font-size: 13px;
  color: var(--color-neutral-500);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.tab-item:hover {
  color: var(--color-primary-500);
}

.tab-item.active {
  color: var(--color-primary-500);
  border-bottom-color: var(--color-primary-500);
  font-weight: 500;
}

.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  font-size: 11px;
  font-weight: 600;
  color: #fff;
  background: var(--color-primary-500);
  border-radius: 9px;
}

.left-list {
  flex: 1;
  overflow-y: auto;
}

.list-item {
  padding: 12px 14px;
  border-bottom: 1px solid var(--color-neutral-50);
  cursor: pointer;
  transition: background 0.15s;
}

.list-item:hover {
  background: var(--color-neutral-50);
}

.list-item.active {
  background: var(--color-primary-50);
  border-left: 3px solid var(--color-primary-500);
}

.list-item__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.list-item__no {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-neutral-800);
  font-family: var(--font-family-mono);
}

.list-item__meta {
  font-size: 12px;
  color: var(--color-neutral-500);
  margin-bottom: 4px;
  display: flex;
  gap: 8px;
}

.list-item__project {
  color: var(--color-neutral-400);
}

.list-item__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  color: var(--color-neutral-400);
}

.list-item__count {
  color: var(--color-warning);
  font-weight: 500;
}

.left-pagination {
  padding: 8px;
  border-top: 1px solid var(--color-neutral-100);
  display: flex;
  justify-content: center;
}

/* 右侧 */
.workbench-right {
  flex: 1;
  overflow-y: auto;
  min-width: 0;
}

.detail-section {
  margin-bottom: 16px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.section-sub {
  font-size: 13px;
  color: var(--color-neutral-400);
}

.card-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-neutral-800);
}

.cell-sub {
  font-size: 12px;
  color: var(--color-neutral-400);
}

/* 非标项 */
.nonstandard-section {
  border-left: 3px solid var(--color-warning);
}

.warning-text {
  color: var(--color-warning);
}

.nonstandard-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.nonstandard-item {
  padding: 10px 12px;
  background: var(--color-warning-light);
  border-radius: var(--radius-sm);
}

.nonstandard-item__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  font-weight: 500;
}

.nonstandard-item__amount {
  color: var(--color-warning);
  font-weight: 600;
}

.nonstandard-item__remark {
  margin-top: 4px;
  font-size: 12px;
  color: var(--color-neutral-500);
}

.nonstandard-tag {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

/* 审核操作区 */
.audit-action-section {
  background: var(--color-neutral-50);
}

/* 历史 */
.history-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.history-action {
  font-weight: 500;
  color: var(--color-neutral-700);
}

.history-operator {
  font-size: 12px;
  color: var(--color-neutral-400);
}

.history-remark {
  margin-top: 4px;
  font-size: 13px;
  color: var(--color-neutral-500);
}

/* 空状态 */
.workbench-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  gap: 12px;
  color: var(--color-neutral-400);
  font-size: 14px;
}
</style>
