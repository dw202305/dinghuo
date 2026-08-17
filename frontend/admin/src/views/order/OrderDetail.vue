<template>
  <div class="order-detail-page">
    <!-- 顶部导航 -->
    <div class="page-header">
      <el-button @click="$router.back()">
        <el-icon><ArrowLeft /></el-icon>
        返回
      </el-button>
      <h2 class="page-title">订单详情</h2>
      <span v-if="detail" class="page-header__no">{{ detail.order_no }}</span>
    </div>

    <el-skeleton :loading="loading" :rows="12" animated>
      <template #default>
        <div v-if="detail" class="detail-layout">
          <!-- 左侧 60% -->
          <div class="detail-main">
            <!-- 状态条 -->
            <div class="status-bar">
              <StatusTag
                :status="detail.order_status"
                :label="detail.order_status_text"
                :type-map="statusTypeMap"
                size="large"
              />
              <div class="status-bar__actions">
                <!-- 待审核状态操作 -->
                <template v-if="detail.order_status === OrderStatus.PAID_PENDING_AUDIT">
                  <el-button type="success" @click="handleAuditPass">审核通过</el-button>
                  <el-button type="warning" @click="openAuditDialog('need_confirm')">需确认</el-button>
                  <el-button type="warning" @click="openAuditDialog('need_supplement')">需补款</el-button>
                  <el-button type="danger" @click="openAuditDialog('cannot_produce')">无法生产</el-button>
                </template>
                <!-- 生产中 -->
                <template v-if="detail.order_status === OrderStatus.IN_PRODUCTION">
                  <el-button type="primary" @click="showShipDialog = true">录入物流</el-button>
                </template>
                <!-- 待发货 -->
                <template v-if="detail.order_status === OrderStatus.PENDING_SHIPMENT">
                  <el-button type="primary" @click="showShipDialog = true">录入物流信息</el-button>
                </template>
                <!-- 取消按钮 -->
                <template v-if="canCancel">
                  <el-button @click="handleCancel">取消订单</el-button>
                </template>
              </div>
            </div>

            <!-- 基本信息 -->
            <el-card class="detail-card">
              <template #header>
                <span class="card-title">基本信息</span>
              </template>
              <el-descriptions :column="3" border>
                <el-descriptions-item label="订单号">
                  <span class="mono-text">{{ detail.order_no }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="门店名称">{{ detail.store_name || "-" }}</el-descriptions-item>
                <el-descriptions-item label="门店编号">{{ detail.store_no || "-" }}</el-descriptions-item>
                <el-descriptions-item label="项目名称">{{ detail.project_name || "-" }}</el-descriptions-item>
                <el-descriptions-item label="终端客户">{{ detail.end_customer || "-" }}</el-descriptions-item>
                <el-descriptions-item label="下单时间">{{ formatDateTime(detail.created_at) }}</el-descriptions-item>
                <el-descriptions-item label="期望交期">{{ detail.expected_delivery_date || "-" }}</el-descriptions-item>
                <el-descriptions-item label="收货方式">{{ detail.delivery_method_text }}</el-descriptions-item>
                <el-descriptions-item label="是否需要发票">
                  <el-tag :type="detail.invoice_required ? 'primary' : 'info'" size="small" effect="light">
                    {{ detail.invoice_required ? "需要" : "不需要" }}
                  </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="备注" :span="3">{{ detail.remark || "-" }}</el-descriptions-item>
              </el-descriptions>
            </el-card>

            <!-- 收货地址 -->
            <el-card class="detail-card">
              <template #header>
                <span class="card-title">收货地址</span>
              </template>
              <el-descriptions :column="2" border>
                <el-descriptions-item label="收件人">{{ detail.receiver.name }}</el-descriptions-item>
                <el-descriptions-item label="联系电话">{{ formatPhone(detail.receiver.phone) }}</el-descriptions-item>
                <el-descriptions-item label="收货地址" :span="2">
                  {{ detail.receiver.province }}{{ detail.receiver.city }}{{ detail.receiver.district }}{{ detail.receiver.detail }}
                </el-descriptions-item>
              </el-descriptions>
            </el-card>

            <!-- 窗帘明细 -->
            <el-card class="detail-card">
              <template #header>
                <div class="card-header-row">
                  <span class="card-title">窗帘明细（{{ detail.items.length }} 副）</span>
                </div>
              </template>
              <el-table :data="detail.items" stripe border>
                <el-table-column type="index" label="序号" width="60" align="center" />
                <el-table-column prop="install_position" label="安装位置" width="100" />
                <el-table-column label="宽×高(cm)" width="130">
                  <template #default="{ row }">
                    {{ row.width }} × {{ row.height }}
                  </template>
                </el-table-column>
                <el-table-column prop="area" label="面积(㎡)" width="90" align="right">
                  <template #default="{ row }">{{ formatArea(row.area) }}</template>
                </el-table-column>
                <el-table-column label="轨道型号+颜色" min-width="140" show-overflow-tooltip>
                  <template #default="{ row }">
                    <span>{{ row.track_color || "-" }}</span>
                  </template>
                </el-table-column>
                <el-table-column label="面料" min-width="160" show-overflow-tooltip>
                  <template #default="{ row }">
                    <div>{{ row.fabric_name }}</div>
                    <div class="cell-sub">¥{{ formatMoney(row.fabric_price) }}/㎡</div>
                  </template>
                </el-table-column>
                <el-table-column prop="power_type_text" label="电源" width="80" align="center" />
                <el-table-column prop="remote_type_text" label="遥控" width="80" align="center" />
                <el-table-column label="墙控" width="80" align="center">
                  <template #default="{ row }">
                    {{ row.wall_control_type_text || "-" }}
                  </template>
                </el-table-column>
                <el-table-column prop="track_amount" label="轨道费" width="90" align="right">
                  <template #default="{ row }">¥{{ formatMoney(row.track_amount) }}</template>
                </el-table-column>
                <el-table-column prop="fabric_amount" label="面料费" width="90" align="right">
                  <template #default="{ row }">¥{{ formatMoney(row.fabric_amount) }}</template>
                </el-table-column>
                <el-table-column label="配件费" width="90" align="right">
                  <template #default="{ row }">¥{{ formatMoney(row.accessory_amount) }}</template>
                </el-table-column>
                <el-table-column label="库存" width="60" align="center">
                  <template #default="{ row }">
                    <el-tag v-if="row.use_inventory" type="success" size="small" effect="light">用</el-tag>
                    <el-tag v-else type="info" size="small" effect="light">新</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="nonstandard_amount" label="非标加价" width="90" align="right">
                  <template #default="{ row }">
                    <span :class="{ 'nonstandard-highlight': parseFloat(row.nonstandard_amount) > 0 }">
                      ¥{{ formatMoney(row.nonstandard_amount) }}
                    </span>
                  </template>
                </el-table-column>
                <el-table-column prop="item_total" label="小计" width="100" align="right" fixed="right">
                  <template #default="{ row }">
                    <span class="amount-text">¥{{ formatMoney(row.item_total) }}</span>
                  </template>
                </el-table-column>
              </el-table>
            </el-card>

            <!-- 费用汇总 -->
            <el-card class="detail-card">
              <template #header>
                <span class="card-title">费用汇总</span>
              </template>
              <div class="summary-grid">
                <div class="summary-item">
                  <span class="summary-label">轨道费用</span>
                  <span class="summary-value">¥{{ formatMoney(detail.summary.track_amount) }}</span>
                </div>
                <div class="summary-item">
                  <span class="summary-label">面料费用</span>
                  <span class="summary-value">¥{{ formatMoney(detail.summary.fabric_amount) }}</span>
                </div>
                <div class="summary-item">
                  <span class="summary-label">选装配件</span>
                  <span class="summary-value">¥{{ formatMoney(detail.summary.accessory_amount) }}</span>
                </div>
                <div class="summary-item">
                  <span class="summary-label">新购套件</span>
                  <span class="summary-value">¥{{ formatMoney(detail.summary.new_purchase_amount) }}</span>
                </div>
                <div class="summary-item">
                  <span class="summary-label">非标加价</span>
                  <span class="summary-value nonstandard-highlight">¥{{ formatMoney(detail.summary.nonstandard_amount) }}</span>
                </div>
                <div class="summary-item">
                  <span class="summary-label">库存使用</span>
                  <span class="summary-value">{{ detail.summary.inventory_used_count }} 套</span>
                </div>
                <div class="summary-item">
                  <span class="summary-label">新购套件数</span>
                  <span class="summary-value">{{ detail.summary.new_purchase_count }} 套</span>
                </div>
                <div class="summary-item">
                  <span class="summary-label">优惠金额</span>
                  <span class="summary-value discount">-¥{{ formatMoney(detail.summary.discount_amount) }}</span>
                </div>
                <div class="summary-item summary-item--total">
                  <span class="summary-label">应付总额</span>
                  <span class="summary-value total">¥{{ formatMoney(detail.summary.total_amount) }}</span>
                </div>
                <div class="summary-item summary-item--total">
                  <span class="summary-label">已付金额</span>
                  <span class="summary-value paid">¥{{ formatMoney(detail.payment.paid_amount) }}</span>
                </div>
              </div>
            </el-card>

            <!-- 支付信息 -->
            <el-card class="detail-card">
              <template #header>
                <span class="card-title">支付信息</span>
              </template>
              <el-descriptions :column="3" border>
                <el-descriptions-item label="支付状态">
                  <StatusTag
                    :status="detail.payment.payment_status"
                    :label="detail.payment.payment_status_text"
                    :type-map="paymentTypeMap"
                  />
                </el-descriptions-item>
                <el-descriptions-item label="已付金额">
                  <span class="amount-text paid">¥{{ formatMoney(detail.payment.paid_amount) }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="锁价时间">
                  {{ detail.payment.price_locked_until ? formatDateTime(detail.payment.price_locked_until) : "-" }}
                </el-descriptions-item>
              </el-descriptions>
            </el-card>

            <!-- 操作日志 -->
            <el-card class="detail-card">
              <template #header>
                <span class="card-title">操作日志</span>
              </template>
              <el-timeline v-if="timeline.length > 0">
                <el-timeline-item
                  v-for="item in timeline"
                  :key="item.log_id"
                  :timestamp="formatDateTime(item.created_at)"
                  placement="top"
                >
                  <div class="timeline-content">
                    <span class="timeline-action">{{ item.action_text }}</span>
                    <span class="timeline-operator">操作人：{{ item.operator_name }}</span>
                  </div>
                  <div v-if="item.remark" class="timeline-remark">{{ item.remark }}</div>
                </el-timeline-item>
              </el-timeline>
              <el-empty v-else description="暂无操作日志" :image-size="60" />
            </el-card>
          </div>

          <!-- 右侧 40% sticky -->
          <div class="detail-sidebar">
            <!-- 操作面板 -->
            <el-card class="sidebar-card">
              <template #header>
                <span class="card-title">操作面板</span>
              </template>
              <div class="operation-panel">
                <template v-if="detail.order_status === OrderStatus.PAID_PENDING_AUDIT">
                  <p class="panel-hint">该订单已支付待审核，请选择审核操作：</p>
                  <div class="panel-actions">
                    <el-button type="success" @click="handleAuditPass" style="width: 100%">
                      ✓ 审核通过
                    </el-button>
                    <el-button type="warning" @click="openAuditDialog('need_confirm')" style="width: 100%">
                      需门店确认
                    </el-button>
                    <el-button type="warning" @click="openAuditDialog('need_supplement')" style="width: 100%">
                      需补款
                    </el-button>
                    <el-button type="danger" @click="openAuditDialog('cannot_produce')" style="width: 100%">
                      ✗ 无法生产
                    </el-button>
                  </div>
                </template>
                <template v-else-if="detail.order_status === OrderStatus.IN_PRODUCTION || detail.order_status === OrderStatus.PENDING_SHIPMENT">
                  <p class="panel-hint">该订单处于{{ detail.order_status_text }}状态：</p>
                  <div class="panel-actions">
                    <el-button type="primary" @click="showShipDialog = true" style="width: 100%">
                      录入物流信息
                    </el-button>
                  </div>
                </template>
                <template v-else>
                  <div class="panel-empty">
                    <el-icon :size="32" color="var(--color-neutral-300)"><InfoFilled /></el-icon>
                    <p>当前状态暂无可执行操作</p>
                  </div>
                </template>
              </div>
            </el-card>

            <!-- 订单状态流转 -->
            <el-card class="sidebar-card">
              <template #header>
                <span class="card-title">状态流转</span>
              </template>
              <el-timeline v-if="timeline.length > 0">
                <el-timeline-item
                  v-for="item in statusTimeline"
                  :key="item.log_id"
                  :timestamp="formatDateTime(item.created_at)"
                  :type="getStatusTimelineType(item.action)"
                  size="small"
                >
                  <span class="status-flow-text">{{ item.action_text }}</span>
                </el-timeline-item>
              </el-timeline>
              <el-empty v-else description="暂无状态流转记录" :image-size="60" />
            </el-card>
          </div>
        </div>
      </template>
    </el-skeleton>

    <!-- 审核操作弹窗 -->
    <el-dialog
      v-model="auditDialogVisible"
      :title="auditDialogTitle"
      width="520px"
      :close-on-click-modal="false"
    >
      <el-form ref="auditFormRef" :model="auditForm" :rules="auditRules" label-width="100px">
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
        <el-form-item label="附件">
          <el-upload
            :auto-upload="false"
            :limit="5"
            accept="image/*,.pdf,.doc,.docx"
            list-type="text"
          >
            <el-button size="small">选择附件</el-button>
            <template #tip>
              <div class="el-upload__tip">支持图片/PDF/Word，最多5个</div>
            </template>
          </el-upload>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="auditDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="auditSubmitting" @click="submitAudit">确认提交</el-button>
      </template>
    </el-dialog>

    <!-- 录入物流弹窗 -->
    <el-dialog v-model="showShipDialog" title="录入物流信息" width="480px" :close-on-click-modal="false">
      <el-form ref="shipFormRef" :model="shipForm" :rules="shipRules" label-width="100px">
        <el-form-item label="物流公司" prop="carrier">
          <el-input v-model="shipForm.carrier" placeholder="请输入物流公司名称" />
        </el-form-item>
        <el-form-item label="物流单号" prop="trackingNo">
          <el-input v-model="shipForm.trackingNo" placeholder="请输入物流单号" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showShipDialog = false">取消</el-button>
        <el-button type="primary" :loading="shipSubmitting" @click="submitShip">确认</el-button>
      </template>
    </el-dialog>

    <!-- 取消订单弹窗 -->
    <ConfirmDialog
      v-model:visible="showCancelDialog"
      title="取消订单"
      message="确定要取消该订单吗？取消后不可恢复。"
      type="danger"
      @confirm="confirmCancel"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue"
import { useRoute } from "vue-router"
import { ElMessage } from "element-plus"
import { ArrowLeft, InfoFilled } from "@element-plus/icons-vue"
import type { FormInstance, FormRules } from "element-plus"
import {
  getOrderDetail,
  getOrderTimeline,
  auditPassOrder,
  auditNeedConfirm,
  auditNeedSupplement,
  auditCannotProduce,
  enterShipping,
  cancelOrder
} from "@/api/order"
import type { OrderDetail as OrderDetailType, OrderTimeline } from "@/api/order"
import { OrderStatus } from "@/types/common"
import { formatMoney, formatDateTime, formatPhone, formatArea } from "@/utils/format"
import StatusTag from "@/components/StatusTag.vue"
import ConfirmDialog from "@/components/ConfirmDialog.vue"

const route = useRoute()

const orderId = computed<number>(() => Number(route.params.id))
const loading = ref<boolean>(true)
const detail = ref<OrderDetailType | null>(null)
const timeline = ref<OrderTimeline[]>([])

/** 状态颜色映射 */
const statusTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "1": "info", "2": "danger", "3": "warning", "4": "warning", "5": "warning",
  "6": "warning", "7": "primary", "8": "primary", "9": "primary", "10": "warning",
  "11": "warning", "12": "success", "13": "success", "14": "success",
  "15": "danger", "16": "info", "17": "danger", "18": "info"
}

const paymentTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "0": "danger", "1": "warning", "2": "success"
}

/** 是否可取消 */
const canCancel = computed<boolean>(() => {
  if (!detail.value) return false
  const s = detail.value.order_status
  return [OrderStatus.DRAFT, OrderStatus.PENDING_PAYMENT, OrderStatus.PAID_PENDING_AUDIT].includes(s)
})

/** 状态流转时间线（只取状态变更类操作） */
const statusTimeline = computed<OrderTimeline[]>(() => {
  return timeline.value.filter(
    (item) => ["创建订单", "支付", "审核", "排产", "生产", "质检", "发货", "签收", "完成", "取消"].some(
      (kw) => item.action_text.includes(kw)
    )
  )
})

/** 审核弹窗 */
const auditDialogVisible = ref<boolean>(false)
const auditFormRef = ref<FormInstance>()
const auditSubmitting = ref<boolean>(false)
const auditForm = reactive<{
  action: string
  remark: string
  supplementAmount: number
  confirmMessage: string
}>({
  action: "",
  remark: "",
  supplementAmount: 0,
  confirmMessage: ""
})

const auditRules = computed<FormRules>(() => ({
  remark: [{ required: true, message: "请输入审核意见", trigger: "blur" }],
  supplementAmount: auditForm.action === "need_supplement"
    ? [{ required: true, message: "请输入补充金额", trigger: "blur" }]
    : [],
  confirmMessage: auditForm.action === "need_confirm"
    ? [{ required: true, message: "请输入确认说明", trigger: "blur" }]
    : []
}))

const auditDialogTitle = computed<string>(() => {
  const map: Record<string, string> = {
    need_confirm: "标记需门店确认",
    need_supplement: "标记需补款",
    cannot_produce: "标记无法生产"
  }
  return map[auditForm.action] || "审核操作"
})

/** 物流弹窗 */
const showShipDialog = ref<boolean>(false)
const shipFormRef = ref<FormInstance>()
const shipSubmitting = ref<boolean>(false)
const shipForm = reactive<{ carrier: string; trackingNo: string }>({
  carrier: "",
  trackingNo: ""
})

const shipRules: FormRules = {
  carrier: [{ required: true, message: "请输入物流公司", trigger: "blur" }],
  trackingNo: [{ required: true, message: "请输入物流单号", trigger: "blur" }]
}

/** 取消订单弹窗 */
const showCancelDialog = ref<boolean>(false)

/** 加载订单详情 */
async function loadDetail(): Promise<void> {
  loading.value = true
  try {
    const [orderData, timelineData] = await Promise.all([
      getOrderDetail(orderId.value),
      getOrderTimeline(orderId.value)
    ])
    detail.value = orderData as unknown as OrderDetailType
    timeline.value = (timelineData as unknown as OrderTimeline[]) || []
  } catch {
    ElMessage.error("加载订单详情失败")
  } finally {
    loading.value = false
  }
}

/** 打开审核弹窗 */
function openAuditDialog(action: string): void {
  auditForm.action = action
  auditForm.remark = ""
  auditForm.supplementAmount = 0
  auditForm.confirmMessage = ""
  auditDialogVisible.value = true
}

/** 提交审核 */
async function submitAudit(): Promise<void> {
  const valid = await auditFormRef.value?.validate().catch(() => false)
  if (!valid) return

  auditSubmitting.value = true
  try {
    switch (auditForm.action) {
      case "pass":
        await auditPassOrder(orderId.value, auditForm.remark)
        ElMessage.success("审核通过")
        break
      case "need_confirm":
        await auditNeedConfirm(orderId.value, auditForm.remark, auditForm.confirmMessage)
        ElMessage.success("已标记需门店确认")
        break
      case "need_supplement":
        await auditNeedSupplement(orderId.value, auditForm.remark, auditForm.supplementAmount)
        ElMessage.success("已标记需补款")
        break
      case "cannot_produce":
        await auditCannotProduce(orderId.value, auditForm.remark)
        ElMessage.success("已标记无法生产")
        break
    }
    auditDialogVisible.value = false
    await loadDetail()
  } catch {
    // handled by interceptor
  } finally {
    auditSubmitting.value = false
  }
}

/** 审核通过 - 打开弹窗填写意见 */
function handleAuditPass(): void {
  openAuditDialog("pass")
}

/** 提交物流 */
async function submitShip(): Promise<void> {
  const valid = await shipFormRef.value?.validate().catch(() => false)
  if (!valid) return

  shipSubmitting.value = true
  try {
    await enterShipping(orderId.value, shipForm.carrier, shipForm.trackingNo)
    ElMessage.success("物流信息录入成功")
    showShipDialog.value = false
    shipForm.carrier = ""
    shipForm.trackingNo = ""
    await loadDetail()
  } catch {
    // handled by interceptor
  } finally {
    shipSubmitting.value = false
  }
}

/** 取消订单 */
function handleCancel(): void {
  showCancelDialog.value = true
}

async function confirmCancel(): Promise<void> {
  try {
    await cancelOrder(orderId.value, "管理员后台取消")
    ElMessage.success("订单已取消")
    await loadDetail()
  } catch {
    // handled by interceptor
  }
}

/** 获取状态时间线类型 */
function getStatusTimelineType(action: string): "primary" | "success" | "warning" | "danger" | "info" {
  if (action.includes("cancel") || action.includes("取消")) return "danger"
  if (action.includes("complete") || action.includes("完成")) return "success"
  if (action.includes("ship") || action.includes("发货")) return "primary"
  return "info"
}

onMounted(() => {
  loadDetail()
})
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-neutral-800);
  margin: 0;
}

.page-header__no {
  font-size: 14px;
  color: var(--color-neutral-500);
  font-family: var(--font-family-mono);
}

.detail-layout {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}

.detail-main {
  flex: 1;
  min-width: 0;
}

.detail-sidebar {
  width: 360px;
  flex-shrink: 0;
  position: sticky;
  top: 20px;
}

.status-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: #fff;
  border-radius: var(--radius-md);
  margin-bottom: 16px;
  box-shadow: var(--shadow-1);
}

.status-bar__actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.detail-card {
  margin-bottom: 16px;
}

.card-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-neutral-800);
}

.card-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.mono-text {
  font-family: var(--font-family-mono);
  font-weight: 500;
}

.cell-sub {
  font-size: 12px;
  color: var(--color-neutral-400);
}

.amount-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.amount-text.paid {
  color: var(--color-success);
}

.nonstandard-highlight {
  color: var(--color-warning);
  font-weight: 600;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px;
  background: var(--color-neutral-50);
  border-radius: var(--radius-sm);
}

.summary-item--total {
  background: var(--color-primary-50);
}

.summary-label {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.summary-value {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-neutral-800);
  font-variant-numeric: tabular-nums;
}

.summary-value.total {
  color: var(--color-primary-500);
  font-size: 20px;
}

.summary-value.paid {
  color: var(--color-success);
}

.summary-value.discount {
  color: var(--color-neutral-400);
}

/* Sidebar */
.sidebar-card {
  margin-bottom: 16px;
}

.operation-panel {
  padding: 4px 0;
}

.panel-hint {
  font-size: 13px;
  color: var(--color-neutral-500);
  margin: 0 0 16px;
}

.panel-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.panel-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 20px 0;
  color: var(--color-neutral-400);
  font-size: 13px;
}

/* Timeline */
.timeline-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.timeline-action {
  font-weight: 500;
  color: var(--color-neutral-700);
}

.timeline-operator {
  font-size: 12px;
  color: var(--color-neutral-400);
}

.timeline-remark {
  margin-top: 4px;
  font-size: 13px;
  color: var(--color-neutral-500);
}

.status-flow-text {
  font-size: 13px;
  color: var(--color-neutral-700);
}
</style>
