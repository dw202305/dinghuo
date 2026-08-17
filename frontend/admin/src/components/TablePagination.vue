<template>
  <div class="table-pagination">
    <el-pagination
      v-model:current-page="currentPage"
      v-model:page-size="currentPageSize"
      :page-sizes="[10, 20, 50, 100]"
      :total="total"
      layout="total, sizes, prev, pager, next, jumper"
      background
      @size-change="handleSizeChange"
      @current-change="handlePageChange"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue"

const props = defineProps<{
  page: number
  pageSize: number
  total: number
}>()

const emit = defineEmits<{
  (e: "update:page", value: number): void
  (e: "update:pageSize", value: number): void
  (e: "page-change", value: number): void
  (e: "size-change", value: number): void
}>()

const currentPage = computed({
  get: () => props.page,
  set: (val: number) => emit("update:page", val)
})

const currentPageSize = computed({
  get: () => props.pageSize,
  set: (val: number) => emit("update:pageSize", val)
})

function handlePageChange(page: number): void {
  emit("page-change", page)
}

function handleSizeChange(size: number): void {
  emit("size-change", size)
}
</script>

<style scoped>
.table-pagination {
  display: flex;
  justify-content: flex-end;
  padding: 16px 0;
}
</style>
