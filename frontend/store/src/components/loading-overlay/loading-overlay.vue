<template>
  <view v-if="visible" class="loading-overlay">
    <view class="loading-overlay__content">
      <view class="loading-overlay__spinner" />
      <text v-if="text" class="loading-overlay__text">{{ text }}</text>
    </view>
  </view>
</template>

<script setup lang="ts">
interface Props {
  visible?: boolean;
  text?: string;
}

withDefaults(defineProps<Props>(), {
  visible: false,
  text: '加载中...',
});
</script>

<style lang="scss" scoped>
.loading-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(0, 0, 0, 0.3);

  &__content {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: $spacing-xl;
    background-color: $color-neutral-0;
    border-radius: $radius-lg;
    box-shadow: $shadow-3;
  }

  &__spinner {
    width: 64rpx;
    height: 64rpx;
    border: 4rpx solid $color-neutral-200;
    border-top-color: $color-primary-500;
    border-radius: $radius-full;
    animation: spin 0.8s linear infinite;
  }

  &__text {
    margin-top: $spacing-md;
    font-size: $font-size-base;
    color: $color-neutral-500;
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
