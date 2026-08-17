<template>
  <view class="page-header" :style="{ paddingTop: statusBarHeight + 'px' }">
    <view class="page-header__content">
      <view v-if="showBack" class="page-header__back" @tap="handleBack">
        <text class="page-header__back-icon">&lt;</text>
      </view>
      <view class="page-header__title">
        <text>{{ title }}</text>
      </view>
      <view class="page-header__right">
        <slot name="right" />
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue';

interface Props {
  title?: string;
  showBack?: boolean;
}

withDefaults(defineProps<Props>(), {
  title: '',
  showBack: true,
});

const statusBarHeight = ref(0);

try {
  const systemInfo = uni.getSystemInfoSync();
  statusBarHeight.value = systemInfo.statusBarHeight ?? 0;
} catch {
  statusBarHeight.value = 44;
}

function handleBack() {
  uni.navigateBack({ delta: 1 });
}
</script>

<style lang="scss" scoped>
.page-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background-color: $color-neutral-0;

  &__content {
    display: flex;
    align-items: center;
    height: 88rpx;
    padding: 0 $spacing-lg;
  }

  &__back {
    width: 64rpx;
    height: 64rpx;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  &__back-icon {
    font-size: $font-size-xl;
    color: $color-neutral-700;
  }

  &__title {
    flex: 1;
    text-align: center;
    font-size: $font-size-lg;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__right {
    min-width: 64rpx;
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }
}
</style>
