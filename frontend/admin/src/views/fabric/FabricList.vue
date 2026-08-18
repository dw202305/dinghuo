<template>
  <div class="fabric-list-page">
    <!-- 页面标题 + 操作按钮 -->
    <div class="page-header">
      <h2 class="page-title">面料列表</h2>
      <div class="page-header__actions">
        <el-button type="primary" @click="$router.push('/fabric/form')">
          <el-icon><Plus /></el-icon>
          新增面料
        </el-button>
        <el-button @click="$router.push('/fabric/import')">批量导入</el-button>
        <el-button :disabled="selectedIds.length === 0" @click="handleBatchToggle(1)">批量上架</el-button>
        <el-button :disabled="selectedIds.length === 0" @click="handleBatchToggle(0)">批量下架</el-button>
      </div>
    </div>

    <!-- 搜索区 -->
    <SearchForm :model-value="queryParams as Record<string, unknown>" @search="handleSearch" @reset="handleReset">
      <el-form-item label="面料编号">
        <el-input
          v-model="queryParams.keyword"
          placeholder="编号/名称关键词"
          clearable
          style="width: 180px"
        />
      </el-form-item>
      <el-form-item label="分类">
        <el-select v-model="queryParams.category" placeholder="全部分类" clearable style="width: 140px">
          <el-option label="窗帘布" value="curtain" />
          <el-option label="纱帘" value="sheer" />
          <el-option label="遮光布" value="blackout" />
          <el-option label="成品帘" value="finished" />
        </el-select>
      </el-form-item>
      <el-form-item label="系列">
        <el-input v-model="queryParams.series" placeholder="系列名称" clearable style="width: 140px" />
      </el-form-item>
      <el-form-item label="上架状态">
        <el-select v-model="queryParams.listing_status" placeholder="全部" clearable style="width: 120px">
          <el-option label="已上架" :value="1" />
          <el-option label="已下架" :value="0" />
        </el-select>
      </el-form-item>
    </SearchForm>

    <!-- 表格 -->
    <el-card shadow="never">
      <div class="table-toolbar">
        <span class="total-text">共 {{ total }} 条记录</span>
      </div>

      <el-table
        :data="tableData"
        v-loading="loading"
        stripe
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="45" align="center" />
        <el-table-column label="图片" width="80" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.main_image"
              :src="row.main_image"
              :preview-src-list="[row.main_image]"
              fit="cover"
              class="fabric-thumb"
              preview-teleported
            />
            <div v-else class="fabric-thumb fabric-thumb--empty">
              <el-icon :size="20" color="var(--color-neutral-300)"><Picture /></el-icon>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="编号/名称" min-width="180">
          <template #default="{ row }">
            <div class="cell-fabric">
              <span class="cell-fabric__no">{{ row.fabric_no }}</span>
              <span class="cell-fabric__name">{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="series" label="系列" width="120" show-overflow-tooltip />
        <el-table-column prop="material" label="材质" width="100" show-overflow-tooltip />
        <el-table-column prop="color_name" label="颜色" width="90" />
        <el-table-column prop="price_per_sqm" label="单价(元/㎡)" width="120" align="right">
          <template #default="{ row }">
            <span class="price-text">¥{{ formatMoney(row.price_per_sqm) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="库存状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="stockStatusType(row.stock_status)" size="small" effect="light">
              {{ stockStatusText(row.stock_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="listing_status" label="上架状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="row.listing_status === 1"
              @change="(val: string | number | boolean) => handleToggleStatus(row as FabricListItem & { _toggling?: boolean }, val === true)"
              :loading="row._toggling"
              inline-prompt
              active-text="上"
              inactive-text="下"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="$router.push(`/fabric/form/${row.id}`)">
              编辑
            </el-button>
            <el-button type="warning" link size="small" @click="handleToggleStatus(row as FabricListItem & { _toggling?: boolean }, row.listing_status !== 1)">
              {{ row.listing_status ? "下架" : "上架" }}
            </el-button>
            <el-button type="danger" link size="small" @click="handleDelete(row as FabricListItem)">删除</el-button>
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

    <!-- 删除确认弹窗 -->
    <ConfirmDialog
      v-model:visible="showDeleteDialog"
      title="删除面料"
      :message="`确定要删除面料「${deleteTarget?.name || ''}」吗？删除后不可恢复。`"
      type="danger"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue"
import { ElMessage } from "element-plus"
import { Plus, Picture } from "@element-plus/icons-vue"
import { getFabricList, toggleFabricStatus, deleteFabric } from "@/api/fabric"
import type { FabricListItem } from "@/types/fabric"
import { StockStatus } from "@/types/common"
import { formatMoney } from "@/utils/format"
import { useTable } from "@/composables/useTable"
import SearchForm from "@/components/SearchForm.vue"
import TablePagination from "@/components/TablePagination.vue"
import ConfirmDialog from "@/components/ConfirmDialog.vue"

/** 多选 */
const selectedIds = ref<number[]>([])

function handleSelectionChange(rows: FabricListItem[]): void {
  selectedIds.value = rows.map((r) => r.id)
}

/** 库存状态 */
function stockStatusType(status: number): "success" | "warning" | "danger" {
  if (status === StockStatus.SUFFICIENT) return "success"
  if (status === StockStatus.TIGHT) return "warning"
  return "danger"
}

function stockStatusText(status: number): string {
  if (status === StockStatus.SUFFICIENT) return "充足"
  if (status === StockStatus.TIGHT) return "紧张"
  return "缺货"
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
  fetchApi: getFabricList,
  defaultParams: {
    keyword: undefined,
    series: undefined,
    listing_status: undefined,
    category: undefined
  }
})

/** 上架/下架 */
async function handleToggleStatus(row: FabricListItem & { _toggling?: boolean }, val: boolean): Promise<void> {
  row._toggling = true
  try {
    await toggleFabricStatus(row.id, val ? 1 : 0)
    row.listing_status = val ? 1 : 0
    ElMessage.success(val ? "已上架" : "已下架")
  } catch {
    ElMessage.error("操作失败")
  } finally {
    row._toggling = false
  }
}

/** 批量上下架 */
async function handleBatchToggle(status: 0 | 1): Promise<void> {
  if (selectedIds.value.length === 0) return
  try {
    await Promise.all(selectedIds.value.map((id) => toggleFabricStatus(id, status)))
    ElMessage.success(`批量${status === 1 ? "上架" : "下架"}成功`)
    await handleSearch()
  } catch {
    ElMessage.error("批量操作失败")
  }
}

/** 删除 */
const showDeleteDialog = ref<boolean>(false)
const deleteTarget = ref<FabricListItem | null>(null)

function handleDelete(row: FabricListItem): void {
  deleteTarget.value = row
  showDeleteDialog.value = true
}

async function confirmDelete(): Promise<void> {
  if (!deleteTarget.value) return
  try {
    await deleteFabric(deleteTarget.value.id)
    ElMessage.success("删除成功")
    await handleSearch()
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

.fabric-thumb {
  width: 50px;
  height: 50px;
  border-radius: var(--radius-sm);
  object-fit: cover;
}

.fabric-thumb--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-neutral-50);
  border: 1px solid var(--color-neutral-100);
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

.price-text {
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  color: var(--color-neutral-700);
}
</style>
