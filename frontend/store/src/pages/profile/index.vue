<template>
  <view class="profile-page">
    <!-- 个人信息卡片 -->
    <view class="profile-header">
      <view class="profile-header__avatar">
        <text class="avatar-text">{{ avatarChar }}</text>
      </view>
      <view class="profile-header__info">
        <view class="profile-header__name-row">
          <text class="profile-header__name">{{ profile?.real_name || authStore.realName }}</text>
          <view class="level-tag">
            <text>{{ levelText }}</text>
          </view>
        </view>
        <text class="profile-header__phone">{{ maskedPhone }}</text>
        <text class="profile-header__store">{{ storeInfo?.store_name || '' }}</text>
      </view>
    </view>

    <!-- 门店信息 -->
    <view class="section-card">
      <view class="section-card__title">
        <text>门店信息</text>
      </view>
      <view class="info-row">
        <text class="info-row__label">门店地址</text>
        <text class="info-row__value">{{ fullAddress || '暂无地址' }}</text>
      </view>
      <view class="info-row">
        <text class="info-row__label">联系电话</text>
        <text class="info-row__value">{{ storeInfo?.contact_phone || '-' }}</text>
      </view>
      <view class="info-row">
        <text class="info-row__label">归属合伙人</text>
        <text class="info-row__value">{{ storeInfo?.partner_name || '直营' }}</text>
      </view>
    </view>

    <!-- 功能菜单 -->
    <view class="menu-card">
      <view class="menu-item" @tap="goPage('/pages/balance/index')">
        <text class="menu-item__icon">💰</text>
        <text class="menu-item__text">我的余额</text>
        <text class="menu-item__arrow">›</text>
      </view>
      <view class="menu-item" @tap="goPage('/pages/balance/recharge/index')">
        <text class="menu-item__icon">💳</text>
        <text class="menu-item__text">储值充值</text>
        <text class="menu-item__arrow">›</text>
      </view>
      <view class="menu-item" @tap="goPage('/pages/profile/store-info')">
        <text class="menu-item__icon">🏪</text>
        <text class="menu-item__text">门店资料管理</text>
        <text class="menu-item__arrow">›</text>
      </view>
      <view class="menu-item" @tap="goPage('/pages/address/index')">
        <text class="menu-item__icon">📍</text>
        <text class="menu-item__text">收货地址</text>
        <text class="menu-item__arrow">›</text>
      </view>
      <view class="menu-item" @tap="goPage('/pages/invoice/apply')">
        <text class="menu-item__icon">📄</text>
        <text class="menu-item__text">发票申请</text>
        <text class="menu-item__arrow">›</text>
      </view>
      <view class="menu-item" @tap="goPage('/pages/after-sale/apply')">
        <text class="menu-item__icon">🔧</text>
        <text class="menu-item__text">售后记录</text>
        <text class="menu-item__arrow">›</text>
      </view>
      <view class="menu-item" @tap="goPage('/pages/profile/about')">
        <text class="menu-item__icon">ℹ️</text>
        <text class="menu-item__text">关于我们</text>
        <text class="menu-item__arrow">›</text>
      </view>
    </view>

    <!-- 退出登录 -->
    <view class="logout-section">
      <button class="logout-btn" @tap="handleLogout">退出登录</button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import { useAuthStore } from '@/stores/auth';
import { getAccountProfile } from '@/api/auth';
import type { AccountProfile } from '@/types/user';
import { CustomerLevel } from '@/types/common';

const authStore = useAuthStore();

/** 账号详情 */
const profile = ref<AccountProfile | null>(null);

/** 门店信息 */
const storeInfo = computed(() => profile.value?.current_store ?? null);

/** 头像首字 */
const avatarChar = computed(() => {
  return profile.value?.real_name?.charAt(0) || authStore.realName?.charAt(0) || '?';
});

/** 手机号脱敏 */
const maskedPhone = computed(() => {
  const phone = profile.value?.phone || '';
  if (phone.length >= 7) {
    return phone.substring(0, 3) + '****' + phone.substring(phone.length - 4);
  }
  return phone;
});

/** 客户等级文本 */
const levelText = computed(() => {
  const level = storeInfo.value?.customer_level;
  if (!level) return '';
  const map: Record<number, string> = {
    [CustomerLevel.CERTIFIED_STORE]: '认证门店',
    [CustomerLevel.CITY_PARTNER]: '城市合伙人',
    [CustomerLevel.EXPERIENCE_CUSTOMER]: '体验客户',
    [CustomerLevel.SPECIAL_CONTRACT]: '特约客户',
    [CustomerLevel.LARGE_B]: '大型B端',
  };
  return map[level] || '';
});

/** 完整地址 */
const fullAddress = computed(() => {
  const s = storeInfo.value;
  if (!s) return '';
  return `${s.province}${s.city}${s.district}${s.address}`;
});

/** 加载账号信息 */
async function loadProfile() {
  try {
    const data = await getAccountProfile();
    profile.value = data;
  } catch {
    // 静默处理
  }
}

/** 跳转页面 */
function goPage(url: string) {
  uni.navigateTo({ url });
}

/** 退出登录 */
function handleLogout() {
  uni.showModal({
    title: '提示',
    content: '确定要退出登录吗？',
    success: async (res) => {
      if (res.confirm) {
        await authStore.logout();
        uni.reLaunch({ url: '/pages/login/index' });
      }
    },
  });
}

// 页面显示时刷新
onShow(() => { loadProfile(); });
</script>

<style lang="scss" scoped>
.profile-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
}

/* 个人信息头部 */
.profile-header {
  display: flex;
  align-items: center;
  padding: $spacing-xl $spacing-lg;
  background-color: $color-primary-500;

  &__avatar {
    width: 120rpx;
    height: 120rpx;
    border-radius: $radius-full;
    background-color: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: $spacing-lg;
    flex-shrink: 0;
  }

  &__info {
    flex: 1;
    min-width: 0;
  }

  &__name-row {
    display: flex;
    align-items: center;
    margin-bottom: $spacing-xs;
  }

  &__name {
    font-size: $font-size-xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-0;
    margin-right: $spacing-sm;
  }

  &__phone {
    font-size: $font-size-sm;
    color: rgba(255, 255, 255, 0.7);
    display: block;
    margin-bottom: 4rpx;
  }

  &__store {
    font-size: $font-size-xs;
    color: rgba(255, 255, 255, 0.5);
    display: block;
  }
}

.avatar-text {
  font-size: $font-size-2xl;
  color: $color-neutral-0;
  font-weight: $font-weight-bold;
}

.level-tag {
  padding: 4rpx 12rpx;
  background-color: rgba(255, 255, 255, 0.2);
  border-radius: $radius-sm;

  text {
    font-size: $font-size-xs;
    color: rgba(255, 255, 255, 0.9);
    font-weight: $font-weight-medium;
  }
}

/* 信息卡片 */
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
  }
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: $spacing-sm 0;
  border-bottom: 2rpx solid $color-neutral-50;

  &:last-child {
    border-bottom: none;
  }

  &__label {
    font-size: $font-size-sm;
    color: $color-neutral-400;
    flex-shrink: 0;
    margin-right: $spacing-lg;
  }

  &__value {
    font-size: $font-size-sm;
    color: $color-neutral-700;
    text-align: right;
    flex: 1;
  }
}

/* 功能菜单 */
.menu-card {
  background-color: $color-neutral-0;
  border-radius: $radius-lg;
  margin: 0 $spacing-md $spacing-md;
  overflow: hidden;
  box-shadow: $shadow-1;
}

.menu-item {
  display: flex;
  align-items: center;
  padding: $spacing-lg;
  border-bottom: 2rpx solid $color-neutral-100;
  transition: background-color 0.15s ease;

  &:last-child {
    border-bottom: none;
  }

  &:active {
    background-color: $color-neutral-50;
  }

  &__icon {
    font-size: 40rpx;
    margin-right: $spacing-md;
    flex-shrink: 0;
  }

  &__text {
    flex: 1;
    font-size: $font-size-base;
    color: $color-neutral-900;
  }

  &__arrow {
    font-size: $font-size-xl;
    color: $color-neutral-300;
    flex-shrink: 0;
  }
}

/* 退出登录 */
.logout-section {
  padding: $spacing-xl $spacing-md;
}

.logout-btn {
  width: 100%;
  height: 88rpx;
  line-height: 88rpx;
  background-color: $color-neutral-0;
  color: $color-error;
  font-size: $font-size-base;
  border-radius: $radius-md;
  border: none;
  box-shadow: $shadow-1;
  font-weight: $font-weight-medium;

  &:active {
    background-color: $color-error-light;
  }
}
</style>
