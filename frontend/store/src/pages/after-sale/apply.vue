<template>
  <view class="after-sale-page">
    <!-- 订单选择 -->
    <view class="section-card">
      <text class="section-card__title">关联订单 <text class="required">*</text></text>
      <view class="order-select" @tap="showOrderPicker = true">
        <text v-if="selectedOrder" class="order-select__value">{{ selectedOrder.order_no }}</text>
        <text v-else class="order-select__placeholder">请选择或搜索订单</text>
        <text class="order-select__arrow">›</text>
      </view>
      <!-- 搜索订单 -->
      <view class="order-search">
        <input
          v-model="orderKeyword"
          class="order-search__input"
          placeholder="输入订单号搜索"
          confirm-type="search"
          @confirm="searchOrders"
        />
      </view>
    </view>

    <!-- 问题类型 -->
    <view class="section-card">
      <text class="section-card__title">问题类型 <text class="required">*</text></text>
      <view class="option-grid">
        <view
          v-for="pt in problemTypes"
          :key="pt.value"
          class="option-tag"
          :class="{ 'option-tag--active': form.problem_type === pt.value }"
          @tap="form.problem_type = pt.value"
        >
          <text>{{ pt.label }}</text>
        </view>
      </view>
    </view>

    <!-- 问题描述 -->
    <view class="section-card">
      <text class="section-card__title">问题描述 <text class="required">*</text></text>
      <view class="desc-wrapper">
        <textarea
          v-model="form.problem_desc"
          class="desc-textarea"
          placeholder="请详细描述遇到的问题（如故障现象、发生时间等）"
          :maxlength="300"
          @input="onDescInput"
        />
        <text class="desc-counter">{{ form.problem_desc.length }}/300</text>
      </view>
    </view>

    <!-- 上传图片 -->
    <view class="section-card">
      <text class="section-card__title">上传图片 <text class="optional">(最多6张)</text></text>
      <view class="image-grid">
        <view v-for="(img, idx) in imageList" :key="idx" class="image-item">
          <image class="image-item__img" :src="img" mode="aspectFill" @tap="previewImage(idx)" />
          <view class="image-item__remove" @tap.stop="removeImage(idx)">
            <text class="image-item__remove-icon">×</text>
          </view>
        </view>
        <view v-if="imageList.length < 6" class="image-add" @tap="chooseImage">
          <text class="image-add__icon">+</text>
          <text class="image-add__text">上传</text>
        </view>
      </view>
    </view>

    <!-- 联系方式 -->
    <view class="section-card">
      <text class="section-card__title">联系方式</text>
      <view class="form-row">
        <text class="form-row__label">联系人</text>
        <input v-model="form.contact_name" class="form-row__input" placeholder="请输入联系人姓名" />
      </view>
      <view class="form-row">
        <text class="form-row__label">手机号</text>
        <input
          v-model="form.contact_phone"
          class="form-row__input"
          type="number"
          placeholder="请输入联系电话"
          :maxlength="11"
        />
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
          <text class="picker-panel__title">选择订单</text>
          <text class="picker-panel__close" @tap="showOrderPicker = false">×</text>
        </view>
        <scroll-view scroll-y class="picker-panel__list">
          <view
            v-for="order in orderList"
            :key="order.order_id"
            class="picker-order-item"
            :class="{ 'picker-order-item--selected': selectedOrder?.order_id === order.order_id }"
            @tap="selectOrder(order)"
          >
            <text class="picker-order-item__no">{{ order.order_no }}</text>
            <text class="picker-order-item__info">{{ order.item_count }}副 · ¥{{ order.total_amount }}</text>
            <text class="picker-order-item__date">{{ order.created_at }}</text>
          </view>
          <view v-if="orderList.length === 0" class="picker-empty">
            <text>暂无可售后订单</text>
          </view>
        </scroll-view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { createAfterSale, getAfterSaleOrders } from '@/api/after-sale';
import type { AfterSaleOrderItem } from '@/api/after-sale';
import { useAuthStore } from '@/stores/auth';

/** 问题类型选项 */
const problemTypes = [
  { label: '电机故障', value: 1 },
  { label: '电源问题', value: 2 },
  { label: '遥控器', value: 3 },
  { label: '墙面控制', value: 4 },
  { label: '轨道', value: 5 },
  { label: '面料', value: 6 },
  { label: '结构', value: 7 },
  { label: '安装', value: 8 },
  { label: '初始化', value: 9 },
  { label: '运输损坏', value: 10 },
  { label: '其他', value: 11 },
];

const authStore = useAuthStore();

/** 表单数据 */
const form = reactive({
  problem_type: 0,
  problem_desc: '',
  contact_name: '',
  contact_phone: '',
});

/** 订单相关 */
const orderList = ref<AfterSaleOrderItem[]>([]);
const selectedOrder = ref<AfterSaleOrderItem | null>(null);
const showOrderPicker = ref(false);
const orderKeyword = ref('');

/** 图片列表 */
const imageList = ref<string[]>([]);

/** 提交状态 */
const submitting = ref(false);

/** 加载可售后订单 */
async function loadOrders(keyword?: string) {
  try {
    const data = await getAfterSaleOrders(keyword);
    orderList.value = data.list;
  } catch {
    // 静默处理
  }
}

/** 搜索订单 */
function searchOrders() {
  loadOrders(orderKeyword.value.trim() || undefined);
}

/** 选择订单 */
function selectOrder(order: AfterSaleOrderItem) {
  selectedOrder.value = order;
  showOrderPicker.value = false;
}

/** 描述输入 */
function onDescInput() {
  // 限制300字
  if (form.problem_desc.length > 300) {
    form.problem_desc = form.problem_desc.substring(0, 300);
  }
}

/** 选择图片 */
function chooseImage() {
  const remaining = 6 - imageList.value.length;
  if (remaining <= 0) return;

  uni.chooseImage({
    count: remaining,
    sizeType: ['compressed'],
    sourceType: ['album', 'camera'],
    success: (res) => {
      // 添加到图片列表（实际项目需上传到服务器获取URL）
      const newImages = res.tempFilePaths.slice(0, remaining);
      imageList.value = [...imageList.value, ...newImages];
    },
  });
}

/** 预览图片 */
function previewImage(index: number) {
  uni.previewImage({
    current: index,
    urls: imageList.value,
  });
}

/** 删除图片 */
function removeImage(index: number) {
  imageList.value.splice(index, 1);
}

/** 初始化联系人信息 */
function initContactInfo() {
  form.contact_name = authStore.realName || '';
  const profile = authStore.profile;
  if (profile?.phone) {
    form.contact_phone = profile.phone;
  }
}

/** 提交申请 */
async function handleSubmit() {
  // 校验
  if (!selectedOrder.value) {
    uni.showToast({ title: '请选择关联订单', icon: 'none' });
    return;
  }
  if (!form.problem_type) {
    uni.showToast({ title: '请选择问题类型', icon: 'none' });
    return;
  }
  if (!form.problem_desc.trim()) {
    uni.showToast({ title: '请描述问题', icon: 'none' });
    return;
  }
  if (!form.contact_phone || form.contact_phone.length < 11) {
    uni.showToast({ title: '请输入正确的联系电话', icon: 'none' });
    return;
  }

  submitting.value = true;
  try {
    await createAfterSale({
      order_id: selectedOrder.value.order_id,
      problem_type: form.problem_type,
      problem_desc: form.problem_desc,
      images: imageList.value.length > 0 ? imageList.value : undefined,
      affect_usage: 1,
      contact_name: form.contact_name || authStore.realName,
      contact_phone: form.contact_phone,
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
  initContactInfo();
});
</script>

<style lang="scss" scoped>
.after-sale-page {
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

.optional {
  font-size: $font-size-xs;
  color: $color-neutral-400;
  font-weight: $font-weight-regular;
}

/* 订单选择 */
.order-select {
  display: flex;
  align-items: center;
  padding: $spacing-md;
  background-color: $color-neutral-50;
  border-radius: $radius-md;
  margin-bottom: $spacing-sm;

  &__value {
    flex: 1;
    font-size: $font-size-base;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }

  &__placeholder {
    flex: 1;
    font-size: $font-size-base;
    color: $color-neutral-400;
  }

  &__arrow {
    font-size: $font-size-xl;
    color: $color-neutral-300;
  }
}

.order-search {
  &__input {
    width: 100%;
    height: 64rpx;
    padding: 0 $spacing-md;
    background-color: $color-neutral-50;
    border-radius: $radius-md;
    font-size: $font-size-sm;
    color: $color-neutral-900;
  }
}

/* 问题类型网格 */
.option-grid {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
}

.option-tag {
  padding: $spacing-sm $spacing-lg;
  background-color: $color-neutral-100;
  border-radius: $radius-full;
  font-size: $font-size-sm;
  color: $color-neutral-600;
  transition: all 0.2s ease;

  &:active {
    opacity: 0.7;
  }

  &--active {
    background-color: $color-primary-500;
    color: $color-neutral-0;
  }
}

/* 问题描述 */
.desc-wrapper {
  position: relative;
}

.desc-textarea {
  width: 100%;
  height: 240rpx;
  font-size: $font-size-base;
  background-color: $color-neutral-50;
  border-radius: $radius-md;
  padding: $spacing-md;
  line-height: $line-height-relaxed;
}

.desc-counter {
  position: absolute;
  right: $spacing-md;
  bottom: $spacing-sm;
  font-size: $font-size-xs;
  color: $color-neutral-400;
}

/* 图片上传 */
.image-grid {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
}

.image-item {
  width: 180rpx;
  height: 180rpx;
  border-radius: $radius-md;
  overflow: hidden;
  position: relative;

  &__img {
    width: 100%;
    height: 100%;
  }

  &__remove {
    position: absolute;
    top: 0;
    right: 0;
    width: 44rpx;
    height: 44rpx;
    background-color: rgba(0, 0, 0, 0.5);
    border-radius: 0 0 0 $radius-md;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  &__remove-icon {
    font-size: 28rpx;
    color: $color-neutral-0;
  }
}

.image-add {
  width: 180rpx;
  height: 180rpx;
  border: 2rpx dashed $color-neutral-200;
  border-radius: $radius-md;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  &:active {
    background-color: $color-neutral-50;
  }

  &__icon {
    font-size: 56rpx;
    color: $color-neutral-300;
    line-height: 1;
  }

  &__text {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    margin-top: $spacing-xs;
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
    min-width: 140rpx;
    flex-shrink: 0;
  }

  &__input {
    flex: 1;
    font-size: $font-size-base;
    color: $color-neutral-900;
    height: 60rpx;
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
  max-height: 70vh;
  background-color: $color-neutral-0;
  border-radius: $radius-2xl $radius-2xl 0 0;
  overflow: hidden;

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: $spacing-lg;
    border-bottom: 2rpx solid $color-neutral-100;
  }

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__close {
    font-size: $font-size-xl;
    color: $color-neutral-400;
    padding: $spacing-xs;
  }

  &__list {
    max-height: 60vh;
    padding: $spacing-sm;
  }
}

.picker-order-item {
  padding: $spacing-lg;
  border-radius: $radius-md;
  margin-bottom: $spacing-sm;
  background-color: $color-neutral-50;

  &:active {
    background-color: $color-neutral-100;
  }

  &--selected {
    background-color: $color-primary-50;
    border: 2rpx solid $color-primary-500;
  }

  &__no {
    font-size: $font-size-sm;
    color: $color-neutral-900;
    font-family: $font-family-mono;
    display: block;
    margin-bottom: 4rpx;
  }

  &__info {
    font-size: $font-size-xs;
    color: $color-neutral-500;
    display: block;
    margin-bottom: 4rpx;
  }

  &__date {
    font-size: $font-size-xs;
    color: $color-neutral-400;
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
