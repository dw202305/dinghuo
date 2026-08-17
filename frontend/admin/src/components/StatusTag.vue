<template>
  <el-tag :type="tagType" :size="size" effect="light" round>
    {{ label }}
  </el-tag>
</template>

<script setup lang="ts">
import { computed } from "vue"

const props = withDefaults(defineProps<{
  status: number | string
  label: string
  size?: "large" | "default" | "small"
  typeMap?: Record<string, "success" | "warning" | "danger" | "info" | "primary">
}>(), {
  size: "default",
  typeMap: () => ({})
})

const tagType = computed<"success" | "warning" | "danger" | "info" | "primary">(() => {
  const key = String(props.status)
  return props.typeMap[key] || "info"
})
</script>
