<template>
  <div class="dashboard-page">
    <h2 class="page-title">工作台</h2>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="dashboard-page__stats">
      <el-col :span="6" v-for="item in statCards" :key="item.label">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-card__label">{{ item.label }}</div>
          <div class="stat-card__value" :style="{ color: item.color }">
            {{ item.value }}
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 待办区域 -->
    <el-row :gutter="16" class="dashboard-page__section">
      <el-col :span="12">
        <el-card>
          <template #header>
            <span class="card-title">待处理订单</span>
          </template>
          <el-empty v-if="!pendingOrders.length" description="暂无待处理订单" />
          <el-table v-else :data="pendingOrders" stripe size="small">
            <el-table-column prop="order_no" label="订单号" />
            <el-table-column prop="store_name" label="门店" />
            <el-table-column prop="order_status_text" label="状态" />
            <el-table-column prop="created_at" label="创建时间" />
          </el-table>
        </el-card>
      </el-col>

      <el-col :span="12">
        <el-card>
          <template #header>
            <span class="card-title">待审核明细</span>
          </template>
          <el-empty description="暂无待审核明细" />
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue"
import { getDashboardStats } from "@/api/dashboard"

interface StatCard {
  label: string
  value: string | number
  color: string
}

const statCards = ref<StatCard[]>([
  { label: "总订单数", value: 0, color: "var(--color-primary-500)" },
  { label: "待支付订单", value: 0, color: "var(--color-error)" },
  { label: "生产中", value: 0, color: "var(--color-warning)" },
  { label: "待发货", value: 0, color: "var(--color-info)" }
])

interface PendingOrder {
  order_no: string
  store_name: string
  order_status_text: string
  created_at: string
}

const pendingOrders = ref<PendingOrder[]>([])

onMounted(async () => {
  try {
    const data = await getDashboardStats()
    statCards.value[0].value = data.total_orders
    statCards.value[1].value = data.pending_payment
    statCards.value[2].value = data.in_production
    statCards.value[3].value = data.pending_ship
  } catch (e) {
    console.error("获取统计数据失败", e)
  }
})
</script>

<style scoped>
.dashboard-page {
  max-width: 1400px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-neutral-800);
  margin: 0 0 24px;
}

.dashboard-page__stats {
  margin-bottom: 24px;
}

.stat-card {
  text-align: center;
}

.stat-card__label {
  font-size: 13px;
  color: var(--color-neutral-500);
  margin-bottom: 8px;
}

.stat-card__value {
  font-size: 28px;
  font-weight: 700;
  font-family: var(--font-family-mono);
}

.dashboard-page__section {
  margin-bottom: 24px;
}

.card-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-neutral-700);
}
</style>
