<template>
  <view class="balance-page">
    <!-- 顶部余额卡片 -->
    <view class="balance-card">
      <view class="balance-card__bg" />
      <view class="balance-card__content">
        <view class="balance-card__header">
          <text class="balance-card__title">可用余额</text>
          <view class="balance-card__eye" @tap="toggleEye">
            <text class="balance-card__eye-icon">{{ balanceVisible ? '👁' : '🙈' }}</text>
          </view>
        </view>
        <view class="balance-card__amount-row">
          <text class="balance-card__symbol">¥</text>
          <text class="balance-card__amount">{{ displayBalance }}</text>
        </view>
        <view v-if="balanceInfo && balanceInfo.frozen_balance_cent > 0" class="balance-card__frozen">
          <text class="balance-card__frozen-text">
            冻结金额 ¥{{ frozenYuan }}
          </text>
        </view>
      </view>
    </view>

    <!-- 快捷操作按钮 -->
    <view class="action-row">
      <view class="action-btn action-btn--primary" @tap="goRecharge">
        <text class="action-btn__icon">💰</text>
        <text class="action-btn__text">去储值</text>
      </view>
      <view class="action-btn action-btn--secondary" @tap="goTransactions">
        <text class="action-btn__icon">📋</text>
        <text class="action-btn__text">查看流水</text>
      </view>
    </view>

    <!-- 最近流水 -->
    <view class="section">
      <view class="section__header">
        <text class="section__title">最近流水</text>
        <view class="section__link" @tap="goTransactions">
          <text class="section__link-text">查看全部 →</text>
        </view>
      </view>

      <!-- 加载中 -->
      <view v-if="recentLoading" class="section__loading">
        <text class="section__loading-text">加载中...</text>
      </view>

      <!-- 空状态 -->
      <view v-else-if="!recentList.length" class="section__empty">
        <text class="section__empty-icon">📭</text>
        <text class="section__empty-text">暂无流水记录</text>
      </view>

      <!-- 流水列表 -->
      <view v-else class="transaction-list">
        <view
          v-for="item in recentList"
          :key="item.id"
          class="transaction-item"
        >
          <view class="transaction-item__left">
            <view
              class="transaction-item__icon"
              :class="getIconClass(item.type)"
            >
              <text class="transaction-item__icon-text">{{ getIconEmoji(item.type) }}</text>
            </view>
            <view class="transaction-item__info">
              <text class="transaction-item__type">{{ item.type_label }}</text>
              <text class="transaction-item__time">{{ formatTime(item.created_at) }}</text>
            </view>
          </view>
          <view class="transaction-item__right">
            <text
              class="transaction-item__amount"
              :class="item.amount_cent >= 0 ? 'transaction-item__amount--income' : 'transaction-item__amount--expense'"
            >
              {{ item.amount_cent >= 0 ? '+' : '' }}{{ formatAmount(item.amount_cent) }}
            </text>
            <text class="transaction-item__balance">余额 {{ formatAmount(item.balance_after_cent) }}</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 底部安全区 -->
    <view class="safe-area" />
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import { getAccountBalance, getBalanceTransactions } from '@/api/balance';
import { fenToYuan } from '@/utils/money';
import { formatDate } from '@/utils/format';
import type { BalanceInfo, BalanceTransaction, TransactionType } from '@/types/balance';

/** 余额数据 */
const balanceInfo = ref<BalanceInfo | null>(null);

/** 余额可见状态 */
const balanceVisible = ref(true);

/** 最近流水列表 */
const recentList = ref<BalanceTransaction[]>([]);

/** 流水加载状态 */
const recentLoading = ref(false);

/** 显示余额（带隐藏功能） */
const displayBalance = computed(() => {
  if (!balanceInfo.value) return '****';
  if (!balanceVisible.value) return '****';
  return fenToYuan(balanceInfo.value.available_balance_cent);
});

/** 冻结金额（元） */
const frozenYuan = computed(() => {
  if (!balanceInfo.value) return '0.00';
  return fenToYuan(balanceInfo.value.frozen_balance_cent);
});

/**
 * 切换余额显示/隐藏
 */
function toggleEye() {
  balanceVisible.value = !balanceVisible.value;
}

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
    recharge: 'transaction-item__icon--recharge',
    payment: 'transaction-item__icon--payment',
    refund: 'transaction-item__icon--refund',
    reversal: 'transaction-item__icon--reversal',
  };
  return map[type] || '';
}

/**
 * 格式化金额（分转元，带千分位）
 */
function formatAmount(cent: number): string {
  const yuan = fenToYuan(Math.abs(cent));
  const parts = yuan.split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return `${cent < 0 ? '-' : ''}${parts.join('.')}`;
}

/**
 * 格式化时间显示
 * 今天显示时分，昨天显示"昨天"，更早显示日期
 */
function formatTime(dateStr: string): string {
  if (!dateStr) return '';
  const date = new Date(dateStr.replace(/-/g, '/'));
  const now = new Date();

  const isToday = date.toDateString() === now.toDateString();
  if (isToday) {
    return formatDate(dateStr, 'HH:mm');
  }

  const yesterday = new Date(now);
  yesterday.setDate(yesterday.getDate() - 1);
  const isYesterday = date.toDateString() === yesterday.toDateString();
  if (isYesterday) {
    return '昨天 ' + formatDate(dateStr, 'HH:mm');
  }

  // 同一年则不显示年份
  if (date.getFullYear() === now.getFullYear()) {
    return formatDate(dateStr, 'MM-DD HH:mm');
  }

  return formatDate(dateStr, 'YYYY-MM-DD HH:mm');
}

/**
 * 加载余额信息
 */
async function loadBalance() {
  try {
    const data = await getAccountBalance();
    balanceInfo.value = data;
  } catch (e) {
    console.error('获取余额失败:', e);
  }
}

/**
 * 加载最近5笔流水
 */
async function loadRecentTransactions() {
  recentLoading.value = true;
  try {
    const data = await getBalanceTransactions({
      page: 1,
      page_size: 5,
    });
    recentList.value = data.list || [];
  } catch (e) {
    console.error('获取流水失败:', e);
  } finally {
    recentLoading.value = false;
  }
}

/**
 * 跳转到储值充值页面
 */
function goRecharge() {
  uni.navigateTo({ url: '/pages/balance/recharge/index' });
}

/**
 * 跳转到流水列表页面
 */
function goTransactions() {
  uni.navigateTo({ url: '/pages/balance/transactions' });
}

/**
 * 页面显示时刷新数据
 */
onShow(() => {
  loadBalance();
  loadRecentTransactions();
});
</script>

<style lang="scss" scoped>
.balance-page {
  min-height: 100vh;
  background-color: #F9FAFB;
  padding-bottom: env(safe-area-inset-bottom);
}

// ── 顶部余额卡片 ──
.balance-card {
  position: relative;
  margin: 24rpx 24rpx 0;
  border-radius: 32rpx;
  overflow: hidden;

  &__bg {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #56638F 0%, #444F73 60%, #38415F 100%);
    border-radius: 32rpx;
  }

  &__content {
    position: relative;
    padding: 48rpx 40rpx 40rpx;
  }

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20rpx;
  }

  &__title {
    font-size: 28rpx;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 400;
  }

  &__eye {
    padding: 8rpx;
  }

  &__eye-icon {
    font-size: 32rpx;
  }

  &__amount-row {
    display: flex;
    align-items: baseline;
    margin-bottom: 16rpx;
  }

  &__symbol {
    font-size: 40rpx;
    color: #FFFFFF;
    font-weight: 600;
    margin-right: 8rpx;
  }

  &__amount {
    font-size: 72rpx;
    color: #FFFFFF;
    font-weight: 700;
    line-height: 1.1;
    letter-spacing: -1rpx;
    font-family: 'DIN Alternate', 'JetBrains Mono', -apple-system, sans-serif;
  }

  &__frozen {
    margin-top: 8rpx;
  }

  &__frozen-text {
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.5);
  }
}

// ── 快捷操作按钮 ──
.action-row {
  display: flex;
  gap: 20rpx;
  padding: 32rpx 24rpx 0;
}

.action-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12rpx;
  height: 88rpx;
  border-radius: 20rpx;
  background-color: #FFFFFF;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.04);

  &--primary {
    background-color: #56638F;
    box-shadow: 0 8rpx 24rpx rgba(86, 99, 143, 0.3);
  }

  &--secondary {
    border: 2rpx solid #E5E7EB;
  }

  &__icon {
    font-size: 32rpx;
  }

  &__text {
    font-size: 30rpx;
    font-weight: 500;
    color: #FFFFFF;
  }

  &--secondary &__text {
    color: #374151;
  }
}

// ── 最近流水区域 ──
.section {
  margin: 32rpx 24rpx 0;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24rpx;
  }

  &__title {
    font-size: 32rpx;
    font-weight: 600;
    color: #111827;
  }

  &__link {
    padding: 8rpx 4rpx;
  }

  &__link-text {
    font-size: 26rpx;
    color: #56638F;
    font-weight: 500;
  }

  &__loading {
    display: flex;
    justify-content: center;
    padding: 60rpx 0;
  }

  &__loading-text {
    font-size: 26rpx;
    color: #9CA3AF;
  }

  &__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 60rpx 0;
  }

  &__empty-icon {
    font-size: 64rpx;
    margin-bottom: 16rpx;
  }

  &__empty-text {
    font-size: 28rpx;
    color: #9CA3AF;
  }
}

// ── 流水列表 ──
.transaction-list {
  background-color: #FFFFFF;
  border-radius: 20rpx;
  overflow: hidden;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);
}

.transaction-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 28rpx 28rpx;
  border-bottom: 1rpx solid #F3F4F6;

  &:last-child {
    border-bottom: none;
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
    gap: 6rpx;
    min-width: 0;
  }

  &__type {
    font-size: 28rpx;
    font-weight: 500;
    color: #111827;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__time {
    font-size: 24rpx;
    color: #9CA3AF;
  }

  &__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6rpx;
    flex-shrink: 0;
    margin-left: 20rpx;
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

  &__balance {
    font-size: 22rpx;
    color: #9CA3AF;
    font-family: 'DIN Alternate', 'JetBrains Mono', -apple-system, sans-serif;
  }
}

// ── 底部安全区 ──
.safe-area {
  height: calc(32rpx + env(safe-area-inset-bottom));
}
</style>
