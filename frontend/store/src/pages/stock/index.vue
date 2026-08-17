<template>
  <view class="stock-page">
    <!-- 库存概览卡片 -->
    <view class="overview-card">
      <view class="overview-card__header">
        <text class="overview-card__title">套件库存</text>
        <text v-if="overview.kit_available < 10" class="overview-card__badge">库存预警</text>
      </view>
      <view class="overview-card__stats">
        <view class="stat-item">
          <text class="stat-value stat-value--primary">{{ overview.kit_available }}</text>
          <text class="stat-label">可用套件</text>
        </view>
        <view class="stat-divider" />
        <view class="stat-item">
          <text class="stat-value">{{ overview.kit_locked }}</text>
          <text class="stat-label">已锁定</text>
        </view>
        <view class="stat-divider" />
        <view class="stat-item">
          <text class="stat-value">{{ overview.kit_total }}</text>
          <text class="stat-label">总库存</text>
        </view>
      </view>
    </view>

    <!-- 库存预警提示 -->
    <view v-if="overview.kit_available < 10" class="warning-banner">
      <text class="warning-banner__icon">⚠️</text>
      <text class="warning-banner__text">可用套件不足10套，建议及时补充库存</text>
    </view>

    <!-- 面料库存列表 -->
    <view class="section">
      <text class="section__title">面料库存</text>
      <view v-if="fabricLoading && fabricList.length === 0" class="skeleton-list">
        <view v-for="i in 3" :key="i" class="skeleton-item">
          <view class="skeleton-line skeleton-line--short" />
          <view class="skeleton-line skeleton-line--long" />
        </view>
      </view>
      <view v-else-if="fabricList.length > 0" class="fabric-list">
        <view v-for="item in fabricList" :key="item.fabric_no" class="fabric-item">
          <view class="fabric-item__header">
            <text class="fabric-item__no">{{ item.fabric_no }}</text>
            <text class="fabric-item__name">{{ item.fabric_name }}</text>
          </view>
          <view class="fabric-item__series">
            <text class="fabric-item__series-text">{{ item.series }}</text>
          </view>
          <view class="fabric-item__stats">
            <view class="fabric-stat">
              <text class="fabric-stat__label">可用面积</text>
              <text class="fabric-stat__value">{{ item.available_area }} ㎡</text>
            </view>
            <view class="fabric-stat">
              <text class="fabric-stat__label">预留面积</text>
              <text class="fabric-stat__value fabric-stat__value--muted">{{ item.reserved_area }} ㎡</text>
            </view>
          </view>
        </view>
      </view>
      <view v-else-if="!fabricLoading" class="empty-state">
        <text class="empty-state__text">暂无面料库存数据</text>
      </view>
    </view>

    <!-- 底部入口 -->
    <view class="flow-entry" @tap="goFlow">
      <text class="flow-entry__text">查看库存流水</text>
      <text class="flow-entry__arrow">›</text>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { onShow, onPullDownRefresh } from '@dcloudio/uni-app';
import { getStockOverview, getFabricStockList } from '@/api/stock';
import type { StockOverview, FabricStockItem } from '@/types/stock';

/** 库存概览 */
const overview = ref<StockOverview>({
  kit_available: 0,
  kit_locked: 0,
  kit_total: 0,
});

/** 面料库存列表 */
const fabricList = ref<FabricStockItem[]>([]);
const fabricLoading = ref(false);

/** 加载库存概览 */
async function loadOverview() {
  try {
    const data = await getStockOverview();
    overview.value = data;
  } catch {
    // 错误由统一拦截器处理
  }
}

/** 加载面料库存 */
async function loadFabricStock() {
  fabricLoading.value = true;
  try {
    const data = await getFabricStockList();
    fabricList.value = data;
  } catch {
    // 错误由统一拦截器处理
  } finally {
    fabricLoading.value = false;
  }
}

/** 加载所有数据 */
function loadAll() {
  loadOverview();
  loadFabricStock();
}

/** 跳转库存流水 */
function goFlow() {
  uni.navigateTo({ url: '/pages/stock/flow' });
}

// 页面显示时刷新
onShow(() => { loadAll(); });

// 下拉刷新
onPullDownRefresh(async () => {
  loadAll();
  uni.stopPullDownRefresh();
});
</script>

<style lang="scss" scoped>
.stock-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  padding: $spacing-md;
  padding-bottom: $spacing-xl;
}

/* 概览卡片 */
.overview-card {
  background-color: $color-neutral-0;
  border-radius: $radius-lg;
  padding: $spacing-lg;
  box-shadow: $shadow-1;
  margin-bottom: $spacing-md;

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $spacing-lg;
  }

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__badge {
    font-size: $font-size-xs;
    color: $color-warning;
    background-color: $color-warning-light;
    padding: 4rpx 16rpx;
    border-radius: $radius-sm;
    font-weight: $font-weight-medium;
  }

  &__stats {
    display: flex;
    align-items: center;
  }
}

.stat-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-value {
  font-size: $font-size-3xl;
  font-weight: $font-weight-bold;
  color: $color-neutral-900;
  font-family: $font-family-mono;
  line-height: $line-height-tight;

  &--primary {
    color: $color-primary-500;
  }
}

.stat-label {
  font-size: $font-size-xs;
  color: $color-neutral-400;
  margin-top: $spacing-xs;
}

.stat-divider {
  width: 2rpx;
  height: 64rpx;
  background-color: $color-neutral-100;
}

/* 预警横幅 */
.warning-banner {
  display: flex;
  align-items: center;
  background-color: $color-warning-light;
  border-radius: $radius-md;
  padding: $spacing-md $spacing-lg;
  margin-bottom: $spacing-md;

  &__icon {
    font-size: 32rpx;
    margin-right: $spacing-sm;
  }

  &__text {
    font-size: $font-size-sm;
    color: $color-warning;
    font-weight: $font-weight-medium;
  }
}

/* 区块标题 */
.section {
  margin-bottom: $spacing-md;

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    margin-bottom: $spacing-md;
    display: block;
  }
}

/* 面料列表 */
.fabric-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-md;
}

.fabric-item {
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
  box-shadow: $shadow-1;

  &__header {
    display: flex;
    align-items: center;
    margin-bottom: $spacing-xs;
  }

  &__no {
    font-size: $font-size-xs;
    color: $color-neutral-0;
    background-color: $color-primary-500;
    padding: 4rpx 12rpx;
    border-radius: $radius-sm;
    font-family: $font-family-mono;
    margin-right: $spacing-sm;
  }

  &__name {
    font-size: $font-size-base;
    font-weight: $font-weight-medium;
    color: $color-neutral-900;
  }

  &__series {
    margin-bottom: $spacing-md;
  }

  &__series-text {
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }

  &__stats {
    display: flex;
    gap: $spacing-xl;
    padding-top: $spacing-md;
    border-top: 2rpx solid $color-neutral-100;
  }
}

.fabric-stat {
  display: flex;
  flex-direction: column;

  &__label {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    margin-bottom: 4rpx;
  }

  &__value {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    font-family: $font-family-mono;

    &--muted {
      color: $color-neutral-500;
    }
  }
}

/* 骨架屏 */
.skeleton-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-md;
}

.skeleton-item {
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
}

.skeleton-line {
  height: 28rpx;
  background-color: $color-neutral-100;
  border-radius: $radius-sm;
  margin-bottom: $spacing-sm;
  animation: skeleton-pulse 1.5s ease-in-out infinite;

  &--short { width: 40%; }
  &--long { width: 70%; }
}

@keyframes skeleton-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* 空状态 */
.empty-state {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 120rpx 0;

  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-400;
  }
}

/* 流水入口 */
.flow-entry {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
  box-shadow: $shadow-1;
  margin-top: $spacing-md;

  &:active {
    background-color: $color-neutral-50;
  }

  &__text {
    font-size: $font-size-base;
    color: $color-primary-500;
    font-weight: $font-weight-medium;
  }

  &__arrow {
    font-size: $font-size-xl;
    color: $color-neutral-300;
  }
}
</style>
