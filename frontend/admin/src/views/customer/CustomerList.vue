<template>
  <div class="customer-list-page">
    <!-- 页面标题 + 操作按钮 -->
    <div class="page-header">
      <h2 class="page-title">客户管理</h2>
      <div class="page-header__actions">
        <el-button type="primary" @click="openCreateDialog">
          <el-icon><Plus /></el-icon>
          新建客户
        </el-button>
      </div>
    </div>

    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="客户名称">
        <el-input
          v-model="queryParams.keyword"
          placeholder="门店名称/编号/联系人"
          clearable
          style="width: 200px"
        />
      </el-form-item>
      <el-form-item label="客户等级">
        <el-select v-model="queryParams.customer_level" placeholder="全部" clearable style="width: 150px">
          <el-option
            v-for="(label, value) in CustomerLevelMap"
            :key="value"
            :label="label"
            :value="Number(value)"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="状态">
        <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 120px">
          <el-option label="正常" :value="1" />
          <el-option label="停用" :value="2" />
          <el-option label="待审核" :value="3" />
        </el-select>
      </el-form-item>
    </SearchForm>

    <!-- 表格 -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <span class="total-text">共 {{ total }} 条记录</span>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="store_no" label="客户编号" width="110" />
        <el-table-column prop="store_name" label="门店名称" min-width="160" show-overflow-tooltip />
        <el-table-column prop="primary_contact_name" label="联系人" width="100" />
        <el-table-column prop="contact_phone" label="手机号" width="130">
          <template #default="{ row }">
            {{ row.contact_phone || "-" }}
          </template>
        </el-table-column>
        <el-table-column prop="customer_level_text" label="客户等级" width="140">
          <template #default="{ row }">
            <el-tag :type="levelTagType(row.customer_level)" size="small" effect="light">
              {{ row.customer_level_text }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="order_count" label="累计订单" width="100" align="center">
          <template #default="{ row }">
            {{ row.order_count ?? 0 }}
          </template>
        </el-table-column>
        <el-table-column prop="order_amount" label="累计金额" width="120" align="right">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatMoney(row.order_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="row.status === 1"
              @change="(val: string | number | boolean) => handleToggleStatus(row as TableRow, val === true)"
              :loading="row._toggling"
              inline-prompt
              active-text="启"
              inactive-text="停"
            />
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="openDetailDrawer(row as TableRow)">
              详情
            </el-button>
            <el-button type="danger" link size="small" @click="handleResetPwd(row as TableRow)">
              重置密码
            </el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty description="暂无客户数据" />
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

    <!-- 详情抽屉 -->
    <el-drawer
      v-model="drawerVisible"
      title="客户详情"
      size="520px"
      destroy-on-close
    >
      <div v-loading="detailLoading" class="detail-content">
        <template v-if="detailData">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="门店编号">{{ detailData.store_no }}</el-descriptions-item>
            <el-descriptions-item label="门店名称">{{ detailData.store_name }}</el-descriptions-item>
            <el-descriptions-item label="经营主体">{{ detailData.business_entity || "-" }}</el-descriptions-item>
            <el-descriptions-item label="信用代码">{{ detailData.credit_code || "-" }}</el-descriptions-item>
            <el-descriptions-item label="客户等级">
              <el-tag :type="levelTagType(detailData.customer_level)" size="small" effect="light">
                {{ detailData.customer_level_text }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="渠道模式">{{ detailData.channel_mode_text }}</el-descriptions-item>
            <el-descriptions-item label="合伙人">{{ detailData.partner_name || "-" }}</el-descriptions-item>
            <el-descriptions-item label="归属销售">{{ detailData.primary_sales_name || "-" }}</el-descriptions-item>
            <el-descriptions-item label="联系电话">{{ detailData.contact_phone || "-" }}</el-descriptions-item>
            <el-descriptions-item label="微信号">{{ detailData.wechat || "-" }}</el-descriptions-item>
            <el-descriptions-item label="所在地区" :span="2">
              {{ [detailData.province, detailData.city, detailData.district].filter(Boolean).join(" ") || "-" }}
            </el-descriptions-item>
            <el-descriptions-item label="详细地址" :span="2">{{ detailData.address || "-" }}</el-descriptions-item>
            <el-descriptions-item label="开票名称">{{ detailData.invoice_title || "-" }}</el-descriptions-item>
            <el-descriptions-item label="税号">{{ detailData.tax_no || "-" }}</el-descriptions-item>
            <el-descriptions-item label="合作开始日期">{{ detailData.cooperation_start_date || "-" }}</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="detailData.status === 1 ? 'success' : detailData.status === 3 ? 'warning' : 'danger'" size="small" effect="light">
                {{ detailData.status_text }}
              </el-tag>
            </el-descriptions-item>
          </el-descriptions>

          <!-- 联系人列表 -->
          <div class="detail-section">
            <h4 class="detail-section__title">联系人信息</h4>
            <el-table :data="detailData.contacts" stripe size="small" v-if="detailData.contacts?.length">
              <el-table-column prop="contact_name" label="姓名" width="100" />
              <el-table-column prop="phone" label="手机号" width="130" />
              <el-table-column prop="position" label="职务" width="100" />
              <el-table-column prop="contact_type_text" label="类型" width="80" />
              <el-table-column label="主联系人" width="90" align="center">
                <template #default="{ row }">
                  <el-tag v-if="row.is_primary === 1" type="primary" size="small" effect="light">是</el-tag>
                  <span v-else class="text-muted">否</span>
                </template>
              </el-table-column>
            </el-table>
            <el-empty v-else description="暂无联系人" :image-size="40" />
          </div>
        </template>
      </div>
    </el-drawer>

    <!-- 新建客户弹窗 -->
    <el-dialog
      v-model="createDialogVisible"
      title="新建客户"
      width="600px"
      :close-on-click-modal="false"
      destroy-on-close
    >
      <el-form
        ref="createFormRef"
        :model="createForm"
        :rules="createRules"
        label-width="110px"
      >
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="门店编号" prop="store_no">
              <el-input v-model="createForm.store_no" placeholder="如 HN002" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="门店名称" prop="store_name">
              <el-input v-model="createForm.store_name" placeholder="请输入门店名称" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="联系人" prop="primary_contact_name">
              <el-input v-model="createForm.primary_contact_name" placeholder="主联系人姓名" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="手机号" prop="primary_contact_phone">
              <el-input v-model="createForm.primary_contact_phone" placeholder="主联系人手机号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="客户等级" prop="customer_level">
              <el-select v-model="createForm.customer_level" placeholder="请选择" style="width: 100%">
                <el-option
                  v-for="(label, value) in CustomerLevelMap"
                  :key="value"
                  :label="label"
                  :value="Number(value)"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="渠道模式" prop="channel_mode">
              <el-select v-model="createForm.channel_mode" placeholder="请选择" style="width: 100%">
                <el-option label="合伙人渠道" :value="1" />
                <el-option label="公司直营" :value="2" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="经营主体">
          <el-input v-model="createForm.business_entity" placeholder="公司/个体名称（选填）" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="createForm.contact_phone" placeholder="门店联系电话（选填）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreateSubmit" :loading="createLoading">
          确认创建
        </el-button>
      </template>
    </el-dialog>

    <!-- 重置密码确认弹窗 -->
    <ConfirmDialog
      v-model:visible="resetPwdDialogVisible"
      title="重置密码"
      :message="`确定要重置客户「${resetPwdTarget?.store_name || ''}」的登录密码吗？`"
      type="warning"
      @confirm="confirmResetPwd"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue"
import { ElMessage } from "element-plus"
import { Plus } from "@element-plus/icons-vue"
import type { FormInstance, FormRules } from "element-plus"
import { getCustomerList, getCustomerDetail, createCustomer, updateCustomerStatus, resetCustomerPassword } from "@/api/customer"
import type { CustomerListItem, CustomerDetail, CustomerCreateParams } from "@/types/customer"
import { CustomerLevelMap, CustomerLevel } from "@/types/common"
import { formatMoney, formatDateTime } from "@/utils/format"
import { useTable } from "@/composables/useTable"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"
import ConfirmDialog from "@/components/ConfirmDialog.vue"

/** 扩展行类型（增加 toggling 标记） */
type TableRow = CustomerListItem & { _toggling?: boolean }

/** 客户列表查询参数 */
interface CustomerQueryParams {
  keyword?: string
  customer_level?: number
  status?: number
  [key: string]: unknown
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
} = useTable<TableRow, CustomerQueryParams>({
  fetchApi: getCustomerList as unknown as (params: CustomerQueryParams) => Promise<{ list: TableRow[]; total: number }>,
  defaultParams: { keyword: undefined, customer_level: undefined, status: undefined }
})

/**
 * 客户等级Tag颜色映射
 */
function levelTagType(level: number): "success" | "warning" | "danger" | "info" | "primary" {
  const map: Record<number, "success" | "warning" | "danger" | "info" | "primary"> = {
    [CustomerLevel.CERTIFIED_STORE]: "primary",
    [CustomerLevel.CITY_PARTNER]: "success",
    [CustomerLevel.EXPERIENCE_CUSTOMER]: "info",
    [CustomerLevel.SPECIAL_CONTRACT]: "warning",
    [CustomerLevel.LARGE_B]: "danger"
  }
  return map[level] || "info"
}

/* ========== 启用/禁用 ========== */

/**
 * 切换客户状态
 */
async function handleToggleStatus(row: TableRow, val: boolean): Promise<void> {
  row._toggling = true
  try {
    await updateCustomerStatus(row.id, val ? 1 : 2)
    row.status = val ? 1 : 2
    row.status_text = val ? "正常" : "停用"
    ElMessage.success(val ? "已启用" : "已停用")
  } catch {
    ElMessage.error("操作失败")
  } finally {
    row._toggling = false
  }
}

/* ========== 详情抽屉 ========== */

const drawerVisible = ref<boolean>(false)
const detailLoading = ref<boolean>(false)
const detailData = ref<CustomerDetail | null>(null)

/**
 * 打开详情抽屉
 */
async function openDetailDrawer(row: TableRow): Promise<void> {
  drawerVisible.value = true
  detailLoading.value = true
  detailData.value = null
  try {
    detailData.value = await getCustomerDetail(row.id)
  } catch {
    // 错误已由拦截器处理
  } finally {
    detailLoading.value = false
  }
}

/* ========== 新建客户 ========== */

const createDialogVisible = ref<boolean>(false)
const createLoading = ref<boolean>(false)
const createFormRef = ref<FormInstance>()

const createForm = reactive<CustomerCreateParams>({
  store_no: "",
  store_name: "",
  business_entity: "",
  customer_level: CustomerLevel.CERTIFIED_STORE,
  channel_mode: 1,
  primary_contact_name: "",
  primary_contact_phone: "",
  contact_phone: "",
  primary_sales_id: 1
})

const createRules: FormRules = {
  store_no: [{ required: true, message: "请输入门店编号", trigger: "blur" }],
  store_name: [{ required: true, message: "请输入门店名称", trigger: "blur" }],
  primary_contact_name: [{ required: true, message: "请输入联系人姓名", trigger: "blur" }],
  primary_contact_phone: [
    { required: true, message: "请输入手机号", trigger: "blur" },
    { pattern: /^1[3-9]\d{9}$/, message: "手机号格式不正确", trigger: "blur" }
  ],
  customer_level: [{ required: true, message: "请选择客户等级", trigger: "change" }],
  channel_mode: [{ required: true, message: "请选择渠道模式", trigger: "change" }]
}

/**
 * 打开新建弹窗
 */
function openCreateDialog(): void {
  createForm.store_no = ""
  createForm.store_name = ""
  createForm.business_entity = ""
  createForm.customer_level = CustomerLevel.CERTIFIED_STORE
  createForm.channel_mode = 1
  createForm.primary_contact_name = ""
  createForm.primary_contact_phone = ""
  createForm.contact_phone = ""
  createForm.primary_sales_id = 1
  createDialogVisible.value = true
}

/**
 * 提交新建客户
 */
async function handleCreateSubmit(): Promise<void> {
  if (!createFormRef.value) return
  const valid = await createFormRef.value.validate().catch(() => false)
  if (!valid) return

  createLoading.value = true
  try {
    await createCustomer(createForm)
    ElMessage.success("客户创建成功")
    createDialogVisible.value = false
    await handleSearch()
  } catch {
    // 错误已由拦截器处理
  } finally {
    createLoading.value = false
  }
}

/* ========== 重置密码 ========== */

const resetPwdDialogVisible = ref<boolean>(false)
const resetPwdTarget = ref<TableRow | null>(null)

/**
 * 重置密码
 */
function handleResetPwd(row: TableRow): void {
  resetPwdTarget.value = row
  resetPwdDialogVisible.value = true
}

/**
 * 确认重置密码
 */
async function confirmResetPwd(): Promise<void> {
  if (!resetPwdTarget.value) return
  try {
    await resetCustomerPassword(resetPwdTarget.value.id, resetPwdTarget.value.contact_phone || "")
    ElMessage.success("密码已重置")
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

.text-muted {
  color: var(--color-neutral-400);
  font-size: 12px;
}

/* 详情抽屉 */
.detail-content {
  padding: 0 4px;
}

.detail-section {
  margin-top: 24px;
}

.detail-section__title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-neutral-800);
  margin: 0 0 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--color-neutral-100);
}
</style>
