<template>
  <div class="customer-level-page">
    <!-- 页面标题 + 操作按钮 -->
    <div class="page-header">
      <h2 class="page-title">客户等级管理</h2>
      <div class="page-header__actions">
        <el-button type="primary" @click="openCreateDialog">
          <el-icon><Plus /></el-icon>
          新建等级
        </el-button>
      </div>
    </div>

    <!-- 表格 -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <span class="total-text">共 {{ total }} 个等级</span>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="level" label="等级" width="80" align="center">
          <template #default="{ row }">
            <el-tag type="primary" size="small" round>{{ row.level }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="等级名称" width="180" />
        <el-table-column prop="code" label="等级编码" width="140" />
        <el-table-column prop="discount_rate" label="折扣率(%)" width="120" align="center">
          <template #default="{ row }">
            <template v-if="row._editing">
              <el-input-number
                v-model="row._editData.discount_rate"
                :min="0"
                :max="100"
                :precision="0"
                size="small"
                style="width: 100px"
              />
            </template>
            <template v-else>
              {{ row.discount_rate }}%
            </template>
          </template>
        </el-table-column>
        <el-table-column prop="points_multiplier" label="积分倍率" width="110" align="center">
          <template #default="{ row }">
            <template v-if="row._editing">
              <el-input-number
                v-model="row._editData.points_multiplier"
                :min="0"
                :max="100"
                :precision="1"
                size="small"
                style="width: 100px"
              />
            </template>
            <template v-else>
              {{ row.points_multiplier }}
            </template>
          </template>
        </el-table-column>
        <el-table-column prop="min_consumption" label="最低消费门槛" width="140" align="right">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatMoney(row.min_consumption) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="store_count" label="门店数量" width="100" align="center">
          <template #default="{ row }">
            <span class="count-text">{{ row.store_count }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="80" align="center" />
        <el-table-column prop="status" label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="row.status === 1"
              @change="(val: string | number | boolean) => handleToggleStatus(row as TableRow, val === true)"
              inline-prompt
              active-text="启"
              inactive-text="停"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right" align="center">
          <template #default="{ row }">
            <template v-if="row._editing">
              <el-button type="primary" link size="small" @click="handleSaveEdit(row as TableRow)">保存</el-button>
              <el-button type="info" link size="small" @click="cancelEdit(row as TableRow)">取消</el-button>
            </template>
            <template v-else>
              <el-button type="primary" link size="small" @click="startEdit(row as TableRow)">编辑</el-button>
              <el-button type="danger" link size="small" @click="handleDelete(row as TableRow)">删除</el-button>
            </template>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty description="暂无等级配置" />
        </template>
      </el-table>
    </el-card>

    <!-- 新建/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="520px"
      :close-on-click-modal="false"
      destroy-on-close
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="120px"
      >
        <el-form-item label="等级序号" prop="level">
          <el-input-number v-model="formData.level" :min="1" :max="99" style="width: 100%" />
        </el-form-item>
        <el-form-item label="等级名称" prop="name">
          <el-input v-model="formData.name" placeholder="如：认证合作门店" />
        </el-form-item>
        <el-form-item label="等级编码" prop="code">
          <el-input v-model="formData.code" placeholder="如：certified_store" />
        </el-form-item>
        <el-form-item label="折扣率(%)" prop="discount_rate">
          <el-input-number v-model="formData.discount_rate" :min="0" :max="100" :precision="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="积分倍率" prop="points_multiplier">
          <el-input-number v-model="formData.points_multiplier" :min="0" :max="100" :precision="1" style="width: 100%" />
        </el-form-item>
        <el-form-item label="最低消费门槛" prop="min_consumption">
          <el-input-number v-model="formData.min_consumption" :min="0" :precision="2" style="width: 100%" />
          <div class="form-tip">低于此金额不计入等级升级条件</div>
        </el-form-item>
        <el-form-item label="排序" prop="sort_order">
          <el-input-number v-model="formData.sort_order" :min="0" :max="9999" style="width: 100%" />
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="formData.description" type="textarea" :rows="2" placeholder="等级说明（选填）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitLoading">
          确认
        </el-button>
      </template>
    </el-dialog>

    <!-- 删除确认弹窗 -->
    <ConfirmDialog
      v-model:visible="deleteDialogVisible"
      title="删除等级"
      :message="`确定要删除等级「${deleteTarget?.name || ''}」吗？该等级下仍有 ${deleteTarget?.store_count || 0} 家门店。`"
      type="danger"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue"
import { ElMessage } from "element-plus"
import { Plus } from "@element-plus/icons-vue"
import type { FormInstance, FormRules } from "element-plus"
import { getCustomerLevelList, saveCustomerLevel, deleteCustomerLevel, updateCustomerLevelStatus } from "@/api/customer-level"
import type { CustomerLevelItem, CustomerLevelSaveParams } from "@/types/customer-level"
import { formatMoney } from "@/utils/format"
import { useTable } from "@/composables/useTable"
import ConfirmDialog from "@/components/ConfirmDialog.vue"

/** 扩展行类型 */
type TableRow = CustomerLevelItem & {
  _editing?: boolean
  _editData?: { discount_rate: number; points_multiplier: number }
}

const {
  loading,
  tableData,
  total,
  loadData
} = useTable<TableRow, Record<string, unknown>>({
  fetchApi: getCustomerLevelList as unknown as (params: Record<string, unknown>) => Promise<{ list: TableRow[]; total: number }>,
  defaultParams: {}
})

/* ========== 行内编辑 ========== */

/**
 * 开始行内编辑
 */
function startEdit(row: TableRow): void {
  row._editing = true
  row._editData = {
    discount_rate: row.discount_rate,
    points_multiplier: row.points_multiplier
  }
}

/**
 * 取消编辑
 */
function cancelEdit(row: TableRow): void {
  row._editing = false
  row._editData = undefined
}

/**
 * 保存行内编辑
 */
async function handleSaveEdit(row: TableRow): Promise<void> {
  if (!row._editData) return
  try {
    await saveCustomerLevel({
      id: row.id,
      level: row.level,
      name: row.name,
      code: row.code,
      discount_rate: row._editData.discount_rate,
      points_multiplier: row._editData.points_multiplier,
      min_consumption: Number(row.min_consumption),
      sort_order: row.sort_order,
      description: row.description
    })
    row.discount_rate = row._editData.discount_rate
    row.points_multiplier = row._editData.points_multiplier
    row._editing = false
    row._editData = undefined
    ElMessage.success("保存成功")
  } catch {
    // 错误已由拦截器处理
  }
}

/* ========== 状态切换 ========== */

/**
 * 切换等级状态
 */
async function handleToggleStatus(row: TableRow, val: boolean): Promise<void> {
  try {
    await updateCustomerLevelStatus(row.id, val ? 1 : 0)
    row.status = val ? 1 : 0
    ElMessage.success(val ? "已启用" : "已停用")
  } catch {
    ElMessage.error("操作失败")
  }
}

/* ========== 新建弹窗 ========== */

const dialogVisible = ref<boolean>(false)
const dialogTitle = ref<string>("新建等级")
const submitLoading = ref<boolean>(false)
const formRef = ref<FormInstance>()
const editingId = ref<number | null>(null)

const formData = reactive<CustomerLevelSaveParams>({
  level: 1,
  name: "",
  code: "",
  discount_rate: 100,
  points_multiplier: 1,
  min_consumption: 0,
  sort_order: 0,
  description: ""
})

const formRules: FormRules = {
  level: [{ required: true, message: "请输入等级序号", trigger: "blur" }],
  name: [
    { required: true, message: "请输入等级名称", trigger: "blur" },
    { min: 2, max: 20, message: "长度在 2 到 20 个字符", trigger: "blur" }
  ],
  code: [{ required: true, message: "请输入等级编码", trigger: "blur" }],
  discount_rate: [{ required: true, message: "请输入折扣率", trigger: "blur" }],
  points_multiplier: [{ required: true, message: "请输入积分倍率", trigger: "blur" }]
}

/**
 * 打开新建弹窗
 */
function openCreateDialog(): void {
  editingId.value = null
  dialogTitle.value = "新建等级"
  formData.level = 1
  formData.name = ""
  formData.code = ""
  formData.discount_rate = 100
  formData.points_multiplier = 1
  formData.min_consumption = 0
  formData.sort_order = 0
  formData.description = ""
  dialogVisible.value = true
}

/**
 * 提交表单
 */
async function handleSubmit(): Promise<void> {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    await saveCustomerLevel({
      ...formData,
      id: editingId.value ?? undefined
    })
    ElMessage.success(editingId.value ? "编辑成功" : "创建成功")
    dialogVisible.value = false
    await loadData()
  } catch {
    // 错误已由拦截器处理
  } finally {
    submitLoading.value = false
  }
}

/* ========== 删除 ========== */

const deleteDialogVisible = ref<boolean>(false)
const deleteTarget = ref<TableRow | null>(null)

/**
 * 删除等级
 */
function handleDelete(row: TableRow): void {
  deleteTarget.value = row
  deleteDialogVisible.value = true
}

/**
 * 确认删除
 */
async function confirmDelete(): Promise<void> {
  if (!deleteTarget.value) return
  try {
    await deleteCustomerLevel(deleteTarget.value.id)
    ElMessage.success("删除成功")
    await loadData()
  } catch {
    // 错误已由拦截器处理
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

.total-text {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.amount-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  color: var(--color-neutral-700);
}

.count-text {
  font-weight: 600;
  color: var(--color-primary, #409eff);
}

.form-tip {
  font-size: 12px;
  color: var(--color-neutral-400);
  margin-top: 4px;
}
</style>
