<template>
  <div class="search-form">
    <el-form :model="modelValue" inline @submit.prevent="handleSearch">
      <slot />
      <el-form-item>
        <el-button type="primary" @click="handleSearch">
          <el-icon><Search /></el-icon>
          搜索
        </el-button>
        <el-button @click="handleReset">
          <el-icon><Refresh /></el-icon>
          重置
        </el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  modelValue: Record<string, unknown>
}>()

const emit = defineEmits<{
  (e: "search"): void
  (e: "reset"): void
}>()

function handleSearch(): void {
  emit("search")
}

function handleReset(): void {
  emit("reset")
}
</script>

<style scoped>
.search-form {
  padding: 16px 24px;
  background: #fff;
  border-radius: var(--radius-md);
  margin-bottom: 16px;
}
</style>
