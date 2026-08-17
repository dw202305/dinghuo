<template>
  <view class="order-list-page">
    <!-- 搜索框 -->
    <view class="search-bar">
      <view class="search-bar__inner">
        <text class="search-icon">🔍</text>
        <input
          class="search-input"
          type="text"
          placeholder="搜索订单号/项目名/客户名"
          :value="keyword"
          confirm-type="search"
          @input="onSearchInput"
          @confirm="onSearchConfirm"
        />
        <text v-if="keyword" class="search-clear" @tap="clearSearch">✕</text>
      </view>
    </view>

    <!-- 状态 Tab 栏 -->
    <view class="tab-bar">
      <scroll-view scroll-x class="tab-scroll" :scroll-left="tabScrollLeft">
        <view
          v-for="(tab, index) in tabs"
          :key="tab.value"
          :id="'tab-' + index"
          class="tab-item"
          :class="{ 'tab-item--active': activeTab === tab.value }"
          @tap="switchTab(tab.value, index)"
        >
          <text>{{ tab.label }}</text>
        </view>
      </scroll-view>
    </view>

    <!-- 订单列表 -->
    <scroll-view
      scroll-y
      class="order-scroll"
      :refresher-enabled="true"
      :refresher-triggered="refreshing"
      @refresherrefresh="onRefresh"
      @scrolltolower="onLoadMore"
    >
      <!-- 骨架屏 -->
      <view v-if="loading && orderList.length === 0" class="skeleton-list">
        <view v-for="i in 4" :key="i" class="skeleton-card">
          <view class="skeleton-line skeleton-line--short" />
          <view class="skeleton-line skeleton-line--medium" />
          <view class="skeleton-line skeleton-line--long" />
        </view>
      </view>

      <!-- 订单卡片 -->
      <view v-else-if="orderList.length > 0" class="order-cards">
        <view
          v-for="order in orderList"
          :key="order.order_id"
          class="order-card"
          @tap="goDetail(order.order_id)"
        >
          <!-- 头部：订单号 + 状态 -->
          <view class="order-card__header">
            <text class="order-card__no">{{ order.order_no }}</text>
            <view class="status-tag" :class="getStatusClass(order.order_status)">
              <text>{{ order.order_status_text }}</text>
            </view>
          </view>

          <!-- 项目/客户 -->
          <view class="order-card__info">
            <text v-if="order.project_name" class="order-card__project">{{ order.project_name }}</text>
            <text class="order-card__customer">
              {{ order.end_customer || '散客' }} · {{ order.item_count }}副窗帘
            </text>
          </view>

          <!-- 底部：金额 + 时间 -->
          <view class="order-card__footer">
            <text class="order-card__date">{{ formatDate(order.created_at) }}</text>
            <text class="order-card__amount">¥{{ formatMoney(order.total_amount) }}</text>
          </view>
        </view>
      </view>

      <!-- 空状态 -->
      <view v-else-if="!loading" class="empty-state">
        <text class="empty-icon">📋</text>
        <text class="empty-title">暂无订单</text>
        <text class="empty-desc">去创建您的第一笔订单吧</text>
      </view>

      <!-- 加载更多 -->
      <view v-if="loading && orderList.length > 0" class="loading-more">
        <text class="loading-text">加载中...</text>
      </view>
      <view v-else-if="!hasMore && orderList.length > 0" class="loading-more">
        <text class="loading-text">已加载全部订单</text>
      </view>
    </scroll-view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { onShow, onPullDownRefresh, onReachBottom } from '@dcloudio/uni-app';
import { getOrderList } from '@/api/order';
import { formatDate, formatMoney } from '@/utils/format';
import type { OrderListItem, OrderListParams } from '@/types/order';
import { OrderStatus } from '@/types/common';

/** Tab 配置 */
const tabs = [
  { label: '全部', value: 0 },
  { label: '待支付', value: OrderStatus.PENDING_PAYMENT },
  { label: '生产中', value: OrderStatus.IN_PRODUCTION },
  { label: '已发货', value: OrderStatus.SHIPPED },
  { label: '已完成', value: OrderStatus.COMPLETED },
];

const activeTab = ref<number>(0);
const keyword = ref('');
const orderList = ref<OrderListItem[]>([]);
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const refreshing = ref(false);
const tabScrollLeft = ref(0);

/** 是否有更多数据 */
const hasMore = computed(() => orderList.value.length < total.value);

/** 加载订单列表 */
async function loadOrders(isRefresh = false) {
  if (loading.value) return;
  if (!isRefresh && !hasMore.value) return;

  loading.value = true;
  if (isRefresh) {
    page.value = 1;
    refreshing.value = true;
  }

  try {
    const params: OrderListParams = {
      page: page.value,
      page_size: 20,
    };
    if (activeTab.value) {
      params.order_status = activeTab.value as OrderStatus;
    }
    if (keyword.value.trim()) {
      params.keyword = keyword.value.trim();
    }

    const data = await getOrderList(params);
    if (isRefresh) {
      orderList.value = data.list;
    } else {
      orderList.value = [...orderList.value, ...data.list];
    }
    total.value = data.total;
  } catch {
    // 错误由统一拦截器处理
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

/** 切换 Tab */
function switchTab(value: number, index: number) {
  if (activeTab.value === value) return;
  activeTab.value = value;
  tabScrollLeft.value = index * 120;
}

/** 搜索输入（防抖） */
let searchTimer: ReturnType<typeof setTimeout> | null = null;
function onSearchInput(e: { detail: { value: string } }) {
  keyword.value = e.detail.value;
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    loadOrders(true);
  }, 500);
}

/** 搜索确认 */
function onSearchConfirm() {
  if (searchTimer) clearTimeout(searchTimer);
  loadOrders(true);
}

/** 清空搜索 */
function clearSearch() {
  keyword.value = '';
  loadOrders(true);
}

/** 下拉刷新 */
function onRefresh() {
  loadOrders(true);
}

/** 上拉加载 */
function onLoadMore() {
  if (hasMore.value) {
    page.value += 1;
    loadOrders(false);
  }
}

/** 跳转订单详情 */
function goDetail(orderId: number) {
  uni.navigateTo({ url: `/pages/order/detail?order_id=${orderId}` });
}

/** 获取状态标签样式类 */
function getStatusClass(status: number): string {
  if (status === OrderStatus.PENDING_PAYMENT) return 'status-tag--warning';
  if (status === OrderStatus.PAYMENT_PROCESSING || status === OrderStatus.IN_PRODUCTION || status === OrderStatus.IN_QUALITY_CHECK) return 'status-tag--info';
  if (status === OrderStatus.SHIPPED || status === OrderStatus.RECEIVED) return 'status-tag--success';
  if (status === OrderStatus.COMPLETED) return 'status-tag--neutral';
  if (status === OrderStatus.CANCELLED || status === OrderStatus.AFTER_SALE_PROCESSING) return 'status-tag--error';
  return 'status-tag--neutral';
}

// Tab 切换时重新加载
watch(activeTab, () => { loadOrders(true); });

// 页面显示时刷新
onShow(() => { loadOrders(true); });

// 兼容页面级下拉刷新
onPullDownRefresh(async () => {
  await loadOrders(true);
  uni.stopPullDownRefresh();
});

// 兼容页面级上拉加载
onReachBottom(() => { onLoadMore(); });
</script>

<style lang="scss" scoped>
.order-list-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  display: flex;
  flex-direction: column;
}

/* 搜索框 */
.search-bar {
  background-color: $color-neutral-0;
  padding: $spacing-sm $spacing-md;

  &__inner {
    display: flex;
    align-items: center;
    background-color: $color-neutral-100;
    border-radius: $radius-full;
    padding: 0 $spacing-md;
    height: 72rpx;
  }
}

.search-icon {
  font-size: 28rpx;
  margin-right: $spacing-sm;
}

.search-input {
  flex: 1;
  font-size: $font-size-sm;
  color: $color-neutral-900;
  height: 72rpx;
}

.search-clear {
  font-size: 24rpx;
  color: $color-neutral-400;
  padding: $spacing-sm;
}

/* Tab 栏 */
.tab-bar {
  background-color: $color-neutral-0;
  border-bottom: 2rpx solid $color-neutral-100;
  position: sticky;
  top: 0;
  z-index: 10;
}

.tab-scroll {
  white-space: nowrap;
  padding: $spacing-sm $spacing-md;
}

.tab-item {
  display: inline-block;
  padding: $spacing-xs $spacing-lg;
  margin-right: $spacing-sm;
  font-size: $font-size-sm;
  color: $color-neutral-500;
  border-radius: $radius-full;
  background-color: $color-neutral-50;
  transition: all 0.2s ease;

  &--active {
    background-color: $color-primary-500;
    color: $color-neutral-0;
    font-weight: $font-weight-medium;
  }
}

/* 订单列表滚动区 */
.order-scroll {
  flex: 1;
  height: calc(100vh - 260rpx);
}

.order-cards {
  padding: $spacing-md;
}

/* 订单卡片 */
.order-card {
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
  margin-bottom: $spacing-md;
  box-shadow: $shadow-1;
  transition: box-shadow 0.2s ease;

  &:active {
    box-shadow: $shadow-2;
  }

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $spacing-sm;
  }

  &__no {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    font-family: $font-family-mono;
  }

  &__info {
    margin-bottom: $spacing-sm;
  }

  &__project {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    display: block;
    margin-bottom: 4rpx;
  }

  &__customer {
    font-size: $font-size-sm;
    color: $color-neutral-500;
  }

  &__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  &__date {
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }

  &__amount {
    font-size: $font-size-lg;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }
}

/* 状态标签 */
.status-tag {
  padding: 4rpx 16rpx;
  border-radius: $radius-sm;
  font-size: $font-size-xs;
  font-weight: $font-weight-medium;

  &--warning {
    background-color: $color-warning-light;
    color: $color-warning;
  }

  &--info {
    background-color: $color-info-light;
    color: $color-info;
  }

  &--success {
    background-color: $color-success-light;
    color: $color-success;
  }

  &--neutral {
    background-color: $color-neutral-100;
    color: $color-neutral-500;
  }

  &--error {
    background-color: $color-error-light;
    color: $color-error;
  }
}

/* 骨架屏 */
.skeleton-list {
  padding: $spacing-md;
}

.skeleton-card {
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
  margin-bottom: $spacing-md;
}

.skeleton-line {
  height: 28rpx;
  background-color: $color-neutral-100;
  border-radius: $radius-sm;
  margin-bottom: $spacing-sm;
  animation: skeleton-pulse 1.5s ease-in-out infinite;

  &--short { width: 40%; }
  &--medium { width: 60%; }
  &--long { width: 80%; }
}

@keyframes skeleton-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* 空状态 */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 200rpx $spacing-lg 0;
}

.empty-icon {
  font-size: 96rpx;
  margin-bottom: $spacing-lg;
}

.empty-title {
  font-size: $font-size-base;
  font-weight: $font-weight-semibold;
  color: $color-neutral-700;
  margin-bottom: $spacing-xs;
}

.empty-desc {
  font-size: $font-size-sm;
  color: $color-neutral-400;
}

/* 加载更多 */
.loading-more {
  text-align: center;
  padding: $spacing-lg 0 $spacing-xl;
}

.loading-text {
  font-size: $font-size-xs;
  color: $color-neutral-400;
}
</style>
