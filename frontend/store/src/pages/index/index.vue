<template>
  <view class="home-page">
    <!-- 顶部导航栏 -->
    <view class="navbar">
      <view class="navbar__left">
        <text class="navbar__title">工作台</text>
      </view>
      <view class="navbar__right" @tap="goNotification">
        <text class="navbar__bell">🔔</text>
        <view v-if="hasUnread" class="navbar__badge" />
      </view>
    </view>

    <!-- 下拉刷新提示 -->
    <view v-if="refreshing" class="refresh-tip">
      <text class="refresh-tip__text">刷新中...</text>
    </view>

    <!-- 门店信息卡片 -->
    <view class="store-card card">
      <view class="store-card__header">
        <view class="store-card__info">
          <text class="store-card__name">{{ storeName }}</text>
          <text class="store-card__level">{{ storeLevelText }}</text>
        </view>
        <view v-if="storeCount > 1" class="store-card__switch" @tap="showStoreSwitch = true">
          <text class="store-card__switch-text">切换</text>
          <text class="store-card__switch-arrow">›</text>
        </view>
      </view>
      <view class="store-card__stats">
        <view class="store-card__stat-item">
          <text class="store-card__stat-value">{{ inventory.available }}</text>
          <text class="store-card__stat-label">可用库存</text>
        </view>
        <view class="store-card__stat-divider" />
        <view class="store-card__stat-item">
          <text class="store-card__stat-value">{{ inventory.locked }}</text>
          <text class="store-card__stat-label">已锁定</text>
        </view>
        <view class="store-card__stat-divider" />
        <view class="store-card__stat-item">
          <text class="store-card__stat-value">¥{{ kitPrice }}</text>
          <text class="store-card__stat-label">套件单价</text>
        </view>
      </view>
      <!-- 库存预警 -->
      <view v-if="inventory.available < 10" class="store-card__warning">
        <text class="store-card__warning-text">⚠ 库存不足，可用仅 {{ inventory.available }} 套</text>
      </view>
    </view>

    <!-- 公告横幅 -->
    <view v-if="notices.length > 0" class="notice-banner" @tap="handleNoticeTap(notices[0])">
      <text class="notice-banner__icon">📢</text>
      <text class="notice-banner__text">{{ notices[0]?.message }}</text>
    </view>

    <!-- 待办提醒卡片 -->
    <view class="todo-card card">
      <text class="todo-card__title">待办提醒</text>
      <view v-if="todoList.length > 0" class="todo-card__list">
        <view
          v-for="item in todoList"
          :key="item.label"
          class="todo-item"
          @tap="handleTodoTap(item.link)"
        >
          <view class="todo-item__indicator" :style="{ backgroundColor: item.color }" />
          <text class="todo-item__text">{{ item.message }}</text>
          <view class="todo-item__badge">
            <text class="todo-item__count">{{ item.count }}</text>
          </view>
        </view>
      </view>
      <view v-else class="todo-card__empty">
        <text class="todo-card__empty-text">暂无待办事项，一切顺利 ✨</text>
      </view>
    </view>

    <!-- 快捷入口（4宫格） -->
    <view class="quick-section">
      <text class="quick-section__title">快捷操作</text>
      <view class="quick-grid">
        <view class="quick-grid__item" @tap="goCreateOrder">
          <view class="quick-grid__icon-wrap" style="background-color: #EFF6FF;">
            <text class="quick-grid__icon">＋</text>
          </view>
          <text class="quick-grid__text">新建订单</text>
        </view>
        <view class="quick-grid__item" @tap="goOrderList">
          <view class="quick-grid__icon-wrap" style="background-color: #F0FDF4;">
            <text class="quick-grid__icon">📋</text>
          </view>
          <text class="quick-grid__text">订单列表</text>
        </view>
        <view class="quick-grid__item" @tap="goStock">
          <view class="quick-grid__icon-wrap" style="background-color: #FFFBEB;">
            <text class="quick-grid__icon">📦</text>
          </view>
          <text class="quick-grid__text">库存查询</text>
        </view>
        <view class="quick-grid__item" @tap="goAfterSale">
          <view class="quick-grid__icon-wrap" style="background-color: #FEF2F2;">
            <text class="quick-grid__icon">🔧</text>
          </view>
          <text class="quick-grid__text">售后申请</text>
        </view>
      </view>
    </view>

    <!-- 最近订单 -->
    <view class="recent-section">
      <view class="recent-section__header">
        <text class="recent-section__title">最近订单</text>
        <text class="recent-section__more" @tap="goOrderList">查看全部 ›</text>
      </view>
      <view v-if="recentOrders.length > 0" class="recent-list">
        <view
          v-for="order in recentOrders"
          :key="order.order_id"
          class="recent-item card"
          @tap="goOrderDetail(order.order_id)"
        >
          <view class="recent-item__top">
            <text class="recent-item__no">{{ order.order_no }}</text>
            <text
              class="recent-item__status"
              :class="getStatusClass(order.order_status)"
            >{{ order.order_status_text }}</text>
          </view>
          <view class="recent-item__bottom">
            <text class="recent-item__info">
              {{ order.project_name || '无项目名' }} · {{ order.item_count }}副
            </text>
            <text class="recent-item__amount">¥{{ formatAmount(order.total_amount) }}</text>
          </view>
        </view>
      </view>
      <view v-else class="recent-section__empty">
        <text class="recent-section__empty-text">暂无订单记录</text>
      </view>
    </view>

    <!-- 底部占位 -->
    <view class="bottom-placeholder" />

    <!-- 门店切换弹窗 -->
    <view v-if="showStoreSwitch" class="store-modal" @tap.self="showStoreSwitch = false">
      <view class="store-modal__content">
        <text class="store-modal__title">切换门店</text>
        <view
          v-for="store in storeList"
          :key="store.store_id"
          class="store-modal__item"
          :class="{ active: store.store_id === currentStoreId }"
          @tap="handleSwitchStore(store.store_id)"
        >
          <text class="store-modal__item-name">{{ store.store_name }}</text>
          <text v-if="store.store_id === currentStoreId" class="store-modal__item-check">✓</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed, onActivated } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { getDashboard, type DashboardData } from '@/api/store';
import { getOrderList } from '@/api/order';
import { formatMoney } from '@/utils/format';
import { fenToYuan } from '@/utils/money';
import type { OrderListItem } from '@/types/order';
import type { StoreBrief } from '@/types/user';
import { OrderStatus } from '@/types/common';

const authStore = useAuthStore();

/** 门店信息 */
const storeName = ref<string>('');
const storeLevelText = ref<string>('');
const kitPrice = ref<string>('0.00');
const inventory = ref<{ available: number; locked: number }>({ available: 0, locked: 0 });

/** 公告 */
const notices = ref<{ type: string; message: string; link: string }[]>([]);

/** 待办 */
interface TodoItem {
  label: string;
  message: string;
  count: number;
  color: string;
  link: string;
}
const todoList = ref<TodoItem[]>([]);

/** 最近订单 */
const recentOrders = ref<OrderListItem[]>([]);

/** 刷新状态 */
const refreshing = ref<boolean>(false);

/** 门店切换 */
const showStoreSwitch = ref<boolean>(false);
const storeList = computed<StoreBrief[]>(() => authStore.stores);
const storeCount = computed<number>(() => authStore.stores.length);
const currentStoreId = computed<number>(() => authStore.currentStoreId);

/** 未读通知 */
const hasUnread = computed<boolean>(() => notices.value.length > 0);

/**
 * 加载工作台数据
 */
async function loadDashboard(): Promise<void> {
  try {
    const data: DashboardData = await getDashboard();
    storeName.value = data.store_info.store_name;
    storeLevelText.value = data.store_info.customer_level_text;
    kitPrice.value = fenToYuan(Math.round(data.store_info.kit_price * 100));
    inventory.value = {
      available: data.inventory.kit_available,
      locked: data.inventory.kit_locked,
    };
    notices.value = data.notices || [];

    // 构建待办列表
    const todos: TodoItem[] = [];
    if (data.order_stats.pending_payment > 0) {
      todos.push({
        label: 'pending_payment',
        message: '笔订单待支付',
        count: data.order_stats.pending_payment,
        color: '#DC2626',
        link: '/pages/order/list?status=pending_payment',
      });
    }
    if (data.order_stats.pending_confirm > 0) {
      todos.push({
        label: 'pending_confirm',
        message: '笔订单待确认',
        count: data.order_stats.pending_confirm,
        color: '#D97706',
        link: '/pages/order/list?status=need_confirm',
      });
    }
    if (data.order_stats.in_production > 0) {
      todos.push({
        label: 'in_production',
        message: '笔订单生产中',
        count: data.order_stats.in_production,
        color: '#2563EB',
        link: '/pages/order/list?status=in_production',
      });
    }
    if (data.order_stats.pending_receive > 0) {
      todos.push({
        label: 'pending_receive',
        message: '笔订单待收货',
        count: data.order_stats.pending_receive,
        color: '#059669',
        link: '/pages/order/list?status=pending_receive',
      });
    }
    if (data.order_stats.after_sale > 0) {
      todos.push({
        label: 'after_sale',
        message: '笔售后处理中',
        count: data.order_stats.after_sale,
        color: '#DC2626',
        link: '/pages/after-sale/apply',
      });
    }
    todoList.value = todos;
  } catch {
    /* silent */
  }
}

/**
 * 加载最近订单
 */
async function loadRecentOrders(): Promise<void> {
  try {
    const data = await getOrderList({ page: 1, page_size: 5 });
    recentOrders.value = data.list;
  } catch {
    /* silent */
  }
}

/**
 * 下拉刷新
 */
async function handlePullRefresh(): Promise<void> {
  refreshing.value = true;
  await Promise.all([loadDashboard(), loadRecentOrders()]);
  refreshing.value = false;
  uni.stopPullDownRefresh();
}

/**
 * 格式化金额
 */
function formatAmount(amount: string): string {
  return formatMoney(amount);
}

/**
 * 获取订单状态样式类
 */
function getStatusClass(status: OrderStatus): string {
  const map: Partial<Record<OrderStatus, string>> = {
    [OrderStatus.PENDING_PAYMENT]: 'status-error',
    [OrderStatus.PAID_PENDING_REVIEW]: 'status-warning',
    [OrderStatus.NEED_STORE_CONFIRM]: 'status-warning',
    [OrderStatus.IN_PRODUCTION]: 'status-info',
    [OrderStatus.SHIPPED]: 'status-success',
    [OrderStatus.COMPLETED]: 'status-muted',
    [OrderStatus.CANCELLED]: 'status-muted',
  };
  return map[status] || 'status-default';
}

/** 页面导航 */
function goCreateOrder(): void {
  uni.navigateTo({ url: '/pages/order/create-step1' });
}
function goOrderList(): void {
  uni.switchTab({ url: '/pages/order/list' });
}
function goStock(): void {
  uni.switchTab({ url: '/pages/stock/index' });
}
function goAfterSale(): void {
  uni.navigateTo({ url: '/pages/after-sale/apply' });
}
function goNotification(): void {
  uni.showToast({ title: '消息通知', icon: 'none' });
}
function goOrderDetail(orderId: number): void {
  uni.navigateTo({ url: `/pages/order/detail?order_id=${orderId}` });
}

function handleTodoTap(link: string): void {
  uni.navigateTo({ url: link });
}

function handleNoticeTap(notice: { link: string }): void {
  if (notice.link) {
    uni.navigateTo({ url: notice.link });
  }
}

async function handleSwitchStore(storeId: number): Promise<void> {
  await authStore.switchStore(storeId);
  showStoreSwitch.value = false;
  loadDashboard();
  loadRecentOrders();
}

// 使用 onActivated 确保每次 tab 切换都刷新
onActivated(() => {
  storeName.value = authStore.currentStore?.store_name ?? '加载中...';
  loadDashboard();
  loadRecentOrders();
});
</script>

<style lang="scss" scoped>
.home-page {
  padding: $spacing-lg;
  padding-bottom: 120rpx;
  min-height: 100vh;
  background-color: $color-neutral-50;
}

// ── 导航栏 ──
.navbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: $spacing-lg;

  &__title {
    font-size: $font-size-2xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
  }
  &__right {
    position: relative;
    padding: $spacing-sm;
  }
  &__bell {
    font-size: 40rpx;
  }
  &__badge {
    position: absolute;
    top: 4rpx;
    right: 4rpx;
    width: 16rpx;
    height: 16rpx;
    border-radius: $radius-full;
    background-color: $color-error;
  }
}

// ── 刷新提示 ──
.refresh-tip {
  text-align: center;
  padding: $spacing-sm;
  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-400;
  }
}

// ── 门店卡片 ──
.store-card {
  margin-bottom: $spacing-md;

  &__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: $spacing-lg;
  }
  &__info {
    display: flex;
    flex-direction: column;
  }
  &__name {
    font-size: $font-size-xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    margin-bottom: $spacing-xs;
  }
  &__level {
    font-size: $font-size-xs;
    color: $color-primary-500;
    background-color: $color-primary-50;
    padding: 4rpx 16rpx;
    border-radius: $radius-sm;
    display: inline-block;
    align-self: flex-start;
  }
  &__switch {
    display: flex;
    align-items: center;
    padding: $spacing-xs $spacing-sm;
    background-color: $color-neutral-100;
    border-radius: $radius-sm;
  }
  &__switch-text {
    font-size: $font-size-xs;
    color: $color-neutral-600;
  }
  &__switch-arrow {
    font-size: $font-size-sm;
    color: $color-neutral-400;
    margin-left: 4rpx;
  }
  &__stats {
    display: flex;
    align-items: center;
  }
  &__stat-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  &__stat-value {
    font-size: $font-size-xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }
  &__stat-label {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    margin-top: 4rpx;
  }
  &__stat-divider {
    width: 2rpx;
    height: 56rpx;
    background-color: $color-neutral-200;
  }
  &__warning {
    margin-top: $spacing-md;
    padding: $spacing-sm $spacing-md;
    background-color: $color-warning-light;
    border-radius: $radius-sm;
  }
  &__warning-text {
    font-size: $font-size-xs;
    color: $color-warning;
  }
}

// ── 公告横幅 ──
.notice-banner {
  display: flex;
  align-items: center;
  padding: $spacing-md $spacing-lg;
  background-color: $color-info-light;
  border-radius: $radius-md;
  margin-bottom: $spacing-md;

  &__icon {
    font-size: 28rpx;
    margin-right: $spacing-sm;
  }
  &__text {
    font-size: $font-size-sm;
    color: $color-info;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

// ── 待办卡片 ──
.todo-card {
  margin-bottom: $spacing-md;

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-700;
    margin-bottom: $spacing-md;
  }
  &__list {
    display: flex;
    flex-direction: column;
  }
  &__empty {
    text-align: center;
    padding: $spacing-xl $spacing-lg;
  }
  &__empty-text {
    font-size: $font-size-sm;
    color: $color-neutral-400;
  }
}

.todo-item {
  display: flex;
  align-items: center;
  padding: $spacing-md 0;
  border-bottom: 2rpx solid $color-neutral-100;

  &:last-child {
    border-bottom: none;
  }

  &__indicator {
    width: 16rpx;
    height: 16rpx;
    border-radius: $radius-full;
    margin-right: $spacing-md;
    flex-shrink: 0;
  }
  &__text {
    flex: 1;
    font-size: $font-size-base;
    color: $color-neutral-700;
  }
  &__badge {
    background-color: $color-error-light;
    padding: 4rpx 16rpx;
    border-radius: $radius-full;
    min-width: 40rpx;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  &__count {
    font-size: $font-size-sm;
    font-weight: $font-weight-semibold;
    color: $color-error;
    font-family: $font-family-mono;
  }
}

// ── 快捷入口 ──
.quick-section {
  margin-bottom: $spacing-md;

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-700;
    margin-bottom: $spacing-md;
  }
}

.quick-grid {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-md;

  &__item {
    width: calc(50% - #{$spacing-md} / 2);
    display: flex;
    align-items: center;
    background-color: $color-neutral-0;
    border-radius: $radius-lg;
    padding: $spacing-lg $spacing-md;
    box-shadow: $shadow-1;
  }
  &__icon-wrap {
    width: 72rpx;
    height: 72rpx;
    border-radius: $radius-md;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: $spacing-md;
    flex-shrink: 0;
  }
  &__icon {
    font-size: 36rpx;
  }
  &__text {
    font-size: $font-size-base;
    font-weight: $font-weight-medium;
    color: $color-neutral-800;
  }
}

// ── 最近订单 ──
.recent-section {
  margin-bottom: $spacing-md;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: $spacing-md;
  }
  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-700;
  }
  &__more {
    font-size: $font-size-sm;
    color: $color-primary-500;
  }
  &__empty {
    text-align: center;
    padding: $spacing-xl;
    background-color: $color-neutral-0;
    border-radius: $radius-lg;
  }
  &__empty-text {
    font-size: $font-size-sm;
    color: $color-neutral-400;
  }
}

.recent-item {
  padding: $spacing-md $spacing-lg !important;
  margin-bottom: $spacing-sm !important;

  &__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: $spacing-sm;
  }
  &__no {
    font-size: $font-size-sm;
    color: $color-neutral-600;
    font-family: $font-family-mono;
  }
  &__status {
    font-size: $font-size-xs;
    font-weight: $font-weight-medium;
    padding: 4rpx 12rpx;
    border-radius: $radius-sm;
  }
  &__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  &__info {
    font-size: $font-size-sm;
    color: $color-neutral-500;
  }
  &__amount {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }
}

// ── 状态标签颜色 ──
.status-error {
  background-color: $color-error-light;
  color: $color-error;
}
.status-warning {
  background-color: $color-warning-light;
  color: $color-warning;
}
.status-info {
  background-color: $color-info-light;
  color: $color-info;
}
.status-success {
  background-color: $color-success-light;
  color: $color-success;
}
.status-muted {
  background-color: $color-neutral-100;
  color: $color-neutral-500;
}
.status-default {
  background-color: $color-neutral-100;
  color: $color-neutral-600;
}

.bottom-placeholder {
  height: 40rpx;
}

// ── 门店切换弹窗 ──
.store-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: flex-end;
  z-index: 999;

  &__content {
    width: 100%;
    background-color: $color-neutral-0;
    border-radius: $radius-2xl $radius-2xl 0 0;
    padding: $spacing-lg $spacing-lg $safe-area-bottom;
    max-height: 60vh;
    overflow-y: auto;
  }
  &__title {
    font-size: $font-size-lg;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    display: block;
    text-align: center;
    margin-bottom: $spacing-lg;
  }
  &__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: $spacing-lg $spacing-md;
    border-bottom: 2rpx solid $color-neutral-100;

    &.active {
      background-color: $color-primary-50;
    }
  }
  &__item-name {
    font-size: $font-size-base;
    color: $color-neutral-800;
  }
  &__item-check {
    font-size: $font-size-lg;
    color: $color-primary-500;
    font-weight: $font-weight-bold;
  }
}
</style>
