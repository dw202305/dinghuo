<template>
  <view class="flow-page">
    <!-- 类型筛选 Tab -->
    <view class="filter-tabs">
      <view
        v-for="tab in tabs"
        :key="tab.value"
        class="filter-tab"
        :class="{ 'filter-tab--active': activeFilter === tab.value }"
        @tap="switchFilter(tab.value)"
      >
        <text>{{ tab.label }}</text>
      </view>
    </view>

    <!-- 流水列表 -->
    <scroll-view
      scroll-y
      class="flow-scroll"
      @scrolltolower="onLoadMore"
    >
      <!-- 骨架屏 -->
      <view v-if="loading && groupedFlows.length === 0" class="skeleton-list">
        <view v-for="i in 5" :key="i" class="skeleton-card">
          <view class="skeleton-line skeleton-line--short" />
          <view class="skeleton-line skeleton-line--medium" />
        </view>
      </view>

      <!-- 按月分组 -->
      <view v-else-if="groupedFlows.length > 0">
        <view v-for="group in groupedFlows" :key="group.month" class="flow-group">
          <view class="flow-group__header">
            <text class="flow-group__month">{{ group.month }}</text>
            <text class="flow-group__count">{{ group.items.length }}条记录</text>
          </view>
          <view class="flow-group__list">
            <view v-for="log in group.items" :key="log.id" class="flow-item">
              <view class="flow-item__left">
                <view class="flow-item__type-tag" :class="getTypeClass(log.log_type)">
                  <text>{{ log.log_type_text }}</text>
                </view>
                <view class="flow-item__info">
                  <text v-if="log.order_no" class="flow-item__order">
                    {{ log.order_no }}
                  </text>
                  <text v-if="log.reason" class="flow-item__reason">{{ log.reason }}</text>
                  <text class="flow-item__meta">
                    {{ formatFlowTime(log.created_at) }}
                    <text v-if="log.operator_name"> · {{ log.operator_name }}</text>
                  </text>
                </view>
              </view>
              <view class="flow-item__right">
                <text class="flow-item__qty" :class="log.quantity > 0 ? 'qty--positive' : 'qty--negative'">
                  {{ log.quantity > 0 ? '+' : '' }}{{ log.quantity }}
                </text>
                <text class="flow-item__balance">余{{ log.after_quantity }}</text>
              </view>
            </view>
          </view>
        </view>
      </view>

      <!-- 空状态 -->
      <view v-else-if="!loading" class="empty-state">
        <text class="empty-state__icon">📊</text>
        <text class="empty-state__text">暂无库存流水记录</text>
      </view>

      <!-- 加载更多 -->
      <view v-if="loading && groupedFlows.length > 0" class="loading-more">
        <text class="loading-more__text">加载中...</text>
      </view>
      <view v-else-if="!hasMore && groupedFlows.length > 0" class="loading-more">
        <text class="loading-more__text">已加载全部记录</text>
      </view>
    </scroll-view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { onLoad, onReachBottom } from '@dcloudio/uni-app';
import { getStockFlowList } from '@/api/stock';
import type { InventoryLogItem, InventoryLogParams, FlowFilterType } from '@/types/stock';
import { InventoryLogType } from '@/types/stock';

/** 筛选 Tab 配置 */
const tabs = [
  { label: '全部', value: 'all' as FlowFilterType },
  { label: '入库', value: 'in' as FlowFilterType },
  { label: '出库', value: 'out' as FlowFilterType },
  { label: '锁定', value: 'lock' as FlowFilterType },
  { label: '解锁', value: 'unlock' as FlowFilterType },
];

const activeFilter = ref<FlowFilterType>('all');
const flowList = ref<InventoryLogItem[]>([]);
const page = ref(1);
const total = ref(0);
const loading = ref(false);

/** 是否有更多数据 */
const hasMore = computed(() => flowList.value.length < total.value);

/** 按月份分组 */
interface FlowGroup {
  month: string;
  items: InventoryLogItem[];
}

const groupedFlows = computed<FlowGroup[]>(() => {
  const map = new Map<string, InventoryLogItem[]>();
  for (const log of flowList.value) {
    const month = log.created_at.substring(0, 7); // YYYY-MM
    const key = formatMonthLabel(month);
    if (!map.has(key)) {
      map.set(key, []);
    }
    map.get(key)!.push(log);
  }
  return Array.from(map.entries()).map(([month, items]) => ({ month, items }));
});

/** 格式化月份标签 */
function formatMonthLabel(ym: string): string {
  const [year, month] = ym.split('-');
  return `${year}年${parseInt(month)}月`;
}

/** 格式化流水时间 */
function formatFlowTime(dateStr: string): string {
  return dateStr.substring(5, 16); // MM-DD HH:mm
}

/** 根据筛选类型获取 log_type 参数 */
function getLogTypeParam(): InventoryLogType | undefined {
  switch (activeFilter.value) {
    case 'in':
      return InventoryLogType.PURCHASE;
    case 'out':
      return InventoryLogType.PAYMENT_CONSUME;
    case 'lock':
      return InventoryLogType.ORDER_LOCK;
    case 'unlock':
      return InventoryLogType.CANCEL_RELEASE;
    default:
      return undefined;
  }
}

/** 获取类型标签样式类 */
function getTypeClass(logType: number): string {
  if (logType === InventoryLogType.PURCHASE || logType === InventoryLogType.REFUND_RETURN) {
    return 'type--in';
  }
  if (logType === InventoryLogType.PAYMENT_CONSUME || logType === InventoryLogType.AFTER_SALE_REPLACE) {
    return 'type--out';
  }
  if (logType === InventoryLogType.ORDER_LOCK) {
    return 'type--lock';
  }
  if (logType === InventoryLogType.CANCEL_RELEASE) {
    return 'type--unlock';
  }
  return 'type--neutral';
}

/** 加载流水数据 */
async function loadFlows(isRefresh = false) {
  if (loading.value) return;
  if (!isRefresh && !hasMore.value) return;

  loading.value = true;
  if (isRefresh) {
    page.value = 1;
    flowList.value = [];
  }

  try {
    const params: InventoryLogParams = {
      page: page.value,
      page_size: 20,
    };
    const logType = getLogTypeParam();
    if (logType !== undefined) {
      params.log_type = logType;
    }

    const data = await getStockFlowList(params);
    if (isRefresh) {
      flowList.value = data.list;
    } else {
      flowList.value = [...flowList.value, ...data.list];
    }
    total.value = data.total;
  } catch {
    // 错误由统一拦截器处理
  } finally {
    loading.value = false;
  }
}

/** 切换筛选 */
function switchFilter(value: FlowFilterType) {
  if (activeFilter.value === value) return;
  activeFilter.value = value;
}

// 筛选变化时重新加载
watch(activeFilter, () => { loadFlows(true); });

// 页面加载
onLoad(() => { loadFlows(true); });

// 上拉加载更多
onReachBottom(() => {
  if (hasMore.value) {
    page.value += 1;
    loadFlows(false);
  }
});
</script>

<style lang="scss" scoped>
.flow-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  display: flex;
  flex-direction: column;
}

/* 筛选 Tab */
.filter-tabs {
  display: flex;
  background-color: $color-neutral-0;
  padding: $spacing-sm $spacing-md;
  border-bottom: 2rpx solid $color-neutral-100;
  position: sticky;
  top: 0;
  z-index: 10;
}

.filter-tab {
  flex: 1;
  text-align: center;
  padding: $spacing-sm $spacing-xs;
  font-size: $font-size-sm;
  color: $color-neutral-500;
  border-radius: $radius-full;
  transition: all 0.2s ease;

  &--active {
    background-color: $color-primary-500;
    color: $color-neutral-0;
    font-weight: $font-weight-medium;
  }

  &:active {
    opacity: 0.7;
  }
}

/* 滚动区 */
.flow-scroll {
  flex: 1;
  height: calc(100vh - 100rpx);
}

/* 月份分组 */
.flow-group {
  margin-bottom: $spacing-sm;

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: $spacing-lg $spacing-md $spacing-sm;
  }

  &__month {
    font-size: $font-size-sm;
    font-weight: $font-weight-semibold;
    color: $color-neutral-700;
  }

  &__count {
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }

  &__list {
    padding: 0 $spacing-md;
  }
}

/* 流水项 */
.flow-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
  margin-bottom: $spacing-sm;
  box-shadow: $shadow-1;

  &__left {
    display: flex;
    align-items: flex-start;
    flex: 1;
    margin-right: $spacing-md;
  }

  &__type-tag {
    padding: 4rpx 12rpx;
    border-radius: $radius-sm;
    font-size: $font-size-xs;
    font-weight: $font-weight-medium;
    margin-right: $spacing-sm;
    flex-shrink: 0;
    margin-top: 2rpx;
  }

  &__info {
    flex: 1;
    min-width: 0;
  }

  &__order {
    font-size: $font-size-sm;
    color: $color-neutral-900;
    font-family: $font-family-mono;
    display: block;
    margin-bottom: 4rpx;
  }

  &__reason {
    font-size: $font-size-xs;
    color: $color-neutral-500;
    display: block;
    margin-bottom: 4rpx;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__meta {
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }

  &__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;
  }

  &__qty {
    font-size: $font-size-lg;
    font-weight: $font-weight-bold;
    font-family: $font-family-mono;
    line-height: $line-height-tight;
  }

  &__balance {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    margin-top: 4rpx;
  }
}

/* 类型标签颜色 */
.type--in {
  background-color: $color-success-light;
  color: $color-success;
}

.type--out {
  background-color: $color-error-light;
  color: $color-error;
}

.type--lock {
  background-color: $color-warning-light;
  color: $color-warning;
}

.type--unlock {
  background-color: $color-info-light;
  color: $color-info;
}

.type--neutral {
  background-color: $color-neutral-100;
  color: $color-neutral-500;
}

/* 数量颜色 */
.qty--positive {
  color: $color-success;
}

.qty--negative {
  color: $color-error;
}

/* 骨架屏 */
.skeleton-list {
  padding: $spacing-md;
}

.skeleton-card {
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
  margin-bottom: $spacing-sm;
}

.skeleton-line {
  height: 24rpx;
  background-color: $color-neutral-100;
  border-radius: $radius-sm;
  margin-bottom: $spacing-sm;
  animation: skeleton-pulse 1.5s ease-in-out infinite;

  &--short { width: 35%; }
  &--medium { width: 65%; }
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

  &__icon {
    font-size: 96rpx;
    margin-bottom: $spacing-lg;
  }

  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-400;
  }
}

/* 加载更多 */
.loading-more {
  text-align: center;
  padding: $spacing-lg 0 $spacing-xl;

  &__text {
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }
}
</style>
