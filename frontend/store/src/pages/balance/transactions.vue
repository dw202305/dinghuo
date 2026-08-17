<template>
  <view class="transactions-page">
    <!-- 顶部筛选标签 -->
    <view class="filter-bar">
      <scroll-view class="filter-bar__scroll" scroll-x enable-flex>
        <view class="filter-bar__inner">
          <view
            v-for="tab in typeTabs"
            :key="tab.value"
            class="filter-tab"
            :class="{ 'filter-tab--active': activeType === tab.value }"
            @tap="switchType(tab.value)"
          >
            <text class="filter-tab__text">{{ tab.label }}</text>
          </view>
        </view>
      </scroll-view>
    </view>

    <!-- 时间范围选择器 -->
    <view class="time-bar">
      <view
        v-for="opt in timeOptions"
        :key="opt.value"
        class="time-option"
        :class="{ 'time-option--active': activeTimeRange === opt.value }"
        @tap="switchTimeRange(opt.value)"
      >
        <text class="time-option__text">{{ opt.label }}</text>
      </view>
      <view v-if="activeTimeRange === 'custom'" class="time-bar__custom">
        <picker mode="date" :value="customStartDate" @change="onStartDateChange">
          <view class="time-bar__date-btn">
            <text class="time-bar__date-text">{{ customStartDate || '开始日期' }}</text>
          </view>
        </picker>
        <text class="time-bar__separator">至</text>
        <picker mode="date" :value="customEndDate" @change="onEndDateChange">
          <view class="time-bar__date-btn">
            <text class="time-bar__date-text">{{ customEndDate || '结束日期' }}</text>
          </view>
        </picker>
      </view>
    </view>

    <!-- 流水列表 -->
    <scroll-view
      class="list-scroll"
      scroll-y
      :refresher-enabled="true"
      :refresher-triggered="refreshing"
      @refresherrefresh="handleRefresh"
      @scrolltolower="handleLoadMore"
    >
      <!-- 加载中 -->
      <view v-if="loading && !list.length" class="list-loading">
        <text class="list-loading__text">加载中...</text>
      </view>

      <!-- 空状态 -->
      <view v-else-if="!loading && !list.length" class="list-empty">
        <text class="list-empty__icon">📭</text>
        <text class="list-empty__title">暂无流水记录</text>
        <text class="list-empty__desc">充值、消费、退款等操作将在此展示</text>
      </view>

      <!-- 列表 -->
      <view v-else class="list-container">
        <view
          v-for="(item, index) in list"
          :key="item.id"
          class="list-item"
          @tap="toggleExpand(index)"
        >
          <!-- 主行 -->
          <view class="list-item__main">
            <view class="list-item__left">
              <view
                class="list-item__icon"
                :class="getIconClass(item.type)"
              >
                <text class="list-item__icon-text">{{ getIconEmoji(item.type) }}</text>
              </view>
              <view class="list-item__info">
                <text class="list-item__type">{{ item.type_label }}</text>
                <view v-if="item.related_order_no" class="list-item__order" @tap.stop="goOrder(item.related_order_no!)">
                  <text class="list-item__order-text">订单 {{ item.related_order_no }}</text>
                  <text class="list-item__order-link">↗</text>
                </view>
              </view>
            </view>
            <view class="list-item__right">
              <text
                class="list-item__amount"
                :class="item.amount_cent >= 0 ? 'list-item__amount--income' : 'list-item__amount--expense'"
              >
                {{ item.amount_cent >= 0 ? '+' : '' }}{{ formatAmount(item.amount_cent) }}
              </text>
              <text class="list-item__time">{{ formatDateTime(item.created_at) }}</text>
            </view>
          </view>

          <!-- 展开详情 -->
          <view v-if="expandedIndex === index" class="list-item__detail">
            <view class="detail-divider" />
            <view class="detail-row">
              <text class="detail-row__label">流水号</text>
              <text class="detail-row__value">{{ item.transaction_no }}</text>
            </view>
            <view class="detail-row">
              <text class="detail-row__label">变动前余额</text>
              <text class="detail-row__value">¥{{ formatAmount(item.balance_before_cent) }}</text>
            </view>
            <view class="detail-row">
              <text class="detail-row__label">变动后余额</text>
              <text class="detail-row__value">¥{{ formatAmount(item.balance_after_cent) }}</text>
            </view>
            <view v-if="item.remark" class="detail-row">
              <text class="detail-row__label">备注</text>
              <text class="detail-row__value detail-row__value--remark">{{ item.remark }}</text>
            </view>
          </view>
        </view>

        <!-- 加载更多 -->
        <view v-if="loading && list.length" class="list-loading-more">
          <text class="list-loading-more__text">加载中...</text>
        </view>
        <view v-else-if="!hasMore && list.length" class="list-no-more">
          <text class="list-no-more__text">— 没有更多了 —</text>
        </view>
      </view>

      <!-- 底部汇总 -->
      <view v-if="list.length" class="summary-bar">
        <view class="summary-item">
          <text class="summary-item__label">收入合计</text>
          <text class="summary-item__value summary-item__value--income">
            ¥{{ formatAmount(summaryIncome) }}
          </text>
        </view>
        <view class="summary-divider" />
        <view class="summary-item">
          <text class="summary-item__label">支出合计</text>
          <text class="summary-item__value summary-item__value--expense">
            ¥{{ formatAmount(summaryExpense) }}
          </text>
        </view>
      </view>

      <!-- 底部安全区 -->
      <view class="safe-area" />
    </scroll-view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { getBalanceTransactions } from '@/api/balance';
import { fenToYuan } from '@/utils/money';
import { formatDate } from '@/utils/format';
import type { BalanceTransaction, TransactionType } from '@/types/balance';

// ── 筛选配置 ──
/** 类型筛选标签 */
const typeTabs = [
  { label: '全部', value: '' as const },
  { label: '充值', value: 'recharge' as const },
  { label: '消费', value: 'payment' as const },
  { label: '退款', value: 'refund' as const },
  { label: '冲正', value: 'reversal' as const },
];

/** 时间范围选项 */
const timeOptions = [
  { label: '近7天', value: '7' },
  { label: '近30天', value: '30' },
  { label: '自定义', value: 'custom' },
];

// ── 筛选状态 ──
/** 当前激活的类型筛选 */
const activeType = ref<string>('');
/** 当前激活的时间范围 */
const activeTimeRange = ref('7');
/** 自定义开始日期 */
const customStartDate = ref('');
/** 自定义结束日期 */
const customEndDate = ref('');

// ── 列表状态 ──
/** 流水列表 */
const list = ref<BalanceTransaction[]>([]);
/** 加载中 */
const loading = ref(false);
/** 下拉刷新中 */
const refreshing = ref(false);
/** 当前页码 */
const page = ref(1);
/** 是否有更多数据 */
const hasMore = ref(true);
/** 总条数 */
const total = ref(0);
/** 展开的条目索引 */
const expandedIndex = ref(-1);

// ── 汇总数据 ──
/** 收入合计（分） */
const summaryIncome = ref(0);
/** 支出合计（分） */
const summaryExpense = ref(0);

/** 每页大小 */
const PAGE_SIZE = 20;

/**
 * 获取类型对应的图标 emoji
 */
function getIconEmoji(type: TransactionType): string {
  const map: Record<TransactionType, string> = {
    recharge: '💰',
    payment: '🛒',
    refund: '↩️',
    reversal: '🔄',
  };
  return map[type] || '💳';
}

/**
 * 获取类型对应的图标样式类名
 */
function getIconClass(type: TransactionType): string {
  const map: Record<TransactionType, string> = {
    recharge: 'list-item__icon--recharge',
    payment: 'list-item__icon--payment',
    refund: 'list-item__icon--refund',
    reversal: 'list-item__icon--reversal',
  };
  return map[type] || '';
}

/**
 * 格式化金额（分转元，带千分位，保留符号）
 */
function formatAmount(cent: number): string {
  const abs = Math.abs(cent);
  const yuan = fenToYuan(abs);
  const parts = yuan.split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return `${cent < 0 ? '-' : ''}${parts.join('.')}`;
}

/**
 * 格式化日期时间
 */
function formatDateTime(dateStr: string): string {
  return formatDate(dateStr, 'MM-DD HH:mm');
}

/**
 * 计算日期范围参数
 */
function getDateRange(): { start_date?: string; end_date?: string } {
  if (activeTimeRange.value === 'custom') {
    return {
      start_date: customStartDate.value || undefined,
      end_date: customEndDate.value || undefined,
    };
  }

  const days = parseInt(activeTimeRange.value);
  if (isNaN(days) || days <= 0) return {};

  const end = new Date();
  const start = new Date();
  start.setDate(start.getDate() - days);

  const formatDateStr = (d: Date): string => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  return {
    start_date: formatDateStr(start),
    end_date: formatDateStr(end),
  };
}

/**
 * 加载流水数据
 */
async function loadData(isRefresh = false) {
  if (loading.value) return;
  if (!isRefresh && !hasMore.value) return;

  loading.value = true;
  if (isRefresh) {
    page.value = 1;
    refreshing.value = true;
    hasMore.value = true;
  }

  try {
    const dateRange = getDateRange();
    const params = {
      page: page.value,
      page_size: PAGE_SIZE,
      type: activeType.value as TransactionType | undefined,
      ...dateRange,
    };

    const data = await getBalanceTransactions(params);

    if (isRefresh) {
      list.value = data.list || [];
    } else {
      list.value = [...list.value, ...(data.list || [])];
    }

    total.value = data.total;
    hasMore.value = list.value.length < data.total;

    // 更新汇总数据
    summaryIncome.value = data.income_total_cent ?? 0;
    summaryExpense.value = data.expense_total_cent ?? 0;
  } catch (e) {
    console.error('加载流水失败:', e);
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

/**
 * 切换类型筛选
 */
function switchType(type: string) {
  activeType.value = type;
  loadData(true);
}

/**
 * 切换时间范围
 */
function switchTimeRange(range: string) {
  activeTimeRange.value = range;
  if (range !== 'custom') {
    loadData(true);
  }
}

/**
 * 自定义开始日期变更
 */
function onStartDateChange(e: { detail: { value: string } }) {
  customStartDate.value = e.detail.value;
  if (customEndDate.value) {
    loadData(true);
  }
}

/**
 * 自定义结束日期变更
 */
function onEndDateChange(e: { detail: { value: string } }) {
  customEndDate.value = e.detail.value;
  if (customStartDate.value) {
    loadData(true);
  }
}

/**
 * 下拉刷新
 */
function handleRefresh() {
  loadData(true);
}

/**
 * 加载更多
 */
function handleLoadMore() {
  if (!hasMore.value || loading.value) return;
  page.value += 1;
  loadData(false);
}

/**
 * 切换展开/收起
 */
function toggleExpand(index: number) {
  expandedIndex.value = expandedIndex.value === index ? -1 : index;
}

/**
 * 跳转订单详情
 */
function goOrder(orderNo: string) {
  // 尝试根据订单号跳转订单详情
  uni.navigateTo({
    url: `/pages/order/detail?order_no=${orderNo}`,
  });
}

// ── 页面生命周期 ──
onLoad(() => {
  // 初始化自定义日期为近7天
  const now = new Date();
  const sevenDaysAgo = new Date();
  sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

  const formatDateStr = (d: Date): string => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  customStartDate.value = formatDateStr(sevenDaysAgo);
  customEndDate.value = formatDateStr(now);

  loadData(true);
});
</script>

<style lang="scss" scoped>
.transactions-page {
  min-height: 100vh;
  background-color: #F9FAFB;
  display: flex;
  flex-direction: column;
}

// ── 类型筛选栏 ──
.filter-bar {
  background-color: #FFFFFF;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #F3F4F6;

  &__scroll {
    white-space: nowrap;
  }

  &__inner {
    display: flex;
    gap: 16rpx;
    padding: 0 24rpx;
  }
}

.filter-tab {
  padding: 12rpx 32rpx;
  border-radius: 999rpx;
  background-color: #F3F4F6;
  flex-shrink: 0;
  transition: all 0.2s;

  &--active {
    background-color: #56638F;
  }

  &__text {
    font-size: 26rpx;
    color: #4B5563;
    font-weight: 500;
  }

  &--active &__text {
    color: #FFFFFF;
  }
}

// ── 时间范围栏 ──
.time-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16rpx;
  padding: 20rpx 24rpx;
  background-color: #FFFFFF;
  border-bottom: 1rpx solid #F3F4F6;

  &__custom {
    display: flex;
    align-items: center;
    gap: 12rpx;
    width: 100%;
    margin-top: 8rpx;
  }

  &__date-btn {
    flex: 1;
    height: 64rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2rpx solid #E5E7EB;
    border-radius: 12rpx;
    background-color: #F9FAFB;
  }

  &__date-text {
    font-size: 26rpx;
    color: #4B5563;
  }

  &__separator {
    font-size: 24rpx;
    color: #9CA3AF;
    flex-shrink: 0;
  }
}

.time-option {
  padding: 10rpx 28rpx;
  border-radius: 999rpx;
  border: 2rpx solid #E5E7EB;
  flex-shrink: 0;

  &--active {
    border-color: #56638F;
    background-color: #F0F1F5;
  }

  &__text {
    font-size: 26rpx;
    color: #4B5563;
    font-weight: 500;
  }

  &--active &__text {
    color: #56638F;
  }
}

// ── 列表滚动区 ──
.list-scroll {
  flex: 1;
  height: 0;
}

// ── 加载中 ──
.list-loading {
  display: flex;
  justify-content: center;
  padding: 100rpx 0;
}

.list-loading__text {
  font-size: 28rpx;
  color: #9CA3AF;
}

// ── 空状态 ──
.list-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 120rpx 40rpx;
}

.list-empty__icon {
  font-size: 80rpx;
  margin-bottom: 24rpx;
}

.list-empty__title {
  font-size: 30rpx;
  font-weight: 500;
  color: #374151;
  margin-bottom: 12rpx;
}

.list-empty__desc {
  font-size: 26rpx;
  color: #9CA3AF;
}

// ── 流水列表 ──
.list-container {
  padding: 16rpx 24rpx 0;
}

.list-item {
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding: 28rpx;
  margin-bottom: 16rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);

  &__main {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__left {
    display: flex;
    align-items: center;
    gap: 20rpx;
    flex: 1;
    min-width: 0;
  }

  &__icon {
    width: 72rpx;
    height: 72rpx;
    border-radius: 20rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &--recharge {
      background-color: #ECFDF5;
    }

    &--payment {
      background-color: #EFF6FF;
    }

    &--refund {
      background-color: #FFFBEB;
    }

    &--reversal {
      background-color: #FEF2F2;
    }
  }

  &__icon-text {
    font-size: 32rpx;
  }

  &__info {
    display: flex;
    flex-direction: column;
    gap: 8rpx;
    min-width: 0;
  }

  &__type {
    font-size: 28rpx;
    font-weight: 500;
    color: #111827;
  }

  &__order {
    display: flex;
    align-items: center;
    gap: 4rpx;
  }

  &__order-text {
    font-size: 22rpx;
    color: #56638F;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 280rpx;
  }

  &__order-link {
    font-size: 22rpx;
    color: #56638F;
  }

  &__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8rpx;
    flex-shrink: 0;
    margin-left: 16rpx;
  }

  &__amount {
    font-size: 30rpx;
    font-weight: 600;
    font-family: 'DIN Alternate', 'JetBrains Mono', -apple-system, sans-serif;

    &--income {
      color: #059669;
    }

    &--expense {
      color: #DC2626;
    }
  }

  &__time {
    font-size: 24rpx;
    color: #9CA3AF;
  }

  // ── 展开详情 ──
  &__detail {
    overflow: hidden;
  }
}

.detail-divider {
  height: 1rpx;
  background-color: #F3F4F6;
  margin: 20rpx 0 16rpx;
}

.detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8rpx 0;

  &__label {
    font-size: 24rpx;
    color: #9CA3AF;
    flex-shrink: 0;
    margin-right: 20rpx;
  }

  &__value {
    font-size: 24rpx;
    color: #374151;
    text-align: right;
    font-family: 'DIN Alternate', 'JetBrains Mono', -apple-system, sans-serif;

    &--remark {
      font-family: -apple-system, BlinkMacSystemFont, sans-serif;
      max-width: 400rpx;
      word-break: break-all;
    }
  }
}

// ── 加载更多 ──
.list-loading-more {
  display: flex;
  justify-content: center;
  padding: 24rpx 0;
}

.list-loading-more__text {
  font-size: 26rpx;
  color: #9CA3AF;
}

.list-no-more {
  display: flex;
  justify-content: center;
  padding: 24rpx 0;
}

.list-no-more__text {
  font-size: 24rpx;
  color: #D1D5DB;
}

// ── 底部汇总 ──
.summary-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 16rpx 24rpx 0;
  padding: 28rpx 32rpx;
  background-color: #FFFFFF;
  border-radius: 20rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);
}

.summary-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8rpx;

  &__label {
    font-size: 24rpx;
    color: #9CA3AF;
  }

  &__value {
    font-size: 32rpx;
    font-weight: 700;
    font-family: 'DIN Alternate', 'JetBrains Mono', -apple-system, sans-serif;

    &--income {
      color: #059669;
    }

    &--expense {
      color: #DC2626;
    }
  }
}

.summary-divider {
  width: 1rpx;
  height: 48rpx;
  background-color: #E5E7EB;
}

// ── 底部安全区 ──
.safe-area {
  height: calc(32rpx + env(safe-area-inset-bottom));
}
</style>
