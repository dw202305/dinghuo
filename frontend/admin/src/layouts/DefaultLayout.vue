<template>
  <div class="layout">
    <Sidebar />
    <div class="layout__main" :class="{ 'layout__main--collapsed': appStore.sidebarCollapsed }">
      <Header />
      <main class="layout__content">
        <router-view v-slot="{ Component, route: viewRoute }">
          <transition name="fade-transform" mode="out-in">
            <!-- key 取完整路由：确保菜单切换/同组件参数变化时重建页面实例，不残留上一页 -->
            <component :is="Component" :key="viewRoute.fullPath" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from "vue"
import { useAppStore } from "@/stores/app"
import { useAuthStore } from "@/stores/auth"
import Sidebar from "./Sidebar.vue"
import Header from "./Header.vue"

const appStore = useAppStore()
const authStore = useAuthStore()

onMounted(() => {
  authStore.restoreFromStorage()
})
</script>

<style scoped>
.layout {
  display: flex;
  height: 100vh;
  width: 100%;
  overflow: hidden;
}

.layout__main {
  flex: 1;
  display: flex;
  flex-direction: column;
  margin-left: var(--sidebar-width);
  transition: margin-left 0.3s ease;
  overflow: hidden;
}

.layout__main--collapsed {
  margin-left: var(--sidebar-collapsed-width);
}

.layout__content {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
  background-color: var(--color-neutral-50);
}

/* 路由切换过渡动画 */
.fade-transform-enter-active,
.fade-transform-leave-active {
  transition: all 0.2s ease;
}

.fade-transform-enter-from {
  opacity: 0;
  transform: translateX(-10px);
}

.fade-transform-leave-to {
  opacity: 0;
  transform: translateX(10px);
}
</style>
