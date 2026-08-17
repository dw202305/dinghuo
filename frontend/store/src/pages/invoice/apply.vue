<template>
  <view class="invoice-page">
    <!-- 发票类型 -->
    <view class="section-card">
      <text class="section-card__title">发票类型</text>
      <view class="type-options">
        <view
          class="type-option"
          :class="{ 'type-option--active': form.invoice_type === 1 }"
          @tap="form.invoice_type = 1"
        >
          <view class="type-option__radio">
            <view v-if="form.invoice_type === 1" class="type-option__radio-dot" />
          </view>
          <view class="type-option__content">
            <text class="type-option__name">增值税普通发票</text>
            <text class="type-option__desc">适用于一般企业</text>
          </view>
        </view>
        <view
          class="type-option"
          :class="{ 'type-option--active': form.invoice_type === 2 }"
          @tap="form.invoice_type = 2"
        >
          <view class="type-option__radio">
            <view v-if="form.invoice_type === 2" class="type-option__radio-dot" />
          </view>
          <view class="type-option__content">
            <text class="type-option__name">增值税专用发票</text>
            <text class="type-option__desc">可抵扣进项税额</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 开票信息 -->
    <view class="section-card">
      <text class="section-card__title">开票信息</text>
      <view class="form-row">
        <text class="form-row__label">企业名称 <text class="required">*</text></text>
        <input v-model="form.title" class="form-row__input" placeholder="请输入企业全称" />
      </view>
      <view class="form-row">
        <text class="form-row__label">税号 <text class="required">*</text></text>
        <input v-model="form.tax_no" class="form-row__input" placeholder="请输入纳税人识别号" />
      </view>
      <!-- 专票额外字段 -->
      <template v-if="form.invoice_type === 2">
        <view class="form-row">
          <text class="form-row__label">开户银行 <text class="required">*</text></text>
          <input v-model="form.bank_name" class="form-row__input" placeholder="请输入开户银行名称" />
        </view>
        <view class="form-row">
          <text class="form-row__label">银行账号 <text class="required">*</text></text>
          <input v-model="form.bank_account" class="form-row__input" placeholder="请输入银行账号" />
        </view>
        <view class="form-row">
          <text class="form-row__label">注册地址 <text class="required">*</text></text>
          <input v-model="form.company_address" class="form-row__input" placeholder="请输入企业注册地址" />
        </view>
        <view class="form-row">
          <text class="form-row__label">注册电话 <text class="required">*</text></text>
          <input v-model="form.company_phone" class="form-row__input" placeholder="请输入注册电话" />
        </view>
      </template>
    </view>

    <!-- 关联订单 -->
    <view class="section-card">
      <text class="section-card__title">关联订单 <text class="required">*</text></text>
      <view class="order-select-btn" @tap="showOrderPicker = true">
        <text v-if="selectedOrders.length === 0" class="order-select-btn__placeholder">请选择开票订单（可多选）</text>
        <text v-else class="order-select-btn__count">已选 {{ selectedOrders.length }} 个订单</text>
        <text class="order-select-btn__arrow">›</text>
      </view>
      <!-- 已选订单列表 -->
      <view v-if="selectedOrders.length > 0" class="selected-orders">
        <view v-for="order in selectedOrders" :key="order.order_id" class="selected-order-item">
          <text class="selected-order-item__no">{{ order.order_no }}</text>
          <text class="selected-order-item__amount">¥{{ order.uninvoiced_amount }}</text>
          <text class="selected-order-item__remove" @tap="removeOrder(order.order_id)">×</text>
        </view>
      </view>
    </view>

    <!-- 发票金额 -->
    <view class="section-card">
      <view class="amount-row">
        <text class="amount-row__label">发票金额</text>
        <text class="amount-row__value">¥{{ invoiceAmount }}</text>
      </view>
      <text class="amount-hint">自动计算已选订单的未开票金额</text>
    </view>

    <!-- 接收方式 -->
    <view class="section-card">
      <text class="section-card__title">接收方式</text>
      <view class="delivery-options">
        <view
          class="delivery-option"
          :class="{ 'delivery-option--active': deliveryMethod === 1 }"
          @tap="deliveryMethod = 1"
        >
          <text class="delivery-option__icon">📧</text>
          <text class="delivery-option__text">电子邮件</text>
        </view>
        <view
          class="delivery-option"
          :class="{ 'delivery-option--active': deliveryMethod === 2 }"
          @tap="deliveryMethod = 2"
        >
          <text class="delivery-option__icon">📮</text>
          <text class="delivery-option__text">邮寄地址</text>
        </view>
      </view>
      <!-- 邮箱输入 -->
      <view v-if="deliveryMethod === 1" class="form-row" style="margin-top: 24rpx;">
        <text class="form-row__label">邮箱地址</text>
        <input v-model="email" class="form-row__input" placeholder="请输入接收邮箱" />
      </view>
      <!-- 邮寄地址 -->
      <view v-if="deliveryMethod === 2" class="form-row" style="margin-top: 24rpx;">
        <text class="form-row__label">邮寄地址</text>
        <input v-model="deliveryAddress" class="form-row__input" placeholder="请输入邮寄地址" />
      </view>
    </view>

    <!-- 提交按钮 -->
    <view class="footer safe-area-bottom">
      <button class="submit-btn" :disabled="submitting" @tap="handleSubmit">
        {{ submitting ? '提交中...' : '提交申请' }}
      </button>
    </view>

    <!-- 订单选择弹窗 -->
    <view v-if="showOrderPicker" class="picker-mask" @tap="showOrderPicker = false">
      <view class="picker-panel" @tap.stop>
        <view class="picker-panel__header">
          <text class="picker-panel__title">选择开票订单</text>
          <view class="picker-panel__actions">
            <text class="picker-panel__action" @tap="selectAll">全选</text>
            <text class="picker-panel__close" @tap="showOrderPicker = false">×</text>
          </view>
        </view>
        <scroll-view scroll-y class="picker-panel__list">
          <view
            v-for="order in invoiceableOrders"
            :key="order.order_id"
            class="picker-order-item"
            :class="{ 'picker-order-item--selected': isSelected(order.order_id) }"
            @tap="toggleOrder(order)"
          >
            <view class="picker-order-item__check">
              <view v-if="isSelected(order.order_id)" class="picker-order-item__check-dot" />
            </view>
            <view class="picker-order-item__info">
              <text class="picker-order-item__no">{{ order.order_no }}</text>
              <text class="picker-order-item__amount">未开票 ¥{{ order.uninvoiced_amount }}</text>
            </view>
            <text class="picker-order-item__date">{{ formatDate(order.created_at) }}</text>
          </view>
          <view v-if="invoiceableOrders.length === 0" class="picker-empty">
            <text>暂无可开票订单</text>
          </view>
        </scroll-view>
        <view class="picker-panel__footer">
          <text class="picker-panel__summary">已选 {{ selectedOrderIds.length }} 个 · ¥{{ tempAmount }}</text>
          <text class="picker-panel__confirm" @tap="confirmOrderSelection">确认选择</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { createInvoice, getInvoiceableOrders } from '@/api/invoice';
import type { InvoiceableOrder } from '@/api/invoice';
import { sumFen, fenToYuan } from '@/utils/money';

/** 表单数据 */
const form = reactive({
  invoice_type: 1 as number,
  title: '',
  tax_no: '',
  bank_name: '',
  bank_account: '',
  company_address: '',
  company_phone: '',
});

/** 接收方式：1邮件 2邮寄 */
const deliveryMethod = ref<number>(1);
const email = ref('');
const deliveryAddress = ref('');

/** 可开票订单 */
const invoiceableOrders = ref<InvoiceableOrder[]>([]);
const selectedOrders = ref<InvoiceableOrder[]>([]);
const selectedOrderIds = ref<number[]>([]);
const showOrderPicker = ref(false);

/** 提交状态 */
const submitting = ref(false);

/** 发票金额（自动计算）— 基于 Decimal 精确求和 */
const invoiceAmount = computed(() => {
  const fenValues = selectedOrders.value.map(
    (order) => Math.round(parseFloat(order.uninvoiced_amount) * 100) || 0
  );
  const totalFen = sumFen(fenValues);
  return fenToYuan(totalFen);
});

/** 弹窗中临时金额 — 基于 Decimal 精确求和 */
const tempAmount = computed(() => {
  const fenValues: number[] = [];
  for (const id of selectedOrderIds.value) {
    const order = invoiceableOrders.value.find((o) => o.order_id === id);
    if (order) fenValues.push(Math.round(parseFloat(order.uninvoiced_amount) * 100) || 0);
  }
  const totalFen = sumFen(fenValues);
  return fenToYuan(totalFen);
});

/** 格式化日期 */
function formatDate(dateStr: string): string {
  return dateStr.substring(0, 10);
}

/** 加载可开票订单 */
async function loadOrders() {
  try {
    const data = await getInvoiceableOrders();
    invoiceableOrders.value = data;
  } catch {
    // 静默处理
  }
}

/** 是否已选中 */
function isSelected(orderId: number): boolean {
  return selectedOrderIds.value.includes(orderId);
}

/** 切换选中 */
function toggleOrder(order: InvoiceableOrder) {
  const idx = selectedOrderIds.value.indexOf(order.order_id);
  if (idx >= 0) {
    selectedOrderIds.value.splice(idx, 1);
  } else {
    selectedOrderIds.value.push(order.order_id);
  }
}

/** 全选 */
function selectAll() {
  if (selectedOrderIds.value.length === invoiceableOrders.value.length) {
    selectedOrderIds.value = [];
  } else {
    selectedOrderIds.value = invoiceableOrders.value.map((o) => o.order_id);
  }
}

/** 确认选择 */
function confirmOrderSelection() {
  selectedOrders.value = invoiceableOrders.value.filter(
    (o) => selectedOrderIds.value.includes(o.order_id)
  );
  showOrderPicker.value = false;
}

/** 移除已选订单 */
function removeOrder(orderId: number) {
  const idx = selectedOrderIds.value.indexOf(orderId);
  if (idx >= 0) {
    selectedOrderIds.value.splice(idx, 1);
  }
  selectedOrders.value = selectedOrders.value.filter((o) => o.order_id !== orderId);
}

/** 表单校验 */
function validate(): boolean {
  if (!form.title.trim()) {
    uni.showToast({ title: '请输入企业名称', icon: 'none' });
    return false;
  }
  if (!form.tax_no.trim()) {
    uni.showToast({ title: '请输入税号', icon: 'none' });
    return false;
  }
  if (form.invoice_type === 2) {
    if (!form.bank_name.trim()) {
      uni.showToast({ title: '请输入开户银行', icon: 'none' });
      return false;
    }
    if (!form.bank_account.trim()) {
      uni.showToast({ title: '请输入银行账号', icon: 'none' });
      return false;
    }
    if (!form.company_address.trim()) {
      uni.showToast({ title: '请输入注册地址', icon: 'none' });
      return false;
    }
    if (!form.company_phone.trim()) {
      uni.showToast({ title: '请输入注册电话', icon: 'none' });
      return false;
    }
  }
  if (selectedOrders.value.length === 0) {
    uni.showToast({ title: '请选择开票订单', icon: 'none' });
    return false;
  }
  if (Number(invoiceAmount.value) <= 0) {
    uni.showToast({ title: '发票金额必须大于0', icon: 'none' });
    return false;
  }
  if (deliveryMethod.value === 1 && !email.value.trim()) {
    uni.showToast({ title: '请输入接收邮箱', icon: 'none' });
    return false;
  }
  if (deliveryMethod.value === 2 && !deliveryAddress.value.trim()) {
    uni.showToast({ title: '请输入邮寄地址', icon: 'none' });
    return false;
  }
  return true;
}

/** 提交申请 */
async function handleSubmit() {
  if (!validate()) return;

  submitting.value = true;
  try {
    await createInvoice({
      order_ids: selectedOrderIds.value,
      invoice_type: form.invoice_type,
      title: form.title.trim(),
      tax_no: form.tax_no.trim(),
      invoice_amount: Number(invoiceAmount.value),
      bank_name: form.invoice_type === 2 ? form.bank_name.trim() : undefined,
      bank_account: form.invoice_type === 2 ? form.bank_account.trim() : undefined,
      company_address: form.invoice_type === 2 ? form.company_address.trim() : undefined,
      company_phone: form.invoice_type === 2 ? form.company_phone.trim() : undefined,
      delivery_method: deliveryMethod.value,
      email: deliveryMethod.value === 1 ? email.value.trim() : undefined,
      delivery_address: deliveryMethod.value === 2 ? deliveryAddress.value.trim() : undefined,
    });

    uni.showToast({ title: '申请提交成功', icon: 'success' });
    setTimeout(() => {
      uni.navigateBack();
    }, 1500);
  } catch {
    // 错误由统一拦截器处理
  } finally {
    submitting.value = false;
  }
}

// 页面加载
onLoad(() => {
  loadOrders();
});
</script>

<style lang="scss" scoped>
.invoice-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  padding-bottom: 180rpx;
}

/* 区块卡片 */
.section-card {
  background-color: $color-neutral-0;
  border-radius: $radius-lg;
  margin: $spacing-md;
  padding: $spacing-lg;
  box-shadow: $shadow-1;

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    margin-bottom: $spacing-lg;
    display: block;
  }
}

.required {
  color: $color-error;
  margin-left: 4rpx;
}

/* 发票类型选项 */
.type-options {
  display: flex;
  flex-direction: column;
  gap: $spacing-md;
}

.type-option {
  display: flex;
  align-items: flex-start;
  padding: $spacing-lg;
  border: 2rpx solid $color-neutral-200;
  border-radius: $radius-md;
  transition: all 0.2s ease;

  &--active {
    border-color: $color-primary-500;
    background-color: $color-primary-50;
  }

  &:active {
    opacity: 0.8;
  }

  &__radio {
    width: 40rpx;
    height: 40rpx;
    border: 2rpx solid $color-neutral-300;
    border-radius: $radius-full;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: $spacing-md;
    flex-shrink: 0;
    margin-top: 4rpx;
  }

  &--active &__radio {
    border-color: $color-primary-500;
  }

  &__radio-dot {
    width: 24rpx;
    height: 24rpx;
    border-radius: $radius-full;
    background-color: $color-primary-500;
  }

  &__content {
    flex: 1;
  }

  &__name {
    font-size: $font-size-base;
    font-weight: $font-weight-medium;
    color: $color-neutral-900;
    display: block;
    margin-bottom: 4rpx;
  }

  &__desc {
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }
}

/* 表单行 */
.form-row {
  display: flex;
  align-items: center;
  padding: $spacing-md 0;
  border-bottom: 2rpx solid $color-neutral-100;

  &:last-child {
    border-bottom: none;
  }

  &__label {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    min-width: 160rpx;
    flex-shrink: 0;
  }

  &__input {
    flex: 1;
    font-size: $font-size-base;
    color: $color-neutral-900;
    height: 60rpx;
  }
}

/* 订单选择按钮 */
.order-select-btn {
  display: flex;
  align-items: center;
  padding: $spacing-md;
  background-color: $color-neutral-50;
  border-radius: $radius-md;
  border: 2rpx solid $color-neutral-200;

  &:active {
    background-color: $color-neutral-100;
  }

  &__placeholder {
    flex: 1;
    font-size: $font-size-base;
    color: $color-neutral-400;
  }

  &__count {
    flex: 1;
    font-size: $font-size-base;
    color: $color-primary-500;
    font-weight: $font-weight-medium;
  }

  &__arrow {
    font-size: $font-size-xl;
    color: $color-neutral-300;
  }
}

/* 已选订单 */
.selected-orders {
  margin-top: $spacing-md;
}

.selected-order-item {
  display: flex;
  align-items: center;
  padding: $spacing-sm 0;
  border-bottom: 2rpx solid $color-neutral-50;

  &:last-child {
    border-bottom: none;
  }

  &__no {
    flex: 1;
    font-size: $font-size-sm;
    color: $color-neutral-700;
    font-family: $font-family-mono;
  }

  &__amount {
    font-size: $font-size-sm;
    color: $color-neutral-900;
    font-weight: $font-weight-medium;
    font-family: $font-family-mono;
    margin-right: $spacing-md;
  }

  &__remove {
    font-size: $font-size-lg;
    color: $color-neutral-300;
    padding: 0 $spacing-xs;

    &:active {
      color: $color-error;
    }
  }
}

/* 金额展示 */
.amount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;

  &__label {
    font-size: $font-size-base;
    color: $color-neutral-700;
  }

  &__value {
    font-size: $font-size-2xl;
    font-weight: $font-weight-bold;
    color: $color-primary-500;
    font-family: $font-family-mono;
  }
}

.amount-hint {
  font-size: $font-size-xs;
  color: $color-neutral-400;
  margin-top: $spacing-xs;
  display: block;
}

/* 接收方式 */
.delivery-options {
  display: flex;
  gap: $spacing-md;
}

.delivery-option {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: $spacing-lg;
  background-color: $color-neutral-50;
  border-radius: $radius-md;
  border: 2rpx solid transparent;
  transition: all 0.2s ease;

  &--active {
    border-color: $color-primary-500;
    background-color: $color-primary-50;
  }

  &:active {
    opacity: 0.8;
  }

  &__icon {
    font-size: 36rpx;
    margin-right: $spacing-sm;
  }

  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-700;
    font-weight: $font-weight-medium;
  }
}

/* 底部提交 */
.footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: $spacing-md $spacing-lg;
  background-color: $color-neutral-0;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.05);
}

.submit-btn {
  width: 100%;
  height: 96rpx;
  line-height: 96rpx;
  background-color: $color-primary-500;
  color: $color-neutral-0;
  font-size: $font-size-lg;
  font-weight: $font-weight-semibold;
  border-radius: $radius-md;
  border: none;

  &[disabled] {
    background-color: $color-neutral-300;
  }

  &:active:not([disabled]) {
    background-color: $color-primary-600;
  }
}

/* 订单选择弹窗 */
.picker-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 100;
  display: flex;
  align-items: flex-end;
}

.picker-panel {
  width: 100%;
  max-height: 80vh;
  background-color: $color-neutral-0;
  border-radius: $radius-2xl $radius-2xl 0 0;
  display: flex;
  flex-direction: column;

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: $spacing-lg;
    border-bottom: 2rpx solid $color-neutral-100;
    flex-shrink: 0;
  }

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: $spacing-md;
  }

  &__action {
    font-size: $font-size-sm;
    color: $color-primary-500;
  }

  &__close {
    font-size: $font-size-xl;
    color: $color-neutral-400;
    padding: $spacing-xs;
  }

  &__list {
    flex: 1;
    max-height: 50vh;
    padding: $spacing-sm;
  }

  &__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: $spacing-lg;
    border-top: 2rpx solid $color-neutral-100;
    flex-shrink: 0;
    padding-bottom: $spacing-xl;
  }

  &__summary {
    font-size: $font-size-sm;
    color: $color-neutral-600;
    font-family: $font-family-mono;
  }

  &__confirm {
    padding: $spacing-sm $spacing-xl;
    background-color: $color-primary-500;
    color: $color-neutral-0;
    font-size: $font-size-sm;
    font-weight: $font-weight-medium;
    border-radius: $radius-full;

    &:active {
      background-color: $color-primary-600;
    }
  }
}

.picker-order-item {
  display: flex;
  align-items: center;
  padding: $spacing-md $spacing-lg;
  border-radius: $radius-md;
  margin-bottom: $spacing-xs;
  transition: background-color 0.15s ease;

  &:active {
    background-color: $color-neutral-50;
  }

  &--selected {
    background-color: $color-primary-50;
  }

  &__check {
    width: 36rpx;
    height: 36rpx;
    border: 2rpx solid $color-neutral-300;
    border-radius: $radius-sm;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: $spacing-md;
    flex-shrink: 0;
  }

  &--selected &__check {
    border-color: $color-primary-500;
    background-color: $color-primary-500;
  }

  &__check-dot {
    width: 20rpx;
    height: 20rpx;
    border-radius: 4rpx;
    background-color: $color-neutral-0;
  }

  &__info {
    flex: 1;
    min-width: 0;
  }

  &__no {
    font-size: $font-size-sm;
    color: $color-neutral-900;
    font-family: $font-family-mono;
    display: block;
    margin-bottom: 4rpx;
  }

  &__amount {
    font-size: $font-size-xs;
    color: $color-neutral-500;
    font-family: $font-family-mono;
  }

  &__date {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    flex-shrink: 0;
    margin-left: $spacing-md;
  }
}

.picker-empty {
  text-align: center;
  padding: 80rpx 0;

  text {
    font-size: $font-size-sm;
    color: $color-neutral-400;
  }
}
</style>
