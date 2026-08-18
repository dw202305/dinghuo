<template>
  <div class="role-list-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">角色管理</h2>
      <el-button type="primary" :icon="Plus" @click="openCreateDialog">新增角色</el-button>
    </div>

    <!-- 表格 -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <div class="table-toolbar__left"></div>
        <div class="table-toolbar__right">
          <span class="total-text">共 {{ total }} 条记录</span>
        </div>
      </div>

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="role_id" label="角色ID" width="90" align="center" />
        <el-table-column prop="role_name" label="角色名称" width="160" />
        <el-table-column prop="role_code" label="角色编码" width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <code class="code-text">{{ row.role_code }}</code>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.description || "-" }}
          </template>
        </el-table-column>
        <el-table-column prop="admin_count" label="管理员数" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ row.admin_count || 0 }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small" effect="light">
              {{ row.status === 1 ? "正常" : "停用" }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="openEditDialogAction(row)">编辑</el-button>
            <el-button type="success" link size="small" @click="openPermDialogAction(row)">分配权限</el-button>
            <el-button type="danger" link size="small" @click="handleDeleteRoleAction(row)">删除</el-button>
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

    <!-- 新增/编辑角色弹窗 -->
    <el-dialog
      v-model="roleDialogVisible"
      :title="isRoleEdit ? '编辑角色' : '新增角色'"
      width="500px"
      destroy-on-close
    >
      <el-form
        ref="roleFormRef"
        :model="roleForm"
        :rules="roleFormRules"
        label-width="80px"
      >
        <el-form-item label="角色名称" prop="role_name">
          <el-input v-model="roleForm.role_name" placeholder="请输入角色名称" maxlength="30" />
        </el-form-item>
        <el-form-item label="角色编码" prop="role_code">
          <el-input
            v-model="roleForm.role_code"
            placeholder="如：order_manager"
            :disabled="isRoleEdit"
            maxlength="50"
          />
        </el-form-item>
        <el-form-item label="描述">
          <el-input
            v-model="roleForm.description"
            type="textarea"
            :rows="3"
            placeholder="请输入角色描述"
            maxlength="200"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="roleForm.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">停用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="roleDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="roleDialogLoading" @click="handleSubmitRole">确定</el-button>
      </template>
    </el-dialog>

    <!-- 分配权限弹窗（重点：树形权限选择器） -->
    <el-dialog
      v-model="permDialogVisible"
      title="分配权限"
      width="600px"
      destroy-on-close
    >
      <div class="perm-dialog-header">
        <span class="perm-role-name">{{ permRoleName }}</span>
        <div class="perm-actions">
          <el-button size="small" @click="handleCheckAll">全选</el-button>
          <el-button size="small" @click="handleUncheckAll">全不选</el-button>
          <el-button size="small" @click="handleInvertCheck">反选</el-button>
        </div>
      </div>

      <div class="perm-tree-wrapper" v-loading="permTreeLoading">
        <el-tree
          ref="treeRef"
          :data="permTreeData"
          :props="treeProps"
          show-checkbox
          node-key="permission_id"
          :default-checked-keys="checkedPermIds"
          check-strictly
          :expand-on-click-node="false"
          default-expand-all
          class="perm-tree"
          @check="handleTreeCheck"
        >
          <template #default="{ node, data }">
            <div class="perm-tree-node">
              <el-tag
                :type="data.permission_type === 1 ? 'primary' : data.permission_type === 2 ? 'success' : 'info'"
                size="small"
                effect="light"
                class="perm-type-tag"
              >
                {{ data.permission_type === 1 ? "菜单" : data.permission_type === 2 ? "按钮" : "接口" }}
              </el-tag>
              <span class="perm-node-label">{{ node.label }}</span>
              <code v-if="data.permission_code" class="perm-node-code">{{ data.permission_code }}</code>
            </div>
          </template>
        </el-tree>
        <el-empty v-if="!permTreeLoading && permTreeData.length === 0" description="暂无权限数据" />
      </div>

      <template #footer>
        <div class="perm-footer">
          <span class="selected-count">已选 {{ checkedPermIds.length }} 项</span>
          <div>
            <el-button @click="permDialogVisible = false">取消</el-button>
            <el-button type="primary" :loading="permDialogLoading" @click="handleSubmitPerm">保存权限</el-button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- 删除确认弹窗 -->
    <ConfirmDialog
      v-model:visible="deleteVisible"
      title="删除角色"
      :message="`确定要删除角色「${deleteTarget?.role_name || ''}」吗？该角色下 ${deleteTarget?.admin_count || 0} 个管理员将失去该角色权限。`"
      type="danger"
      @confirm="confirmDeleteRole"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from "vue"
import { Plus } from "@element-plus/icons-vue"
import { ElMessage, type FormInstance, type FormRules } from "element-plus"
import type { DefaultRow } from "@/types/element-plus"
import type { ElTree } from "element-plus"
import { getRoleList, saveRole, deleteRole, getPermissionTree } from "@/api/system"
import type { RoleInfo, PermissionNode, RoleSaveParams } from "@/types/admin"
import { useTable } from "@/composables/useTable"
import TablePagination from "@/components/TablePagination.vue"
import ConfirmDialog from "@/components/ConfirmDialog.vue"

const {
  loading,
  tableData,
  total,
  queryParams,
  loadData,
  handlePageChange,
  handleSizeChange
} = useTable({
  fetchApi: getRoleList,
  defaultParams: {}
})

/** ==================== 角色新增/编辑 ==================== */
const roleDialogVisible = ref<boolean>(false)
const roleDialogLoading = ref<boolean>(false)
const isRoleEdit = ref<boolean>(false)
const editRoleId = ref<number>(0)
const roleFormRef = ref<FormInstance>()

const roleForm = reactive<{
  role_name: string
  role_code: string
  description: string
  status: 0 | 1
}>({
  role_name: "",
  role_code: "",
  description: "",
  status: 1
})

const roleFormRules: FormRules = {
  role_name: [
    { required: true, message: "请输入角色名称", trigger: "blur" },
    { min: 2, max: 30, message: "长度在 2 到 30 个字符", trigger: "blur" }
  ],
  role_code: [
    { required: true, message: "请输入角色编码", trigger: "blur" },
    { pattern: /^[a-z][a-z0-9_]*$/, message: "编码需为小写字母开头，仅含小写字母、数字和下划线", trigger: "blur" }
  ]
}

/**
 * 打开新增角色弹窗
 */
function openCreateDialog(): void {
  isRoleEdit.value = false
  editRoleId.value = 0
  roleForm.role_name = ""
  roleForm.role_code = ""
  roleForm.description = ""
  roleForm.status = 1
  roleDialogVisible.value = true
}

/** 操作列按钮适配（DefaultRow 为 el-table 插槽的通用行类型，行数据由 fetchApi 类型约束为 RoleInfo） */
function openEditDialogAction(row: DefaultRow): void {
  openEditDialog(row as RoleInfo)
}

function openPermDialogAction(row: DefaultRow): void {
  void openPermDialog(row as RoleInfo)
}

function handleDeleteRoleAction(row: DefaultRow): void {
  handleDeleteRole(row as RoleInfo)
}

/**
 * 打开编辑角色弹窗
 */
function openEditDialog(row: RoleInfo): void {
  isRoleEdit.value = true
  editRoleId.value = row.role_id
  roleForm.role_name = row.role_name
  roleForm.role_code = row.role_code
  roleForm.description = row.description || ""
  roleForm.status = row.status
  roleDialogVisible.value = true
}

/**
 * 提交角色新增/编辑
 */
async function handleSubmitRole(): Promise<void> {
  if (!roleFormRef.value) return
  await roleFormRef.value.validate()

  roleDialogLoading.value = true
  try {
    const params: RoleSaveParams = {
      role_name: roleForm.role_name,
      role_code: roleForm.role_code,
      description: roleForm.description,
      status: roleForm.status,
      permission_ids: []
    }
    if (isRoleEdit.value) {
      params.role_id = editRoleId.value
    }
    await saveRole(params)
    ElMessage.success(isRoleEdit.value ? "编辑成功" : "新增成功")
    roleDialogVisible.value = false
    loadData()
  } catch {
    ElMessage.error(isRoleEdit.value ? "编辑失败" : "新增失败")
  } finally {
    roleDialogLoading.value = false
  }
}

/** ==================== 权限分配（树形选择器） ==================== */
const permDialogVisible = ref<boolean>(false)
const permDialogLoading = ref<boolean>(false)
const permTreeLoading = ref<boolean>(false)
const permRoleName = ref<string>("")
const permRoleId = ref<number>(0)
const permTreeData = ref<PermissionNode[]>([])
const checkedPermIds = ref<number[]>([])
const treeRef = ref<InstanceType<typeof ElTree>>()

/** 树形配置 */
const treeProps = {
  children: "children",
  label: "permission_name"
}

/**
 * 打开权限分配弹窗
 */
async function openPermDialog(row: RoleInfo): Promise<void> {
  permRoleId.value = row.role_id
  permRoleName.value = row.role_name
  checkedPermIds.value = row.permission_ids || []
  permDialogVisible.value = true

  // 加载权限树
  permTreeLoading.value = true
  try {
    const res = await getPermissionTree()
    permTreeData.value = res.tree || []
  } catch {
    ElMessage.error("加载权限树失败")
  } finally {
    permTreeLoading.value = false
  }
}

/**
 * 树节点勾选变化
 */
function handleTreeCheck(
  _data: PermissionNode,
  checkInfo: { checkedKeys: (string | number)[]; checkedNodes: unknown[] }
): void {
  checkedPermIds.value = checkInfo.checkedKeys.map((key) => Number(key))
}

/** 全选 */
function handleCheckAll(): void {
  const allKeys = flattenTreeKeys(permTreeData.value)
  checkedPermIds.value = allKeys
  treeRef.value?.setCheckedKeys(allKeys)
}

/** 全不选 */
function handleUncheckAll(): void {
  checkedPermIds.value = []
  treeRef.value?.setCheckedKeys([])
}

/** 反选 */
function handleInvertCheck(): void {
  const allKeys = flattenTreeKeys(permTreeData.value)
  const currentSet = new Set(checkedPermIds.value)
  const inverted = allKeys.filter((k) => !currentSet.has(k))
  checkedPermIds.value = inverted
  treeRef.value?.setCheckedKeys(inverted)
}

/**
 * 递归获取树中所有 key
 */
function flattenTreeKeys(nodes: PermissionNode[]): number[] {
  const keys: number[] = []
  for (const node of nodes) {
    keys.push(node.permission_id)
    if (node.children?.length) {
      keys.push(...flattenTreeKeys(node.children))
    }
  }
  return keys
}

/**
 * 提交权限分配
 */
async function handleSubmitPerm(): Promise<void> {
  permDialogLoading.value = true
  try {
    await saveRole({
      role_id: permRoleId.value,
      role_name: permRoleName.value,
      role_code: "",
      permission_ids: checkedPermIds.value
    })
    ElMessage.success("权限分配成功")
    permDialogVisible.value = false
    loadData()
  } catch {
    ElMessage.error("权限分配失败")
  } finally {
    permDialogLoading.value = false
  }
}

/** ==================== 删除角色 ==================== */
const deleteVisible = ref<boolean>(false)
const deleteTarget = ref<RoleInfo | null>(null)

/**
 * 打开删除确认
 */
function handleDeleteRole(row: RoleInfo): void {
  deleteTarget.value = row
  deleteVisible.value = true
}

/**
 * 确认删除
 */
async function confirmDeleteRole(): Promise<void> {
  if (!deleteTarget.value) return
  try {
    await deleteRole(deleteTarget.value.role_id)
    ElMessage.success("删除成功")
    loadData()
  } catch {
    ElMessage.error("删除失败")
  }
}

onMounted(() => {
  // 预加载完成
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

.code-text {
  font-family: var(--font-family-mono);
  font-size: 12px;
  color: var(--color-neutral-500);
  background: var(--color-neutral-50);
  padding: 2px 6px;
  border-radius: 3px;
}

/* 权限弹窗 */
.perm-dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--color-neutral-100);
}

.perm-role-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-neutral-800);
}

.perm-actions {
  display: flex;
  gap: 8px;
}

.perm-tree-wrapper {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid var(--color-neutral-100);
  border-radius: var(--radius-md);
  padding: 12px;
}

.perm-tree {
  width: 100%;
}

.perm-tree-node {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.perm-type-tag {
  flex-shrink: 0;
}

.perm-node-label {
  font-size: 14px;
  color: var(--color-neutral-800);
}

.perm-node-code {
  font-family: var(--font-family-mono);
  font-size: 11px;
  color: var(--color-neutral-400);
  background: var(--color-neutral-50);
  padding: 1px 5px;
  border-radius: 3px;
}

.perm-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.selected-count {
  font-size: 13px;
  color: var(--color-neutral-500);
}
</style>
