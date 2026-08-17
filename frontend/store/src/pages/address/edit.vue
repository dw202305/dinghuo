<template>
  <view class="edit-page">
    <!-- 表单 -->
    <view class="form-card">
      <!-- 收货人姓名 -->
      <view class="form-item">
        <view class="form-item__label">
          <text class="form-item__required">*</text>
          <text>收货人姓名</text>
        </view>
        <input
          v-model="form.receiver_name"
          class="form-item__input"
          type="text"
          placeholder="请输入收货人姓名"
          :maxlength="20"
          @blur="validateField('receiver_name')"
        />
        <text v-if="errors.receiver_name" class="form-item__error">{{ errors.receiver_name }}</text>
      </view>

      <!-- 手机号 -->
      <view class="form-item">
        <view class="form-item__label">
          <text class="form-item__required">*</text>
          <text>手机号</text>
        </view>
        <input
          v-model="form.receiver_phone"
          class="form-item__input"
          type="number"
          placeholder="请输入11位手机号"
          :maxlength="11"
          @blur="validateField('receiver_phone')"
        />
        <text v-if="errors.receiver_phone" class="form-item__error">{{ errors.receiver_phone }}</text>
      </view>

      <!-- 省市区选择 -->
      <view class="form-item">
        <view class="form-item__label">
          <text class="form-item__required">*</text>
          <text>所在地区</text>
        </view>
        <picker
          mode="region"
          :value="regionValue"
          @change="handleRegionChange"
        >
          <view class="form-item__picker" :class="{ 'form-item__placeholder': !form.province }">
            <text>{{ regionDisplayText || '请选择省/市/区' }}</text>
            <text class="picker-arrow">›</text>
          </view>
        </picker>
        <text v-if="errors.region" class="form-item__error">{{ errors.region }}</text>
      </view>

      <!-- 详细地址 -->
      <view class="form-item">
        <view class="form-item__label">
          <text class="form-item__required">*</text>
          <text>详细地址</text>
        </view>
        <textarea
          v-model="form.detail_address"
          class="form-item__textarea"
          placeholder="请输入详细地址（5-100字符）"
          :maxlength="100"
          :auto-height="true"
          @blur="validateField('detail_address')"
        />
        <view class="form-item__counter">
          <text :class="{ 'counter-warn': form.detail_address.length > 100 }">
            {{ form.detail_address.length }}/100
          </text>
        </view>
        <text v-if="errors.detail_address" class="form-item__error">{{ errors.detail_address }}</text>
      </view>

      <!-- 默认地址开关 -->
      <view class="form-item form-item--switch">
        <view class="form-item__label">
          <text>设为默认地址</text>
        </view>
        <switch
          :checked="form.is_default"
          color="#56638F"
          @change="form.is_default = $event.detail.value"
        />
      </view>
    </view>

    <!-- 底部保存按钮 -->
    <view class="bottom-bar safe-area-bottom">
      <button class="bottom-bar__btn" :disabled="saving" @tap="handleSave">
        {{ saving ? '保存中...' : '保存地址' }}
      </button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { getAddressDetail, createAddress, updateAddress } from '@/api/address';
import { isValidPhone } from '@/utils/validator';
import type { AddressFormData } from '@/types/address';

/** 是否编辑模式 */
const isEdit = ref(false);

/** 编辑的地址ID */
const editId = ref<number>(0);

/** 保存中 */
const saving = ref(false);

/** 表单数据 */
const form = reactive<AddressFormData>({
  receiver_name: '',
  receiver_phone: '',
  province: '',
  city: '',
  district: '',
  detail_address: '',
  is_default: false,
});

/** 表单校验错误 */
const errors = reactive<Record<string, string>>({
  receiver_name: '',
  receiver_phone: '',
  region: '',
  detail_address: '',
});

/** 省市区 picker 值 */
const regionValue = ref<string[]>([]);

/** 省市区展示文本 */
const regionDisplayText = computed(() => {
  if (form.province) {
    return `${form.province} ${form.city} ${form.district}`;
  }
  return '';
});

/** 校验单个字段 */
function validateField(field: string): boolean {
  switch (field) {
    case 'receiver_name': {
      const name = form.receiver_name.trim();
      if (!name) {
        errors.receiver_name = '请输入收货人姓名';
        return false;
      }
      if (name.length < 2 || name.length > 20) {
        errors.receiver_name = '姓名需2-20个字符';
        return false;
      }
      errors.receiver_name = '';
      return true;
    }
    case 'receiver_phone': {
      if (!form.receiver_phone) {
        errors.receiver_phone = '请输入手机号';
        return false;
      }
      if (!isValidPhone(form.receiver_phone)) {
        errors.receiver_phone = '手机号格式不正确';
        return false;
      }
      errors.receiver_phone = '';
      return true;
    }
    case 'region': {
      if (!form.province) {
        errors.region = '请选择省/市/区';
        return false;
      }
      errors.region = '';
      return true;
    }
    case 'detail_address': {
      const detail = form.detail_address.trim();
      if (!detail) {
        errors.detail_address = '请输入详细地址';
        return false;
      }
      if (detail.length < 5) {
        errors.detail_address = '详细地址至少5个字符';
        return false;
      }
      if (detail.length > 100) {
        errors.detail_address = '详细地址不能超过100个字符';
        return false;
      }
      errors.detail_address = '';
      return true;
    }
    default:
      return true;
  }
}

/** 全表单校验 */
function validateAll(): boolean {
  const nameOk = validateField('receiver_name');
  const phoneOk = validateField('receiver_phone');
  const regionOk = validateField('region');
  const detailOk = validateField('detail_address');
  return nameOk && phoneOk && regionOk && detailOk;
}

/** 省市区选择器变更 */
function handleRegionChange(e: { detail: { value: string[] } }) {
  const [province, city, district] = e.detail.value;
  form.province = province;
  form.city = city;
  form.district = district;
  regionValue.value = e.detail.value;
  errors.region = '';
}

/** 加载已有地址数据 */
async function loadAddress(id: number) {
  try {
    const data = await getAddressDetail(id);
    form.receiver_name = data.receiver_name;
    form.receiver_phone = data.receiver_phone;
    form.province = data.province;
    form.city = data.city;
    form.district = data.district;
    form.detail_address = data.detail_address;
    form.is_default = data.is_default;
    regionValue.value = [data.province, data.city, data.district];
  } catch {
    uni.showToast({ title: '加载地址失败', icon: 'none' });
  }
}

/** 保存地址 */
async function handleSave() {
  if (!validateAll()) return;
  if (saving.value) return;

  saving.value = true;
  try {
    const params: AddressFormData = {
      receiver_name: form.receiver_name.trim(),
      receiver_phone: form.receiver_phone.trim(),
      province: form.province,
      city: form.city,
      district: form.district,
      detail_address: form.detail_address.trim(),
      is_default: form.is_default,
    };

    if (isEdit.value) {
      await updateAddress(editId.value, params);
      uni.showToast({ title: '已更新', icon: 'success' });
    } else {
      await createAddress(params);
      uni.showToast({ title: '已添加', icon: 'success' });
    }

    // 返回列表页（列表页会自动刷新）
    setTimeout(() => {
      uni.navigateBack();
    }, 1000);
  } catch {
    // 错误由拦截器处理
  } finally {
    saving.value = false;
  }
}

// 接收页面参数
onLoad((options) => {
  if (options?.id) {
    isEdit.value = true;
    editId.value = Number(options.id);
    uni.setNavigationBarTitle({ title: '编辑地址' });
  } else {
    uni.setNavigationBarTitle({ title: '新增地址' });
  }
});

// 编辑模式下加载数据
onMounted(() => {
  if (isEdit.value && editId.value) {
    loadAddress(editId.value);
  }
});
</script>

<style lang="scss" scoped>
.edit-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  padding: $spacing-md;
  padding-bottom: 200rpx;
}

/* 表单卡片 */
.form-card {
  background-color: $color-neutral-0;
  border-radius: $radius-md;
  padding: $spacing-lg;
  box-shadow: $shadow-1;
}

.form-item {
  padding: $spacing-md 0;
  border-bottom: 2rpx solid $color-neutral-100;

  &:last-child {
    border-bottom: none;
  }

  &--switch {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  &__label {
    display: flex;
    align-items: center;
    font-size: $font-size-sm;
    color: $color-neutral-700;
    font-weight: $font-weight-medium;
    margin-bottom: $spacing-sm;
    gap: 4rpx;

    .form-item--switch & {
      margin-bottom: 0;
    }
  }

  &__required {
    color: $color-error;
    font-size: $font-size-sm;
  }

  &__input {
    width: 100%;
    height: 80rpx;
    font-size: $font-size-sm;
    color: $color-neutral-900;
    padding: 0 $spacing-sm;
    background-color: $color-neutral-50;
    border-radius: $radius-sm;
    border: 2rpx solid $color-neutral-200;
    transition: border-color 0.2s ease;

    &:focus {
      border-color: $color-primary-500;
    }
  }

  &__textarea {
    width: 100%;
    min-height: 120rpx;
    font-size: $font-size-sm;
    color: $color-neutral-900;
    padding: $spacing-sm;
    background-color: $color-neutral-50;
    border-radius: $radius-sm;
    border: 2rpx solid $color-neutral-200;
    box-sizing: border-box;
    transition: border-color 0.2s ease;

    &:focus {
      border-color: $color-primary-500;
    }
  }

  &__picker {
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 80rpx;
    padding: 0 $spacing-sm;
    background-color: $color-neutral-50;
    border-radius: $radius-sm;
    border: 2rpx solid $color-neutral-200;
    font-size: $font-size-sm;
    color: $color-neutral-900;
  }

  &__placeholder {
    color: $color-neutral-400;
  }

  &__counter {
    text-align: right;
    margin-top: $spacing-xs;

    text {
      font-size: $font-size-xs;
      color: $color-neutral-400;
    }

    .counter-warn {
      color: $color-error;
    }
  }

  &__error {
    display: block;
    font-size: $font-size-xs;
    color: $color-error;
    margin-top: $spacing-xs;
  }
}

.picker-arrow {
  font-size: $font-size-xl;
  color: $color-neutral-300;
}

/* 底部栏 */
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

    &[disabled] {
      background-color: $color-neutral-200;
      color: $color-neutral-400;
    }

    &:active:not([disabled]) {
      background-color: $color-primary-600;
    }
  }
}
</style>
