<template>
  <div class="after-sale-list-page">
    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="关键词">
        <el-input
          v-model="queryParams.keyword"
          placeholder="售后单号/订单号"
          clearable
          style="width: 200px"
        />
      </el-form-item>
      <el-form-item label="门店名称">
        <el-input
          v-model="storeKeyword"
          placeholder="请输入门店名称"
          clearable
          style="width: 160px"
        />
      </el-form-item>
      <el-form-item label="问题类型">
        <el-select
          v-model="queryParams.problem_type"
          placeholder="全部"
          clearable
          style="width: 130px"
        >
          <el-option
            v-for="(label, value) in problemTypeOptions"
            :key="value"
            :label="label"
            :value="Number(value)"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="处理状态">
        <el-select
          v-model="queryParams.status"
          placeholder="全部"
          clearable
          style="width: 130px"
        >
          <el-option label="待处理" :value="1" />
          <el-option label="处理中" :value="2" />
          <el-option label="已解决" :value="3" />
          <el-option label="已关闭" :value="4" />
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
        <el-table-column prop="after_sale_no" label="售后单号" width="160" fixed show-overflow-tooltip />
        <el-table-column prop="order_no" label="关联订单号" width="220" show-overflow-tooltip />
        <el-table-column prop="store_name" label="门店名称" width="140" show-overflow-tooltip />
        <el-table-column label="问题类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small" effect="light" :type="problemTypeTag(row.problem_type)">
              {{ row.problem_type_text || problemTypeLabel(row.problem_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="problem_desc" label="问题描述" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.problem_desc || "-" }}
          </template>
        </el-table-column>
        <el-table-column label="图片" width="70" align="center">
          <template #default="{ row }">
            <el-badge :value="row.images?.length || 0" :type="row.images?.length ? 'primary' : 'info'" class="img-badge">
              <el-icon :size="16"><Picture /></el-icon>
            </el-badge>
          </template>
        </el-table-column>
        <el-table-column label="处理状态" width="100" align="center">
          <template #default="{ row }">
            <StatusTag
              :status="row.status"
              :label="row.status_text || statusLabel(row.status)"
              :type-map="afterSaleStatusTypeMap"
            />
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column prop="handler_name" label="处理人" width="100">
          <template #default="{ row }">
            {{ row.handler_name || "-" }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleViewDetail(row)">
              详情
            </el-button>
            <el-button
              v-if="!row.handler_name && row.status === 1"
              type="warning"
              link
              size="small"
              @click="handleAssign(row)"
            >
              分配
            </el-button>
            <el-button
              v-if="row.status === 2"
              type="success"
              link
              size="small"
              @click="handleProcess(row)"
            >
              处理
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

    <!-- 详情 Drawer -->
    <el-drawer v-model="drawerVisible" title="售后工单详情" size="560px" destroy-on-close>
      <div v-loading="detailLoading" class="drawer-content">
        <template v-if="detailData">
          <!-- 基本信息 -->
          <el-descriptions :column="2" border class="detail-section">
            <el-descriptions-item label="售后单号">{{ detailData.after_sale_no }}</el-descriptions-item>
            <el-descriptions-item label="关联订单号">{{ detailData.order_no }}</el-descriptions-item>
            <el-descriptions-item label="门店名称">{{ detailData.store_name }}</el-descriptions-item>
            <el-descriptions-item label="门店编号">{{ detailData.store_no }}</el-descriptions-item>
            <el-descriptions-item label="问题类型">
              <el-tag size="small" effect="light" :type="problemTypeTag(detailData.problem_type)">
                {{ detailData.problem_type_text || problemTypeLabel(detailData.problem_type) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="处理状态">
              <StatusTag
                :status="detailData.status"
                :label="detailData.status_text || statusLabel(detailData.status)"
                :type-map="afterSaleStatusTypeMap"
              />
            </el-descriptions-item>
            <el-descriptions-item label="联系人">{{ detailData.contact_name }}</el-descriptions-item>
            <el-descriptions-item label="联系电话">{{ detailData.contact_phone }}</el-descriptions-item>
            <el-descriptions-item label="安装日期">{{ detailData.install_date || "-" }}</el-descriptions-item>
            <el-descriptions-item label="是否影响使用">{{ detailData.affect_usage === 1 ? "是" : "否" }}</el-descriptions-item>
            <el-descriptions-item label="处理人">{{ detailData.handler_name || "未分配" }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ formatDateTime(detailData.created_at) }}</el-descriptions-item>
            <el-descriptions-item label="问题描述" :span="2">{{ detailData.problem_desc || "-" }}</el-descriptions-item>
            <el-descriptions-item label="期望解决方案" :span="2">{{ detailData.expected_solution || "-" }}</el-descriptions-item>
          </el-descriptions>

          <!-- 图片预览 -->
          <div v-if="detailData.images?.length" class="detail-section">
            <h4 class="section-title">问题图片</h4>
            <div class="image-grid">
              <el-image
                v-for="(img, idx) in detailData.images"
                :key="idx"
                :src="img"
                fit="cover"
                class="grid-image"
                :preview-src-list="detailData.images"
                :initial-index="idx"
                preview-teleported
              />
            </div>
          </div>

          <!-- 处理记录 -->
          <div v-if="detailData.diagnosis || detailData.solution" class="detail-section">
            <h4 class="section-title">处理记录</h4>
            <el-timeline>
              <el-timeline-item
                v-if="detailData.diagnosis"
                :timestamp="'诊断：' + (detailData.handler_name || '系统')"
                type="primary"
              >
                <p>{{ detailData.diagnosis }}</p>
                <p v-if="detailData.responsibility" class="timeline-sub">
                  责任判定：{{ responsibilityLabel(detailData.responsibility) }}
                </p>
              </el-timeline-item>
              <el-timeline-item
                v-if="detailData.solution"
                :timestamp="'解决方案：' + (detailData.handler_name || '系统')"
                type="success"
              >
                <p>{{ detailData.solution }}</p>
                <p class="timeline-sub">
                  费用：配件 ¥{{ formatMoney(detailData.accessory_cost) }}
                  + 人工 ¥{{ formatMoney(detailData.labor_cost) }}
                  + 物流 ¥{{ formatMoney(detailData.logistics_cost) }}
                </p>
              </el-timeline-item>
            </el-timeline>
          </div>
        </template>
      </div>
    </el-drawer>

    <!-- 分配处理人弹窗 -->
    <el-dialog v-model="assignVisible" title="分配处理人" width="420px" destroy-on-close>
      <el-form :model="assignForm" label-width="90px">
        <el-form-item label="售后单号">
          <span>{{ assignForm.after_sale_no }}</span>
        </el-form-item>
        <el-form-item label="处理人">
          <el-select v-model="assignForm.handler_id" placeholder="请选择处理人" style="width: 100%">
            <el-option
              v-for="admin in adminOptions"
              :key="admin.admin_id"
              :label="admin.real_name"
              :value="admin.admin_id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="assignVisible = false">取消</el-button>
        <el-button type="primary" :loading="assignLoading" @click="confirmAssign">确认分配</el-button>
      </template>
    </el-dialog>

    <!-- 处理结果弹窗 -->
    <el-dialog v-model="processVisible" title="处理售后" width="520px" destroy-on-close>
      <el-form :model="processForm" label-width="90px">
        <el-form-item label="售后单号">
          <span>{{ processForm.after_sale_no }}</span>
        </el-form-item>
        <el-form-item label="诊断结果">
          <el-input v-model="processForm.diagnosis" type="textarea" :rows="3" placeholder="请输入诊断结果" />
        </el-form-item>
        <el-form-item label="责任判定">
          <el-select v-model="processForm.responsibility" placeholder="请选择" style="width: 100%">
            <el-option label="世尚责任" :value="1" />
            <el-option label="门店责任" :value="2" />
            <el-option label="物流责任" :value="3" />
            <el-option label="其他" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="解决方案">
          <el-input v-model="processForm.solution" type="textarea" :rows="3" placeholder="请输入解决方案" />
        </el-form-item>
        <el-form-item label="配件费用">
          <el-input-number v-model="processForm.accessory_cost" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="人工费用">
          <el-input-number v-model="processForm.labor_cost" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="物流费用">
          <el-input-number v-model="processForm.logistics_cost" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="processVisible = false">取消</el-button>
        <el-button type="primary" :loading="processLoading" @click="confirmProcess">确认处理</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive, onMounted } from "vue"
import { Picture } from "@element-plus/icons-vue"
import { ElMessage } from "element-plus"
import { getAfterSaleList, getAfterSaleDetail, processAfterSale, assignAfterSaleHandler } from "@/api/afterSale"
import { getAdminList } from "@/api/system"
import type { AfterSaleListItem, AfterSaleDetail } from "@/api/afterSale"
import type { AdminInfo } from "@/types/admin"
import { useTable } from "@/composables/useTable"
import { formatMoney, formatDateTime } from "@/utils/format"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"
import StatusTag from "@/components/StatusTag.vue"

/** 日期范围 */
const dateRange = ref<[string, string] | null>(null)
/** 门店关键词 */
const storeKeyword = ref<string>("")

/** 问题类型选项 */
const problemTypeOptions: Record<number, string> = {
  1: "电机", 2: "电源", 3: "遥控器", 4: "壁控", 5: "轨道",
  6: "面料", 7: "结构", 8: "安装", 9: "初始化", 10: "运输破损", 11: "其他"
}

/** 售后状态颜色映射 */
const afterSaleStatusTypeMap: Record<string, "success" | "warning" | "danger" | "info" | "primary"> = {
  "1": "danger",
  "2": "warning",
  "3": "success",
  "4": "info"
}

/**
 * 问题类型标签类型
 */
function problemTypeTag(type: number): "success" | "warning" | "danger" | "info" | "primary" {
  if ([1, 2].includes(type)) return "danger"
  if ([5, 6, 8].includes(type)) return "warning"
  return "info"
}

/**
 * 问题类型文本
 */
function problemTypeLabel(type: number): string {
  return problemTypeOptions[type] || "未知"
}

/**
 * 状态文本
 */
function statusLabel(status: number): string {
  const map: Record<number, string> = { 1: "待处理", 2: "处理中", 3: "已解决", 4: "已关闭" }
  return map[status] || "未知"
}

/**
 * 责任判定文本
 */
function responsibilityLabel(type: number): string {
  const map: Record<number, string> = { 1: "世尚责任", 2: "门店责任", 3: "物流责任", 4: "其他" }
  return map[type] || "未知"
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
  fetchApi: getAfterSaleList,
  defaultParams: {
    keyword: undefined,
    status: undefined,
    problem_type: undefined,
    start_date: undefined,
    end_date: undefined
  }
})

/** 搜索时同步额外参数 */
watch([dateRange, storeKeyword], () => {
  if (dateRange.value) {
    (queryParams as Record<string, unknown>).start_date = dateRange.value[0]
    (queryParams as Record<string, unknown>).end_date = dateRange.value[1]
  } else {
    (queryParams as Record<string, unknown>).start_date = undefined
    (queryParams as Record<string, unknown>).end_date = undefined
  }
  ;(queryParams as Record<string, unknown>).store_name = storeKeyword.value || undefined
})

/** 详情 Drawer */
const drawerVisible = ref<boolean>(false)
const detailLoading = ref<boolean>(false)
const detailData = ref<AfterSaleDetail | null>(null)

/**
 * 查看售后详情
 */
async function handleViewDetail(row: AfterSaleListItem): Promise<void> {
  drawerVisible.value = true
  detailLoading.value = true
  try {
    detailData.value = await getAfterSaleDetail(row.after_sale_id)
  } catch {
    ElMessage.error("获取详情失败")
  } finally {
    detailLoading.value = false
  }
}

/** 分配处理人弹窗 */
const assignVisible = ref<boolean>(false)
const assignLoading = ref<boolean>(false)
const assignForm = reactive<{ after_sale_id: number; after_sale_no: string; handler_id: number }>({
  after_sale_id: 0,
  after_sale_no: "",
  handler_id: 0
})
const adminOptions = ref<Pick<AdminInfo, "admin_id" | "real_name">[]>([])

/**
 * 打开分配弹窗
 */
async function handleAssign(row: AfterSaleListItem): Promise<void> {
  assignForm.after_sale_id = row.after_sale_id
  assignForm.after_sale_no = row.after_sale_no
  assignForm.handler_id = 0
  assignVisible.value = true
  // 加载管理员列表
  try {
    const res = await getAdminList({ page: 1, page_size: 100, status: 1 })
    adminOptions.value = res.list.map((a) => ({ admin_id: a.admin_id, real_name: a.real_name }))
  } catch {
    ElMessage.error("加载管理员列表失败")
  }
}

/**
 * 确认分配
 */
async function confirmAssign(): Promise<void> {
  if (!assignForm.handler_id) {
    ElMessage.warning("请选择处理人")
    return
  }
  assignLoading.value = true
  try {
    await assignAfterSaleHandler(assignForm.after_sale_id, assignForm.handler_id)
    ElMessage.success("分配成功")
    assignVisible.value = false
    handleSearch()
  } catch {
    ElMessage.error("分配失败")
  } finally {
    assignLoading.value = false
  }
}

/** 处理结果弹窗 */
const processVisible = ref<boolean>(false)
const processLoading = ref<boolean>(false)
const processForm = reactive<{
  after_sale_id: number
  after_sale_no: string
  diagnosis: string
  responsibility: number | undefined
  solution: string
  accessory_cost: number
  labor_cost: number
  logistics_cost: number
}>({
  after_sale_id: 0,
  after_sale_no: "",
  diagnosis: "",
  responsibility: undefined,
  solution: "",
  accessory_cost: 0,
  labor_cost: 0,
  logistics_cost: 0
})

/**
 * 打开处理弹窗
 */
function handleProcess(row: AfterSaleListItem): void {
  processForm.after_sale_id = row.after_sale_id
  processForm.after_sale_no = row.after_sale_no
  processForm.diagnosis = ""
  processForm.responsibility = undefined
  processForm.solution = ""
  processForm.accessory_cost = 0
  processForm.labor_cost = 0
  processForm.logistics_cost = 0
  processVisible.value = true
}

/**
 * 确认处理
 */
async function confirmProcess(): Promise<void> {
  if (!processForm.diagnosis.trim()) {
    ElMessage.warning("请输入诊断结果")
    return
  }
  processLoading.value = true
  try {
    await processAfterSale({
      after_sale_id: processForm.after_sale_id,
      status: 3,
      diagnosis: processForm.diagnosis,
      responsibility: processForm.responsibility,
      solution: processForm.solution,
      accessory_cost: processForm.accessory_cost,
      labor_cost: processForm.labor_cost,
      logistics_cost: processForm.logistics_cost
    })
    ElMessage.success("处理成功")
    processVisible.value = false
    handleSearch()
  } catch {
    ElMessage.error("处理失败")
  } finally {
    processLoading.value = false
  }
}

onMounted(() => {
  // 预加载管理员列表备用
})
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

.drawer-content {
  padding: 0 4px;
}

.detail-section {
  margin-bottom: 24px;
}

.section-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-neutral-800);
  margin: 0 0 12px;
  padding-left: 8px;
  border-left: 3px solid var(--el-color-primary);
}

.image-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.grid-image {
  width: 120px;
  height: 120px;
  border-radius: var(--radius-sm);
}

.img-badge {
  line-height: 1;
}

.timeline-sub {
  font-size: 12px;
  color: var(--color-neutral-400);
  margin-top: 4px;
}
</style>
