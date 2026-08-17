<template>
  <header class="header">
    <div class="header__left">
      <el-icon class="header__collapse-btn" @click="appStore.toggleSidebar">
        <Fold v-if="!appStore.sidebarCollapsed" />
        <Expand v-else />
      </el-icon>
      <Breadcrumb />
    </div>

    <div class="header__right">
      <el-dropdown trigger="click" @command="handleCommand">
        <span class="header__user">
          <el-avatar :size="28" :src="authStore.avatar || undefined">
            {{ authStore.realName?.charAt(0) || "A" }}
          </el-avatar>
          <span class="header__user-name">{{ authStore.realName || "管理员" }}</span>
          <el-icon><ArrowDown /></el-icon>
        </span>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="profile">个人信息</el-dropdown-item>
            <el-dropdown-item divided command="logout">退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </header>
</template>

<script setup lang="ts">
import { useAppStore } from "@/stores/app"
import { useAuthStore } from "@/stores/auth"
import Breadcrumb from "@/components/Breadcrumb.vue"

const appStore = useAppStore()
const authStore = useAuthStore()

/**
 * 处理下拉菜单命令
 */
function handleCommand(command: string): void {
  if (command === "logout") {
    authStore.logout()
  }
}
</script>

<style scoped>
.header {
  height: var(--header-height);
  background-color: #fff;
  border-bottom: 1px solid var(--color-neutral-200);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  flex-shrink: 0;
}

.header__left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header__collapse-btn {
  font-size: 20px;
  cursor: pointer;
  color: var(--color-neutral-600);
  transition: color 0.2s;
}

.header__collapse-btn:hover {
  color: var(--color-primary-500);
}

.header__right {
  display: flex;
  align-items: center;
}

.header__user {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  color: var(--color-neutral-700);
  font-size: 14px;
}

.header__user:hover {
  color: var(--color-primary-500);
}

.header__user-name {
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
