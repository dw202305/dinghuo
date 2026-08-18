<template>
  <div class="stock-list-page">
    <!-- 页面标题 + 操作按钮 -->
    <div class="page-header">
      <h2 class="page-title">库存管理</h2>
      <div class="page-header__actions">
        <el-button type="success" :icon="Download" @click="handleExport" :loading="exporting">
          导出 Excel
        </el-button>
      </div>
    </div>

    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="面料名称/编号">
        <el-input
          v-model="queryParams.keyword"
          placeholder="套件名称/SKU/门店"
          clearable
          style="width: 220px"
        />
      </el-form-item>
    </SearchForm>

    <!-- 表格 -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <div class="table-toolbar__left">
          <span class="total-text">共 {{ total }} 条记录</span>
        </div>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="store_no" label="门店编号" width="110" />
        <el-table-column prop="store_name" label="门店名称" width="160" show-overflow-tooltip />
        <el-table-column prop="kit_sku" label="套件SKU" width="150" show-overflow-tooltip />
        <el-table-column prop="kit_name" label="套件名称" min-width="160" show-overflow-tooltip />
        <el-table-column prop="available" label="可用库存" width="110" align="center">
          <template #default="{ row }">
            <span :class="{ 'text-danger': row.available < 10 }">{{ row.available }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="locked" label="锁定库存" width="110" align="center" />
        <el-table-column prop="total_purchased" label="总库存" width="100" align="center">
          <template #default="{ row }">
            {{ row.total_purchased }}
          </template>
        </el-table-column>
        <el-table-column prop="consumed" label="已核销" width="90" align="center" />
        <el-table-column prop="frozen" label="冻结" width="80" align="center" />
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="openAdjustDialog(row as InventoryRecord)">
              库存调整
            </el-button>
            <el-button type="info" link size="small" @click="openLogDialog(row as InventoryRecord)">
              查看流水
            </el-button>
          </template>
        </el-table-column>

        <!-- 空状态 -->
        <template #empty>
          <div class="empty-state">
            <el-empty description="暂无库存数据" />
          </div>
        </template>
      </el-table>

      <TablePagination
        :page="queryParams.page"
        :page-size="queryParams.page_size"
        :total="total"
        @page-change="handlePageChange"
        @size-change="handleSizeChange"
      />
    </el-card>

    <!-- 库存调整弹窗 -->
    <el-dialog
      v-model="adjustDialogVisible"
      title="库存调整"
      width="480px"
      :close-on-click-modal="false"
      destroy-on-close
    >
      <el-form
        ref="adjustFormRef"
        :model="adjustForm"
        :rules="adjustRules"
        label-width="100px"
      >
        <el-form-item label="门店">
          <span>{{ currentRecord?.store_name }}（{{ currentRecord?.store_no }}）</span>
        </el-form-item>
        <el-form-item label="套件">
          <span>{{ currentRecord?.kit_name }}（{{ currentRecord?.kit_sku }}）</span>
        </el-form-item>
        <el-form-item label="当前可用">
          <span class="text-highlight">{{ currentRecord?.available }}</span>
        </el-form-item>
        <el-form-item label="调整数量" prop="quantity">
          <el-input-number
            v-model="adjustForm.quantity"
            :precision="0"
            :min="-9999"
            :max="9999"
            placeholder="正数增加，负数减少"
            style="width: 100%"
          />
          <div class="form-tip">正数为入库，负数为出库</div>
        </el-form-item>
        <el-form-item label="调整原因" prop="reason">
          <el-input
            v-model="adjustForm.reason"
            type="textarea"
            :rows="3"
            placeholder="请输入调整原因（必填）"
            maxlength="200"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adjustDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleAdjustSubmit" :loading="adjustLoading">
          确认调整
        </el-button>
      </template>
    </el-dialog>

    <!-- 库存流水弹窗 -->
    <el-dialog
      v-model="logDialogVisible"
      title="库存流水"
      width="800px"
      destroy-on-close
    >
      <div class="log-header">
        <span>门店：{{ currentRecord?.store_name }}</span>
        <span class="log-header__sep">|</span>
        <span>套件：{{ currentRecord?.kit_name }}</span>
      </div>
      <el-table :data="logList" v-loading="logLoading" stripe max-height="400">
        <el-table-column prop="log_type_text" label="类型" width="100" />
        <el-table-column prop="quantity" label="变动数量" width="100" align="center">
          <template #default="{ row }">
            <span :class="row.quantity > 0 ? 'text-success' : 'text-danger'">
              {{ row.quantity > 0 ? '+' : '' }}{{ row.quantity }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="before_quantity" label="变动前" width="90" align="center" />
        <el-table-column prop="after_quantity" label="变动后" width="90" align="center" />
        <el-table-column prop="order_no" label="关联订单" width="200" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.order_no || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="operator_name" label="操作人" width="100" />
        <el-table-column prop="reason" label="原因" min-width="150" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.reason || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" width="170" />
        <template #empty>
          <el-empty description="暂无流水记录" :image-size="60" />
        </template>
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue"
import { ElMessage } from "element-plus"
import { Download } from "@element-plus/icons-vue"
import type { FormInstance, FormRules } from "element-plus"
import { getInventoryList, adjustInventory, getInventoryLogs, exportInventory } from "@/api/stock"
import type { InventoryRecord, InventoryLog } from "@/types/stock"
import { useTable } from "@/composables/useTable"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"

/** 导出loading */
const exporting = ref<boolean>(false)

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
  fetchApi: getInventoryList,
  defaultParams: { keyword: undefined }
})

/** 导出库存 */
async function handleExport(): Promise<void> {
  exporting.value = true
  try {
    const result = await exportInventory(queryParams as Record<string, unknown>)
    if (result?.file_url) {
      window.open(result.file_url, "_blank")
      ElMessage.success("导出成功，正在下载")
    }
  } catch {
    ElMessage.error("导出失败")
  } finally {
    exporting.value = false
  }
}

/* ========== 库存调整 ========== */

/** 调整弹窗可见 */
const adjustDialogVisible = ref<boolean>(false)
/** 调整loading */
const adjustLoading = ref<boolean>(false)
/** 当前操作行 */
const currentRecord = ref<InventoryRecord | null>(null)
/** 调整表单ref */
const adjustFormRef = ref<FormInstance>()

/** 调整表单 */
const adjustForm = reactive({
  quantity: 0,
  reason: ""
})

/** 调整校验规则 */
const adjustRules: FormRules = {
  quantity: [
    { required: true, message: "请输入调整数量", trigger: "blur" }
  ],
  reason: [
    { required: true, message: "请输入调整原因", trigger: "blur" },
    { min: 2, max: 200, message: "长度在 2 到 200 个字符", trigger: "blur" }
  ]
}

/**
 * 打开调整弹窗
 */
function openAdjustDialog(row: InventoryRecord): void {
  currentRecord.value = row
  adjustForm.quantity = 0
  adjustForm.reason = ""
  adjustDialogVisible.value = true
}

/**
 * 提交库存调整
 */
async function handleAdjustSubmit(): Promise<void> {
  if (!adjustFormRef.value || !currentRecord.value) return
  const valid = await adjustFormRef.value.validate().catch(() => false)
  if (!valid) return

  adjustLoading.value = true
  try {
    await adjustInventory(
      currentRecord.value.store_id,
      currentRecord.value.kit_sku,
      adjustForm.quantity,
      adjustForm.reason
    )
    ElMessage.success("库存调整成功")
    adjustDialogVisible.value = false
    await handleSearch()
  } catch {
    // 错误已由拦截器处理
  } finally {
    adjustLoading.value = false
  }
}

/* ========== 库存流水 ========== */

/** 流水弹窗可见 */
const logDialogVisible = ref<boolean>(false)
/** 流水loading */
const logLoading = ref<boolean>(false)
/** 流水数据 */
const logList = ref<InventoryLog[]>([])

/**
 * 打开流水弹窗
 */
async function openLogDialog(row: InventoryRecord): Promise<void> {
  currentRecord.value = row
  logList.value = []
  logDialogVisible.value = true
  logLoading.value = true
  try {
    const result = await getInventoryLogs({
      store_id: row.store_id,
      kit_sku: row.kit_sku,
      page: 1,
      page_size: 50
    })
    logList.value = result.list
  } catch {
    // 错误已由拦截器处理
  } finally {
    logLoading.value = false
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

.page-header__actions {
  display: flex;
  gap: 8px;
}

.table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.table-toolbar__left {
  display: flex;
  gap: 8px;
}

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.text-danger {
  color: var(--color-error, #f56c6c);
  font-weight: 600;
}

.text-success {
  color: var(--color-success, #67c23a);
  font-weight: 600;
}

.text-highlight {
  font-weight: 600;
  font-size: 16px;
  color: var(--color-primary, #409eff);
}

.form-tip {
  font-size: 12px;
  color: var(--color-neutral-400);
  margin-top: 4px;
}

.log-header {
  margin-bottom: 12px;
  font-size: 13px;
  color: var(--color-neutral-500);
}

.log-header__sep {
  margin: 0 8px;
  color: var(--color-neutral-300);
}

.empty-state {
  padding: 20px 0;
}
</style>
