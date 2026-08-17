<template>
  <view class="result-page">
    <!-- 结果图标 -->
    <view class="result-icon-wrap">
      <view class="result-icon" :class="isSuccess ? 'result-icon--success' : 'result-icon--error'">
        <text class="result-icon__symbol">{{ isSuccess ? '✓' : '✕' }}</text>
      </view>
    </view>

    <!-- 结果文字 -->
    <text class="result-page__title">{{ isSuccess ? '支付成功' : '支付失败' }}</text>

    <!-- 支付金额 -->
    <text class="result-page__amount">¥{{ formatMoney(payAmount) }}</text>

    <!-- 失败原因 -->
    <text v-if="!isSuccess && errorMsg" class="result-page__error">{{ errorMsg }}</text>

    <!-- 订单信息（成功时） -->
    <view v-if="isSuccess" class="card result-info">
      <view class="info-row">
        <text class="info-label">订单编号</text>
        <text class="info-value info-value--mono">{{ orderId }}</text>
      </view>
      <view v-if="paymentNo" class="info-row">
        <text class="info-label">支付单号</text>
        <text class="info-value info-value--mono">{{ paymentNo }}</text>
      </view>
      <view class="info-row">
        <text class="info-label">支付方式</text>
        <text class="info-value">{{ payChannelText }}</text>
      </view>
    </view>

    <!-- 操作按钮 -->
    <view class="result-actions">
      <!-- 成功 -->
      <template v-if="isSuccess">
        <button class="action-btn action-btn--primary" @tap="goOrderDetail">查看订单</button>
        <button class="action-btn action-btn--secondary" @tap="goHome">继续下单</button>
      </template>

      <!-- 失败 -->
      <template v-else>
        <button class="action-btn action-btn--primary" @tap="retryPay">重新支付</button>
        <button class="action-btn action-btn--ghost" @tap="goOrderList">稍后支付</button>
        <button class="action-btn action-btn--text" @tap="contactService">联系客服</button>
      </template>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { formatMoney } from '@/utils/format';

/** 页面参数 */
const orderId = ref('');
const payAmount = ref('0.00');
const paymentNo = ref('');
const payChannelText = ref('微信支付');

/** 状态参数 */
const status = ref('success');
const errorMsg = ref('');

/** 是否支付成功 */
const isSuccess = computed(() => status.value === 'success');

/** 查看订单详情 */
function goOrderDetail() {
  if (orderId.value) {
    uni.redirectTo({ url: `/pages/order/detail?order_id=${orderId.value}` });
  } else {
    uni.switchTab({ url: '/pages/order/list' });
  }
}

/** 返回首页/工作台 */
function goHome() {
  uni.switchTab({ url: '/pages/index/index' });
}

/** 重新支付 */
function retryPay() {
  if (orderId.value) {
    uni.redirectTo({
      url: `/pages/payment/index?order_id=${orderId.value}&amount=${payAmount.value}`,
    });
  }
}

/** 去订单列表（稍后支付） */
function goOrderList() {
  uni.switchTab({ url: '/pages/order/list' });
}

/** 联系客服 */
function contactService() {
  uni.showModal({
    title: '联系客服',
    content: '请拨打客服电话：400-XXX-XXXX\n服务时间：9:00-18:00',
    showCancel: true,
    cancelText: '关闭',
    confirmText: '拨打',
    success: (res) => {
      if (res.confirm) {
        uni.makePhoneCall({ phoneNumber: '400-XXX-XXXX' });
      }
    },
  });
}

onLoad((options) => {
  if (options?.status) status.value = options.status;
  if (options?.order_id) orderId.value = options.order_id;
  if (options?.amount) payAmount.value = options.amount;
  if (options?.payment_no) paymentNo.value = options.payment_no;
  if (options?.error_msg) errorMsg.value = decodeURIComponent(options.error_msg);
  if (options?.pay_channel) {
    payChannelText.value = options.pay_channel === '2' ? '支付宝' : '微信支付';
  }
});
</script>

<style lang="scss" scoped>
.result-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 120rpx $spacing-lg $spacing-xl;
}

/* 结果图标 */
.result-icon-wrap {
  margin-bottom: $spacing-lg;
}

.result-icon {
  width: 140rpx;
  height: 140rpx;
  border-radius: $radius-full;
  display: flex;
  align-items: center;
  justify-content: center;

  &--success {
    background-color: $color-success;
  }

  &--error {
    background-color: $color-error;
  }

  &__symbol {
    font-size: 64rpx;
    color: $color-neutral-0;
    font-weight: $font-weight-bold;
  }
}

/* 结果文字 */
.result-page {
  &__title {
    font-size: $font-size-xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    margin-bottom: $spacing-sm;
  }

  &__amount {
    font-size: $font-size-2xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
    margin-bottom: $spacing-md;
  }

  &__error {
    font-size: $font-size-sm;
    color: $color-error;
    text-align: center;
    margin-bottom: $spacing-lg;
    line-height: $line-height-relaxed;
  }
}

/* 订单信息卡 */
.result-info {
  width: 100%;
  margin-top: $spacing-md;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: $spacing-xs 0;
}

.info-label {
  font-size: $font-size-sm;
  color: $color-neutral-500;
}

.info-value {
  font-size: $font-size-sm;
  color: $color-neutral-900;

  &--mono {
    font-family: $font-family-mono;
    color: $color-neutral-600;
  }
}

/* 操作按钮 */
.result-actions {
  width: 100%;
  margin-top: $spacing-2xl;
}

.action-btn {
  width: 100%;
  height: 96rpx;
  line-height: 96rpx;
  border-radius: $radius-md;
  font-size: $font-size-base;
  font-weight: $font-weight-semibold;
  margin-bottom: $spacing-md;
  border: none;

  &--primary {
    background-color: $color-primary-500;
    color: $color-neutral-0;
  }

  &--secondary {
    background-color: $color-neutral-0;
    color: $color-primary-500;
    border: 2rpx solid $color-primary-500;
  }

  &--ghost {
    background-color: $color-neutral-100;
    color: $color-neutral-700;
  }

  &--text {
    background-color: transparent;
    color: $color-neutral-500;
    font-weight: $font-weight-regular;
    font-size: $font-size-sm;
  }
}
</style>
