<template>
  <view class="step2-page">
    <!-- 页面标题 -->
    <view class="page-header">
      <view class="page-header__left">
        <text class="page-header__title">窗帘配置</text>
      </view>
      <text class="page-header__step">步骤 2/5</text>
    </view>

    <!-- 订单信息条 -->
    <view class="order-info-bar">
      <text class="order-info-bar__label">订单编号</text>
      <text class="order-info-bar__value">{{ orderStore.orderNo }}</text>
    </view>

    <!-- 窗帘列表 -->
    <view v-if="items.length > 0" class="items-list">
      <view
        v-for="(item, idx) in items"
        :key="item._tempId"
        class="config-card card"
      >
        <!-- 卡片头部 -->
        <view class="config-card__header" @tap="toggleFold(idx)">
          <view class="config-card__header-left">
            <text class="config-card__seq">第 {{ idx + 1 }} 副窗帘</text>
            <text v-if="item.fabric_no" class="config-card__fabric-tag">
              {{ item.fabric_no }}
            </text>
          </view>
          <view class="config-card__header-right">
            <text v-if="collapsedSet.has(idx)" class="config-card__price">
              ¥{{ formatMoney(item._itemTotal || '0') }}
            </text>
            <text class="config-card__toggle" @tap.stop="showMoreActions(idx)">⋯</text>
          </view>
        </view>

        <!-- 折叠态摘要 -->
        <view v-if="collapsedSet.has(idx) && item.fabric_no" class="config-card__summary">
          <text class="config-card__summary-text">
            {{ item.install_position || '未设置位置' }} · {{ item.width }}×{{ item.height }}cm
          </text>
        </view>

        <!-- 展开态内容 -->
        <view v-if="!collapsedSet.has(idx)" class="config-card__body">
          <!-- 面料选择 -->
          <view class="config-card__field-label">面料</view>
          <view
            class="config-card__fabric-entry"
            :class="{ selected: !!item.fabric_no }"
            @tap="selectFabric(idx)"
          >
            <view v-if="!item.fabric_no" class="config-card__fabric-placeholder">
              <text class="config-card__fabric-placeholder-text">＋ 点击选择面料</text>
            </view>
            <view v-else class="config-card__fabric-info">
              <image
                v-if="item._fabricImage"
                class="config-card__fabric-thumb"
                :src="item._fabricImage"
                mode="aspectFill"
              />
              <view class="config-card__fabric-detail">
                <text class="config-card__fabric-no">{{ item.fabric_no }}</text>
                <text class="config-card__fabric-series">{{ item._fabricSeries || '' }}</text>
                <text class="config-card__fabric-price">¥{{ item._fabricPrice || '--' }}/㎡</text>
              </view>
              <text class="config-card__fabric-arrow">修改 ›</text>
            </view>
          </view>

          <!-- 安装位置 -->
          <view class="config-card__field-label">安装位置</view>
          <view class="config-card__input-row">
            <input
              v-model="item.install_position"
              class="config-card__input"
              placeholder="如：客厅、主卧"
              maxlength="50"
            />
          </view>

          <!-- 尺寸 -->
          <view class="config-card__field-label">尺寸（cm）</view>
          <view class="config-card__size-row">
            <view class="config-card__size-input">
              <text class="config-card__size-label">宽度</text>
              <input
                v-model="item.width"
                class="config-card__size-value"
                type="digit"
                placeholder="90-350"
                @blur="validateSize(idx, 'width')"
              />
              <text class="config-card__size-unit">cm</text>
            </view>
            <text class="config-card__size-sep">×</text>
            <view class="config-card__size-input">
              <text class="config-card__size-label">高度</text>
              <input
                v-model="item.height"
                class="config-card__size-value"
                type="digit"
                placeholder="50-600"
                @blur="validateSize(idx, 'height')"
              />
              <text class="config-card__size-unit">cm</text>
            </view>
          </view>
          <text v-if="sizeError[idx]" class="config-card__error">{{ sizeError[idx] }}</text>
          <text v-if="item.width && item.height" class="config-card__area">
            面积：{{ calcArea(Number(item.width), Number(item.height)) }} ㎡
          </text>

          <!-- 轨道颜色 -->
          <view class="config-card__field-label">轨道颜色</view>
          <view class="config-card__color-row">
            <view
              v-for="color in trackColors"
              :key="color"
              class="config-card__color-item"
              :class="{ active: item.track_color === color }"
              @tap="item.track_color = color"
            >
              <view class="config-card__color-dot" :style="{ backgroundColor: colorMap[color] }" />
              <text class="config-card__color-name">{{ color }}</text>
            </view>
          </view>
        </view>
      </view>
    </view>

    <!-- 空状态 -->
    <view v-if="items.length === 0" class="empty-state">
      <text class="empty-state__icon">🪟</text>
      <text class="empty-state__text">还没有添加窗帘</text>
      <text class="empty-state__desc">点击下方按钮添加第一副窗帘配置</text>
    </view>

    <!-- 添加按钮 -->
    <view class="add-btn" @tap="addItem">
      <text class="add-btn__icon">＋</text>
      <text class="add-btn__text">添加一副窗帘</text>
    </view>

    <!-- 底部汇总 -->
    <view class="step2-page__footer safe-area-bottom">
      <view class="footer-summary">
        <text class="footer-summary__count">共 {{ items.length }} 副</text>
        <view class="footer-summary__right">
          <text class="footer-summary__label">合计</text>
          <text class="footer-summary__total">¥{{ formatMoney(totalAmount) }}</text>
        </view>
      </view>
      <view class="footer-actions">
        <button class="btn-back" @tap="handleBack">上一步</button>
        <button
          class="btn-next"
          :disabled="items.length === 0"
          @tap="handleNext"
        >下一步</button>
      </view>
    </view>

    <!-- 更多操作弹窗 -->
    <view v-if="actionSheet.show" class="action-sheet" @tap.self="actionSheet.show = false">
      <view class="action-sheet__content">
        <view class="action-sheet__item" @tap="handleCopy(actionSheet.index)">
          <text class="action-sheet__text">复制此配置</text>
        </view>
        <view class="action-sheet__item danger" @tap="handleDelete(actionSheet.index)">
          <text class="action-sheet__text danger-text">删除此配置</text>
        </view>
        <view class="action-sheet__cancel" @tap="actionSheet.show = false">
          <text class="action-sheet__cancel-text">取消</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted } from 'vue';
import { useOrderStore } from '@/stores/order';
import type { DraftOrderItem } from '@/stores/order';
import type { TrackColor } from '@/types/common';
import { formatMoney as formatMoneyUtil } from '@/utils/format';
import { calcArea as calcAreaDecimal, sumFen } from '@/utils/money';
import { isValidWidth, isValidHeight } from '@/utils/validator';
import { addOrderItem, deleteOrderItem, getOrderDetail } from '@/api/order';
import type { OrderDetail } from '@/types/order';

const orderStore = useOrderStore();

/** 轨道颜色选项 */
const trackColors: TrackColor[] = ['黑色', '白色', '灰色'];
/** 颜色映射 */
const colorMap: Record<string, string> = {
  '黑色': '#1F2937',
  '白色': '#FFFFFF',
  '灰色': '#9CA3AF',
};

/** 窗帘列表 */
const items = ref<DraftOrderItem[]>([]);

/** 折叠状态 */
const collapsedSet = ref<Set<number>>(new Set());

/** 尺寸错误信息 */
const sizeError = reactive<Record<number, string>>({});

/** 操作菜单 */
const actionSheet = reactive({
  show: false,
  index: 0,
});

/** 总金额 — 基于 Decimal 精确求和（单位：元） */
const totalAmount = computed<string>(() => {
  // 将元转分后精确求和，再转回元
  const fenValues = items.value.map((item) => {
    const yuan = Number(item._itemTotal || '0');
    return Math.round(yuan * 100);
  });
  const totalFen = sumFen(fenValues);
  return (totalFen / 100).toFixed(2);
});

/** 切换折叠 */
function toggleFold(idx: number): void {
  if (collapsedSet.value.has(idx)) {
    collapsedSet.value.delete(idx);
  } else {
    collapsedSet.value.add(idx);
  }
  // 触发响应式更新
  collapsedSet.value = new Set(collapsedSet.value);
}

/**
 * 添加一副窗帘
 */
function addItem(): void {
  orderStore.addItem({
    order_id: orderStore.orderId,
    install_position: '',
    width: 0,
    height: 0,
    track_color: '黑色' as TrackColor,
    fabric_no: '',
  });
  syncItems();
  // 折叠之前的，展开最后一个
  for (let i = 0; i < items.value.length - 1; i++) {
    collapsedSet.value.add(i);
  }
  collapsedSet.value = new Set(collapsedSet.value);
}

/**
 * 同步 orderStore 数据到本地
 */
function syncItems(): void {
  items.value = [...orderStore.items];
}

/**
 * 选择面料 — 跳转到独立面料选择页
 * @param idx 窗帘配置索引
 */
function selectFabric(idx: number): void {
  // 同时监听 uni.$on 事件（兼容方案）
  const handler = (data: { fabric_no: string; name: string; series: string; price: string; image: string; id?: number }) => {
    const item = items.value[idx];
    if (item) {
      item.fabric_no = data.fabric_no;
      item._fabricSeries = data.series;
      item._fabricPrice = data.price;
      item._fabricImage = data.image;
      orderStore.updateItem(item._tempId, { fabric_no: data.fabric_no });
    }
    uni.$off('fabric:selected', handler);
  };
  uni.$on('fabric:selected', handler);

  uni.navigateTo({
    url: `/pages/fabric/selector?index=${idx}`,
    events: {
      onFabricSelected: (data: { fabric_no: string; name: string; series: string; price: string; image: string; id?: number }) => {
        const item = items.value[idx];
        if (item) {
          item.fabric_no = data.fabric_no;
          item._fabricSeries = data.series;
          item._fabricPrice = data.price;
          item._fabricImage = data.image;
          orderStore.updateItem(item._tempId, { fabric_no: data.fabric_no });
        }
        uni.$off('fabric:selected', handler);
      },
    },
  });
}

/**
 * 校验尺寸
 */
function validateSize(idx: number, field: 'width' | 'height'): void {
  const item = items.value[idx];
  if (!item) return;

  const val = Number(field === 'width' ? item.width : item.height);
  const key = `${idx}`;

  if (field === 'width' && item.width && !isValidWidth(val)) {
    sizeError[key] = '宽度需在 90~350cm 之间';
  } else if (field === 'height' && item.height && !isValidHeight(val)) {
    sizeError[key] = '高度需在 50~600cm 之间';
  } else {
    delete sizeError[key];
  }
}

/**
 * 计算面积 — 委托 money.ts 的 Decimal 精确计算
 */
function calcArea(w: number, h: number): string {
  return calcAreaDecimal(w, h);
}

/**
 * 显示更多操作
 */
function showMoreActions(idx: number): void {
  actionSheet.index = idx;
  actionSheet.show = true;
}

/**
 * 复制配置
 */
function handleCopy(sourceIdx: number): void {
  actionSheet.show = false;
  const source = items.value[sourceIdx];
  if (!source) return;

  orderStore.addItem({
    order_id: orderStore.orderId,
    install_position: source.install_position,
    width: source.width,
    height: source.height,
    track_color: source.track_color,
    fabric_no: source.fabric_no,
    power_type: source.power_type,
    remote_type: source.remote_type,
    wall_control_type: source.wall_control_type,
    wall_control_quantity: source.wall_control_quantity,
    use_inventory: source.use_inventory,
  });

  // 复制额外展示数据
  const newItem = orderStore.items[orderStore.items.length - 1];
  if (newItem && source._fabricImage) {
    (newItem as DraftOrderItem & { _fabricImage?: string; _fabricSeries?: string; _fabricPrice?: string; _itemTotal?: string })._fabricImage = source._fabricImage;
    (newItem as DraftOrderItem & { _fabricSeries?: string })._fabricSeries = source._fabricSeries;
    (newItem as DraftOrderItem & { _fabricPrice?: string })._fabricPrice = source._fabricPrice;
    (newItem as DraftOrderItem & { _itemTotal?: string })._itemTotal = source._itemTotal;
  }

  syncItems();
  uni.showToast({ title: '已复制配置', icon: 'success' });
}

/**
 * 删除配置
 */
function handleDelete(idx: number): void {
  actionSheet.show = false;
  const item = items.value[idx];
  if (!item) return;

  uni.showModal({
    title: '删除确认',
    content: `确定删除第 ${idx + 1} 副窗帘？此操作不可撤销`,
    confirmText: '删除',
    confirmColor: '#DC2626',
    success: async (res) => {
      if (res.confirm) {
        // 如果已保存到服务端，调用删除接口
        if (item._saved && item._itemId) {
          try {
            await deleteOrderItem(item._itemId);
          } catch {
            /* handled */
          }
        }
        orderStore.removeItem(item._tempId);
        syncItems();
        // 重新整理折叠状态
        collapsedSet.value = new Set();
        uni.showToast({ title: '已删除', icon: 'success' });
      }
    },
  });
}

/**
 * 上一步
 */
function handleBack(): void {
  uni.navigateBack();
}

/**
 * 下一步
 */
function handleNext(): void {
  if (items.value.length === 0) {
    uni.showToast({ title: '请至少添加一副窗帘', icon: 'none' });
    return;
  }

  // 校验必填项
  for (let i = 0; i < items.value.length; i++) {
    const item = items.value[i];
    if (!item.fabric_no) {
      uni.showToast({ title: `第 ${i + 1} 副窗帘未选择面料`, icon: 'none' });
      return;
    }
    if (!item.install_position.trim()) {
      uni.showToast({ title: `第 ${i + 1} 副窗帘未填写安装位置`, icon: 'none' });
      return;
    }
    const w = Number(item.width);
    const h = Number(item.height);
    if (!w || !isValidWidth(w)) {
      uni.showToast({ title: `第 ${i + 1} 副窗帘宽度不在 90~350cm 范围内`, icon: 'none' });
      return;
    }
    if (!h || !isValidHeight(h)) {
      uni.showToast({ title: `第 ${i + 1} 副窗帘高度不在 50~600cm 范围内`, icon: 'none' });
      return;
    }
  }

  uni.navigateTo({ url: '/pages/order/create-step4' });
}

/**
 * 格式化金额
 */
function formatMoney(val: string): string {
  return formatMoneyUtil(val);
}

/**
 * 初始化 — 尝试恢复服务端数据
 */
onMounted(async () => {
  syncItems();
  // 如果 orderStore 中没有数据但已有 orderId，尝试从服务端恢复
  if (orderStore.orderId && items.value.length === 0) {
    try {
      const detail: OrderDetail = await getOrderDetail(orderStore.orderId);
      if (detail.items && detail.items.length > 0) {
        for (const serverItem of detail.items) {
          orderStore.addItem({
            order_id: orderStore.orderId,
            install_position: serverItem.install_position,
            width: Number(serverItem.width),
            height: Number(serverItem.height),
            track_color: serverItem.track_color,
            fabric_no: serverItem.fabric_no,
            power_type: serverItem.power_type,
            remote_type: serverItem.remote_type,
            wall_control_type: serverItem.wall_control_type,
            wall_control_quantity: serverItem.wall_control_quantity,
            use_inventory: serverItem.use_inventory,
          });
          // 标记为已保存并设置 itemId
          const newItem = orderStore.items[orderStore.items.length - 1];
          newItem._saved = true;
          newItem._itemId = serverItem.item_id;
          (newItem as DraftOrderItem & { _itemTotal?: string; _fabricSeries?: string })._itemTotal = serverItem.item_total;
          (newItem as DraftOrderItem & { _fabricSeries?: string })._fabricSeries = serverItem.fabric_name;
        }
        syncItems();
      }
    } catch {
      /* 恢复失败时使用本地数据 */
    }
  }
});
</script>

<style lang="scss" scoped>
.step2-page {
  padding: $spacing-lg;
  padding-bottom: 280rpx;
  min-height: 100vh;
  background-color: $color-neutral-50;

  &__footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: $spacing-md $spacing-lg;
    background-color: $color-neutral-0;
    box-shadow: $shadow-2;
    z-index: 100;
  }
}

// ── 页面标题 ──
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: $spacing-md;

  &__title {
    font-size: $font-size-2xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
  }
  &__step {
    font-size: $font-size-sm;
    color: $color-neutral-400;
    background-color: $color-neutral-100;
    padding: 4rpx 16rpx;
    border-radius: $radius-full;
  }
}

// ── 订单信息条 ──
.order-info-bar {
  display: flex;
  align-items: center;
  padding: $spacing-sm $spacing-lg;
  background-color: $color-primary-50;
  border-radius: $radius-md;
  margin-bottom: $spacing-lg;

  &__label {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    margin-right: $spacing-md;
  }
  &__value {
    font-size: $font-size-sm;
    color: $color-primary-500;
    font-family: $font-family-mono;
    font-weight: $font-weight-medium;
  }
}

// ── 配置卡片 ──
.config-card {
  margin-bottom: $spacing-md;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: $spacing-sm;

    &-left {
      display: flex;
      align-items: center;
      gap: $spacing-sm;
    }
    &-right {
      display: flex;
      align-items: center;
      gap: $spacing-md;
    }
  }
  &__seq {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }
  &__fabric-tag {
    font-size: $font-size-xs;
    color: $color-primary-500;
    background-color: $color-primary-50;
    padding: 2rpx 12rpx;
    border-radius: $radius-sm;
    font-family: $font-family-mono;
  }
  &__price {
    font-size: $font-size-base;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }
  &__toggle {
    font-size: $font-size-xl;
    color: $color-neutral-400;
    padding: 0 $spacing-xs;
  }

  &__summary {
    padding: $spacing-xs 0 $spacing-sm;
    border-top: 2rpx solid $color-neutral-100;

    &-text {
      font-size: $font-size-sm;
      color: $color-neutral-500;
    }
  }

  &__body {
    padding-top: $spacing-sm;
    border-top: 2rpx solid $color-neutral-100;
  }

  &__field-label {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    margin-bottom: $spacing-sm;
    margin-top: $spacing-md;

    &:first-child {
      margin-top: 0;
    }
  }

  // ── 面料选择 ──
  &__fabric-entry {
    border: 2rpx solid $color-neutral-200;
    border-radius: $radius-md;
    overflow: hidden;
    transition: border-color 0.2s;

    &.selected {
      border-color: $color-primary-300;
      background-color: $color-primary-50;
    }
  }
  &__fabric-placeholder {
    padding: $spacing-xl;
    display: flex;
    align-items: center;
    justify-content: center;

    &-text {
      font-size: $font-size-base;
      color: $color-neutral-400;
    }
  }
  &__fabric-info {
    display: flex;
    align-items: center;
    padding: $spacing-md;
  }
  &__fabric-thumb {
    width: 96rpx;
    height: 96rpx;
    border-radius: $radius-md;
    margin-right: $spacing-md;
    flex-shrink: 0;
  }
  &__fabric-detail {
    flex: 1;
    display: flex;
    flex-direction: column;
  }
  &__fabric-no {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }
  &__fabric-series {
    font-size: $font-size-xs;
    color: $color-neutral-500;
    margin-top: 4rpx;
  }
  &__fabric-price {
    font-size: $font-size-sm;
    color: $color-primary-500;
    font-weight: $font-weight-medium;
    margin-top: 4rpx;
  }
  &__fabric-arrow {
    font-size: $font-size-sm;
    color: $color-primary-500;
    flex-shrink: 0;
  }

  // ── 输入行 ──
  &__input-row {
    background-color: $color-neutral-50;
    border-radius: $radius-md;
    padding: 0 $spacing-md;
  }
  &__input {
    height: 80rpx;
    font-size: $font-size-base;
    color: $color-neutral-900;
    width: 100%;
  }

  // ── 尺寸输入 ──
  &__size-row {
    display: flex;
    align-items: center;
    gap: $spacing-md;
  }
  &__size-input {
    flex: 1;
    display: flex;
    align-items: center;
    background-color: $color-neutral-50;
    border-radius: $radius-md;
    padding: 0 $spacing-md;
    height: 80rpx;
  }
  &__size-label {
    font-size: $font-size-sm;
    color: $color-neutral-400;
    margin-right: $spacing-sm;
    flex-shrink: 0;
  }
  &__size-value {
    flex: 1;
    font-size: $font-size-base;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }
  &__size-unit {
    font-size: $font-size-sm;
    color: $color-neutral-400;
    margin-left: $spacing-xs;
  }
  &__size-sep {
    font-size: $font-size-lg;
    color: $color-neutral-300;
  }
  &__error {
    font-size: $font-size-xs;
    color: $color-error;
    margin-top: $spacing-xs;
  }
  &__area {
    font-size: $font-size-xs;
    color: $color-neutral-500;
    margin-top: $spacing-xs;
    font-family: $font-family-mono;
  }

  // ── 轨道颜色 ──
  &__color-row {
    display: flex;
    gap: $spacing-md;
  }
  &__color-item {
    display: flex;
    align-items: center;
    padding: $spacing-sm $spacing-md;
    border-radius: $radius-md;
    border: 2rpx solid $color-neutral-200;
    transition: all 0.2s;

    &.active {
      border-color: $color-primary-500;
      background-color: $color-primary-50;
    }
  }
  &__color-dot {
    width: 28rpx;
    height: 28rpx;
    border-radius: $radius-full;
    margin-right: $spacing-sm;
    border: 2rpx solid $color-neutral-200;
  }
  &__color-name {
    font-size: $font-size-sm;
    color: $color-neutral-700;
  }
}

// ── 空状态 ──
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: $spacing-2xl $spacing-lg;
  margin-bottom: $spacing-md;

  &__icon {
    font-size: 80rpx;
    margin-bottom: $spacing-md;
  }
  &__text {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-700;
    margin-bottom: $spacing-xs;
  }
  &__desc {
    font-size: $font-size-sm;
    color: $color-neutral-400;
  }
}

// ── 添加按钮 ──
.add-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: $spacing-lg;
  border: 2rpx dashed $color-neutral-300;
  border-radius: $radius-lg;
  margin-bottom: $spacing-md;
  transition: all 0.2s;

  &:active {
    border-color: $color-primary-500;
    background-color: $color-primary-50;
  }

  &__icon {
    font-size: 32rpx;
    color: $color-neutral-500;
    margin-right: $spacing-sm;
  }
  &__text {
    font-size: $font-size-base;
    color: $color-neutral-600;
  }
}

// ── 底部汇总 ──
.footer-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: $spacing-md;
  border-bottom: 2rpx solid $color-neutral-100;
  margin-bottom: $spacing-md;

  &__count {
    font-size: $font-size-sm;
    color: $color-neutral-500;
  }
  &__right {
    display: flex;
    align-items: baseline;
    gap: $spacing-sm;
  }
  &__label {
    font-size: $font-size-sm;
    color: $color-neutral-500;
  }
  &__total {
    font-size: $font-size-2xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }
}

.footer-actions {
  display: flex;
  gap: $spacing-md;
}

.btn-back {
  flex: 1;
  height: 88rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: $color-neutral-100;
  color: $color-neutral-700;
  font-size: $font-size-base;
  font-weight: $font-weight-medium;
  border-radius: $radius-md;
  border: none;
}

.btn-next {
  flex: 2;
  height: 88rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: $color-primary-500;
  color: $color-neutral-0;
  font-size: $font-size-base;
  font-weight: $font-weight-semibold;
  border-radius: $radius-md;
  border: none;

  &[disabled] {
    background-color: $color-neutral-200;
    color: $color-neutral-400;
  }
}

// ── ActionSheet ──
.action-sheet {
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
    padding-bottom: $safe-area-bottom;
  }
  &__item {
    padding: $spacing-xl $spacing-lg;
    text-align: center;
    border-bottom: 2rpx solid $color-neutral-100;

    &.danger {
      // 危险操作样式
    }
  }
  &__text {
    font-size: $font-size-base;
    color: $color-neutral-800;
  }
  &__cancel {
    padding: $spacing-xl $spacing-lg;
    text-align: center;
    margin-top: $spacing-sm;
    border-top: 12rpx solid $color-neutral-100;
  }
  &__cancel-text {
    font-size: $font-size-base;
    color: $color-neutral-500;
  }
}
.danger-text {
  color: $color-error;
}
</style>
