<template>
  <view class="step1-page">
    <!-- 页面标题 -->
    <view class="page-header">
      <text class="page-header__title">新建订单</text>
      <text class="page-header__step">步骤 1/5</text>
    </view>

    <!-- 收货方式 -->
    <view class="card section">
      <text class="section__title">收货方式</text>
      <view class="radio-group">
        <view
          class="radio-item"
          :class="{ active: form.delivery_method === 1 }"
          @tap="form.delivery_method = 1"
        >
          <view class="radio-dot" />
          <text class="radio-item__text">发送至门店</text>
          <text class="radio-item__desc">默认收货地址</text>
        </view>
        <view
          class="radio-item"
          :class="{ active: form.delivery_method === 2 }"
          @tap="form.delivery_method = 2"
        >
          <view class="radio-dot" />
          <text class="radio-item__text">直接发送至终端客户</text>
          <text class="radio-item__desc">需手动填写收货地址</text>
        </view>
      </view>
    </view>

    <!-- 终端客户地址（仅发到客户时显示） -->
    <view v-if="form.delivery_method === 2" class="card section">
      <text class="section__title">收货地址</text>
      <view class="form-row">
        <text class="form-row__label">收件人 <text class="required">*</text></text>
        <input
          v-model="form.receiver_name"
          class="form-row__input"
          placeholder="请输入收件人姓名"
        />
      </view>
      <view class="form-row">
        <text class="form-row__label">手机号 <text class="required">*</text></text>
        <input
          v-model="form.receiver_phone"
          class="form-row__input"
          type="number"
          maxlength="11"
          placeholder="请输入收件人手机号"
        />
      </view>
      <view class="form-row">
        <text class="form-row__label">省市区 <text class="required">*</text></text>
        <picker
          mode="region"
          :value="regionPicker"
          @change="onRegionChange"
        >
          <view class="form-row__picker">
            <text :class="regionText ? 'form-row__picker-value' : 'form-row__picker-placeholder'">
              {{ regionText || '请选择省市区' }}
            </text>
            <text class="form-row__picker-arrow">›</text>
          </view>
        </picker>
      </view>
      <view class="form-row">
        <text class="form-row__label">详细地址 <text class="required">*</text></text>
        <input
          v-model="form.receiver_detail"
          class="form-row__input"
          placeholder="请输入详细街道门牌号"
        />
      </view>
      <view class="save-address-row" @tap="form.save_address = form.save_address === 1 ? 0 : 1">
        <view class="save-address-row__check" :class="{ checked: form.save_address === 1 }">
          <text v-if="form.save_address === 1" class="save-address-row__icon">✓</text>
        </view>
        <text class="save-address-row__text">保存此地址到地址簿</text>
      </view>
    </view>

    <!-- 项目信息 -->
    <view class="card section">
      <text class="section__title">项目信息（选填）</text>
      <view class="form-row">
        <text class="form-row__label">项目名称</text>
        <input
          v-model="form.project_name"
          class="form-row__input"
          placeholder="如：万科样板间项目"
          maxlength="100"
        />
      </view>
      <view class="form-row">
        <text class="form-row__label">终端客户</text>
        <input
          v-model="form.end_customer"
          class="form-row__input"
          placeholder="如：王先生"
          maxlength="100"
        />
      </view>
    </view>

    <!-- 期望交期 -->
    <view class="card section">
      <text class="section__title">期望交期（选填）</text>
      <picker
        mode="date"
        :start="minDate"
        :value="form.expected_delivery_date"
        @change="onDateChange"
      >
        <view class="form-row">
          <text class="form-row__label">期望交期</text>
          <view class="form-row__picker">
            <text
              :class="form.expected_delivery_date ? 'form-row__picker-value' : 'form-row__picker-placeholder'"
            >
              {{ form.expected_delivery_date || '请选择日期（不早于7天后）' }}
            </text>
            <text class="form-row__picker-arrow">›</text>
          </view>
        </view>
      </picker>
    </view>

    <!-- 发票 -->
    <view class="card section">
      <view class="switch-row">
        <text class="switch-row__label">是否需要发票</text>
        <switch
          :checked="form.invoice_required === 1"
          color="#56638F"
          @change="form.invoice_required = form.invoice_required === 1 ? 0 : 1"
        />
      </view>
    </view>

    <!-- 备注 -->
    <view class="card section">
      <text class="section__title">备注（选填）</text>
      <view class="textarea-wrap">
        <textarea
          v-model="form.remark"
          class="textarea-wrap__input"
          placeholder="请输入备注信息，如安装注意事项等"
          maxlength="200"
        />
        <text class="textarea-wrap__count">{{ (form.remark || '').length }}/200</text>
      </view>
    </view>

    <!-- 附件上传 -->
    <view class="card section">
      <text class="section__title">附件（选填，最多5张）</text>
      <view class="upload-grid">
        <view
          v-for="(img, idx) in attachments"
          :key="idx"
          class="upload-item"
        >
          <image
            class="upload-item__image"
            :src="img"
            mode="aspectFill"
            @tap="previewImage(idx)"
          />
          <view class="upload-item__remove" @tap="removeAttachment(idx)">
            <text class="upload-item__remove-icon">×</text>
          </view>
        </view>
        <view
          v-if="attachments.length < 5"
          class="upload-add"
          @tap="chooseImage"
        >
          <text class="upload-add__icon">＋</text>
          <text class="upload-add__text">上传</text>
        </view>
      </view>
    </view>

    <!-- 底部按钮 -->
    <view class="step1-page__footer safe-area-bottom">
      <button
        class="submit-btn"
        :disabled="submitting"
        :class="{ loading: submitting }"
        @tap="handleNext"
      >
        {{ submitting ? '提交中...' : '下一步' }}
      </button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { reactive, ref, computed } from 'vue';
import { useOrderStore } from '@/stores/order';
import { createOrder } from '@/api/order';
import type { CreateOrderParams } from '@/types/order';
import { isValidPhone } from '@/utils/validator';
import { DeliveryMethod } from '@/types/common';

const orderStore = useOrderStore();

/** 表单数据 */
const form = reactive({
  delivery_method: DeliveryMethod.TO_STORE as number,
  receiver_name: '',
  receiver_phone: '',
  receiver_province: '',
  receiver_city: '',
  receiver_district: '',
  receiver_detail: '',
  project_name: '',
  end_customer: '',
  expected_delivery_date: '',
  invoice_required: 0 as number,
  remark: '',
  save_address: 0 as number,
});

/** 附件列表 */
const attachments = ref<string[]>([]);

/** 提交中 */
const submitting = ref<boolean>(false);

/** 省市区 picker 值 */
const regionPicker = computed<string[]>(() => {
  if (form.receiver_province) {
    return [form.receiver_province, form.receiver_city, form.receiver_district];
  }
  return [];
});

/** 省市区显示文字 */
const regionText = computed<string>(() => {
  if (form.receiver_province) {
    return `${form.receiver_province} ${form.receiver_city} ${form.receiver_district}`;
  }
  return '';
});

/** 最小可选日期（今天+7天） */
const minDate = computed<string>(() => {
  const d = new Date();
  d.setDate(d.getDate() + 7);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});

/**
 * 省市区选择
 */
function onRegionChange(e: { detail: { value: string[] } }): void {
  const [province, city, district] = e.detail.value;
  form.receiver_province = province;
  form.receiver_city = city;
  form.receiver_district = district;
}

/**
 * 日期选择
 */
function onDateChange(e: { detail: { value: string } }): void {
  form.expected_delivery_date = e.detail.value;
}

/**
 * 选择图片
 */
function chooseImage(): void {
  uni.chooseImage({
    count: 5 - attachments.value.length,
    sizeType: ['compressed'],
    sourceType: ['album', 'camera'],
    success: (res) => {
      const remaining = 5 - attachments.value.length;
      const files = res.tempFilePaths.slice(0, remaining);
      attachments.value.push(...files);
    },
  });
}

/**
 * 预览图片
 */
function previewImage(index: number): void {
  uni.previewImage({
    urls: attachments.value,
    current: index,
  });
}

/**
 * 删除附件
 */
function removeAttachment(index: number): void {
  attachments.value.splice(index, 1);
}

/**
 * 表单校验
 */
function validate(): boolean {
  if (form.delivery_method === DeliveryMethod.TO_CUSTOMER) {
    if (!form.receiver_name.trim()) {
      uni.showToast({ title: '请输入收件人姓名', icon: 'none' });
      return false;
    }
    if (!isValidPhone(form.receiver_phone)) {
      uni.showToast({ title: '请输入正确的收件人手机号', icon: 'none' });
      return false;
    }
    if (!form.receiver_province) {
      uni.showToast({ title: '请选择省市区', icon: 'none' });
      return false;
    }
    if (!form.receiver_detail.trim()) {
      uni.showToast({ title: '请输入详细地址', icon: 'none' });
      return false;
    }
  }
  return true;
}

/**
 * 下一步 — 创建订单草稿
 */
async function handleNext(): Promise<void> {
  if (!validate()) return;
  if (submitting.value) return;

  submitting.value = true;
  try {
    const params: CreateOrderParams = {
      delivery_method: form.delivery_method as 1 | 2,
      project_name: form.project_name || undefined,
      end_customer: form.end_customer || undefined,
      expected_delivery_date: form.expected_delivery_date || undefined,
      invoice_required: form.invoice_required,
      remark: form.remark || undefined,
      attachments: attachments.value.length > 0 ? attachments.value : undefined,
      save_address: form.save_address,
    };

    // 发到客户时附加地址信息
    if (form.delivery_method === DeliveryMethod.TO_CUSTOMER) {
      params.receiver_name = form.receiver_name;
      params.receiver_phone = form.receiver_phone;
      params.receiver_province = form.receiver_province;
      params.receiver_city = form.receiver_city;
      params.receiver_district = form.receiver_district;
      params.receiver_detail = form.receiver_detail;
    }

    const result = await createOrder(params);
    orderStore.setOrderId(result.order_id, result.order_no);
    orderStore.setOrderBase(params);

    uni.navigateTo({ url: '/pages/order/create-step2' });
  } catch {
    /* handled in request interceptor */
  } finally {
    submitting.value = false;
  }
}
</script>

<style lang="scss" scoped>
.step1-page {
  padding: $spacing-lg;
  padding-bottom: 200rpx;
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
  margin-bottom: $spacing-lg;

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

// ── 区块 ──
.section {
  margin-bottom: $spacing-md;

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-800;
    margin-bottom: $spacing-md;
  }
}

// ── 单选组 ──
.radio-group {
  display: flex;
  flex-direction: column;
  gap: $spacing-sm;
}
.radio-item {
  display: flex;
  align-items: center;
  padding: $spacing-md $spacing-lg;
  background-color: $color-neutral-50;
  border-radius: $radius-md;
  border: 2rpx solid transparent;
  transition: all 0.2s;

  &.active {
    border-color: $color-primary-500;
    background-color: $color-primary-50;
  }

  &__text {
    font-size: $font-size-base;
    color: $color-neutral-800;
    font-weight: $font-weight-medium;
  }
  &__desc {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    margin-left: auto;
    padding-left: $spacing-md;
  }
}
.radio-dot {
  width: 36rpx;
  height: 36rpx;
  border-radius: $radius-full;
  border: 3rpx solid $color-neutral-300;
  margin-right: $spacing-md;
  flex-shrink: 0;
  transition: all 0.2s;

  .active > & {
    border-color: $color-primary-500;
    background-color: $color-primary-500;
    box-shadow: inset 0 0 0 6rpx $color-neutral-0;
  }
}

// ── 表单行 ──
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
    color: $color-neutral-600;
    min-width: 140rpx;
    flex-shrink: 0;
  }
  &__input {
    flex: 1;
    font-size: $font-size-base;
    color: $color-neutral-900;
    text-align: right;
  }
  &__picker {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }
  &__picker-value {
    font-size: $font-size-base;
    color: $color-neutral-900;
  }
  &__picker-placeholder {
    font-size: $font-size-base;
    color: $color-neutral-400;
  }
  &__picker-arrow {
    font-size: $font-size-lg;
    color: $color-neutral-300;
    margin-left: $spacing-sm;
  }
}

.required {
  color: $color-error;
  font-size: $font-size-sm;
}

// ── 保存地址勾选 ──
.save-address-row {
  display: flex;
  align-items: center;
  padding-top: $spacing-md;
  margin-top: $spacing-sm;

  &__check {
    width: 32rpx;
    height: 32rpx;
    border-radius: $radius-sm;
    border: 2rpx solid $color-neutral-300;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: $spacing-sm;

    &.checked {
      background-color: $color-primary-500;
      border-color: $color-primary-500;
    }
  }
  &__icon {
    color: $color-neutral-0;
    font-size: 20rpx;
    font-weight: $font-weight-bold;
  }
  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-600;
  }
}

// ── 开关行 ──
.switch-row {
  display: flex;
  align-items: center;
  justify-content: space-between;

  &__label {
    font-size: $font-size-base;
    color: $color-neutral-800;
    font-weight: $font-weight-medium;
  }
}

// ── 文本域 ──
.textarea-wrap {
  position: relative;

  &__input {
    width: 100%;
    height: 200rpx;
    font-size: $font-size-base;
    color: $color-neutral-900;
    background-color: $color-neutral-50;
    border-radius: $radius-md;
    padding: $spacing-md;
    box-sizing: border-box;
  }
  &__count {
    position: absolute;
    right: $spacing-md;
    bottom: $spacing-sm;
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }
}

// ── 上传 ──
.upload-grid {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
}
.upload-item {
  position: relative;
  width: 160rpx;
  height: 160rpx;
  border-radius: $radius-md;
  overflow: hidden;

  &__image {
    width: 100%;
    height: 100%;
  }
  &__remove {
    position: absolute;
    top: 4rpx;
    right: 4rpx;
    width: 40rpx;
    height: 40rpx;
    background-color: rgba(0, 0, 0, 0.5);
    border-radius: $radius-full;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  &__remove-icon {
    color: $color-neutral-0;
    font-size: 24rpx;
  }
}
.upload-add {
  width: 160rpx;
  height: 160rpx;
  border: 2rpx dashed $color-neutral-300;
  border-radius: $radius-md;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  &__icon {
    font-size: 40rpx;
    color: $color-neutral-400;
  }
  &__text {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    margin-top: 4rpx;
  }
}

// ── 提交按钮 ──
.submit-btn {
  width: 100%;
  height: 96rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: $color-primary-500;
  color: $color-neutral-0;
  font-size: $font-size-lg;
  font-weight: $font-weight-semibold;
  border-radius: $radius-md;
  border: none;

  &.loading {
    opacity: 0.7;
  }
  &[disabled] {
    background-color: $color-neutral-200;
    color: $color-neutral-400;
  }
}
</style>
