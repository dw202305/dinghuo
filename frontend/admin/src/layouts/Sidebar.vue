<template>
  <aside class="sidebar" :class="{ 'sidebar--collapsed': appStore.sidebarCollapsed }">
    <div class="sidebar__logo">
      <span v-if="!appStore.sidebarCollapsed" class="sidebar__logo-text">世尚后台</span>
      <span v-else class="sidebar__logo-text--mini">SS</span>
    </div>

    <el-scrollbar>
      <el-menu
        :default-active="activeMenu"
        :collapse="appStore.sidebarCollapsed"
        :collapse-transition="false"
        router
        background-color="#38415f"
        text-color="#c9d1e3"
        active-text-color="#ffffff"
      >
        <!-- 工作台 -->
        <el-menu-item index="/dashboard">
          <el-icon><Odometer /></el-icon>
          <template #title>工作台</template>
        </el-menu-item>

        <!-- 订单管理 -->
        <el-sub-menu index="/order">
          <template #title>
            <el-icon><Document /></el-icon>
            <span>订单管理</span>
          </template>
          <el-menu-item index="/order/list">订单列表</el-menu-item>
        </el-sub-menu>

        <!-- 技术审核 -->
        <el-menu-item index="/audit">
          <el-icon><Check /></el-icon>
          <template #title>技术审核</template>
        </el-menu-item>

        <!-- 面料管理 -->
        <el-sub-menu index="/fabric">
          <template #title>
            <el-icon><Goods /></el-icon>
            <span>面料管理</span>
          </template>
          <el-menu-item index="/fabric/list">面料列表</el-menu-item>
          <el-menu-item index="/fabric/import">批量导入</el-menu-item>
        </el-sub-menu>

        <!-- 库存管理 -->
        <el-menu-item index="/stock/list">
          <el-icon><Box /></el-icon>
          <template #title>库存管理</template>
        </el-menu-item>

        <!-- 客户管理 -->
        <el-sub-menu index="/customer">
          <template #title>
            <el-icon><User /></el-icon>
            <span>客户管理</span>
          </template>
          <el-menu-item index="/customer/list">客户列表</el-menu-item>
          <el-menu-item index="/customer/level">等级管理</el-menu-item>
        </el-sub-menu>

        <!-- 生产单管理 -->
        <el-menu-item index="/production/list">
          <el-icon><SetUp /></el-icon>
          <template #title>生产单管理</template>
        </el-menu-item>

        <!-- 财务管理 -->
        <el-menu-item index="/finance/list">
          <el-icon><Money /></el-icon>
          <template #title>财务管理</template>
        </el-menu-item>

        <!-- 售后管理 -->
        <el-menu-item index="/after-sale/list">
          <el-icon><Service /></el-icon>
          <template #title>售后管理</template>
        </el-menu-item>

        <!-- 发票管理 -->
        <el-menu-item index="/invoice">
          <el-icon><Tickets /></el-icon>
          <template #title>发票管理</template>
        </el-menu-item>

        <!-- 系统管理 -->
        <el-sub-menu index="/system">
          <template #title>
            <el-icon><Setting /></el-icon>
            <span>系统管理</span>
          </template>
          <el-menu-item index="/system/admin">管理员管理</el-menu-item>
          <el-menu-item index="/system/role">角色管理</el-menu-item>
          <el-menu-item index="/system/permission">权限管理</el-menu-item>
        </el-sub-menu>
      </el-menu>
    </el-scrollbar>
  </aside>
</template>

<script setup lang="ts">
import { computed } from "vue"
import { useRoute } from "vue-router"
import { useAppStore } from "@/stores/app"

const route = useRoute()
const appStore = useAppStore()

const activeMenu = computed<string>(() => {
  return route.path
})
</script>

<style scoped>
.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  width: var(--sidebar-width);
  background-color: var(--color-primary-700);
  transition: width 0.3s ease;
  z-index: 1001;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.sidebar--collapsed {
  width: var(--sidebar-collapsed-width);
}

.sidebar__logo {
  height: var(--header-height);
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.sidebar__logo-text {
  color: #fff;
  font-size: 18px;
  font-weight: 700;
  letter-spacing: 2px;
}

.sidebar__logo-text--mini {
  color: #fff;
  font-size: 20px;
  font-weight: 700;
}

:deep(.el-menu) {
  border-right: none;
}

:deep(.el-menu-item.is-active) {
  background-color: var(--color-primary-500) !important;
}

:deep(.el-sub-menu__title:hover),
:deep(.el-menu-item:hover) {
  background-color: rgba(255, 255, 255, 0.06) !important;
}
</style>
