<template>
  <view class="empty-state">
    <view class="empty-state__icon">
      <slot name="icon">
        <text class="empty-state__default-icon">📭</text>
      </slot>
    </view>
    <text class="empty-state__title">{{ title }}</text>
    <text v-if="description" class="empty-state__desc">{{ description }}</text>
    <view v-if="actionText" class="empty-state__action" @tap="$emit('action')">
      <text class="empty-state__action-text">{{ actionText }}</text>
    </view>
  </view>
</template>

<script setup lang="ts">
interface Props {
  title?: string;
  description?: string;
  actionText?: string;
}

withDefaults(defineProps<Props>(), {
  title: '暂无数据',
  description: '',
  actionText: '',
});

defineEmits<{
  (e: 'action'): void;
}>();
</script>

<style lang="scss" scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 120rpx $spacing-xl;

  &__icon { margin-bottom: $spacing-lg; }
  &__default-icon { font-size: 120rpx; }
  &__title {
    font-size: $font-size-lg;
    font-weight: $font-weight-semibold;
    color: $color-neutral-700;
    margin-bottom: $spacing-sm;
  }
  &__desc {
    font-size: $font-size-sm;
    color: $color-neutral-400;
    text-align: center;
    margin-bottom: $spacing-lg;
  }
  &__action {
    padding: $spacing-sm $spacing-xl;
    background-color: $color-primary-500;
    border-radius: $radius-full;
  }
  &__action-text {
    color: $color-neutral-0;
    font-size: $font-size-base;
    font-weight: $font-weight-medium;
  }
}
</style>
