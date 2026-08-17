<template>
  <div class="price-manage-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">商品和价格管理</h2>
    </div>

    <el-card shadow="never">
      <el-tabs v-model="activeTab" @tab-change="handleTabChange">
        <!-- ==================== 面料价格 ==================== -->
        <el-tab-pane label="面料价格" name="fabric">
          <div class="tab-toolbar">
            <div class="tab-toolbar__left">
              <el-input
                v-model="fabricKeyword"
                placeholder="编号/名称/颜色关键词"
                clearable
                style="width: 220px"
                @keyup.enter="handleFabricSearch"
                @clear="handleFabricSearch"
              />
              <el-button type="primary" @click="handleFabricSearch">
                <el-icon><Search /></el-icon>
                搜索
              </el-button>
            </div>
            <div class="tab-toolbar__right">
              <span class="selected-hint">已选 {{ selectedFabricIds.length }} 项</span>
              <el-button
                type="primary"
                :icon="PriceTag"
                :disabled="selectedFabricIds.length === 0"
                @click="openBatchPriceDialog"
              >
                批量调价
              </el-button>
            </div>
          </div>

          <el-table :data="fabricTable.tableData.value" v-loading="fabricTable.loading.value" stripe @selection-change="handleFabricSelection">
            <el-table-column type="selection" width="45" align="center" />
            <el-table-column label="编号/名称" min-width="190">
              <template #default="{ row }">
                <div class="cell-fabric">
                  <span class="cell-fabric__no">{{ row.fabric_no }}</span>
                  <span class="cell-fabric__name">{{ row.name }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="series" label="系列" width="110" show-overflow-tooltip />
            <el-table-column prop="color_name" label="颜色" width="100" />
            <el-table-column label="单价(元/㎡)" width="120" align="right">
              <template #default="{ row }">
                <span class="price-text">¥{{ formatMoney(fabricPriceYuan(row)) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="价格版本" width="100" align="center">
              <template #default="{ row }">
                <el-tag size="small" effect="plain">V{{ row.price_version ?? 1 }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="生效日期" width="120" align="center">
              <template #default="{ row }">
                {{ formatDate(row.effective_date) }}
              </template>
            </el-table-column>
            <el-table-column label="上架状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="row.listing_status === 1 ? 'success' : 'info'" size="small" effect="light">
                  {{ row.listing_status === 1 ? "已上架" : "已下架" }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>

          <TablePagination
            :page="fabricTable.queryParams.page"
            :page-size="fabricTable.queryParams.page_size"
            :total="fabricTable.total.value"
            @page-change="fabricTable.handlePageChange"
            @size-change="fabricTable.handleSizeChange"
          />
        </el-tab-pane>

        <!-- ==================== 轨道价格 ==================== -->
        <el-tab-pane label="轨道价格" name="track">
          <el-table :data="trackTable.tableData.value" v-loading="trackTable.loading.value" stripe>
            <el-table-column prop="sku" label="SKU" width="150">
              <template #default="{ row }">
                <span class="sku-text">{{ row.sku }}</span>
              </template>
            </el-table-column>
            <el-table-column label="类型" width="90" align="center">
              <template #default="{ row }">
                <el-tag size="small" effect="plain">{{ row.track_type_text || trackTypeText(row.track_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="color" label="颜色" width="100" />
            <el-table-column label="标准长度(米)" width="120" align="right">
              <template #default="{ row }">
                {{ row.standard_length }}
              </template>
            </el-table-column>
            <el-table-column label="门店单价(元/米)" width="140" align="right">
              <template #default="{ row }">
                <span class="price-text">¥{{ formatMoney(centToYuan(row.price_per_meter_cent)) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="合伙人价(元)" width="130" align="right">
              <template #default="{ row }">
                <span v-if="row.partner_price_cent != null" class="price-text">
                  ¥{{ formatMoney(centToYuan(row.partner_price_cent)) }}
                </span>
                <span v-else class="empty-text">-</span>
              </template>
            </el-table-column>
            <el-table-column label="价格版本" width="100" align="center">
              <template #default="{ row }">
                <el-tag size="small" effect="plain">V{{ row.price_version ?? 1 }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="生效日期" width="120" align="center">
              <template #default="{ row }">
                {{ formatDate(row.effective_date) }}
              </template>
            </el-table-column>
            <el-table-column label="状态" width="90" align="center">
              <template #default="{ row }">
                <el-tag :type="row.enabled === 1 ? 'success' : 'info'" size="small" effect="light">
                  {{ row.enabled === 1 ? "启用" : "停用" }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>

          <TablePagination
            :page="trackTable.queryParams.page"
            :page-size="trackTable.queryParams.page_size"
            :total="trackTable.total.value"
            @page-change="trackTable.handlePageChange"
            @size-change="trackTable.handleSizeChange"
          />
        </el-tab-pane>

        <!-- ==================== 配件价格 ==================== -->
        <el-tab-pane label="配件价格" name="accessory">
          <el-table :data="accessoryTable.tableData.value" v-loading="accessoryTable.loading.value" stripe>
            <el-table-column prop="sku" label="SKU" width="150">
              <template #default="{ row }">
                <span class="sku-text">{{ row.sku }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="name" label="名称" min-width="160" show-overflow-tooltip />
            <el-table-column label="配置组" width="130" align="center">
              <template #default="{ row }">
                <el-tag size="small" effect="plain">{{ configGroupText(row.config_group) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="加价(元)" width="120" align="right">
              <template #default="{ row }">
                <span class="price-text">¥{{ formatMoney(centToYuan(row.surcharge_cent)) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="合伙人加价(元)" width="140" align="right">
              <template #default="{ row }">
                <span v-if="row.partner_surcharge_cent != null" class="price-text">
                  ¥{{ formatMoney(centToYuan(row.partner_surcharge_cent)) }}
                </span>
                <span v-else class="empty-text">-</span>
              </template>
            </el-table-column>
            <el-table-column label="价格版本" width="100" align="center">
              <template #default="{ row }">
                <el-tag size="small" effect="plain">V{{ row.price_version ?? 1 }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="生效日期" width="120" align="center">
              <template #default="{ row }">
                {{ formatDate(row.effective_date) }}
              </template>
            </el-table-column>
            <el-table-column label="状态" width="90" align="center">
              <template #default="{ row }">
                <el-tag :type="row.enabled === 1 ? 'success' : 'info'" size="small" effect="light">
                  {{ row.enabled === 1 ? "启用" : "停用" }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>

          <TablePagination
            :page="accessoryTable.queryParams.page"
            :page-size="accessoryTable.queryParams.page_size"
            :total="accessoryTable.total.value"
            @page-change="accessoryTable.handlePageChange"
            @size-change="accessoryTable.handleSizeChange"
          />
        </el-tab-pane>

        <!-- ==================== 价格版本历史 ==================== -->
        <el-tab-pane label="价格版本历史" name="history">
          <!-- 汇总卡片 -->
          <div class="version-summary">
            <div class="version-summary__card">
              <span class="version-summary__value">{{ historyList.length }}</span>
              <span class="version-summary__label">面料总数</span>
            </div>
            <div class="version-summary__card">
              <span class="version-summary__value">V{{ maxPriceVersion }}</span>
              <span class="version-summary__label">最高价格版本</span>
            </div>
            <div class="version-summary__card">
              <span class="version-summary__value">{{ latestEffectiveDate }}</span>
              <span class="version-summary__label">最新生效日期</span>
            </div>
          </div>

          <el-table :data="historyList" v-loading="historyLoading" stripe>
            <el-table-column label="编号/名称" min-width="190">
              <template #default="{ row }">
                <div class="cell-fabric">
                  <span class="cell-fabric__no">{{ row.fabric_no }}</span>
                  <span class="cell-fabric__name">{{ row.name }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="series" label="系列" width="110" show-overflow-tooltip />
            <el-table-column label="当前单价(元/㎡)" width="140" align="right">
              <template #default="{ row }">
                <span class="price-text">¥{{ formatMoney(fabricPriceYuan(row)) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="价格版本" width="110" align="center">
              <template #default="{ row }">
                <el-tag :type="row.price_version > 1 ? 'warning' : 'info'" size="small" effect="light">
                  V{{ row.price_version ?? 1 }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="生效日期" width="130" align="center">
              <template #default="{ row }">
                {{ formatDate(row.effective_date) }}
              </template>
            </el-table-column>
            <el-table-column label="最近更新时间" width="170" align="center">
              <template #default="{ row }">
                {{ formatDateTime(row.updated_at) }}
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 批量调价弹窗 -->
    <el-dialog v-model="batchDialogVisible" title="面料批量调价" width="520px" destroy-on-close>
      <el-alert
        :title="`已选择 ${selectedFabricIds.length} 个面料，提交后价格立即按新规则计算并升版`"
        type="warning"
        :closable="false"
        show-icon
        class="batch-alert"
      />
      <el-form ref="batchFormRef" :model="batchForm" :rules="batchRules" label-width="100px" class="batch-form">
        <el-form-item label="调价方式" prop="adjust_type">
          <el-radio-group v-model="batchForm.adjust_type">
            <el-radio value="fixed">固定金额增减</el-radio>
            <el-radio value="percent">百分比调整</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="batchForm.adjust_type === 'fixed' ? '调整金额(元)' : '调整幅度(%)'" prop="adjust_value">
          <el-input-number
            v-model="batchForm.adjust_value"
            :min="batchForm.adjust_type === 'fixed' ? -99999 : -100"
            :max="batchForm.adjust_type === 'fixed' ? 99999 : 1000"
            :precision="2"
            :step="1"
            style="width: 240px"
          />
          <span class="batch-hint">
            {{ batchForm.adjust_type === "fixed" ? "正数为涨价，负数为降价，作用于单价(元/㎡)" : "如 5 表示涨价 5%，-5 表示降价 5%" }}
          </span>
        </el-form-item>
        <el-form-item label="生效日期" prop="effective_date">
          <el-date-picker
            v-model="batchForm.effective_date"
            type="date"
            placeholder="选择生效日期"
            value-format="YYYY-MM-DD"
            style="width: 240px"
          />
        </el-form-item>
        <el-form-item label="调价原因" prop="reason">
          <el-input
            v-model="batchForm.reason"
            type="textarea"
            :rows="3"
            placeholder="请输入调价原因（必填，用于审计追溯）"
            maxlength="200"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="batchDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="batchSubmitting" @click="handleBatchSubmit">确认调价</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue"
import { Search, PriceTag } from "@element-plus/icons-vue"
import { ElMessage, type FormInstance, type FormRules } from "element-plus"
import { getFabricList, batchUpdateFabricPrice } from "@/api/fabric"
import { getTrackList } from "@/api/product"
import { getAccessoryList } from "@/api/accessory"
import type { FabricListItem } from "@/types/fabric"
import type { TrackItem, AccessoryItem, FabricBatchPriceParams } from "@/types/product"
import { formatMoney, formatDate, formatDateTime } from "@/utils/format"
import { useTable } from "@/composables/useTable"
import TablePagination from "@/components/TablePagination.vue"

/** 面料行扩展字段（后端返回原始表字段，含版本信息） */
type FabricRow = FabricListItem & {
  price_per_m2_cent?: number
  price_version?: number
  effective_date?: string | null
  updated_at?: string
}

const CONFIG_GROUP_LABEL_MAP: Record<string, string> = {
  power: "电动配置",
  remote: "遥控器配置",
  wall_control: "墙壁开关配置"
}

function configGroupText(group: string): string {
  return CONFIG_GROUP_LABEL_MAP[group] || group || "-"
}

function trackTypeText(type: number): string {
  return type === 1 ? "横轨" : "竖轨"
}

/** 分转元 */
function centToYuan(cent: number | null | undefined): number {
  return Number(((cent ?? 0) / 100).toFixed(2))
}

/**
 * 面料单价（元/㎡）：兼容 price_per_sqm（元）与 price_per_m2_cent（分）两种后端字段
 */
function fabricPriceYuan(row: FabricRow): number {
  if (row.price_per_sqm !== undefined && row.price_per_sqm !== null) {
    const num = Number(row.price_per_sqm)
    if (!isNaN(num)) return num
  }
  if (row.price_per_m2_cent !== undefined && row.price_per_m2_cent !== null) {
    return centToYuan(row.price_per_m2_cent)
  }
  return 0
}

// ==================== Tab 切换与懒加载 ====================

const activeTab = ref<string>("fabric")
const loadedTabs = ref<Set<string>>(new Set(["fabric"]))

function handleTabChange(name: string | number): void {
  const tab = String(name)
  if (loadedTabs.value.has(tab)) return
  loadedTabs.value.add(tab)

  if (tab === "track") {
    trackTable.loadData()
  } else if (tab === "accessory") {
    accessoryTable.loadData()
  } else if (tab === "history") {
    loadHistory()
  }
}

// ==================== 面料价格 ====================

const fabricKeyword = ref<string>("")

const fabricTable = useTable<FabricRow, Record<string, unknown>>({
  fetchApi: (params) => getFabricList({ ...params, keyword: fabricKeyword.value || undefined }),
  pageSize: 20
})

function handleFabricSearch(): void {
  fabricTable.handleSearch()
}

/** 面料多选 */
const selectedFabricIds = ref<number[]>([])

function handleFabricSelection(rows: FabricRow[]): void {
  selectedFabricIds.value = rows.map((r) => r.id)
}

// ==================== 轨道价格 ====================

const trackTable = useTable<TrackItem, Record<string, unknown>>({
  fetchApi: getTrackList,
  immediate: false
})

// ==================== 配件价格 ====================

const accessoryTable = useTable<AccessoryItem, Record<string, unknown>>({
  fetchApi: getAccessoryList,
  immediate: false
})

// ==================== 批量调价 ====================

const batchDialogVisible = ref<boolean>(false)
const batchSubmitting = ref<boolean>(false)
const batchFormRef = ref<FormInstance>()

const batchForm = reactive<{
  adjust_type: "fixed" | "percent"
  adjust_value: number
  effective_date: string
  reason: string
}>({
  adjust_type: "fixed",
  adjust_value: 0,
  effective_date: "",
  reason: ""
})

const batchRules: FormRules = {
  adjust_type: [{ required: true, message: "请选择调价方式", trigger: "change" }],
  adjust_value: [{ required: true, message: "请输入调整数值", trigger: "blur" }],
  effective_date: [{ required: true, message: "请选择生效日期", trigger: "change" }],
  reason: [{ required: true, message: "请输入调价原因", trigger: "blur" }]
}

function openBatchPriceDialog(): void {
  batchForm.adjust_type = "fixed"
  batchForm.adjust_value = 0
  batchForm.effective_date = ""
  batchForm.reason = ""
  batchDialogVisible.value = true
}

/**
 * 提交批量调价
 */
async function handleBatchSubmit(): Promise<void> {
  if (!batchFormRef.value) return
  await batchFormRef.value.validate()

  batchSubmitting.value = true
  try {
    const params: FabricBatchPriceParams = {
      fabric_ids: [...selectedFabricIds.value],
      adjust_type: batchForm.adjust_type,
      adjust_value: batchForm.adjust_value,
      effective_date: batchForm.effective_date,
      reason: batchForm.reason
    }
    const res = await batchUpdateFabricPrice(params)
    ElMessage.success(`批量调价成功，共影响 ${res.affected_count ?? params.fabric_ids.length} 个面料`)
    batchDialogVisible.value = false
    selectedFabricIds.value = []
    fabricTable.loadData()
    // 版本历史数据已过时，下次进入重新加载
    loadedTabs.value.delete("history")
  } catch {
    ElMessage.error("批量调价失败")
  } finally {
    batchSubmitting.value = false
  }
}

// ==================== 价格版本历史 ====================

const historyLoading = ref<boolean>(false)
const historyList = ref<FabricRow[]>([])

/** 最高价格版本 */
const maxPriceVersion = computed<number>(() => {
  return historyList.value.reduce((max, item) => Math.max(max, item.price_version ?? 1), 1)
})

/** 最新生效日期 */
const latestEffectiveDate = computed<string>(() => {
  const dates = historyList.value
    .map((item) => item.effective_date)
    .filter((d): d is string => Boolean(d))
    .sort()
  return dates.length > 0 ? dates[dates.length - 1] : "-"
})

/**
 * 加载版本历史（面料价格版本快照，按版本号/生效日期倒序）
 */
async function loadHistory(): Promise<void> {
  historyLoading.value = true
  try {
    const res = await getFabricList({ page: 1, page_size: 200 })
    historyList.value = [...(res.list as FabricRow[])].sort((a, b) => {
      const versionDiff = (b.price_version ?? 1) - (a.price_version ?? 1)
      if (versionDiff !== 0) return versionDiff
      return (b.effective_date || "").localeCompare(a.effective_date || "")
    })
  } catch {
    ElMessage.error("加载版本历史失败")
  } finally {
    historyLoading.value = false
  }
}
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-neutral-800);
  margin: 0;
}

.tab-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.tab-toolbar__left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tab-toolbar__right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.selected-hint {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.cell-fabric {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cell-fabric__no {
  font-size: 12px;
  color: var(--color-neutral-400);
  font-family: var(--font-family-mono);
}

.cell-fabric__name {
  font-weight: 500;
  color: var(--color-neutral-800);
}

.sku-text {
  font-family: var(--font-family-mono);
  color: var(--color-neutral-700);
}

.price-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  color: var(--color-neutral-700);
}

.empty-text {
  color: var(--color-neutral-300);
}

/* 版本历史汇总卡片 */
.version-summary {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}

.version-summary__card {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px;
  background: var(--color-neutral-50);
  border: 1px solid var(--color-neutral-100);
  border-radius: var(--radius-md);
}

.version-summary__value {
  font-size: 22px;
  font-weight: 600;
  color: var(--color-neutral-800);
  font-variant-numeric: tabular-nums;
}

.version-summary__label {
  font-size: 13px;
  color: var(--color-neutral-500);
}

/* 批量调价弹窗 */
.batch-alert {
  margin-bottom: 16px;
}

.batch-form {
  margin-top: 8px;
}

.batch-hint {
  margin-left: 8px;
  font-size: 12px;
  color: var(--color-neutral-400);
}
</style>
