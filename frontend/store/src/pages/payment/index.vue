<template>
  <view class="payment-page">
    <!-- 顶部订单信息 -->
    <view class="order-header">
      <view class="order-header__row">
        <text class="order-header__label">订单编号</text>
        <text class="order-header__value">{{ orderNo }}</text>
      </view>
      <view v-if="projectName" class="order-header__row">
        <text class="order-header__label">项目名称</text>
        <text class="order-header__value">{{ projectName }}</text>
      </view>
      <view class="order-header__amount">
        <text class="order-header__amount-label">应付金额</text>
        <view class="order-header__amount-row">
          <text class="order-header__amount-symbol">¥</text>
          <text class="order-header__amount-value">{{ displayAmount }}</text>
        </view>
      </view>
      <view v-if="isShippingPayOnDelivery" class="order-header__tip">
        <text class="order-header__tip-text">📦 运费到付，实际运费以发货时确认为准</text>
      </view>
    </view>

    <!-- 预审入口 -->
    <view v-if="showPreAudit" class="card pre-audit-card">
      <view class="pre-audit-card__content">
        <view class="pre-audit-card__info">
          <text class="pre-audit-card__icon">🔍</text>
          <view class="pre-audit-card__text">
            <text class="pre-audit-card__title">申请支付前技术预审</text>
            <text class="pre-audit-card__desc">提交技术团队审核配置方案，审核通过后再支付</text>
          </view>
        </view>
        <button
          class="pre-audit-card__btn"
          :class="{ 'pre-audit-card__btn--disabled': preAuditSubmitting }"
          :disabled="preAuditSubmitting || preAuditStatus === 'pending'"
          @tap="handlePreAudit"
        >
          {{ preAuditBtnText }}
        </button>
      </view>
    </view>

    <!-- 支付方式选择 -->
    <view class="card pay-section">
      <view class="card__title">选择支付方式</view>

      <!-- 余额支付 -->
      <view
        class="pay-method"
        :class="{
          'pay-method--active': selectedChannel === 'balance',
          'pay-method--disabled': balanceInsufficient
        }"
        @tap="handleSelectBalance"
      >
        <view class="pay-method__icon-wrap pay-method__icon-wrap--balance">
          <text class="pay-method__icon-text">💳</text>
        </view>
        <view class="pay-method__info">
          <text class="pay-method__name">余额支付</text>
          <text class="pay-method__desc">
            可用余额 ¥{{ balanceYuan }}
          </text>
        </view>
        <view v-if="balanceInsufficient" class="pay-method__tag">
          <text class="pay-method__tag-text">余额不足</text>
        </view>
        <view class="pay-method__radio">
          <view
            v-if="selectedChannel === 'balance'"
            class="pay-method__radio-dot pay-method__radio-dot--balance"
          />
        </view>
      </view>

      <!-- 余额不足提示 + 去储值 -->
      <view v-if="balanceInsufficient" class="balance-tip">
        <text class="balance-tip__text">余额不足以支付本订单，请先储值或选择其他支付方式</text>
        <view class="balance-tip__action" @tap="goToRecharge">
          <text class="balance-tip__action-text">去储值 →</text>
        </view>
      </view>

      <!-- 微信支付 -->
      <view
        class="pay-method"
        :class="{ 'pay-method--active': selectedChannel === 'wechat' }"
        @tap="selectedChannel = 'wechat'"
      >
        <view class="pay-method__icon-wrap pay-method__icon-wrap--wechat">
          <text class="pay-method__icon-text">💚</text>
        </view>
        <view class="pay-method__info">
          <text class="pay-method__name">微信支付</text>
          <text class="pay-method__desc">{{ wechatDesc }}</text>
        </view>
        <view class="pay-method__radio">
          <view
            v-if="selectedChannel === 'wechat'"
            class="pay-method__radio-dot pay-method__radio-dot--wechat"
          />
        </view>
      </view>

      <!-- 支付宝支付 -->
      <view
        class="pay-method"
        :class="{ 'pay-method--active': selectedChannel === 'alipay' }"
        @tap="selectedChannel = 'alipay'"
      >
        <view class="pay-method__icon-wrap pay-method__icon-wrap--alipay">
          <text class="pay-method__icon-text">💙</text>
        </view>
        <view class="pay-method__info">
          <text class="pay-method__name">支付宝支付</text>
          <text class="pay-method__desc">{{ alipayDesc }}</text>
        </view>
        <view class="pay-method__radio">
          <view
            v-if="selectedChannel === 'alipay'"
            class="pay-method__radio-dot pay-method__radio-dot--alipay"
          />
        </view>
      </view>
    </view>

    <!-- 价格锁定倒计时 -->
    <view v-if="countdown > 0" class="card countdown-card">
      <view class="countdown-row">
        <text class="countdown-row__icon">🔒</text>
        <text class="countdown-row__label">价格锁定剩余</text>
        <text class="countdown-row__time">{{ countdownText }}</text>
      </view>
      <text class="countdown-card__hint">锁定期间内价格不受调价影响</text>
    </view>

    <!-- 安全提示 -->
    <view class="security-tip">
      <text class="security-tip__text">🛡️ 支付安全由世尚官方保障</text>
    </view>

    <!-- 底部占位 -->
    <view class="bottom-placeholder" />

    <!-- 底部支付按钮 -->
    <view class="pay-bar safe-area-bottom">
      <button
        class="pay-bar__btn"
        :class="{ 'pay-bar__btn--disabled': isPayDisabled }"
        :disabled="isPayDisabled || paying"
        @tap="handlePay"
      >
        <text v-if="paying" class="pay-bar__loading">⏳</text>
        <text>{{ payBtnText }}</text>
      </button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { createPayment, payByBalance, requestPreAudit } from '@/api/order';
import { getAccountBalance } from '@/api/balance';
import { fenToYuan, isBalanceSufficient } from '@/utils/money';
import { PayChannel } from '@/types/common';
import type { PaymentChannel, BalanceInfo } from '@/types/order';

/** 页面参数 */
const orderId = ref(0);
const orderNo = ref('');
const projectName = ref('');
/** 应付金额，单位为分 */
const amountCent = ref(0);
/** 是否运费到付 */
const isShippingPayOnDelivery = ref(false);
/** 是否支持预审流程 */
const supportPreAudit = ref(false);

/** 展示用金额（元） */
const displayAmount = computed(() => fenToYuan(amountCent.value));

/** 选中的支付渠道 */
const selectedChannel = ref<PaymentChannel>('wechat');

/** 余额信息 */
const balanceInfo = ref<BalanceInfo>({
  available_balance_cent: 0,
  frozen_balance_cent: 0,
  currency: 'CNY',
});
const balanceYuan = computed(() => fenToYuan(balanceInfo.value.available_balance_cent));

/** 余额是否不足 */
const balanceInsufficient = computed(() => !isBalanceSufficient(
  balanceInfo.value.available_balance_cent,
  amountCent.value
));

/** 支付状态 */
const paying = ref(false);

/** 预审状态 */
const preAuditStatus = ref<'idle' | 'pending' | 'submitted'>('idle');
const preAuditSubmitting = ref(false);
const showPreAudit = computed(() => supportPreAudit.value && preAuditStatus.value !== 'submitted');
const preAuditBtnText = computed(() => {
  if (preAuditStatus.value === 'pending') return '提交中...';
  return '申请预审';
});

/** 价格锁定倒计时 */
const countdown = ref(0);
let countdownTimer: ReturnType<typeof setInterval> | null = null;

const countdownText = computed(() => {
  if (countdown.value <= 0) return '已过期';
  const days = Math.floor(countdown.value / 86400);
  const hours = Math.floor((countdown.value % 86400) / 3600);
  const minutes = Math.floor((countdown.value % 3600) / 60);
  if (days > 0) return `${days}天${hours}时${minutes}分`;
  if (hours > 0) return `${hours}时${minutes}分`;
  return `${minutes}分`;
});

/** 平台环境描述 */
const isMpWeixin = ref(false);
const wechatDesc = computed(() => isMpWeixin.value ? '推荐使用' : '微信支付');
const alipayDesc = computed(() => isMpWeixin.value ? '将跳转支付宝支付' : '支付宝支付');

/** 支付按钮禁用状态 */
const isPayDisabled = computed(() => {
  if (!selectedChannel.value) return true;
  if (selectedChannel.value === 'balance' && balanceInsufficient.value) return true;
  return false;
});

/** 支付按钮文案 */
const payBtnText = computed(() => {
  if (paying.value) return '支付中...';
  const amountText = `¥${displayAmount.value}`;
  if (selectedChannel.value === 'balance') return `余额支付 ${amountText}`;
  if (selectedChannel.value === 'wechat') return `微信支付 ${amountText}`;
  if (selectedChannel.value === 'alipay') return `支付宝支付 ${amountText}`;
  return `确认支付 ${amountText}`;
});

/** 选择余额支付 */
function handleSelectBalance() {
  if (balanceInsufficient.value) {
    uni.showToast({ title: '余额不足，请储值或选择其他支付方式', icon: 'none' });
    return;
  }
  selectedChannel.value = 'balance';
}

/** 跳转到储值页面 */
function goToRecharge() {
  uni.navigateTo({ url: '/pages/balance/recharge/index' });
}

/** 加载账户余额 */
async function loadBalance() {
  try {
    const data = await getAccountBalance();
    balanceInfo.value = data;
  } catch {
    // 余额加载失败不阻断支付流程，默认余额为 0
    balanceInfo.value = {
      available_balance_cent: 0,
      frozen_balance_cent: 0,
      currency: 'CNY',
    };
  }
}

/** 申请预审 */
async function handlePreAudit() {
  if (preAuditSubmitting.value) return;
  preAuditSubmitting.value = true;
  try {
    await requestPreAudit(orderNo.value);
    preAuditStatus.value = 'submitted';
    uni.showToast({ title: '预审申请已提交', icon: 'success' });
  } catch {
    uni.showToast({ title: '预审申请失败，请重试', icon: 'none' });
  } finally {
    preAuditSubmitting.value = false;
  }
}

/** 生成幂等键 */
function generateIdempotencyKey(): string {
  const timestamp = Date.now().toString(36);
  const random = Math.random().toString(36).substring(2, 8);
  return `pay_${orderNo.value}_${timestamp}_${random}`;
}

/** 余额支付 */
async function handleBalancePayment() {
  try {
    const idempotencyKey = generateIdempotencyKey();
    const result = await payByBalance(orderNo.value, idempotencyKey);
    if (result.status === 'success') {
      onPaySuccess(result.payment_no);
    } else {
      uni.showToast({ title: result.message || '余额支付失败', icon: 'none' });
      paying.value = false;
    }
  } catch {
    paying.value = false;
    uni.showToast({ title: '余额支付失败', icon: 'none' });
  }
}

/** 发起第三方支付 */
async function handleThirdPartyPayment() {
  const channel = selectedChannel.value;
  const legacyChannel = channel === 'wechat' ? PayChannel.WECHAT : PayChannel.ALIPAY;

  let payMethod = 'H5';
  // #ifdef MP-WEIXIN
  payMethod = 'JSAPI';
  // #endif

  try {
    const result = await createPayment(orderId.value, legacyChannel, payMethod);

    // #ifdef MP-WEIXIN
    if (channel === 'wechat' && result.wechat_params) {
      const params = result.wechat_params;
      uni.requestPayment({
        timeStamp: params.timeStamp,
        nonceStr: params.nonceStr,
        package: params.package,
        signType: params.signType as 'MD5' | 'HMAC-SHA256' | 'RSA',
        paySign: params.paySign,
        success: () => {
          onPaySuccess(result.payment_no);
        },
        fail: (err) => {
          paying.value = false;
          if (err.errMsg?.includes('cancel')) {
            uni.showToast({ title: '已取消支付', icon: 'none' });
          } else {
            uni.showToast({ title: '支付失败', icon: 'none' });
          }
        },
      });
    }
    // #endif

    // #ifdef H5
    if (channel === 'alipay' && result.alipay_params) {
      uni.showToast({ title: '正在跳转支付宝...', icon: 'none' });
      setTimeout(() => {
        onPaySuccess(result.payment_no);
      }, 3000);
    } else if (channel === 'wechat' && result.wechat_params) {
      uni.showToast({ title: '正在调起微信支付...', icon: 'none' });
      setTimeout(() => {
        onPaySuccess(result.payment_no);
      }, 3000);
    }
    // #endif
  } catch {
    paying.value = false;
    uni.showToast({ title: '支付发起失败', icon: 'none' });
  }
}

/** 发起支付 */
async function handlePay() {
  if (isPayDisabled.value || paying.value) return;
  paying.value = true;

  if (selectedChannel.value === 'balance') {
    await handleBalancePayment();
  } else {
    await handleThirdPartyPayment();
  }
}

/** 支付成功处理 */
function onPaySuccess(paymentNo: string) {
  uni.redirectTo({
    url: `/pages/payment/result?status=success&order_id=${orderId.value}&amount=${displayAmount.value}&payment_no=${paymentNo}`,
  });
}

/** 启动倒计时 */
function startCountdown(seconds: number) {
  countdown.value = seconds;
  countdownTimer = setInterval(() => {
    countdown.value--;
    if (countdown.value <= 0 && countdownTimer) {
      clearInterval(countdownTimer);
    }
  }, 1000);
}

/** 检测运行环境 */
function detectEnvironment() {
  // #ifdef MP-WEIXIN
  isMpWeixin.value = true;
  // #endif
}

onLoad((options) => {
  if (options?.order_id) orderId.value = Number(options.order_id);
  if (options?.order_no) orderNo.value = options.order_no;
  if (options?.project_name) projectName.value = options.project_name;
  if (options?.amount_cent) amountCent.value = Number(options.amount_cent);
  if (options?.support_pre_audit === '1') supportPreAudit.value = true;
  if (options?.shipping_pay_on_delivery === '1') isShippingPayOnDelivery.value = true;
});

onMounted(() => {
  detectEnvironment();
  loadBalance();
});

onUnmounted(() => {
  if (countdownTimer) {
    clearInterval(countdownTimer);
  }
});
</script>

<style lang="scss" scoped>
.payment-page {
  min-height: 100vh;
  background-color: #F9FAFB;
  padding: 24rpx;
}

/* 卡片通用样式 */
.card {
  background-color: #ffffff;
  border-radius: 16rpx;
  padding: 32rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);

  &__title {
    font-size: 30rpx;
    font-weight: 600;
    color: #111827;
    margin-bottom: 24rpx;
  }
}

/* 订单头部信息 */
.order-header {
  background-color: #ffffff;
  border-radius: 16rpx;
  padding: 32rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);

  &__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8rpx 0;
  }

  &__label {
    font-size: 26rpx;
    color: #6B7280;
  }

  &__value {
    font-size: 26rpx;
    color: #111827;
  }

  &__amount {
    text-align: center;
    padding: 32rpx 0 16rpx;
    border-top: 1rpx solid #F3F4F6;
    margin-top: 16rpx;
  }

  &__amount-label {
    font-size: 26rpx;
    color: #6B7280;
    display: block;
    margin-bottom: 12rpx;
  }

  &__amount-row {
    display: flex;
    align-items: baseline;
    justify-content: center;
  }

  &__amount-symbol {
    font-size: 36rpx;
    font-weight: 700;
    color: #111827;
    margin-right: 4rpx;
  }

  &__amount-value {
    font-size: 72rpx;
    font-weight: 700;
    color: #111827;
    line-height: 1;
  }

  &__tip {
    margin-top: 16rpx;
    text-align: center;
  }

  &__tip-text {
    font-size: 24rpx;
    color: #C49338;
    background-color: #FFFBEB;
    padding: 8rpx 20rpx;
    border-radius: 8rpx;
  }
}

/* 预审卡片 */
.pre-audit-card {
  background-color: #EEF2FF;
  border: 1rpx solid #C7D2FE;

  &__content {
    display: flex;
    align-items: center;
    gap: 24rpx;
  }

  &__info {
    flex: 1;
    display: flex;
    align-items: flex-start;
    gap: 16rpx;
  }

  &__icon {
    font-size: 40rpx;
    flex-shrink: 0;
  }

  &__text {
    flex: 1;
  }

  &__title {
    font-size: 28rpx;
    font-weight: 600;
    color: #3730A3;
    display: block;
    margin-bottom: 4rpx;
  }

  &__desc {
    font-size: 22rpx;
    color: #6366F1;
    display: block;
  }

  &__btn {
    flex-shrink: 0;
    font-size: 24rpx;
    padding: 12rpx 24rpx;
    background-color: #56638F;
    color: #ffffff;
    border-radius: 12rpx;
    border: none;
    line-height: 1.4;

    &--disabled {
      background-color: #9CA3AF;
    }
  }
}

/* 支付方式 */
.pay-section {
  /* 继承 card 基础样式 */
}

.pay-method {
  display: flex;
  align-items: center;
  padding: 24rpx;
  border-radius: 16rpx;
  border: 2rpx solid #E5E7EB;
  margin-bottom: 16rpx;
  transition: all 0.2s ease;

  &:last-child {
    margin-bottom: 0;
  }

  &--active {
    border-color: #56638F;
    background-color: #F0F2F7;
  }

  &--disabled {
    opacity: 0.55;
  }

  &__icon-wrap {
    width: 72rpx;
    height: 72rpx;
    border-radius: 16rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 24rpx;
    flex-shrink: 0;

    &--balance {
      background-color: #FEF3C7;
    }

    &--wechat {
      background-color: #D1FAE5;
    }

    &--alipay {
      background-color: #DBEAFE;
    }
  }

  &__icon-text {
    font-size: 40rpx;
  }

  &__info {
    flex: 1;
  }

  &__name {
    font-size: 28rpx;
    color: #111827;
    font-weight: 600;
    display: block;
    margin-bottom: 4rpx;
  }

  &__desc {
    font-size: 24rpx;
    color: #6B7280;
    display: block;
  }

  &__tag {
    margin-right: 12rpx;
  }

  &__tag-text {
    font-size: 22rpx;
    color: #DC2626;
    background-color: #FEE2E2;
    padding: 4rpx 12rpx;
    border-radius: 8rpx;
  }

  &__radio {
    width: 40rpx;
    height: 40rpx;
    border-radius: 50%;
    border: 2rpx solid #D1D5DB;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  &--active &__radio {
    border-color: #56638F;
  }

  &__radio-dot {
    width: 22rpx;
    height: 22rpx;
    border-radius: 50%;

    &--balance {
      background-color: #C49338;
    }

    &--wechat {
      background-color: #059669;
    }

    &--alipay {
      background-color: #2563EB;
    }
  }
}

/* 余额不足提示 */
.balance-tip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16rpx 24rpx;
  background-color: #FEF2F2;
  border-radius: 12rpx;
  margin-bottom: 16rpx;

  &__text {
    font-size: 24rpx;
    color: #DC2626;
    flex: 1;
  }

  &__action {
    flex-shrink: 0;
    margin-left: 16rpx;
  }

  &__action-text {
    font-size: 24rpx;
    color: #56638F;
    font-weight: 600;
  }
}

/* 倒计时 */
.countdown-card {
  &__hint {
    font-size: 24rpx;
    color: #9CA3AF;
    margin-top: 8rpx;
    display: block;
  }
}

.countdown-row {
  display: flex;
  align-items: center;

  &__icon {
    font-size: 28rpx;
    margin-right: 8rpx;
  }

  &__label {
    font-size: 26rpx;
    color: #6B7280;
    margin-right: 20rpx;
  }

  &__time {
    font-size: 26rpx;
    font-weight: 600;
    color: #56638F;
  }
}

/* 安全提示 */
.security-tip {
  text-align: center;
  padding: 32rpx 0;

  &__text {
    font-size: 24rpx;
    color: #9CA3AF;
  }
}

/* 底部支付栏 */
.bottom-placeholder {
  height: 180rpx;
}

.pay-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 20rpx 32rpx;
  background-color: #ffffff;
  box-shadow: 0 -4rpx 16rpx rgba(0, 0, 0, 0.06);

  &__btn {
    width: 100%;
    height: 96rpx;
    line-height: 96rpx;
    background-color: #56638F;
    color: #ffffff;
    font-size: 32rpx;
    font-weight: 600;
    border-radius: 16rpx;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;

    &--disabled {
      background-color: #D1D5DB;
      color: #9CA3AF;
    }

    &[disabled] {
      background-color: #D1D5DB;
      color: #9CA3AF;
    }
  }

  &__loading {
    margin-right: 12rpx;
    animation: spin 1s linear infinite;
  }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
