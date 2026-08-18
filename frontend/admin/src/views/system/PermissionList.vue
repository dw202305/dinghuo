<template>
  <div class="permission-list-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">权限管理</h2>
      <div class="page-header__actions">
        <el-button @click="handleExpandAll">{{ isExpandAll ? '全部折叠' : '全部展开' }}</el-button>
        <el-button type="primary" :icon="Plus" @click="openCreateDialog()">新增权限</el-button>
      </div>
    </div>

    <!-- 树形表格 -->
    <el-card shadow="never">
      <el-table
        ref="tableRef"
        :data="permissionTree"
        v-loading="loading"
        row-key="permission_id"
        :tree-props="{ children: 'children' }"
        border
        :default-expand-all="isExpandAll"
        :row-class-name="rowClassName"
      >
        <el-table-column prop="permission_name" label="权限名称" min-width="220">
          <template #default="{ row }">
            <div class="perm-name-cell">
              <el-icon v-if="row.icon" class="perm-icon">
                <component :is="row.icon" />
              </el-icon>
              <span class="perm-name">{{ row.permission_name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="permission_code" label="权限编码" width="200">
          <template #default="{ row }">
            <code class="code-text">{{ row.permission_code || "-" }}</code>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag
              :type="permTypeTag(row.permission_type)"
              size="small"
              effect="light"
            >
              {{ permTypeLabel(row.permission_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="path" label="路由路径" width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <span class="path-text">{{ row.path || "-" }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="icon" label="图标" width="80" align="center">
          <template #default="{ row }">
            {{ row.icon || "-" }}
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="70" align="center" />
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small" effect="light">
              {{ row.status === 1 ? "启用" : "禁用" }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }: { row: PermissionNode }">
            <el-button type="primary" link size="small" @click="openEditDialog(row)">编辑</el-button>
            <el-button type="success" link size="small" @click="openCreateDialog(row)">新增子权限</el-button>
            <el-button type="danger" link size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 新增/编辑权限弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="560px"
      destroy-on-close
    >
      <el-form
        ref="formRef"
        :model="permForm"
        :rules="formRules"
        label-width="90px"
      >
        <el-form-item label="上级权限">
          <el-tree-select
            v-model="permForm.parent_id"
            :data="parentOptions"
            node-key="permission_id"
            :props="{ label: 'permission_name', children: 'children' }"
            placeholder="无（顶级权限）"
            clearable
            check-strictly
            :render-after-expand="false"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="权限名称" prop="permission_name">
          <el-input v-model="permForm.permission_name" placeholder="如：订单管理" maxlength="30" />
        </el-form-item>
        <el-form-item label="权限编码" prop="permission_code">
          <el-input
            v-model="permForm.permission_code"
            placeholder="如：order、order:view"
            maxlength="60"
          />
        </el-form-item>
        <el-form-item label="权限类型" prop="permission_type">
          <el-radio-group v-model="permForm.permission_type">
            <el-radio :value="1">菜单</el-radio>
            <el-radio :value="2">按钮</el-radio>
            <el-radio :value="3">接口</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="路由路径" v-if="permForm.permission_type === 1">
          <el-input v-model="permForm.path" placeholder="如：/order" maxlength="100" />
        </el-form-item>
        <el-form-item label="图标" v-if="permForm.permission_type === 1">
          <el-input v-model="permForm.icon" placeholder="图标名称" maxlength="30" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number
            v-model="permForm.sort_order"
            :min="0"
            :max="9999"
            controls-position="right"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="permForm.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="dialogLoading" @click="handleSubmitPerm">确定</el-button>
      </template>
    </el-dialog>

    <!-- 删除确认弹窗 -->
    <ConfirmDialog
      v-model:visible="deleteVisible"
      title="删除权限"
      :message="deleteMessage"
      type="danger"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from "vue"
import { Plus } from "@element-plus/icons-vue"
import { ElMessage, type FormInstance, type FormRules } from "element-plus"
import { getPermissionTree, savePermission, deletePermission } from "@/api/system"
import type { PermissionNode } from "@/types/admin"
import ConfirmDialog from "@/components/ConfirmDialog.vue"

/** 加载状态 */
const loading = ref<boolean>(false)
/** 权限树数据 */
const permissionTree = ref<PermissionNode[]>([])
/** 是否全部展开 */
const isExpandAll = ref<boolean>(true)
/** 表格引用 */
const tableRef = ref()

/**
 * 加载权限树
 */
async function loadTree(): Promise<void> {
  loading.value = true
  try {
    const res = await getPermissionTree()
    permissionTree.value = res.tree || []
  } catch {
    ElMessage.error("加载权限树失败")
  } finally {
    loading.value = false
  }
}

/** 切换展开/折叠 */
function handleExpandAll(): void {
  isExpandAll.value = !isExpandAll.value
}

/**
 * 权限类型标签
 */
function permTypeTag(type: number): "primary" | "success" | "info" | "warning" {
  if (type === 1) return "primary"
  if (type === 2) return "success"
  return "info"
}

/**
 * 权限类型文本
 */
function permTypeLabel(type: number): string {
  const map: Record<number, string> = { 1: "菜单", 2: "按钮", 3: "接口" }
  return map[type] || "未知"
}

/**
 * 行样式
 */
function rowClassName({ row }: { row: PermissionNode }): string {
  if (row.status === 0) return "row-disabled"
  return ""
}

/** ==================== 新增/编辑弹窗 ==================== */
const dialogVisible = ref<boolean>(false)
const dialogLoading = ref<boolean>(false)
const isEdit = ref<boolean>(false)
const formRef = ref<FormInstance>()

const permForm = reactive<{
  permission_id: number | undefined
  parent_id: number | undefined
  permission_name: string
  permission_code: string
  permission_type: 1 | 2 | 3
  path: string
  icon: string
  sort_order: number
  status: 0 | 1
}>({
  permission_id: undefined,
  parent_id: undefined,
  permission_name: "",
  permission_code: "",
  permission_type: 1,
  path: "",
  icon: "",
  sort_order: 100,
  status: 1
})

const formRules: FormRules = {
  permission_name: [
    { required: true, message: "请输入权限名称", trigger: "blur" }
  ],
  permission_code: [
    { required: true, message: "请输入权限编码", trigger: "blur" }
  ],
  permission_type: [
    { required: true, message: "请选择权限类型", trigger: "change" }
  ]
}

/** 弹窗标题 */
const dialogTitle = computed<string>(() => {
  if (isEdit.value) return "编辑权限"
  return permForm.parent_id ? "新增子权限" : "新增权限"
})

/** 上级权限树选择器数据 */
const parentOptions = computed<PermissionNode[]>(() => {
  // 仅展示菜单类型（type=1）作为可选的父节点
  return permissionTree.value.filter((n) => n.permission_type === 1)
})

/**
 * 打开新增权限弹窗
 * @param parentRow 如果传入，则自动填入父级ID
 */
function openCreateDialog(parentRow?: PermissionNode): void {
  isEdit.value = false
  permForm.permission_id = undefined
  permForm.parent_id = parentRow?.permission_id ?? undefined
  permForm.permission_name = ""
  permForm.permission_code = ""
  permForm.permission_type = parentRow ? 2 : 1 // 子权限默认按钮类型
  permForm.path = ""
  permForm.icon = ""
  permForm.sort_order = 100
  permForm.status = 1
  dialogVisible.value = true
}

/**
 * 打开编辑弹窗
 */
function openEditDialog(row: PermissionNode): void {
  isEdit.value = true
  permForm.permission_id = row.permission_id
  permForm.parent_id = row.parent_id || undefined
  permForm.permission_name = row.permission_name
  permForm.permission_code = row.permission_code
  permForm.permission_type = row.permission_type
  permForm.path = row.path || ""
  permForm.icon = row.icon || ""
  permForm.sort_order = row.sort_order
  permForm.status = row.status
  dialogVisible.value = true
}

/**
 * 提交新增/编辑
 */
async function handleSubmitPerm(): Promise<void> {
  if (!formRef.value) return
  await formRef.value.validate()

  dialogLoading.value = true
  try {
    const params: Record<string, unknown> = {
      permission_name: permForm.permission_name,
      permission_code: permForm.permission_code,
      permission_type: permForm.permission_type,
      path: permForm.path || undefined,
      icon: permForm.icon || undefined,
      sort_order: permForm.sort_order,
      status: permForm.status
    }

    if (isEdit.value && permForm.permission_id) {
      params.permission_id = permForm.permission_id
    }
    if (permForm.parent_id) {
      params.parent_id = permForm.parent_id
    }

    await savePermission(params)
    ElMessage.success(isEdit.value ? "编辑成功" : "新增成功")
    dialogVisible.value = false
    loadTree()
  } catch {
    ElMessage.error("操作失败")
  } finally {
    dialogLoading.value = false
  }
}

/** ==================== 删除 ==================== */
const deleteVisible = ref<boolean>(false)
const deleteTarget = ref<PermissionNode | null>(null)

/** 删除提示信息 */
const deleteMessage = computed<string>(() => {
  const name = deleteTarget.value?.permission_name || ""
  const hasChildren = (deleteTarget.value?.children?.length ?? 0) > 0
  return hasChildren
    ? `权限「${name}」下有子权限，删除后子权限也将一并删除，确定继续吗？`
    : `确定要删除权限「${name}」吗？`
})

/**
 * 打开删除确认
 */
function handleDelete(row: PermissionNode): void {
  deleteTarget.value = row
  deleteVisible.value = true
}

/**
 * 确认删除
 */
async function confirmDelete(): Promise<void> {
  if (!deleteTarget.value) return
  try {
    await deletePermission(deleteTarget.value.permission_id)
    ElMessage.success("删除成功")
    loadTree()
  } catch {
    ElMessage.error("删除失败")
  }
}

onMounted(() => {
  loadTree()
})
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

.perm-name-cell {
  display: flex;
  align-items: center;
  gap: 6px;
}

.perm-icon {
  font-size: 16px;
  color: var(--color-neutral-500);
}

.perm-name {
  font-weight: 500;
  color: var(--color-neutral-800);
}

.code-text {
  font-family: var(--font-family-mono);
  font-size: 12px;
  color: var(--color-neutral-500);
  background: var(--color-neutral-50);
  padding: 2px 6px;
  border-radius: 3px;
}

.path-text {
  font-family: var(--font-family-mono);
  font-size: 13px;
  color: var(--color-neutral-600);
}

:deep(.row-disabled) {
  opacity: 0.5;
}
</style>
