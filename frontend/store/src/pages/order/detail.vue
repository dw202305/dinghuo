<template>
  <view class="detail-page">
    <!-- 加载中 -->
    <view v-if="loading && !order" class="loading-state">
      <text class="loading-text">加载中...</text>
    </view>

    <template v-if="order">
      <!-- 顶部状态卡片 -->
      <view class="status-card" :class="'status-card--' + getStatusTheme(order.order_status)">
        <text class="status-card__icon">{{ getStatusIcon(order.order_status) }}</text>
        <text class="status-card__text">{{ order.order_status_text }}</text>
        <text class="status-card__hint">{{ getStatusHint(order.order_status) }}</text>
      </view>

      <!-- 订单基本信息 -->
      <view class="card">
        <view class="card__title">订单信息</view>
        <view class="info-row">
          <text class="info-row__label">订单号</text>
          <text class="info-row__value info-row__value--mono">{{ order.order_no }}</text>
        </view>
        <view class="info-row">
          <text class="info-row__label">创建时间</text>
          <text class="info-row__value">{{ formatDate(order.created_at, 'YYYY-MM-DD HH:mm') }}</text>
        </view>
        <view v-if="order.expected_delivery_date" class="info-row">
          <text class="info-row__label">预计交期</text>
          <text class="info-row__value">{{ order.expected_delivery_date }}</text>
        </view>
        <view class="info-row">
          <text class="info-row__label">收货方式</text>
          <text class="info-row__value">{{ order.delivery_method_text }}</text>
        </view>
        <view v-if="order.remark" class="info-row">
          <text class="info-row__label">备注</text>
          <text class="info-row__value">{{ order.remark }}</text>
        </view>
      </view>

      <!-- 收货地址 -->
      <view class="card">
        <view class="card__title">📍 收货地址</view>
        <view class="address-block">
          <text class="address-block__name">
            {{ order.receiver.name }}
            <text class="address-block__phone">{{ order.receiver.phone }}</text>
          </text>
          <text class="address-block__detail">
            {{ order.receiver.province }}{{ order.receiver.city }}{{ order.receiver.district }}{{ order.receiver.detail }}
          </text>
        </view>
      </view>

      <!-- 窗帘明细 -->
      <view class="card">
        <view class="card__title">
          窗帘明细
          <text class="card__subtitle">（共{{ order.summary.item_count }}副）</text>
        </view>

        <view
          v-for="item in order.items"
          :key="item.item_id"
          class="item-card"
        >
          <!-- 安装位置 + 尺寸 -->
          <view class="item-card__header">
            <text class="item-card__position">{{ item.install_position }}</text>
            <text class="item-card__subtotal">¥{{ formatMoney(item.item_total) }}</text>
          </view>
          <view class="item-card__size">
            {{ item.width }} × {{ item.height }}cm · {{ item.area }}㎡
          </view>

          <!-- 轨道 -->
          <view class="item-card__detail-row">
            <text class="detail-label">轨道</text>
            <text class="detail-value">{{ item.track_color }} · ¥{{ formatMoney(item.track_amount) }}</text>
          </view>

          <!-- 面料 -->
          <view class="item-card__detail-row">
            <text class="detail-label">面料</text>
            <text class="detail-value">{{ item.fabric_name }} · ¥{{ formatMoney(item.fabric_amount) }}</text>
          </view>

          <!-- 电源 -->
          <view class="item-card__detail-row">
            <text class="detail-label">电源</text>
            <text class="detail-value">{{ item.power_type_text }} · ¥{{ formatMoney(item.power_surcharge) }}</text>
          </view>

          <!-- 遥控器 -->
          <view class="item-card__detail-row">
            <text class="detail-label">遥控器</text>
            <text class="detail-value">{{ item.remote_type_text }} · ¥{{ formatMoney(item.remote_surcharge) }}</text>
          </view>

          <!-- 墙控开关 -->
          <view v-if="item.wall_control_type !== 0" class="item-card__detail-row">
            <text class="detail-label">墙控</text>
            <text class="detail-value">{{ item.wall_control_type_text }} ×{{ item.wall_control_quantity }} · ¥{{ formatMoney(item.wall_control_amount) }}</text>
          </view>

          <!-- 配件 -->
          <view class="item-card__detail-row">
            <text class="detail-label">配件</text>
            <text class="detail-value">¥{{ formatMoney(item.accessory_amount) }}</text>
          </view>

          <!-- 库存套件 -->
          <view v-if="item.use_inventory" class="item-card__detail-row">
            <text class="detail-label">库存抵扣</text>
            <text class="detail-value detail-value--success">-¥{{ formatMoney(item.kit_amount) }}</text>
          </view>

          <!-- 非标加价 -->
          <view v-if="Number(item.nonstandard_amount) > 0" class="item-card__detail-row">
            <text class="detail-label">非标加价</text>
            <text class="detail-value detail-value--warning">+¥{{ formatMoney(item.nonstandard_amount) }}</text>
          </view>
        </view>
      </view>

      <!-- 费用汇总 -->
      <view class="card">
        <view class="card__title">💰 费用汇总</view>
        <view class="info-row">
          <text class="info-row__label">轨道总额</text>
          <text class="info-row__value">¥{{ formatMoney(order.summary.track_amount) }}</text>
        </view>
        <view class="info-row">
          <text class="info-row__label">面料总额（{{ order.summary.fabric_area_total }}㎡）</text>
          <text class="info-row__value">¥{{ formatMoney(order.summary.fabric_amount) }}</text>
        </view>
        <view class="info-row">
          <text class="info-row__label">配件总额</text>
          <text class="info-row__value">¥{{ formatMoney(order.summary.accessory_amount) }}</text>
        </view>
        <view v-if="order.summary.inventory_used_count > 0" class="info-row">
          <text class="info-row__label">库存抵扣（{{ order.summary.inventory_used_count }}套）</text>
          <text class="info-row__value info-row__value--success">-¥{{ formatMoney(order.summary.new_purchase_amount) }}</text>
        </view>
        <view v-if="Number(order.summary.nonstandard_amount) > 0" class="info-row">
          <text class="info-row__label">非标加价</text>
          <text class="info-row__value info-row__value--warning">+¥{{ formatMoney(order.summary.nonstandard_amount) }}</text>
        </view>
        <view v-if="Number(order.summary.discount_amount) > 0" class="info-row">
          <text class="info-row__label">折扣</text>
          <text class="info-row__value info-row__value--success">-¥{{ formatMoney(order.summary.discount_amount) }}</text>
        </view>
        <view class="info-row info-row--total">
          <text class="info-row__label">合计</text>
          <text class="info-row__value info-row__value--total">¥{{ formatMoney(order.summary.total_amount) }}</text>
        </view>
      </view>

      <!-- 支付信息 -->
      <view class="card">
        <view class="card__title">💳 支付信息</view>
        <view class="info-row">
          <text class="info-row__label">支付状态</text>
          <view class="status-tag" :class="getPaymentStatusClass(order.payment.payment_status)">
            <text>{{ order.payment.payment_status_text }}</text>
          </view>
        </view>
        <view class="info-row">
          <text class="info-row__label">已付金额</text>
          <text class="info-row__value">¥{{ formatMoney(order.payment.paid_amount) }}</text>
        </view>
      </view>

      <!-- 底部占位 -->
      <view v-if="showActions" class="bottom-placeholder" />

      <!-- 底部操作按钮 -->
      <view v-if="showActions" class="action-bar safe-area-bottom">
        <button
          v-if="canCancel"
          class="action-btn action-btn--danger"
          @tap="handleCancel"
        >取消订单</button>
        <button
          v-if="canPay"
          class="action-btn action-btn--primary"
          @tap="handlePay"
        >去支付</button>
        <button
          v-if="canConfirmReceipt"
          class="action-btn action-btn--primary"
          @tap="handleConfirmReceipt"
        >确认签收</button>
      </view>
    </template>
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { getOrderDetail, cancelOrder, confirmReceipt } from '@/api/order';
import { formatDate, formatMoney } from '@/utils/format';
import type { OrderDetail } from '@/types/order';
import { OrderStatus, PaymentStatus } from '@/types/common';

const order = ref<OrderDetail | null>(null);
const loading = ref(true);
const orderId = ref(0);

/** 是否显示底部操作 */
const showActions = computed(() => canPay.value || canCancel.value || canConfirmReceipt.value);

/** 待支付状态 */
const canPay = computed(() => {
  if (!order.value) return false;
  return order.value.order_status === OrderStatus.PENDING_PAYMENT;
});

/** 可取消状态 */
const canCancel = computed(() => {
  if (!order.value) return false;
  return [
    OrderStatus.DRAFT,
    OrderStatus.PENDING_PAYMENT,
  ].includes(order.value.order_status);
});

/** 可确认签收 */
const canConfirmReceipt = computed(() => {
  if (!order.value) return false;
  return order.value.order_status === OrderStatus.SHIPPED;
});

/** 加载订单详情 */
async function loadDetail() {
  if (!orderId.value) return;
  loading.value = true;
  try {
    order.value = await getOrderDetail(orderId.value);
  } catch {
    uni.showToast({ title: '加载失败', icon: 'none' });
  } finally {
    loading.value = false;
  }
}

/** 去支付 */
function handlePay() {
  uni.navigateTo({ url: `/pages/payment/index?order_id=${orderId.value}` });
}

/** 取消订单 */
function handleCancel() {
  uni.showModal({
    title: '确认取消',
    content: '确定要取消此订单吗？取消后不可恢复。',
    confirmColor: '#DC2626',
    success: async (res) => {
      if (res.confirm) {
        try {
          await cancelOrder(orderId.value, '门店主动取消');
          uni.showToast({ title: '订单已取消', icon: 'success' });
          loadDetail();
        } catch {
          // 错误由拦截器处理
        }
      }
    },
  });
}

/** 确认签收 */
function handleConfirmReceipt() {
  uni.showModal({
    title: '确认签收',
    content: '确认已收到全部货物？',
    success: async (res) => {
      if (res.confirm) {
        try {
          await confirmReceipt(orderId.value);
          uni.showToast({ title: '已确认签收', icon: 'success' });
          loadDetail();
        } catch {
          // 错误由拦截器处理
        }
      }
    },
  });
}

/** 获取状态主题色 */
function getStatusTheme(status: number): string {
  if (status === OrderStatus.PENDING_PAYMENT) return 'warning';
  if (status === OrderStatus.IN_PRODUCTION || status === OrderStatus.IN_QUALITY_CHECK || status === OrderStatus.APPROVED_PENDING_SCHEDULE) return 'info';
  if (status === OrderStatus.SHIPPED || status === OrderStatus.RECEIVED || status === OrderStatus.COMPLETED) return 'success';
  if (status === OrderStatus.CANCELLED) return 'error';
  return 'neutral';
}

/** 获取状态图标 */
function getStatusIcon(status: number): string {
  if (status === OrderStatus.PENDING_PAYMENT) return '💰';
  if (status === OrderStatus.IN_PRODUCTION || status === OrderStatus.IN_QUALITY_CHECK) return '🏭';
  if (status === OrderStatus.SHIPPED) return '🚚';
  if (status === OrderStatus.RECEIVED || status === OrderStatus.COMPLETED) return '✅';
  if (status === OrderStatus.CANCELLED) return '❌';
  if (status === OrderStatus.AFTER_SALE_PROCESSING) return '🔧';
  return '📋';
}

/** 获取操作提示 */
function getStatusHint(status: number): string {
  if (status === OrderStatus.PENDING_PAYMENT) return '请尽快完成支付，订单将进入审核流程';
  if (status === OrderStatus.PAID_PENDING_REVIEW) return '已支付，等待总部审核确认';
  if (status === OrderStatus.NEED_STORE_CONFIRM) return '请确认订单定制内容，确认后进入生产';
  if (status === OrderStatus.IN_PRODUCTION) return '订单正在生产中，请耐心等待';
  if (status === OrderStatus.SHIPPED) return '货物已发出，请确认签收';
  if (status === OrderStatus.COMPLETED) return '订单已完成，感谢您的信任';
  return '';
}

/** 支付状态样式 */
function getPaymentStatusClass(status: number): string {
  if (status === PaymentStatus.PAID) return 'status-tag--success';
  if (status === PaymentStatus.PARTIAL) return 'status-tag--warning';
  return 'status-tag--neutral';
}

onLoad((options) => {
  if (options?.order_id) {
    orderId.value = Number(options.order_id);
    loadDetail();
  }
});
</script>

<style lang="scss" scoped>
.detail-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  padding-bottom: $spacing-xl;
}

/* 加载中 */
.loading-state {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
}

.loading-text {
  font-size: $font-size-sm;
  color: $color-neutral-400;
}

/* 状态卡片 */
.status-card {
  padding: $spacing-xl $spacing-lg;
  margin: $spacing-md;
  border-radius: $radius-lg;
  display: flex;
  flex-direction: column;
  align-items: center;

  &--warning { background-color: $color-warning-light; }
  &--info { background-color: $color-info-light; }
  &--success { background-color: $color-success-light; }
  &--neutral { background-color: $color-neutral-100; }
  &--error { background-color: $color-error-light; }

  &__icon {
    font-size: 72rpx;
    margin-bottom: $spacing-sm;
  }

  &__text {
    font-size: $font-size-xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    margin-bottom: $spacing-xs;
  }

  &__hint {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    text-align: center;
  }
}

/* 卡片内容 */
.card__title {
  font-size: $font-size-base;
  font-weight: $font-weight-semibold;
  color: $color-neutral-900;
  margin-bottom: $spacing-md;
}

.card__subtitle {
  font-size: $font-size-sm;
  font-weight: $font-weight-regular;
  color: $color-neutral-400;
}

/* 页面内卡片间距覆盖 */
.detail-page .card {
  margin: 0 $spacing-md $spacing-md;
}

/* 信息行 */
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: $spacing-sm 0;

  &__label {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    flex-shrink: 0;
  }

  &__value {
    font-size: $font-size-sm;
    color: $color-neutral-900;
    text-align: right;

    &--mono {
      font-family: $font-family-mono;
      color: $color-neutral-600;
    }

    &--success {
      color: $color-success;
      font-weight: $font-weight-medium;
    }

    &--warning {
      color: $color-warning;
      font-weight: $font-weight-medium;
    }

    &--total {
      font-size: $font-size-xl;
      font-weight: $font-weight-bold;
      color: $color-primary-500;
      font-family: $font-family-mono;
    }
  }

  &--total {
    border-top: 2rpx solid $color-neutral-200;
    margin-top: $spacing-sm;
    padding-top: $spacing-md;
  }
}

/* 地址 */
.address-block {
  &__name {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    display: block;
    margin-bottom: $spacing-xs;
  }

  &__phone {
    font-size: $font-size-sm;
    font-weight: $font-weight-regular;
    color: $color-neutral-500;
    margin-left: $spacing-md;
  }

  &__detail {
    font-size: $font-size-sm;
    color: $color-neutral-600;
    line-height: $line-height-relaxed;
  }
}

/* 窗帘明细卡 */
.item-card {
  background-color: $color-neutral-50;
  border-radius: $radius-md;
  padding: $spacing-md;
  margin-bottom: $spacing-sm;

  &:last-child {
    margin-bottom: 0;
  }

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $spacing-xs;
  }

  &__position {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__subtotal {
    font-size: $font-size-base;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }

  &__size {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    margin-bottom: $spacing-sm;
    font-family: $font-family-mono;
  }

  &__detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4rpx 0;
  }
}

.detail-label {
  font-size: $font-size-sm;
  color: $color-neutral-400;
}

.detail-value {
  font-size: $font-size-sm;
  color: $color-neutral-700;

  &--success { color: $color-success; }
  &--warning { color: $color-warning; }
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
  &--success {
    background-color: $color-success-light;
    color: $color-success;
  }
  &--neutral {
    background-color: $color-neutral-100;
    color: $color-neutral-500;
  }
}

/* 底部操作栏 */
.bottom-placeholder {
  height: 140rpx;
}

.action-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  gap: $spacing-md;
  padding: $spacing-md $spacing-lg;
  background-color: $color-neutral-0;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.05);
}

.action-btn {
  flex: 1;
  height: 88rpx;
  line-height: 88rpx;
  border-radius: $radius-md;
  font-size: $font-size-base;
  font-weight: $font-weight-semibold;
  border: none;

  &--primary {
    background-color: $color-primary-500;
    color: $color-neutral-0;
  }

  &--danger {
    background-color: $color-neutral-0;
    color: $color-error;
    border: 2rpx solid $color-error;
  }

  &[disabled] {
    background-color: $color-neutral-200;
    color: $color-neutral-400;
  }
}
</style>
