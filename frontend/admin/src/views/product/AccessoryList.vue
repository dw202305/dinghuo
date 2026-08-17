<template>
  <div class="accessory-list-page">
    <!-- 页面标题 + 操作按钮 -->
    <div class="page-header">
      <h2 class="page-title">选装配件管理</h2>
      <el-button type="primary" :icon="Plus" @click="openCreateDialog">新增配件</el-button>
    </div>

    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="配置组">
        <el-select v-model="queryParams.config_group" placeholder="全部配置组" clearable style="width: 160px">
          <el-option
            v-for="item in CONFIG_GROUP_OPTIONS"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="状态">
        <el-select v-model="queryParams.enabled" placeholder="全部" clearable style="width: 120px">
          <el-option label="启用" :value="1" />
          <el-option label="停用" :value="0" />
        </el-select>
      </el-form-item>
    </SearchForm>

    <!-- 表格 -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <span class="total-text">共 {{ total }} 条记录</span>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column label="图片" width="80" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.image"
              :src="row.image"
              :preview-src-list="[row.image]"
              fit="cover"
              class="accessory-thumb"
              preview-teleported
            />
            <div v-else class="accessory-thumb accessory-thumb--empty">
              <el-icon :size="20" color="var(--color-neutral-300)"><Picture /></el-icon>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="SKU / 名称" min-width="190">
          <template #default="{ row }">
            <div class="cell-accessory">
              <span class="cell-accessory__sku">{{ row.sku }}</span>
              <span class="cell-accessory__name">{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="配置组" width="120" align="center">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ configGroupText(row.config_group) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="optionTypeTagType(row.option_type)" size="small" effect="light">
              {{ row.option_type_text || optionTypeText(row.option_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="加价(元)" width="110" align="right">
          <template #default="{ row }">
            <span class="price-text">¥{{ formatMoney(centToYuan(row.surcharge_cent)) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="升级价(元)" width="110" align="right">
          <template #default="{ row }">
            <span v-if="row.upgrade_price_cent != null" class="price-text">
              ¥{{ formatMoney(centToYuan(row.upgrade_price_cent)) }}
            </span>
            <span v-else class="empty-text">-</span>
          </template>
        </el-table-column>
        <el-table-column label="必选" width="70" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.required === 1" type="danger" size="small" effect="light">必选</el-tag>
            <span v-else class="empty-text">-</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="row.enabled === 1"
              :loading="row._toggling"
              inline-prompt
              active-text="启"
              inactive-text="停"
              @change="(val: boolean) => handleToggleEnabled(row, val)"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="90" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="openEditDialog(row)">编辑</el-button>
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

    <!-- 新增/编辑配件弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑配件' : '新增配件'"
      width="620px"
      destroy-on-close
    >
      <el-form ref="formRef" :model="accessoryForm" :rules="formRules" label-width="110px">
        <el-form-item label="配件 SKU" prop="sku">
          <el-input v-model="accessoryForm.sku" placeholder="请输入配件 SKU" maxlength="50" />
        </el-form-item>
        <el-form-item label="配件名称" prop="name">
          <el-input v-model="accessoryForm.name" placeholder="请输入配件名称" maxlength="100" />
        </el-form-item>
        <el-form-item label="图片地址" prop="image">
          <el-input v-model="accessoryForm.image" placeholder="图片 URL（可选）" maxlength="500" />
        </el-form-item>
        <el-form-item label="配置组" prop="config_group">
          <el-select v-model="accessoryForm.config_group" placeholder="请选择配置组" style="width: 100%">
            <el-option
              v-for="item in CONFIG_GROUP_OPTIONS"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="类型" prop="option_type">
          <el-radio-group v-model="accessoryForm.option_type">
            <el-radio :value="1">标准</el-radio>
            <el-radio :value="2">升级</el-radio>
            <el-radio :value="3">新增</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="加价/补差价(元)" prop="surcharge_yuan">
          <el-input-number
            v-model="accessoryForm.surcharge_yuan"
            :min="0"
            :precision="2"
            :step="1"
            style="width: 220px"
          />
        </el-form-item>
        <el-form-item label="升级价格(元)" prop="upgrade_price_yuan">
          <el-input-number
            v-model="accessoryForm.upgrade_price_yuan"
            :min="0"
            :precision="2"
            :step="1"
            style="width: 220px"
          />
        </el-form-item>
        <el-form-item label="合伙人加价(元)" prop="partner_surcharge_yuan">
          <el-input-number
            v-model="accessoryForm.partner_surcharge_yuan"
            :min="0"
            :precision="2"
            :step="1"
            style="width: 220px"
          />
        </el-form-item>
        <el-form-item label="是否必选">
          <el-switch v-model="accessoryForm.required" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="选择模式">
          <el-radio-group v-model="accessoryForm.select_mode">
            <el-radio :value="1">单选</el-radio>
            <el-radio :value="2">多选</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="允许数量">
          <el-switch v-model="accessoryForm.allow_quantity" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item v-if="accessoryForm.allow_quantity === 1" label="最大数量">
          <el-input-number v-model="accessoryForm.max_quantity" :min="1" :max="999" style="width: 220px" />
        </el-form-item>
        <el-form-item label="生效日期">
          <el-date-picker
            v-model="accessoryForm.effective_date"
            type="date"
            placeholder="选择生效日期"
            value-format="YYYY-MM-DD"
            style="width: 220px"
          />
        </el-form-item>
        <el-form-item label="是否启用">
          <el-switch v-model="accessoryForm.enabled" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="dialogLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue"
import { Plus, Picture } from "@element-plus/icons-vue"
import { ElMessage, type FormInstance, type FormRules } from "element-plus"
import { getAccessoryList, saveAccessory, toggleAccessoryEnabled } from "@/api/accessory"
import type { AccessoryItem, AccessorySaveParams } from "@/types/product"
import { formatMoney } from "@/utils/format"
import { useTable } from "@/composables/useTable"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"

/** 配置组选项（与后端 lj_accessory.config_group 对齐） */
const CONFIG_GROUP_OPTIONS = [
  { value: "power", label: "电动配置" },
  { value: "remote", label: "遥控器配置" },
  { value: "wall_control", label: "墙壁开关配置" }
]

const CONFIG_GROUP_LABEL_MAP: Record<string, string> = Object.fromEntries(
  CONFIG_GROUP_OPTIONS.map((item) => [item.value, item.label])
)

function configGroupText(group: string): string {
  return CONFIG_GROUP_LABEL_MAP[group] || group || "-"
}

function optionTypeText(type: number): string {
  if (type === 1) return "标准"
  if (type === 2) return "升级"
  return "新增"
}

function optionTypeTagType(type: number): "" | "success" | "warning" {
  if (type === 1) return ""
  if (type === 2) return "warning"
  return "success"
}

/** 分转元 */
function centToYuan(cent: number | null | undefined): number {
  return Number(((cent ?? 0) / 100).toFixed(2))
}

/** 元转分 */
function yuanToCent(yuan: number | null | undefined): number {
  return Math.round((yuan ?? 0) * 100)
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
} = useTable<AccessoryItem, Record<string, unknown>>({
  fetchApi: getAccessoryList,
  defaultParams: {
    config_group: undefined,
    enabled: undefined
  }
})

/** 启用/停用 */
async function handleToggleEnabled(row: AccessoryItem & { _toggling?: boolean }, val: boolean): Promise<void> {
  row._toggling = true
  try {
    await toggleAccessoryEnabled(row.id, val ? 1 : 0)
    row.enabled = val ? 1 : 0
    ElMessage.success(val ? "已启用" : "已停用")
  } catch {
    ElMessage.error("操作失败")
  } finally {
    row._toggling = false
  }
}

/** 新增/编辑弹窗 */
const dialogVisible = ref<boolean>(false)
const dialogLoading = ref<boolean>(false)
const isEdit = ref<boolean>(false)
const editId = ref<number>(0)
const formRef = ref<FormInstance>()

interface AccessoryFormState {
  sku: string
  name: string
  image: string
  config_group: string
  option_type: 1 | 2 | 3
  surcharge_yuan: number
  upgrade_price_yuan: number | undefined
  partner_surcharge_yuan: number | undefined
  required: 0 | 1
  select_mode: 1 | 2
  allow_quantity: 0 | 1
  max_quantity: number | undefined
  effective_date: string
  enabled: 0 | 1
}

const accessoryForm = reactive<AccessoryFormState>({
  sku: "",
  name: "",
  image: "",
  config_group: "",
  option_type: 1,
  surcharge_yuan: 0,
  upgrade_price_yuan: undefined,
  partner_surcharge_yuan: undefined,
  required: 0,
  select_mode: 1,
  allow_quantity: 0,
  max_quantity: undefined,
  effective_date: "",
  enabled: 1
})

const formRules: FormRules = {
  sku: [{ required: true, message: "请输入配件 SKU", trigger: "blur" }],
  name: [{ required: true, message: "请输入配件名称", trigger: "blur" }],
  config_group: [{ required: true, message: "请选择配置组", trigger: "change" }],
  option_type: [{ required: true, message: "请选择类型", trigger: "change" }],
  surcharge_yuan: [{ required: true, message: "请输入加价金额", trigger: "blur" }]
}

/**
 * 重置表单
 */
function resetForm(): void {
  accessoryForm.sku = ""
  accessoryForm.name = ""
  accessoryForm.image = ""
  accessoryForm.config_group = ""
  accessoryForm.option_type = 1
  accessoryForm.surcharge_yuan = 0
  accessoryForm.upgrade_price_yuan = undefined
  accessoryForm.partner_surcharge_yuan = undefined
  accessoryForm.required = 0
  accessoryForm.select_mode = 1
  accessoryForm.allow_quantity = 0
  accessoryForm.max_quantity = undefined
  accessoryForm.effective_date = ""
  accessoryForm.enabled = 1
}

/**
 * 打开新增弹窗
 */
function openCreateDialog(): void {
  isEdit.value = false
  editId.value = 0
  resetForm()
  dialogVisible.value = true
}

/**
 * 打开编辑弹窗
 */
function openEditDialog(row: AccessoryItem): void {
  isEdit.value = true
  editId.value = row.id
  accessoryForm.sku = row.sku
  accessoryForm.name = row.name
  accessoryForm.image = row.image || ""
  accessoryForm.config_group = row.config_group
  accessoryForm.option_type = row.option_type
  accessoryForm.surcharge_yuan = centToYuan(row.surcharge_cent)
  accessoryForm.upgrade_price_yuan = row.upgrade_price_cent != null ? centToYuan(row.upgrade_price_cent) : undefined
  accessoryForm.partner_surcharge_yuan =
    row.partner_surcharge_cent != null ? centToYuan(row.partner_surcharge_cent) : undefined
  accessoryForm.required = row.required
  accessoryForm.select_mode = row.select_mode
  accessoryForm.allow_quantity = row.allow_quantity
  accessoryForm.max_quantity = row.max_quantity ?? undefined
  accessoryForm.effective_date = row.effective_date || ""
  accessoryForm.enabled = row.enabled
  dialogVisible.value = true
}

/**
 * 提交新增/编辑
 */
async function handleSubmit(): Promise<void> {
  if (!formRef.value) return
  await formRef.value.validate()

  dialogLoading.value = true
  try {
    const params: AccessorySaveParams = {
      sku: accessoryForm.sku,
      name: accessoryForm.name,
      image: accessoryForm.image || undefined,
      config_group: accessoryForm.config_group,
      option_type: accessoryForm.option_type,
      surcharge_cent: yuanToCent(accessoryForm.surcharge_yuan),
      upgrade_price_cent:
        accessoryForm.upgrade_price_yuan != null ? yuanToCent(accessoryForm.upgrade_price_yuan) : null,
      partner_surcharge_cent:
        accessoryForm.partner_surcharge_yuan != null ? yuanToCent(accessoryForm.partner_surcharge_yuan) : null,
      required: accessoryForm.required,
      select_mode: accessoryForm.select_mode,
      allow_quantity: accessoryForm.allow_quantity,
      max_quantity: accessoryForm.allow_quantity === 1 ? (accessoryForm.max_quantity ?? null) : null,
      enabled: accessoryForm.enabled,
      effective_date: accessoryForm.effective_date || null
    }

    if (isEdit.value) {
      params.id = editId.value
    }

    await saveAccessory(params)
    ElMessage.success(isEdit.value ? "编辑成功" : "新增成功")
    dialogVisible.value = false
    loadData()
  } catch {
    ElMessage.error(isEdit.value ? "编辑失败" : "新增失败")
  } finally {
    dialogLoading.value = false
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

.accessory-thumb {
  width: 50px;
  height: 50px;
  border-radius: var(--radius-sm);
  object-fit: cover;
}

.accessory-thumb--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-neutral-50);
  border: 1px solid var(--color-neutral-100);
}

.cell-accessory {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cell-accessory__sku {
  font-size: 12px;
  color: var(--color-neutral-400);
  font-family: var(--font-family-mono);
}

.cell-accessory__name {
  font-weight: 500;
  color: var(--color-neutral-800);
}

.price-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  color: var(--color-neutral-700);
}

.empty-text {
  color: var(--color-neutral-300);
}
</style>
