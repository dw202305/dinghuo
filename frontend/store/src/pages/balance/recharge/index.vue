<template>
  <view class="recharge-page">
    <!-- 充值金额区域 -->
    <view class="card amount-card">
      <view class="card__title">充值金额</view>

      <!-- 预设金额网格 -->
      <view class="preset-grid">
        <view
          v-for="item in presetAmounts"
          :key="item.value"
          class="preset-item"
          :class="{ 'preset-item--active': selectedPreset === item.value && !customMode }"
          @tap="selectPreset(item.value)"
        >
          <text class="preset-item__symbol">¥</text>
          <text class="preset-item__value">{{ item.value }}</text>
          <text v-if="item.bonus" class="preset-item__bonus">{{ item.bonus }}</text>
        </view>
      </view>

      <!-- 自定义金额输入 -->
      <view class="custom-input-section" :class="{ 'custom-input-section--active': customMode }">
        <view class="custom-input-row" @tap="focusCustomInput">
          <text class="custom-input-row__label">自定义</text>
          <view class="custom-input-row__field">
            <text class="custom-input-row__symbol">¥</text>
            <input
              v-if="customMode"
              ref="customInputRef"
              class="custom-input-row__input"
              type="digit"
              :value="customAmount"
              placeholder="输入充值金额"
              placeholder-style="color: #9CA3AF"
              @input="handleCustomInput"
              @blur="handleCustomBlur"
            />
            <text v-else class="custom-input-row__placeholder">点击输入金额</text>
          </view>
        </view>
        <view v-if="customMode && customAmount" class="custom-input-row__hint">
          <text class="custom-input-row__hint-text">
            充值金额：¥{{ customAmount }}（{{ formatYuanDisplay(customAmount) }}）
          </text>
        </view>
      </view>
    </view>

    <!-- 支付方式选择 -->
    <view class="card pay-card">
      <view class="card__title">支付方式</view>

      <view
        class="pay-method"
        :class="{ 'pay-method--active': selectedPay === 'wechat' }"
        @tap="selectedPay = 'wechat'"
      >
        <view class="pay-method__icon-wrap pay-method__icon-wrap--wechat">
          <text class="pay-method__icon-text">💚</text>
        </view>
        <view class="pay-method__info">
          <text class="pay-method__name">微信支付</text>
          <text class="pay-method__desc">推荐使用微信支付</text>
        </view>
        <view class="pay-method__radio">
          <view
            v-if="selectedPay === 'wechat'"
            class="pay-method__radio-dot pay-method__radio-dot--active"
          />
        </view>
      </view>

      <view
        class="pay-method"
        :class="{ 'pay-method--active': selectedPay === 'alipay' }"
        @tap="selectedPay = 'alipay'"
      >
        <view class="pay-method__icon-wrap pay-method__icon-wrap--alipay">
          <text class="pay-method__icon-text">💙</text>
        </view>
        <view class="pay-method__info">
          <text class="pay-method__name">支付宝</text>
          <text class="pay-method__desc">支持支付宝快捷支付</text>
        </view>
        <view class="pay-method__radio">
          <view
            v-if="selectedPay === 'alipay'"
            class="pay-method__radio-dot pay-method__radio-dot--active"
          />
        </view>
      </view>
    </view>

    <!-- 充值协议 -->
    <view class="agreement-row" @tap="agreed = !agreed">
      <view class="agreement-check" :class="{ 'agreement-check--active': agreed }">
        <text v-if="agreed" class="agreement-check__icon">✓</text>
      </view>
      <text class="agreement-text">
        我已阅读并同意
        <text class="agreement-text__link" @tap.stop="viewAgreement">《储值充值协议》</text>
      </text>
    </view>

    <!-- 确认充值按钮 -->
    <view class="submit-section">
      <button
        class="submit-btn"
        :class="{ 'submit-btn--disabled': !canSubmit || submitting }"
        :disabled="!canSubmit || submitting"
        @tap="handleSubmit"
      >
        {{ submitBtnText }}
      </button>
    </view>

    <!-- 充值记录 -->
    <view class="card records-card">
      <view class="card__title">充值记录</view>

      <!-- 状态筛选标签 -->
      <view class="status-tabs">
        <view
          v-for="tab in statusTabs"
          :key="tab.value"
          class="status-tab"
          :class="{ 'status-tab--active': activeStatusTab === tab.value }"
          @tap="switchStatusTab(tab.value)"
        >
          <text class="status-tab__text">{{ tab.label }}</text>
        </view>
      </view>

      <!-- 加载中 -->
      <view v-if="recordsLoading" class="records-loading">
        <text class="records-loading__text">加载中...</text>
      </view>

      <!-- 空状态 -->
      <view v-else-if="!recordsList.length" class="records-empty">
        <text class="records-empty__text">暂无充值记录</text>
      </view>

      <!-- 记录列表 -->
      <view v-else class="records-list">
        <view
          v-for="record in recordsList"
          :key="record.id"
          class="record-item"
        >
          <view class="record-item__left">
            <text class="record-item__amount">¥{{ formatRecordAmount(record.amount_cent) }}</text>
            <text class="record-item__channel">{{ getChannelLabel(record.payment_channel) }}</text>
          </view>
          <view class="record-item__right">
            <view class="record-item__status" :class="getStatusClass(record.status)">
              <text class="record-item__status-text">{{ getStatusLabel(record.status) }}</text>
            </view>
            <text class="record-item__time">{{ formatRecordTime(record.created_at) }}</text>
          </view>
        </view>

        <!-- 加载更多 -->
        <view v-if="recordsHasMore" class="records-more" @tap="loadMoreRecords">
          <text class="records-more__text">{{ recordsLoadingMore ? '加载中...' : '加载更多' }}</text>
        </view>
      </view>
    </view>

    <!-- 底部安全区 -->
    <view class="safe-area" />
  </view>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import { rechargeWithAutoKey, getRechargeRecords, getAccountBalance } from '@/api/balance';
import { yuanToFen, fenToYuan } from '@/utils/money';
import { formatDate } from '@/utils/format';
import type { RechargeRecord } from '@/types/balance';

// ── 预设金额配置 ──
interface PresetAmount {
  value: number
  bonus?: string
}

const presetAmounts: PresetAmount[] = [
  { value: 500 },
  { value: 1000, bonus: '热门' },
  { value: 2000, bonus: '推荐' },
  { value: 5000, bonus: '¥50' },
  { value: 10000, bonus: '¥150' },
];

// ── 金额选择状态 ──
/** 选中的预设金额 */
const selectedPreset = ref(500);
/** 是否处于自定义输入模式 */
const customMode = ref(false);
/** 自定义金额文本 */
const customAmount = ref('');
/** 自定义输入框 ref */
const customInputRef = ref<HTMLElement | null>(null);

// ── 支付方式 ──
/** 当前选择的支付方式 */
const selectedPay = ref<'wechat' | 'alipay'>('wechat');

// ── 协议 ──
/** 是否同意充值协议 */
const agreed = ref(false);

// ── 提交状态 ──
/** 提交中 */
const submitting = ref(false);

/** 实际充值金额（分），用于提交 */
const rechargeAmountCent = computed(() => {
  if (customMode.value && customAmount.value) {
    return yuanToFen(customAmount.value);
  }
  return yuanToFen(selectedPreset.value);
});

/** 是否可提交 */
const canSubmit = computed(() => {
  const amount = rechargeAmountCent.value;
  return amount > 0 && agreed.value;
});

/** 提交按钮文案 */
const submitBtnText = computed(() => {
  if (submitting.value) return '处理中...';
  if (!agreed.value) return '请先同意充值协议';
  const yuan = fenToYuan(rechargeAmountCent.value);
  return `确认充值 ¥${yuan}`;
});

/**
 * 选择预设金额
 */
function selectPreset(value: number) {
  customMode.value = false;
  customAmount.value = '';
  selectedPreset.value = value;
}

/**
 * 点击自定义输入区域
 */
function focusCustomInput() {
  customMode.value = true;
  nextTick(() => {
    // uni-app input focus handled by v-if
  });
}

/**
 * 自定义金额输入处理
 */
function handleCustomInput(e: { detail: { value: string } }) {
  let val = e.detail.value;

  // 移除非数字和小数点
  val = val.replace(/[^\d.]/g, '');

  // 只保留第一个小数点
  const dotIndex = val.indexOf('.');
  if (dotIndex !== -1) {
    val = val.substring(0, dotIndex + 1) + val.substring(dotIndex + 1).replace(/\./g, '');
    // 限制最多2位小数
    const decimalPart = val.substring(dotIndex + 1);
    if (decimalPart.length > 2) {
      val = val.substring(0, dotIndex + 3);
    }
  }

  // 限制最大金额 50000 元
  const numVal = parseFloat(val);
  if (!isNaN(numVal) && numVal > 50000) {
    val = '50000';
  }

  customAmount.value = val;
}

/**
 * 自定义输入框失焦处理
 */
function handleCustomBlur() {
  if (!customAmount.value) {
    customMode.value = false;
    return;
  }
  // 确保合法数值
  const num = parseFloat(customAmount.value);
  if (isNaN(num) || num <= 0) {
    customAmount.value = '';
    customMode.value = false;
  } else {
    // 标准化显示
    customAmount.value = num.toString();
  }
}

/**
 * 格式化元金额展示
 */
function formatYuanDisplay(yuan: string): string {
  const num = parseFloat(yuan);
  if (isNaN(num)) return '¥0.00';
  return `¥${num.toFixed(2)}`;
}

/**
 * 查看充值协议
 */
function viewAgreement() {
  uni.showToast({ title: '协议页面开发中', icon: 'none' });
}

/**
 * 提交充值
 */
async function handleSubmit() {
  if (!canSubmit.value || submitting.value) return;

  submitting.value = true;
  try {
    const result = await rechargeWithAutoKey(rechargeAmountCent.value, selectedPay.value);

    // 调起第三方支付
    if (selectedPay.value === 'wechat' && result.payment_params) {
      // #ifdef MP-WEIXIN
      uni.requestPayment({
        ...result.payment_params,
        success: () => {
          uni.showToast({ title: '充值成功', icon: 'success' });
          loadRechargeRecords();
        },
        fail: () => {
          uni.showToast({ title: '支付取消', icon: 'none' });
        },
      });
      // #endif

      // #ifdef H5
      uni.showToast({ title: '请在弹出窗口完成支付', icon: 'none' });
      // #endif
    } else if (selectedPay.value === 'alipay' && result.payment_params) {
      // #ifdef MP-ALIPAY
      my.tradePay({
        orderStr: result.payment_params.order_string,
        success: () => {
          uni.showToast({ title: '充值成功', icon: 'success' });
          loadRechargeRecords();
        },
        fail: () => {
          uni.showToast({ title: '支付取消', icon: 'none' });
        },
      });
      // #endif

      // #ifdef H5
      uni.showToast({ title: '请在弹出窗口完成支付', icon: 'none' });
      // #endif
    }

    // 刷新余额
    refreshBalance();
    loadRechargeRecords();
  } catch (e) {
    console.error('充值失败:', e);
  } finally {
    submitting.value = false;
  }
}

/**
 * 刷新余额
 */
async function refreshBalance() {
  try {
    await getAccountBalance();
  } catch (e) {
    console.error('刷新余额失败:', e);
  }
}

// ── 充值记录相关 ──
/** 状态标签配置 */
const statusTabs = [
  { label: '全部', value: '' as const },
  { label: '充值中', value: 'pending' as const },
  { label: '已完成', value: 'success' as const },
  { label: '已失败', value: 'failed' as const },
];

/** 当前激活的状态标签 */
const activeStatusTab = ref<string>('');
/** 充值记录列表 */
const recordsList = ref<RechargeRecord[]>([]);
/** 记录加载中 */
const recordsLoading = ref(false);
/** 记录加载更多中 */
const recordsLoadingMore = ref(false);
/** 当前页码 */
const recordsPage = ref(1);
/** 是否有更多 */
const recordsHasMore = ref(false);
/** 总数 */
const recordsTotal = ref(0);

/**
 * 切换状态标签
 */
function switchStatusTab(status: string) {
  activeStatusTab.value = status;
  loadRechargeRecords();
}

/**
 * 加载充值记录
 */
async function loadRechargeRecords() {
  recordsLoading.value = true;
  recordsPage.value = 1;
  try {
    const params: { page: number; page_size: number; status?: 'pending' | 'success' | 'failed' } = {
      page: 1,
      page_size: 10,
    };
    if (activeStatusTab.value) {
      params.status = activeStatusTab.value as 'pending' | 'success' | 'failed';
    }
    const data = await getRechargeRecords(params);
    recordsList.value = data.list || [];
    recordsTotal.value = data.total;
    recordsHasMore.value = data.list.length < data.total;
  } catch (e) {
    console.error('加载充值记录失败:', e);
  } finally {
    recordsLoading.value = false;
  }
}

/**
 * 加载更多充值记录
 */
async function loadMoreRecords() {
  if (recordsLoadingMore.value || !recordsHasMore.value) return;
  recordsLoadingMore.value = true;
  recordsPage.value += 1;
  try {
    const params: { page: number; page_size: number; status?: 'pending' | 'success' | 'failed' } = {
      page: recordsPage.value,
      page_size: 10,
    };
    if (activeStatusTab.value) {
      params.status = activeStatusTab.value as 'pending' | 'success' | 'failed';
    }
    const data = await getRechargeRecords(params);
    recordsList.value = [...recordsList.value, ...(data.list || [])];
    recordsHasMore.value = recordsList.value.length < data.total;
  } catch (e) {
    console.error('加载更多充值记录失败:', e);
    recordsPage.value -= 1;
  } finally {
    recordsLoadingMore.value = false;
  }
}

/**
 * 格式化充值记录金额
 */
function formatRecordAmount(cent: number): string {
  const yuan = fenToYuan(cent);
  const parts = yuan.split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return parts.join('.');
}

/**
 * 获取支付渠道标签
 */
function getChannelLabel(channel: string): string {
  const map: Record<string, string> = {
    wechat: '微信支付',
    alipay: '支付宝',
  };
  return map[channel] || channel;
}

/**
 * 获取状态样式类名
 */
function getStatusClass(status: string): string {
  const map: Record<string, string> = {
    pending: 'record-item__status--pending',
    success: 'record-item__status--success',
    failed: 'record-item__status--failed',
  };
  return map[status] || '';
}

/**
 * 获取状态文本标签
 */
function getStatusLabel(status: string): string {
  const map: Record<string, string> = {
    pending: '充值中',
    success: '已完成',
    failed: '已失败',
  };
  return map[status] || status;
}

/**
 * 格式化充值记录时间
 */
function formatRecordTime(dateStr: string): string {
  return formatDate(dateStr, 'MM-DD HH:mm');
}

// ── 页面生命周期 ──
onShow(() => {
  loadRechargeRecords();
});
</script>

<style lang="scss" scoped>
.recharge-page {
  min-height: 100vh;
  background-color: #F9FAFB;
  padding: 24rpx 24rpx 0;
  padding-bottom: env(safe-area-inset-bottom);
}

// ── 通用卡片 ──
.card {
  background-color: #FFFFFF;
  border-radius: 20rpx;
  padding: 32rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);

  &__title {
    font-size: 30rpx;
    font-weight: 600;
    color: #111827;
    margin-bottom: 28rpx;
  }
}

// ── 预设金额网格 ──
.preset-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 20rpx;
  margin-bottom: 28rpx;
}

.preset-item {
  flex: 0 0 calc(33.33% - 14rpx);
  height: 140rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 2rpx solid #E5E7EB;
  border-radius: 20rpx;
  position: relative;
  transition: all 0.2s;

  &--active {
    border-color: #56638F;
    background-color: #F0F1F5;
  }

  &__symbol {
    font-size: 24rpx;
    color: #6B7280;
    margin-bottom: 4rpx;
  }

  &__value {
    font-size: 36rpx;
    font-weight: 700;
    color: #111827;
    font-family: 'DIN Alternate', 'JetBrains Mono', -apple-system, sans-serif;
  }

  &--active &__symbol,
  &--active &__value {
    color: #56638F;
  }

  &__bonus {
    position: absolute;
    top: -2rpx;
    right: -2rpx;
    background-color: #C49338;
    color: #FFFFFF;
    font-size: 20rpx;
    padding: 4rpx 12rpx;
    border-radius: 0 18rpx 0 16rpx;
    font-weight: 500;
  }
}

// ── 自定义金额 ──
.custom-input-section {
  border: 2rpx solid #E5E7EB;
  border-radius: 20rpx;
  overflow: hidden;
  transition: border-color 0.2s;

  &--active {
    border-color: #56638F;
  }
}

.custom-input-row {
  display: flex;
  align-items: center;
  padding: 24rpx 28rpx;

  &__label {
    font-size: 28rpx;
    color: #6B7280;
    margin-right: 24rpx;
    flex-shrink: 0;
  }

  &__field {
    flex: 1;
    display: flex;
    align-items: center;
  }

  &__symbol {
    font-size: 32rpx;
    font-weight: 600;
    color: #111827;
    margin-right: 8rpx;
  }

  &__input {
    flex: 1;
    font-size: 36rpx;
    font-weight: 600;
    color: #111827;
    font-family: 'DIN Alternate', 'JetBrains Mono', -apple-system, sans-serif;
  }

  &__placeholder {
    font-size: 30rpx;
    color: #9CA3AF;
  }

  &__hint {
    padding: 0 28rpx 20rpx;
  }

  &__hint-text {
    font-size: 24rpx;
    color: #9CA3AF;
  }
}

// ── 支付方式 ──
.pay-method {
  display: flex;
  align-items: center;
  padding: 24rpx 0;
  border-bottom: 1rpx solid #F3F4F6;

  &:last-child {
    border-bottom: none;
  }

  &--active {
    // 轻微高亮
  }

  &__icon-wrap {
    width: 72rpx;
    height: 72rpx;
    border-radius: 18rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 24rpx;

    &--wechat {
      background-color: #ECFDF5;
    }

    &--alipay {
      background-color: #EFF6FF;
    }
  }

  &__icon-text {
    font-size: 32rpx;
  }

  &__info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6rpx;
  }

  &__name {
    font-size: 28rpx;
    font-weight: 500;
    color: #111827;
  }

  &__desc {
    font-size: 24rpx;
    color: #9CA3AF;
  }

  &__radio {
    width: 40rpx;
    height: 40rpx;
    border-radius: 20rpx;
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
    width: 24rpx;
    height: 24rpx;
    border-radius: 12rpx;

    &--active {
      background-color: #56638F;
    }
  }
}

// ── 充值协议 ──
.agreement-row {
  display: flex;
  align-items: center;
  padding: 16rpx 8rpx;
  margin-bottom: 24rpx;
}

.agreement-check {
  width: 36rpx;
  height: 36rpx;
  border-radius: 8rpx;
  border: 2rpx solid #D1D5DB;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 16rpx;
  flex-shrink: 0;
  transition: all 0.2s;

  &--active {
    background-color: #56638F;
    border-color: #56638F;
  }

  &__icon {
    color: #FFFFFF;
    font-size: 24rpx;
    font-weight: 700;
  }
}

.agreement-text {
  font-size: 26rpx;
  color: #6B7280;

  &__link {
    color: #56638F;
    font-weight: 500;
  }
}

// ── 提交按钮 ──
.submit-section {
  margin-bottom: 32rpx;
}

.submit-btn {
  width: 100%;
  height: 96rpx;
  border-radius: 20rpx;
  background: linear-gradient(135deg, #56638F 0%, #444F73 100%);
  color: #FFFFFF;
  font-size: 32rpx;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8rpx 24rpx rgba(86, 99, 143, 0.3);
  border: none;
  line-height: 96rpx;

  &--disabled {
    opacity: 0.5;
    box-shadow: none;
  }

  &::after {
    border: none;
  }
}

// ── 状态标签 ──
.status-tabs {
  display: flex;
  gap: 16rpx;
  margin-bottom: 24rpx;
}

.status-tab {
  padding: 12rpx 28rpx;
  border-radius: 999rpx;
  background-color: #F3F4F6;
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

// ── 充值记录列表 ──
.records-loading {
  display: flex;
  justify-content: center;
  padding: 40rpx 0;
}

.records-loading__text {
  font-size: 26rpx;
  color: #9CA3AF;
}

.records-empty {
  display: flex;
  justify-content: center;
  padding: 40rpx 0;
}

.records-empty__text {
  font-size: 26rpx;
  color: #9CA3AF;
}

.records-list {
  // 列表容器
}

.record-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 0;
  border-bottom: 1rpx solid #F3F4F6;

  &:last-child {
    border-bottom: none;
  }

  &__left {
    display: flex;
    flex-direction: column;
    gap: 8rpx;
  }

  &__amount {
    font-size: 32rpx;
    font-weight: 600;
    color: #111827;
    font-family: 'DIN Alternate', 'JetBrains Mono', -apple-system, sans-serif;
  }

  &__channel {
    font-size: 24rpx;
    color: #9CA3AF;
  }

  &__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8rpx;
  }

  &__status {
    padding: 4rpx 16rpx;
    border-radius: 8rpx;

    &--pending {
      background-color: #FFFBEB;
    }

    &--success {
      background-color: #ECFDF5;
    }

    &--failed {
      background-color: #FEF2F2;
    }
  }

  &__status-text {
    font-size: 22rpx;
    font-weight: 500;
  }

  &__status--pending &__status-text {
    color: #D97706;
  }

  &__status--success &__status-text {
    color: #059669;
  }

  &__status--failed &__status-text {
    color: #DC2626;
  }

  &__time {
    font-size: 24rpx;
    color: #9CA3AF;
  }
}

.records-more {
  display: flex;
  justify-content: center;
  padding: 24rpx 0 8rpx;
}

.records-more__text {
  font-size: 26rpx;
  color: #56638F;
  font-weight: 500;
}

// ── 底部安全区 ──
.safe-area {
  height: calc(32rpx + env(safe-area-inset-bottom));
}
</style>
