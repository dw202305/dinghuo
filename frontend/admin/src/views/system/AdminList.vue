<template>
  <div class="admin-list-page">
    <!-- 搜索区 -->
    <div class="page-header">
      <h2 class="page-title">管理员管理</h2>
      <el-button type="primary" :icon="Plus" @click="openCreateDialog">新增管理员</el-button>
    </div>

    <SearchForm :model-value="searchParams as Record<string, unknown>" @search="handleSearch" @reset="handleResetSearch">
      <el-form-item label="关键词">
        <el-input
          v-model="searchParams.keyword"
          placeholder="用户名/姓名/手机号"
          clearable
          style="width: 200px"
        />
      </el-form-item>
      <el-form-item label="状态">
        <el-select
          v-model="searchParams.status"
          placeholder="全部"
          clearable
          style="width: 120px"
        >
          <el-option label="启用" :value="1" />
          <el-option label="禁用" :value="0" />
        </el-select>
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
        <el-table-column prop="admin_id" label="管理员ID" width="90" align="center" />
        <el-table-column prop="username" label="用户名" width="140" show-overflow-tooltip />
        <el-table-column prop="real_name" label="姓名" width="120" />
        <el-table-column prop="phone" label="手机号" width="140">
          <template #default="{ row }">
            {{ row.phone || "-" }}
          </template>
        </el-table-column>
        <el-table-column label="角色" width="200">
          <template #default="{ row }">
            <template v-if="row.role_names?.length">
              <el-tag
                v-for="name in row.role_names"
                :key="name"
                size="small"
                effect="light"
                class="role-tag"
              >
                {{ name }}
              </el-tag>
            </template>
            <el-tag v-else size="small" effect="light">
              {{ row.role_name || "-" }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="row.status === 1"
              @change="(val: string | number | boolean) => handleToggleStatus(row as AdminInfo & { _statusToggling?: boolean }, val === true)"
              :loading="row._statusToggling"
              inline-prompt
              active-text="启"
              inactive-text="禁"
            />
          </template>
        </el-table-column>
        <el-table-column prop="last_login_at" label="最后登录" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.last_login_at) }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="openEditDialog(row as AdminInfo)">编辑</el-button>
            <el-button type="warning" link size="small" @click="handleResetPassword(row as AdminInfo)">重置密码</el-button>
            <el-button type="danger" link size="small" @click="handleDeleteAdmin(row as AdminInfo)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <TablePagination
        :page="pageParams.page"
        :page-size="pageParams.page_size"
        :total="total"
        @page-change="handlePageChange"
        @size-change="handleSizeChange"
      />
    </el-card>

    <!-- 新增/编辑管理员弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑管理员' : '新增管理员'"
      width="520px"
      destroy-on-close
    >
      <el-form
        ref="formRef"
        :model="adminForm"
        :rules="formRules"
        label-width="80px"
      >
        <el-form-item label="用户名" prop="username">
          <el-input
            v-model="adminForm.username"
            placeholder="请输入用户名"
            :disabled="isEdit"
            maxlength="30"
          />
        </el-form-item>
        <el-form-item v-if="!isEdit" label="密码" prop="password">
          <el-input
            v-model="adminForm.password"
            type="password"
            placeholder="请输入密码（至少8位）"
            show-password
            maxlength="32"
          />
        </el-form-item>
        <el-form-item label="姓名" prop="real_name">
          <el-input v-model="adminForm.real_name" placeholder="请输入姓名" maxlength="20" />
        </el-form-item>
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="adminForm.phone" placeholder="请输入手机号" maxlength="11" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="adminForm.email" placeholder="请输入邮箱" maxlength="50" />
        </el-form-item>
        <el-form-item label="角色" prop="role_ids">
          <el-select
            v-model="adminForm.role_ids"
            multiple
            placeholder="请选择角色"
            style="width: 100%"
            filterable
          >
            <el-option
              v-for="role in roleOptions"
              :key="role.role_id"
              :label="role.role_name"
              :value="role.role_id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="dialogLoading" @click="handleSubmitAdmin">确定</el-button>
      </template>
    </el-dialog>

    <!-- 删除确认弹窗 -->
    <ConfirmDialog
      v-model:visible="deleteVisible"
      title="删除管理员"
      :message="`确定要删除管理员「${deleteTarget?.real_name || deleteTarget?.username || ''}」吗？删除后不可恢复。`"
      type="danger"
      @confirm="confirmDeleteAdmin"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from "vue"
import { Plus } from "@element-plus/icons-vue"
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from "element-plus"
import { getAdminList, saveAdmin, deleteAdmin, resetAdminPassword, updateAdminStatus, getAllRoles } from "@/api/system"
import type { AdminInfo } from "@/types/admin"
import type { RoleInfo } from "@/types/admin"
import { useTable } from "@/composables/useTable"
import { formatDateTime } from "@/utils/format"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"
import ConfirmDialog from "@/components/ConfirmDialog.vue"

/** 搜索参数（独立管理） */
const searchParams = reactive<{ keyword?: string; status?: number }>({
  keyword: undefined,
  status: undefined
})

/** 分页参数 */
const pageParams = reactive<{ page: number; page_size: number }>({
  page: 1,
  page_size: 20
})

const {
  loading,
  tableData,
  total,
  loadData,
  handlePageChange,
  handleSizeChange
} = useTable({
  fetchApi: (params: Record<string, unknown>) =>
    getAdminList({
      keyword: searchParams.keyword,
      status: searchParams.status,
      page: params.page as number,
      page_size: params.page_size as number
    }),
  defaultParams: {}
})

/** 搜索 */
function handleSearch(): void {
  pageParams.page = 1
  loadData()
}

/** 重置搜索 */
function handleResetSearch(): void {
  searchParams.keyword = undefined
  searchParams.status = undefined
  pageParams.page = 1
  loadData()
}

/** 角色选项 */
const roleOptions = ref<RoleInfo[]>([])

onMounted(async () => {
  try {
    const res = await getAllRoles()
    roleOptions.value = res.list || []
  } catch {
    // 静默处理
  }
})

/** 启用/禁用 */
async function handleToggleStatus(row: AdminInfo & { _statusToggling?: boolean }, val: boolean): Promise<void> {
  row._statusToggling = true
  try {
    await updateAdminStatus(row.admin_id, val ? 1 : 0)
    row.status = val ? 1 : 0
    ElMessage.success(val ? "已启用" : "已禁用")
  } catch {
    ElMessage.error("操作失败")
  } finally {
    row._statusToggling = false
  }
}

/** 重置密码 */
async function handleResetPassword(row: AdminInfo): Promise<void> {
  try {
    await ElMessageBox.confirm(
      `确定要重置管理员「${row.real_name || row.username}」的密码吗？`,
      "重置密码",
      { confirmButtonText: "确定", cancelButtonText: "取消", type: "warning" }
    )
    await resetAdminPassword(row.admin_id)
    ElMessage.success("密码已重置，新密码已发送至管理员手机")
  } catch {
    // 用户取消
  }
}

/** 新增/编辑弹窗 */
const dialogVisible = ref<boolean>(false)
const dialogLoading = ref<boolean>(false)
const isEdit = ref<boolean>(false)
const editId = ref<number>(0)
const formRef = ref<FormInstance>()

const adminForm = reactive<{
  username: string
  password: string
  real_name: string
  phone: string
  email: string
  role_ids: number[]
}>({
  username: "",
  password: "",
  real_name: "",
  phone: "",
  email: "",
  role_ids: []
})

const formRules: FormRules = {
  username: [
    { required: true, message: "请输入用户名", trigger: "blur" },
    { min: 3, max: 30, message: "长度在 3 到 30 个字符", trigger: "blur" }
  ],
  password: [
    { required: true, message: "请输入密码", trigger: "blur" },
    { min: 8, message: "密码至少 8 位", trigger: "blur" }
  ],
  real_name: [
    { required: true, message: "请输入姓名", trigger: "blur" }
  ],
  phone: [
    { pattern: /^1[3-9]\d{9}$/, message: "手机号格式不正确", trigger: "blur" }
  ],
  email: [
    { type: "email", message: "邮箱格式不正确", trigger: "blur" }
  ],
  role_ids: [
    { required: true, message: "请选择角色", trigger: "change", type: "array" as const }
  ]
}

/**
 * 打开新增弹窗
 */
function openCreateDialog(): void {
  isEdit.value = false
  editId.value = 0
  adminForm.username = ""
  adminForm.password = ""
  adminForm.real_name = ""
  adminForm.phone = ""
  adminForm.email = ""
  adminForm.role_ids = []
  dialogVisible.value = true
}

/**
 * 打开编辑弹窗
 */
function openEditDialog(row: AdminInfo): void {
  isEdit.value = true
  editId.value = row.admin_id
  adminForm.username = row.username
  adminForm.password = ""
  adminForm.real_name = row.real_name
  adminForm.phone = row.phone || ""
  adminForm.email = row.email || ""
  adminForm.role_ids = row.role_ids?.length ? [...row.role_ids] : (row.role_id ? [row.role_id] : [])
  dialogVisible.value = true
}

/**
 * 提交新增/编辑
 */
async function handleSubmitAdmin(): Promise<void> {
  if (!formRef.value) return
  await formRef.value.validate()

  dialogLoading.value = true
  try {
    const params: Record<string, unknown> = {
      username: adminForm.username,
      real_name: adminForm.real_name,
      phone: adminForm.phone || undefined,
      email: adminForm.email || undefined,
      role_ids: adminForm.role_ids,
      role_id: adminForm.role_ids[adminForm.role_ids.length - 1] // 取最后一个作为主角色
    }

    if (isEdit.value) {
      params.admin_id = editId.value
    } else {
      params.password = adminForm.password
    }

    await saveAdmin(params as unknown as import("@/types/admin").AdminSaveParams)
    ElMessage.success(isEdit.value ? "编辑成功" : "新增成功")
    dialogVisible.value = false
    loadData()
  } catch {
    ElMessage.error(isEdit.value ? "编辑失败" : "新增失败")
  } finally {
    dialogLoading.value = false
  }
}

/** 删除 */
const deleteVisible = ref<boolean>(false)
const deleteTarget = ref<AdminInfo | null>(null)

/**
 * 打开删除确认
 */
function handleDeleteAdmin(row: AdminInfo): void {
  deleteTarget.value = row
  deleteVisible.value = true
}

/**
 * 确认删除
 */
async function confirmDeleteAdmin(): Promise<void> {
  if (!deleteTarget.value) return
  try {
    await deleteAdmin(deleteTarget.value.admin_id)
    ElMessage.success("删除成功")
    loadData()
  } catch {
    ElMessage.error("删除失败")
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

.role-tag {
  margin-right: 4px;
}
</style>
