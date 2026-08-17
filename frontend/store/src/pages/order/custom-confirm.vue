<template>
  <view class="confirm-page">
    <!-- 非标订单提示 -->
    <view class="notice-card">
      <text class="notice-card__icon">⚠️</text>
      <text class="notice-card__title">定制商品确认</text>
      <text class="notice-card__text">
        您即将提交的订单包含定制商品。定制商品将按照您确认的配置信息进行生产，一经确认将进入生产流程。
      </text>
    </view>

    <!-- 定制声明内容（滚动阅读） -->
    <view class="statement-card card">
      <view class="card__title">📜 定制产品声明</view>
      <scroll-view scroll-y class="statement-scroll">
        <view class="statement-content">
          <text class="statement-content__item">
            1. 定制窗帘属于非标定制产品，每副窗帘的面料、尺寸、颜色、配件等均按照您确认的订单配置进行单独生产。
          </text>
          <text class="statement-content__item">
            2. 由于产品的定制属性，一旦订单确认并进入生产流程后，不支持取消订单、更换面料、修改尺寸或退换货品。
          </text>
          <text class="statement-content__item">
            3. 请您在提交订单前仔细核对以下信息：
          </text>
          <text class="statement-content__sub">• 收货地址和收件人信息</text>
          <text class="statement-content__sub">• 每副窗帘的安装位置和尺寸</text>
          <text class="statement-content__sub">• 面料选择（颜色、编号、材质）</text>
          <text class="statement-content__sub">• 轨道颜色和型号</text>
          <text class="statement-content__sub">• 选装配件（电源、遥控器、墙控开关）</text>
          <text class="statement-content__sub">• 库存套件使用情况</text>
          <text class="statement-content__item">
            4. 因定制产品的特殊性，如收到产品后发现尺寸与订单配置不符（非测量误差），请及时联系总部处理。
          </text>
          <text class="statement-content__item">
            5. 如因门店端测量数据错误导致产品尺寸不符，相关损失由门店自行承担。
          </text>
          <text class="statement-content__item">
            6. 订单确认后将锁定当前价格，后续不因调价而变动。
          </text>
        </view>
      </scroll-view>
    </view>

    <!-- 订单摘要 -->
    <view v-if="totalAmount" class="card order-summary">
      <view class="card__title">💰 订单金额</view>
      <view class="info-row">
        <text class="info-label">应付总额</text>
        <text class="info-value info-value--amount">¥{{ formatMoney(totalAmount) }}</text>
      </view>
    </view>

    <!-- 确认勾选 -->
    <view class="check-row" @tap="confirmed = !confirmed">
      <view class="checkbox" :class="{ 'checkbox--checked': confirmed }">
        <text v-if="confirmed" class="checkbox__icon">✓</text>
      </view>
      <text class="check-row__text">
        我已确认定制信息，知悉定制商品不可退换
      </text>
    </view>

    <!-- 底部占位 -->
    <view class="bottom-placeholder" />

    <!-- 底部操作 -->
    <view class="bottom-bar safe-area-bottom">
      <button class="bottom-bar__cancel" @tap="goBack">返回修改</button>
      <button
        class="bottom-bar__confirm"
        :disabled="!confirmed || submitting"
        @tap="handleConfirm"
      >{{ submitting ? '提交中...' : '确认提交' }}</button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { formatMoney } from '@/utils/format';

/** 页面参数 */
const orderId = ref(0);
const totalAmount = ref('');
const confirmed = ref(false);
const submitting = ref(false);

/** 确认提交 → 去支付 */
function handleConfirm() {
  if (!confirmed.value || submitting.value) return;
  submitting.value = true;

  try {
    // 跳转到支付页
    uni.navigateTo({
      url: `/pages/payment/index?order_id=${orderId.value}&amount=${totalAmount.value}`,
    });
  } finally {
    submitting.value = false;
  }
}

/** 返回修改 */
function goBack() {
  uni.navigateBack();
}

onLoad((options) => {
  if (options?.order_id) orderId.value = Number(options.order_id);
  if (options?.total) totalAmount.value = options.total;
});
</script>

<style lang="scss" scoped>
.confirm-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  padding: $spacing-md;
  padding-bottom: 200rpx;
}

/* 提示卡片 */
.notice-card {
  background-color: $color-warning-light;
  border-radius: $radius-md;
  padding: $spacing-lg;
  margin-bottom: $spacing-md;
  text-align: center;
  border: 2rpx solid rgba(217, 119, 6, 0.15);

  &__icon {
    font-size: 56rpx;
    display: block;
    margin-bottom: $spacing-sm;
  }

  &__title {
    font-size: $font-size-lg;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    display: block;
    margin-bottom: $spacing-sm;
  }

  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-600;
    line-height: $line-height-relaxed;
  }
}

/* 声明滚动区 */
.statement-card {
  margin-bottom: $spacing-md;
}

.statement-scroll {
  max-height: 480rpx;
  background-color: $color-neutral-50;
  border-radius: $radius-sm;
  padding: $spacing-md;
}

.statement-content {
  &__item {
    font-size: $font-size-sm;
    color: $color-neutral-700;
    line-height: $line-height-relaxed;
    display: block;
    margin-bottom: $spacing-sm;
  }

  &__sub {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    line-height: $line-height-relaxed;
    display: block;
    padding-left: $spacing-lg;
    margin-bottom: 4rpx;
  }
}

/* 订单摘要 */
.order-summary {
  margin-bottom: $spacing-md;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.info-label {
  font-size: $font-size-sm;
  color: $color-neutral-500;
}

.info-value {
  font-size: $font-size-sm;
  color: $color-neutral-900;

  &--amount {
    font-size: $font-size-xl;
    font-weight: $font-weight-bold;
    color: $color-primary-500;
    font-family: $font-family-mono;
  }
}

/* 确认勾选 */
.check-row {
  display: flex;
  align-items: center;
  padding: $spacing-lg $spacing-md;
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  margin-bottom: $spacing-md;

  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-700;
    flex: 1;
  }
}

.checkbox {
  width: 44rpx;
  height: 44rpx;
  border: 2rpx solid $color-neutral-300;
  border-radius: $radius-sm;
  margin-right: $spacing-md;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s ease;

  &--checked {
    background-color: $color-primary-500;
    border-color: $color-primary-500;
  }

  &__icon {
    color: $color-neutral-0;
    font-size: $font-size-xs;
    font-weight: $font-weight-bold;
  }
}

/* 底部操作栏 */
.bottom-placeholder {
  height: 160rpx;
}

.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  gap: $spacing-md;
  padding: $spacing-md $spacing-lg;
  background-color: $color-neutral-0;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.05);

  &__cancel {
    flex: 1;
    height: 88rpx;
    line-height: 88rpx;
    background-color: $color-neutral-0;
    color: $color-neutral-700;
    font-size: $font-size-base;
    border: 2rpx solid $color-neutral-200;
    border-radius: $radius-md;
  }

  &__confirm {
    flex: 2;
    height: 88rpx;
    line-height: 88rpx;
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
}
</style>
