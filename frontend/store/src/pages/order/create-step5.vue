<template>
  <view class="preview-page">
    <!-- 加载中 -->
    <view v-if="loading" class="loading-state">
      <text class="loading-text">正在生成订单预览...</text>
    </view>

    <template v-else-if="preview">
      <!-- 收货方式选择 -->
      <view class="card delivery-card">
        <view class="card__title">🚚 收货方式</view>
        <view class="delivery-options">
          <view
            class="delivery-radio"
            :class="{ 'delivery-radio--active': deliveryMethod === 'store' }"
            @tap="deliveryMethod = 'store'"
          >
            <view class="radio-dot" :class="{ 'radio-dot--checked': deliveryMethod === 'store' }" />
            <text class="delivery-radio__text">门店地址</text>
          </view>
          <view
            class="delivery-radio"
            :class="{ 'delivery-radio--active': deliveryMethod === 'customer' }"
            @tap="deliveryMethod = 'customer'"
          >
            <view class="radio-dot" :class="{ 'radio-dot--checked': deliveryMethod === 'customer' }" />
            <text class="delivery-radio__text">终端客户地址</text>
          </view>
        </view>

        <!-- 门店地址展示（只读） -->
        <view v-if="deliveryMethod === 'store'" class="address-display address-display--readonly">
          <view class="address-display__info">
            <text class="address-display__name">{{ storeAddress.receiver_name }}</text>
            <text class="address-display__phone">{{ maskPhone(storeAddress.receiver_phone) }}</text>
          </view>
          <text class="address-display__detail">{{ storeAddress.full_address }}</text>
          <view class="address-display__hint">
            <text>门店注册地址（只读）</text>
          </view>
        </view>

        <!-- 终端客户地址 -->
        <view v-else class="customer-address-section">
          <!-- 已选地址展示 -->
          <view v-if="selectedCustomerAddress" class="address-display address-display--selectable">
            <view class="address-display__info">
              <text class="address-display__name">{{ selectedCustomerAddress.receiver_name }}</text>
              <text class="address-display__phone">{{ maskPhone(selectedCustomerAddress.receiver_phone) }}</text>
            </view>
            <text class="address-display__detail">{{ selectedCustomerAddress.full_address || formatFullAddress(selectedCustomerAddress) }}</text>
            <view class="address-display__change" @tap="goSelectAddress">
              <text>更换地址</text>
            </view>
          </view>

          <!-- 未选地址 - 操作区 -->
          <view v-else class="customer-address-empty">
            <text class="customer-address-empty__text">请选择或新增终端客户地址</text>
            <view class="customer-address-empty__actions">
              <view class="action-link" @tap="goSelectAddress">
                <text>从地址簿选择</text>
              </view>
              <view class="action-link action-link--primary" @tap="goAddAddress">
                <text>+ 新增地址</text>
              </view>
            </view>
          </view>

          <!-- 地址簿快捷入口（有地址时展示已保存的地址列表供快速选择） -->
          <view v-if="!selectedCustomerAddress && savedAddresses.length > 0" class="address-quick-list">
            <view class="address-quick-list__title">
              <text>已保存的地址</text>
            </view>
            <view
              v-for="addr in savedAddresses"
              :key="addr.id"
              class="address-quick-item"
              @tap="selectCustomerAddress(addr)"
            >
              <view class="address-quick-item__info">
                <text class="address-quick-item__name">{{ addr.receiver_name }}</text>
                <text class="address-quick-item__phone">{{ maskPhone(addr.receiver_phone) }}</text>
              </view>
              <text class="address-quick-item__detail">{{ addr.full_address || formatFullAddress(addr) }}</text>
            </view>
          </view>
        </view>
      </view>

      <!-- 订单基本信息 -->
      <view class="card">
        <view class="card__title">📋 订单信息</view>
        <view class="info-row">
          <text class="info-label">订单号</text>
          <text class="info-value info-value--mono">{{ preview.order_no }}</text>
        </view>
        <view class="info-row">
          <text class="info-label">窗帘数量</text>
          <text class="info-value">{{ preview.summary.item_count }}副</text>
        </view>
      </view>

      <!-- 窗帘明细列表 -->
      <view class="card">
        <view class="card__title">🪟 窗帘明细</view>
        <view
          v-for="(item, index) in preview.items"
          :key="item.item_no"
          class="preview-item"
        >
          <view class="preview-item__header">
            <text class="preview-item__index">第{{ index + 1 }}副</text>
            <text class="preview-item__total">¥{{ formatMoney(item.item_total) }}</text>
          </view>
          <view class="preview-item__info">
            <text class="preview-item__position">{{ item.install_position }}</text>
            <text class="preview-item__size">{{ item.width }} × {{ item.height }}cm</text>
          </view>
          <view class="preview-item__amounts">
            <view class="amount-tag">
              <text>轨道</text>
              <text>¥{{ formatMoney(item.track_amount) }}</text>
            </view>
            <view class="amount-tag">
              <text>面料</text>
              <text>¥{{ formatMoney(item.fabric_amount) }}</text>
            </view>
            <view v-if="Number(item.accessory_amount) > 0" class="amount-tag">
              <text>配件</text>
              <text>¥{{ formatMoney(item.accessory_amount) }}</text>
            </view>
            <view v-if="Number(item.kit_amount) > 0" class="amount-tag amount-tag--success">
              <text>库存抵扣</text>
              <text>-¥{{ formatMoney(item.kit_amount) }}</text>
            </view>
            <view v-if="Number(item.nonstandard_amount) > 0" class="amount-tag amount-tag--warning">
              <text>非标加价</text>
              <text>+¥{{ formatMoney(item.nonstandard_amount) }}</text>
            </view>
          </view>
        </view>
      </view>

      <!-- 费用汇总大卡片 -->
      <view class="card summary-card">
        <view class="card__title">💰 费用汇总</view>

        <view class="summary-row">
          <text class="summary-row__label">轨道总额</text>
          <text class="summary-row__value">¥{{ formatMoney(preview.summary.track_amount) }}</text>
        </view>
        <view class="summary-row">
          <text class="summary-row__label">面料总额（{{ preview.summary.fabric_area_total }}㎡）</text>
          <text class="summary-row__value">¥{{ formatMoney(preview.summary.fabric_amount) }}</text>
        </view>
        <view class="summary-row">
          <text class="summary-row__label">配件总额</text>
          <text class="summary-row__value">¥{{ formatMoney(preview.summary.accessory_amount) }}</text>
        </view>

        <!-- 库存使用摘要 -->
        <view v-if="preview.summary.inventory_used_count > 0" class="summary-section">
          <view class="summary-row">
            <text class="summary-row__label">使用库存套件</text>
            <text class="summary-row__value">{{ preview.inventory_summary.kit_use_in_order }}套</text>
          </view>
          <view class="summary-row">
            <text class="summary-row__label">新购套件</text>
            <text class="summary-row__value">{{ preview.summary.new_purchase_count }}套 · ¥{{ formatMoney(preview.summary.new_purchase_amount) }}</text>
          </view>
        </view>

        <!-- 非标加价 -->
        <view v-if="Number(preview.summary.nonstandard_amount) > 0" class="summary-row">
          <text class="summary-row__label">非标加价</text>
          <text class="summary-row__value summary-row__value--warning">+¥{{ formatMoney(preview.summary.nonstandard_amount) }}</text>
        </view>

        <!-- 折扣 -->
        <view v-if="Number(preview.summary.discount_amount) > 0" class="summary-row">
          <text class="summary-row__label">折扣优惠</text>
          <text class="summary-row__value summary-row__value--success">-¥{{ formatMoney(preview.summary.discount_amount) }}</text>
        </view>

        <!-- 合计 -->
        <view class="summary-total">
          <text class="summary-total__label">合计金额</text>
          <text class="summary-total__value">¥{{ formatMoney(preview.summary.total_amount) }}</text>
        </view>
      </view>

      <!-- 库存使用摘要 -->
      <view v-if="preview.inventory_summary" class="card">
        <view class="card__title">📦 库存摘要</view>
        <view class="info-row">
          <text class="info-label">可用库存</text>
          <text class="info-value">{{ preview.inventory_summary.kit_available }}套</text>
        </view>
        <view class="info-row">
          <text class="info-label">本单使用</text>
          <text class="info-value">{{ preview.inventory_summary.kit_use_in_order }}套</text>
        </view>
        <view class="info-row">
          <text class="info-label">其他订单锁定</text>
          <text class="info-value">{{ preview.inventory_summary.kit_locked_other }}套</text>
        </view>
        <view class="info-row">
          <text class="info-label">下单后剩余</text>
          <text class="info-value" :class="{ 'text-warning': preview.inventory_summary.kit_remaining_after_order < 10 }">
            {{ preview.inventory_summary.kit_remaining_after_order }}套
          </text>
        </view>
      </view>

      <!-- 底部占位 -->
      <view class="bottom-placeholder" />

      <!-- 底部提交按钮 -->
      <view class="submit-bar safe-area-bottom">
        <view class="submit-bar__info">
          <text class="submit-bar__count">共{{ preview.summary.item_count }}副</text>
          <text class="submit-bar__total">¥{{ formatMoney(preview.summary.total_amount) }}</text>
        </view>
        <button
          class="submit-bar__btn"
          :disabled="submitting"
          @tap="handleSubmit"
        >{{ submitting ? '提交中...' : '提交订单' }}</button>
      </view>
    </template>
  </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { getOrderPreview, submitOrder } from '@/api/order';
import { getAddressList } from '@/api/address';
import { useOrderStore } from '@/stores/order';
import { getAccountProfile } from '@/api/auth';
import { formatMoney } from '@/utils/format';
import type { OrderPreviewData } from '@/types/order';
import type { AddressItem, ShippingAddressSnapshot } from '@/types/address';
import type { StoreDetail } from '@/types/user';

const orderStore = useOrderStore();

/** 预览数据 */
const preview = ref<OrderPreviewData | null>(null);
const loading = ref(true);
const submitting = ref(false);

/** 收货方式：store=门店地址, customer=终端客户地址 */
const deliveryMethod = ref<'store' | 'customer'>('store');

/** 门店地址（只读） */
const storeAddress = ref<{
  receiver_name: string;
  receiver_phone: string;
  full_address: string;
}>({
  receiver_name: '',
  receiver_phone: '',
  full_address: '',
});

/** 已保存的客户地址列表 */
const savedAddresses = ref<AddressItem[]>([]);

/** 当前选中的客户地址 */
const selectedCustomerAddress = ref<AddressItem | null>(null);

/** 门店信息 */
const storeInfo = ref<StoreDetail | null>(null);

/** 手机号脱敏 */
function maskPhone(phone: string): string {
  if (phone.length >= 7) {
    return phone.substring(0, 3) + '****' + phone.substring(phone.length - 4);
  }
  return phone;
}

/** 前端兜底拼接完整地址 */
function formatFullAddress(addr: AddressItem): string {
  return `${addr.province}${addr.city}${addr.district}${addr.detail_address}`;
}

/** 加载门店信息 */
async function loadStoreInfo() {
  try {
    const data = await getAccountProfile();
    const store = data.current_store;
    storeInfo.value = store;
    storeAddress.value = {
      receiver_name: data.real_name,
      receiver_phone: store.contact_phone,
      full_address: `${store.province}${store.city}${store.district}${store.address}`,
    };
  } catch {
    // 静默处理
  }
}

/** 加载已保存地址列表 */
async function loadSavedAddresses() {
  try {
    savedAddresses.value = await getAddressList();
  } catch {
    // 静默处理
  }
}

/** 选中客户地址 */
function selectCustomerAddress(addr: AddressItem) {
  selectedCustomerAddress.value = addr;
}

/** 跳转到地址选择页 */
function goSelectAddress() {
  uni.navigateTo({
    url: '/pages/address/index?mode=select&source=step5',
  });
}

/** 跳转到新增地址页 */
function goAddAddress() {
  uni.navigateTo({
    url: '/pages/address/edit',
  });
}

/** 获取当前收货地址快照 */
function getShippingSnapshot(): ShippingAddressSnapshot | null {
  if (deliveryMethod.value === 'store') {
    return {
      receiver_name: storeAddress.value.receiver_name,
      receiver_phone: storeAddress.value.receiver_phone,
      province: storeInfo.value?.province || '',
      city: storeInfo.value?.city || '',
      district: storeInfo.value?.district || '',
      detail_address: storeInfo.value?.address || '',
      full_address: storeAddress.value.full_address,
    };
  } else {
    const addr = selectedCustomerAddress.value;
    if (!addr) return null;
    return {
      receiver_name: addr.receiver_name,
      receiver_phone: addr.receiver_phone,
      province: addr.province,
      city: addr.city,
      district: addr.district,
      detail_address: addr.detail_address,
      full_address: addr.full_address || formatFullAddress(addr),
    };
  }
}

/** 加载预览数据 */
async function loadPreview() {
  if (!orderStore.orderId) {
    uni.showToast({ title: '订单数据异常', icon: 'none' });
    return;
  }
  loading.value = true;
  try {
    preview.value = await getOrderPreview(orderStore.orderId);
  } catch {
    uni.showToast({ title: '加载预览失败', icon: 'none' });
  } finally {
    loading.value = false;
  }
}

/** 提交订单 → 跳转定制确认页 */
async function handleSubmit() {
  if (submitting.value) return;

  // 终端客户地址模式必须选择地址
  if (deliveryMethod.value === 'customer' && !selectedCustomerAddress.value) {
    uni.showToast({ title: '请选择终端客户地址', icon: 'none' });
    return;
  }

  const snapshot = getShippingSnapshot();
  if (!snapshot) {
    uni.showToast({ title: '收货地址信息不完整', icon: 'none' });
    return;
  }

  submitting.value = true;
  try {
    // 提交订单确认，附带收货地址快照
    const result = await submitOrder({
      order_id: orderStore.orderId,
      confirmed: 1,
    });

    // 重置 store
    orderStore.reset();

    // 跳转到定制确认页，确认后去支付
    uni.navigateTo({
      url: `/pages/order/custom-confirm?order_id=${result.order_id}&total=${result.total_amount}`,
    });
  } catch {
    // 错误由拦截器处理
  } finally {
    submitting.value = false;
  }
}

// 监听从地址选择页返回的地址数据
uni.$on('selectAddress', (data: AddressItem) => {
  if (data && data.id) {
    selectedCustomerAddress.value = data as AddressItem;
  }
});

onMounted(() => {
  loadPreview();
  loadStoreInfo();
  loadSavedAddresses();
});
</script>

<style lang="scss" scoped>
.preview-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  padding: $spacing-md;
  padding-bottom: 240rpx;
}

/* 加载状态 */
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

/* 收货方式卡片 */
.delivery-card {
  border: 2rpx solid $color-primary-100;
}

.delivery-options {
  display: flex;
  gap: $spacing-lg;
  margin-bottom: $spacing-lg;
}

.delivery-radio {
  display: flex;
  align-items: center;
  gap: $spacing-sm;
  padding: $spacing-sm $spacing-md;
  border-radius: $radius-sm;
  background-color: $color-neutral-50;
  border: 2rpx solid transparent;
  transition: all 0.2s ease;

  &--active {
    border-color: $color-primary-500;
    background-color: $color-primary-50;
  }

  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-700;
    font-weight: $font-weight-medium;
  }
}

.radio-dot {
  width: 32rpx;
  height: 32rpx;
  border-radius: $radius-full;
  border: 2rpx solid $color-neutral-300;
  position: relative;
  flex-shrink: 0;

  &--checked {
    border-color: $color-primary-500;

    &::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 16rpx;
      height: 16rpx;
      border-radius: $radius-full;
      background-color: $color-primary-500;
    }
  }
}

/* 地址展示 */
.address-display {
  padding: $spacing-md;
  border-radius: $radius-md;

  &--readonly {
    background-color: $color-neutral-100;
  }

  &--selectable {
    background-color: $color-primary-50;
    border: 2rpx solid $color-primary-200;
  }

  &__info {
    display: flex;
    align-items: center;
    gap: $spacing-lg;
    margin-bottom: $spacing-sm;
  }

  &__name {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__phone {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    font-family: $font-family-mono;
  }

  &__detail {
    font-size: $font-size-sm;
    color: $color-neutral-600;
    line-height: $line-height-relaxed;
    display: block;
    margin-bottom: $spacing-sm;
  }

  &__hint {
    text {
      font-size: $font-size-xs;
      color: $color-neutral-400;
    }
  }

  &__change {
    text {
      font-size: $font-size-xs;
      color: $color-primary-500;
      font-weight: $font-weight-medium;
    }
  }
}

/* 终端客户地址空状态 */
.customer-address-empty {
  padding: $spacing-md;
  background-color: $color-neutral-50;
  border-radius: $radius-md;
  text-align: center;

  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    display: block;
    margin-bottom: $spacing-md;
  }

  &__actions {
    display: flex;
    justify-content: center;
    gap: $spacing-lg;
  }
}

.action-link {
  padding: $spacing-xs $spacing-md;
  border-radius: $radius-sm;
  background-color: $color-neutral-200;
  text {
    font-size: $font-size-xs;
    color: $color-neutral-600;
  }

  &--primary {
    background-color: $color-primary-500;
    text {
      color: $color-neutral-0;
      font-weight: $font-weight-medium;
    }
  }
}

/* 地址快捷列表 */
.address-quick-list {
  margin-top: $spacing-md;

  &__title {
    margin-bottom: $spacing-sm;
    text {
      font-size: $font-size-xs;
      color: $color-neutral-500;
      font-weight: $font-weight-medium;
    }
  }
}

.address-quick-item {
  padding: $spacing-md;
  background-color: $color-neutral-50;
  border-radius: $radius-sm;
  margin-bottom: $spacing-sm;
  border: 2rpx solid transparent;
  transition: all 0.2s ease;

  &:active {
    border-color: $color-primary-500;
    background-color: $color-primary-50;
  }

  &:last-child {
    margin-bottom: 0;
  }

  &__info {
    display: flex;
    align-items: center;
    gap: $spacing-md;
    margin-bottom: $spacing-xs;
  }

  &__name {
    font-size: $font-size-sm;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__phone {
    font-size: $font-size-xs;
    color: $color-neutral-500;
    font-family: $font-family-mono;
  }

  &__detail {
    font-size: $font-size-xs;
    color: $color-neutral-500;
    line-height: $line-height-normal;
  }
}

/* 卡片 */
.card {
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
  margin-bottom: $spacing-md;
  box-shadow: $shadow-1;

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    margin-bottom: $spacing-md;
  }
}

/* 信息行 */
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

/* 预览窗帘项 */
.preview-item {
  background-color: $color-neutral-50;
  border-radius: $radius-md;
  padding: $spacing-md;
  margin-bottom: $spacing-sm;

  &:last-child { margin-bottom: 0; }

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $spacing-xs;
  }

  &__index {
    font-size: $font-size-sm;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__total {
    font-size: $font-size-base;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }

  &__info {
    display: flex;
    gap: $spacing-md;
    margin-bottom: $spacing-sm;
  }

  &__position {
    font-size: $font-size-xs;
    color: $color-neutral-600;
  }

  &__size {
    font-size: $font-size-xs;
    color: $color-neutral-400;
    font-family: $font-family-mono;
  }

  &__amounts {
    display: flex;
    flex-wrap: wrap;
    gap: $spacing-xs;
  }
}

.amount-tag {
  display: inline-flex;
  gap: 8rpx;
  padding: 4rpx 12rpx;
  background-color: $color-neutral-100;
  border-radius: $radius-sm;
  font-size: $font-size-xs;
  color: $color-neutral-500;

  &--success {
    background-color: $color-success-light;
    color: $color-success;
  }

  &--warning {
    background-color: $color-warning-light;
    color: $color-warning;
  }
}

/* 费用汇总 */
.summary-card {
  border: 2rpx solid $color-primary-100;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: $spacing-xs 0;

  &__label {
    font-size: $font-size-sm;
    color: $color-neutral-500;
  }

  &__value {
    font-size: $font-size-sm;
    color: $color-neutral-900;
    font-family: $font-family-mono;

    &--warning { color: $color-warning; font-weight: $font-weight-medium; }
    &--success { color: $color-success; font-weight: $font-weight-medium; }
  }
}

.summary-section {
  padding: $spacing-sm 0;
  border-top: 2rpx dashed $color-neutral-200;
  margin-top: $spacing-xs;
}

.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: $spacing-md;
  margin-top: $spacing-sm;
  border-top: 2rpx solid $color-neutral-200;

  &__label {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__value {
    font-size: $font-size-2xl;
    font-weight: $font-weight-bold;
    color: $color-primary-500;
    font-family: $font-family-mono;
  }
}

/* 底部提交栏 */
.bottom-placeholder {
  height: 200rpx;
}

.submit-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: $color-neutral-0;
  padding: $spacing-sm $spacing-lg $spacing-md;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.05);

  &__info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $spacing-sm;
  }

  &__count {
    font-size: $font-size-sm;
    color: $color-neutral-500;
  }

  &__total {
    font-size: $font-size-xl;
    font-weight: $font-weight-bold;
    color: $color-primary-500;
    font-family: $font-family-mono;
  }

  &__btn {
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
      background-color: $color-neutral-200;
      color: $color-neutral-400;
    }
  }
}

.text-warning {
  color: $color-warning;
}
</style>
