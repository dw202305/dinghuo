<template>
  <el-breadcrumb separator="/">
    <el-breadcrumb-item
      v-for="item in breadcrumbs"
      :key="item.path"
      :to="item.path"
    >
      {{ item.title }}
    </el-breadcrumb-item>
  </el-breadcrumb>
</template>

<script setup lang="ts">
import { computed } from "vue"
import { useRoute } from "vue-router"
import type { RouteLocationMatched } from "vue-router"

interface BreadcrumbItem {
  path: string
  title: string
}

const route = useRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
  const matched = route.matched.filter(
    (item: RouteLocationMatched) => item.meta?.title
  )
  return matched.map((item) => ({
    path: item.path,
    title: (item.meta.title as string) || ""
  }))
})
</script>
