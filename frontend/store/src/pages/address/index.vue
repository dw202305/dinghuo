<template>
  <view class="address-page">
    <!-- 地址列表 -->
    <view v-if="loading" class="loading-state">
      <text class="loading-text">加载中...</text>
    </view>

    <view v-else-if="addressList.length === 0" class="empty-state">
      <text class="empty-icon">📍</text>
      <text class="empty-text">暂无收货地址</text>
      <text class="empty-hint">点击下方按钮新增地址</text>
    </view>

    <view v-else class="address-list">
      <uni-swipe-action>
        <uni-swipe-action-item
          v-for="item in addressList"
          :key="item.id"
          :right-options="[
            { text: '删除', style: { backgroundColor: '#DC2626' } }
          ]"
          @click="handleSwipeClick($event, item)"
        >
          <view
            class="address-card"
            :class="{ 'address-card--selected': mode === 'select' && selectedId === item.id }"
            @tap="handleCardTap(item)"
          >
            <!-- 默认标签 -->
            <view v-if="item.is_default" class="address-card__default-tag">
              <text>默认</text>
            </view>

            <!-- 收货人信息 -->
            <view class="address-card__header">
              <text class="address-card__name">{{ item.receiver_name }}</text>
              <text class="address-card__phone">{{ maskPhone(item.receiver_phone) }}</text>
            </view>

            <!-- 详细地址 -->
            <view class="address-card__body">
              <text class="address-card__detail">{{ item.full_address || formatFullAddress(item) }}</text>
            </view>

            <!-- 操作按钮 -->
            <view class="address-card__actions">
              <view class="action-btn" @tap.stop="handleEdit(item)">
                <text class="action-btn__text">编辑</text>
              </view>
              <view class="action-btn" @tap.stop="handleDelete(item)">
                <text class="action-btn__text">删除</text>
              </view>
              <view
                v-if="!item.is_default"
                class="action-btn action-btn--primary"
                @tap.stop="handleSetDefault(item)"
              >
                <text class="action-btn__text">设为默认</text>
              </view>
            </view>

            <!-- 选择模式下的选中标记 -->
            <view v-if="mode === 'select' && selectedId === item.id" class="address-card__check">
              <text class="check-icon">✓</text>
            </view>
          </view>
        </uni-swipe-action-item>
      </uni-swipe-action>
    </view>

    <!-- 底部占位 -->
    <view class="bottom-placeholder" />

    <!-- 底部新增按钮 -->
    <view class="bottom-bar safe-area-bottom">
      <button class="bottom-bar__btn" @tap="handleAdd">
        <text class="bottom-bar__icon">+</text>
        <text>新增地址</text>
      </button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { getAddressList, deleteAddress, setDefaultAddress } from '@/api/address';
import type { AddressItem } from '@/types/address';

/** 页面模式：manage=管理, select=选择 */
const mode = ref<'manage' | 'select'>('manage');

/** 地址列表 */
const addressList = ref<AddressItem[]>([]);

/** 加载状态 */
const loading = ref(true);

/** 当前选中地址ID（select模式） */
const selectedId = ref<number>(0);

/** 来源页面标识（select模式下返回时传递） */
const sourcePage = ref<string>('');

/** 加载地址列表 */
async function loadAddressList() {
  loading.value = true;
  try {
    addressList.value = await getAddressList();
  } catch {
    uni.showToast({ title: '加载地址失败', icon: 'none' });
  } finally {
    loading.value = false;
  }
}

/** 手机号脱敏展示（中间4位 *） */
function maskPhone(phone: string): string {
  if (phone.length >= 7) {
    return phone.substring(0, 3) + '****' + phone.substring(phone.length - 4);
  }
  return phone;
}

/** 拼接完整地址（前端兜底） */
function formatFullAddress(item: AddressItem): string {
  return `${item.province}${item.city}${item.district}${item.detail_address}`;
}

/** 点击地址卡片 */
function handleCardTap(item: AddressItem) {
  if (mode.value === 'select') {
    selectedId.value = item.id;
    // 返回下单页，传递选中地址信息
    const eventChannel = uni.getOpenerEventChannel?.();
    if (eventChannel) {
      eventChannel.emit('selectAddress', {
        id: item.id,
        receiver_name: item.receiver_name,
        receiver_phone: item.receiver_phone,
        province: item.province,
        city: item.city,
        district: item.district,
        detail_address: item.detail_address,
        full_address: item.full_address || formatFullAddress(item),
      });
    }
    // 使用全局事件兜底
    uni.$emit('selectAddress', {
      id: item.id,
      receiver_name: item.receiver_name,
      receiver_phone: item.receiver_phone,
      province: item.province,
      city: item.city,
      district: item.district,
      detail_address: item.detail_address,
      full_address: item.full_address || formatFullAddress(item),
    });
    setTimeout(() => {
      uni.navigateBack();
    }, 300);
  }
}

/** 编辑地址 */
function handleEdit(item: AddressItem) {
  uni.navigateTo({
    url: `/pages/address/edit?id=${item.id}`,
  });
}

/** 删除地址 */
function handleDelete(item: AddressItem) {
  uni.showModal({
    title: '确认删除',
    content: `确定要删除${item.receiver_name}的地址吗？`,
    confirmColor: '#DC2626',
    success: async (res) => {
      if (res.confirm) {
        try {
          await deleteAddress(item.id);
          uni.showToast({ title: '已删除', icon: 'success' });
          await loadAddressList();
        } catch {
          // 错误由拦截器处理
        }
      }
    },
  });
}

/** 左滑删除按钮点击 */
function handleSwipeClick(e: { index: number }, item: AddressItem) {
  if (e.index === 0) {
    handleDelete(item);
  }
}

/** 设为默认地址 */
async function handleSetDefault(item: AddressItem) {
  try {
    await setDefaultAddress(item.id);
    uni.showToast({ title: '已设为默认', icon: 'success' });
    await loadAddressList();
  } catch {
    // 错误由拦截器处理
  }
}

/** 新增地址 */
function handleAdd() {
  uni.navigateTo({
    url: '/pages/address/edit',
  });
}

// 接收页面参数
onLoad((options) => {
  if (options?.mode === 'select') {
    mode.value = 'select';
    sourcePage.value = options.source || '';
    uni.setNavigationBarTitle({ title: '选择地址' });
  }
});

// 页面显示时刷新列表（编辑返回时自动更新）
onMounted(() => {
  loadAddressList();
});
</script>

<style lang="scss" scoped>
.address-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  padding: $spacing-md;
  padding-bottom: 240rpx;
}

/* 加载 & 空状态 */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
}

.loading-text {
  font-size: $font-size-sm;
  color: $color-neutral-400;
}

.empty-icon {
  font-size: 96rpx;
  margin-bottom: $spacing-lg;
}

.empty-text {
  font-size: $font-size-base;
  color: $color-neutral-600;
  font-weight: $font-weight-medium;
}

.empty-hint {
  font-size: $font-size-sm;
  color: $color-neutral-400;
  margin-top: $spacing-xs;
}

/* 地址卡片 */
.address-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-md;
}

.address-card {
  position: relative;
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
  box-shadow: $shadow-1;
  transition: all 0.2s ease;

  &--selected {
    border: 2rpx solid $color-primary-500;
    background-color: $color-primary-50;
  }

  &__default-tag {
    display: inline-flex;
    padding: 4rpx 16rpx;
    background-color: $color-primary-500;
    border-radius: $radius-sm;
    margin-bottom: $spacing-sm;

    text {
      font-size: $font-size-xs;
      color: $color-neutral-0;
      font-weight: $font-weight-medium;
    }
  }

  &__header {
    display: flex;
    align-items: center;
    margin-bottom: $spacing-sm;
    gap: $spacing-lg;
  }

  &__name {
    font-size: $font-size-lg;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__phone {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    font-family: $font-family-mono;
  }

  &__body {
    margin-bottom: $spacing-md;
  }

  &__detail {
    font-size: $font-size-sm;
    color: $color-neutral-600;
    line-height: $line-height-relaxed;
  }

  &__actions {
    display: flex;
    gap: $spacing-md;
    padding-top: $spacing-sm;
    border-top: 2rpx solid $color-neutral-100;
  }

  &__check {
    position: absolute;
    top: $spacing-lg;
    right: $spacing-lg;
    width: 48rpx;
    height: 48rpx;
    border-radius: $radius-full;
    background-color: $color-primary-500;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}

.check-icon {
  color: $color-neutral-0;
  font-size: $font-size-base;
  font-weight: $font-weight-bold;
}

.action-btn {
  padding: $spacing-xs $spacing-md;
  border-radius: $radius-sm;
  background-color: $color-neutral-100;
  transition: background-color 0.15s ease;

  &:active {
    background-color: $color-neutral-200;
  }

  &--primary {
    background-color: $color-primary-50;

    .action-btn__text {
      color: $color-primary-500;
    }

    &:active {
      background-color: $color-primary-100;
    }
  }

  &__text {
    font-size: $font-size-xs;
    color: $color-neutral-600;
  }
}

/* 底部栏 */
.bottom-placeholder {
  height: 160rpx;
}

.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: $color-neutral-0;
  padding: $spacing-sm $spacing-lg $spacing-md;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.05);

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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: $spacing-xs;

    &:active {
      background-color: $color-primary-600;
    }
  }

  &__icon {
    font-size: $font-size-xl;
    font-weight: $font-weight-bold;
  }
}
</style>
