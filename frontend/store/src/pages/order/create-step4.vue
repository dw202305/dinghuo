<template>
  <view class="step4-page">
    <!-- 当前窗帘摘要 -->
    <view class="card curtain-summary">
      <view class="curtain-summary__header">
        <text class="curtain-summary__title">第{{ sequence }}副窗帘</text>
        <text class="curtain-summary__position">{{ installPosition }}</text>
      </view>
      <view class="curtain-summary__info">
        <text class="curtain-summary__size">{{ width }} × {{ height }}cm · {{ area }}㎡</text>
      </view>
    </view>

    <!-- 电源类型 -->
    <view class="card">
      <view class="section-title">⚡ 电源类型</view>
      <view class="option-group">
        <view
          class="option-item"
          :class="{ 'option-item--active': powerType === 1 }"
          @tap="powerType = 1"
        >
          <text class="option-item__name">标准电源</text>
          <text class="option-item__price">标配</text>
        </view>
        <view
          class="option-item"
          :class="{ 'option-item--active': powerType === 2 }"
          @tap="powerType = 2"
        >
          <text class="option-item__name">锂电池</text>
          <text class="option-item__price">{{ surchargeText(powerSurchargeUnit) }}</text>
        </view>
      </view>
    </view>

    <!-- 遥控器类型 -->
    <view class="card">
      <view class="section-title">📡 遥控器类型</view>
      <view class="option-group">
        <view
          class="option-item"
          :class="{ 'option-item--active': remoteType === 1 }"
          @tap="remoteType = 1"
        >
          <text class="option-item__name">标准遥控器</text>
          <text class="option-item__price">标配</text>
        </view>
        <view
          class="option-item"
          :class="{ 'option-item--active': remoteType === 2 }"
          @tap="remoteType = 2"
        >
          <text class="option-item__name">专业遥控器</text>
          <text class="option-item__price">{{ surchargeText(remoteSurchargeUnit) }}</text>
        </view>
      </view>
    </view>

    <!-- 墙控开关 -->
    <view class="card">
      <view class="section-title">🔘 墙面控制器</view>
      <view class="option-group option-group--three">
        <view
          class="option-item"
          :class="{ 'option-item--active': wallControlType === 0 }"
          @tap="wallControlType = 0"
        >
          <text class="option-item__name">不需要</text>
          <text class="option-item__price">—</text>
        </view>
        <view
          class="option-item"
          :class="{ 'option-item--active': wallControlType === 1 }"
          @tap="wallControlType = 1"
        >
          <text class="option-item__name">标准款</text>
          <text class="option-item__price">¥{{ standardPriceDisplay }}/个</text>
        </view>
        <view
          class="option-item"
          :class="{ 'option-item--active': wallControlType === 2 }"
          @tap="wallControlType = 2"
        >
          <text class="option-item__name">专业款</text>
          <text class="option-item__price">¥{{ proPriceDisplay }}/个</text>
        </view>
      </view>
      <view class="price-hint">
        <text class="price-hint__text">* 价格以实际结算为准</text>
      </view>

      <!-- 数量选择 -->
      <view v-if="wallControlType > 0" class="quantity-row">
        <text class="quantity-row__label">数量</text>
        <view class="stepper">
          <view
            class="stepper__btn"
            :class="{ 'stepper__btn--disabled': wallControlQty <= 1 }"
            @tap="decrementQty"
          >−</view>
          <text class="stepper__value">{{ wallControlQty }}</text>
          <view class="stepper__btn" @tap="incrementQty">+</view>
        </view>
      </view>
    </view>

    <!-- 库存套件抵扣 -->
    <view class="card">
      <view class="section-title">📦 套件库存抵扣</view>
      <view class="inventory-row">
        <text class="inventory-row__label">使用库存套件</text>
        <switch :checked="useInventory" @change="onInventorySwitch" color="#56638F" />
      </view>
      <view v-if="useInventory" class="inventory-detail">
        <text class="inventory-detail__stock">可用库存：{{ availableCount }}套</text>
        <view v-if="availableCount <= 0" class="inventory-detail__warning">
          <text>⚠️ 库存不足，无法使用库存抵扣</text>
        </view>
        <view v-else class="quantity-row">
          <text class="quantity-row__label">抵扣套数</text>
          <view class="stepper">
            <view
              class="stepper__btn"
              :class="{ 'stepper__btn--disabled': inventoryQty <= 1 }"
              @tap="decrementInventory"
            >−</view>
            <text class="stepper__value">{{ inventoryQty }}</text>
            <view
              class="stepper__btn"
              :class="{ 'stepper__btn--disabled': inventoryQty >= availableCount }"
              @tap="incrementInventory"
            >+</view>
          </view>
        </view>
        <view v-if="useInventory && availableCount > 0" class="inventory-detail__save">
          <text>每套抵扣 ¥{{ kitPrice }}/副</text>
        </view>
      </view>
    </view>

    <!-- 价格明细预览 -->
    <view class="card price-preview">
      <view class="section-title">💰 价格明细预览</view>
      <view v-if="priceError" class="price-error">
        <text>⚠️ 价格信息加载失败，请返回上一页重试</text>
      </view>
      <view class="price-row">
        <text class="price-row__label">电源加价</text>
        <text class="price-row__value">¥{{ formatMoney(powerSurcharge) }}</text>
      </view>
      <view class="price-row">
        <text class="price-row__label">遥控器加价</text>
        <text class="price-row__value">¥{{ formatMoney(remoteSurcharge) }}</text>
      </view>
      <view v-if="wallControlType > 0" class="price-row">
        <text class="price-row__label">墙控开关（×{{ wallControlQty }}）</text>
        <text class="price-row__value">¥{{ wallControlAmountYuan }}</text>
      </view>
      <view class="price-row">
        <text class="price-row__label">配件合计</text>
        <text class="price-row__value">¥{{ totalAccessoryAmountYuan }}</text>
      </view>
      <view v-if="useInventory && availableCount > 0" class="price-row price-row--success">
        <text class="price-row__label">库存抵扣</text>
        <text class="price-row__value">-¥{{ formatMoney(inventoryDeduction) }}</text>
      </view>
    </view>

    <!-- 底部操作栏 -->
    <view class="bottom-bar safe-area-bottom">
      <button class="bottom-bar__prev" @tap="goPrev">上一步</button>
      <button
        class="bottom-bar__next"
        :class="{ 'bottom-bar__next--disabled': nextDisabled }"
        :disabled="nextDisabled"
        @tap="goNext"
      >下一步</button>
    </view>
    <!-- 底部占位 -->
    <view class="bottom-placeholder" />
  </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { updateOrderItem } from '@/api/order';
import {
  getWallControllerProducts,
  getAccessories,
  getKitInfo,
  getInventoryKit,
} from '@/api/product';
import type { AccessoryItem, KitInfo } from '@/api/product';
import { useOrderStore } from '@/stores/order';
import { formatMoney, formatSize, formatArea } from '@/utils/format';
import { calcArea as calcAreaDecimal, fenToYuan } from '@/utils/money';
import { PowerType, RemoteType, WallControlType } from '@/types/common';
import type { WallControllerProduct } from '@/types/product';

const orderStore = useOrderStore();

/** 页面参数 */
const itemId = ref(0);
const sequence = ref(1);
const installPosition = ref('');
const width = ref('0');
const height = ref('0');
const area = computed(() => {
  return calcAreaDecimal(width.value, height.value);
});

/** 配件选择 */
const powerType = ref<number>(PowerType.STANDARD);
const remoteType = ref<number>(RemoteType.STANDARD);
const wallControlType = ref<number>(WallControlType.NONE);
const wallControlQty = ref(1);
const useInventory = ref(false);
const inventoryQty = ref(1);
const availableCount = ref(0);
const kitPrice = ref(0);

/** 套件信息（从后端获取） */
const kitInfo = ref<KitInfo | null>(null);

/** 选装配件列表（从后端获取） */
const accessories = ref<AccessoryItem[]>([]);

/** 锂电池加价（元，来自后端配件价格） */
const powerSurchargeUnit = ref(0);

/** 专业遥控器加价（元，来自后端配件价格） */
const remoteSurchargeUnit = ref(0);

/** 价格加载状态 */
const priceLoading = ref(true);
const priceError = ref(false);

/** 墙面控制器商品列表（从后台获取） */
const wallControlProducts = ref<WallControllerProduct[]>([]);

/** 标准款单价（分），API 未返回有效价格时为 null */
const standardPriceCent = computed<number | null>(() => {
  const product = wallControlProducts.value.find(p => p.type === 'standard' && p.is_active);
  return product?.unit_price_cent ?? null;
});

/** 专业款单价（分），API 未返回有效价格时为 null */
const proPriceCent = computed<number | null>(() => {
  const product = wallControlProducts.value.find(p => p.type === 'pro' && p.is_active);
  return product?.unit_price_cent ?? null;
});

/** 当前选中墙控单价（分） */
const wallControlUnitPriceCent = computed<number>(() => {
  if (wallControlType.value === WallControlType.STANDARD) return standardPriceCent.value ?? 0;
  if (wallControlType.value === WallControlType.PRO) return proPriceCent.value ?? 0;
  return 0;
});

/** 标准款显示价格（元字符串），无有效价格时显示 "--" */
const standardPriceDisplay = computed<string>(() =>
  standardPriceCent.value === null ? '--' : fenToYuan(standardPriceCent.value)
);

/** 专业款显示价格（元字符串），无有效价格时显示 "--" */
const proPriceDisplay = computed<string>(() =>
  proPriceCent.value === null ? '--' : fenToYuan(proPriceCent.value)
);

/** 墙控总金额（分），用分值计算保证精度 */
const wallControlAmountCent = computed<number>(() => {
  return wallControlUnitPriceCent.value * wallControlQty.value;
});

/** 墙控总金额（元字符串），用于展示 */
const wallControlAmountYuan = computed<string>(() => fenToYuan(wallControlAmountCent.value));

/** 计算配件金额（加价单价来自后端配件价格） */
const powerSurcharge = computed(() => {
  return powerType.value === PowerType.LITHIUM_BATTERY ? powerSurchargeUnit.value : 0;
});

const remoteSurcharge = computed(() => {
  return remoteType.value === RemoteType.PRO ? remoteSurchargeUnit.value : 0;
});

/** 配件合计金额（元字符串），用于展示 */
const totalAccessoryAmountYuan = computed<string>(() => {
  const powerFen = Math.round(powerSurcharge.value * 100);
  const remoteFen = Math.round(remoteSurcharge.value * 100);
  const totalFen = powerFen + remoteFen + wallControlAmountCent.value;
  return fenToYuan(totalFen);
});

const inventoryDeduction = computed(() => {
  if (!useInventory.value || availableCount.value <= 0) return 0;
  return kitPrice.value * inventoryQty.value;
});

/** 墙控价格缺失（API 未返回有效价格） */
const wallControlPriceMissing = computed(() =>
  standardPriceCent.value === null || proPriceCent.value === null
);

/** 下一步按钮禁用：价格加载中 / 加载失败 / 墙控价格缺失 */
const nextDisabled = computed(() =>
  priceLoading.value || priceError.value || wallControlPriceMissing.value
);

/** 加价文案：加载中 / 失败 / 正常 */
function surchargeText(unitYuan: number): string {
  if (priceError.value) return '--';
  if (priceLoading.value) return '加载中';
  return `+¥${unitYuan}`;
}

/** 减少墙控数量 */
function decrementQty() {
  if (wallControlQty.value > 1) wallControlQty.value--;
}

/** 增加墙控数量 */
function incrementQty() {
  if (wallControlQty.value < 10) wallControlQty.value++;
}

/** 库存开关 */
function onInventorySwitch(e: { detail: { value: boolean } }) {
  useInventory.value = e.detail.value;
}

/** 减少库存抵扣 */
function decrementInventory() {
  if (inventoryQty.value > 1) inventoryQty.value--;
}

/** 增加库存抵扣 */
function incrementInventory() {
  if (inventoryQty.value < availableCount.value) inventoryQty.value++;
}

/** 保存配件配置到服务端 */
async function saveAccessories(): Promise<boolean> {
  if (!itemId.value) return false;

  try {
    uni.showLoading({ title: '保存中...', mask: true });
    await updateOrderItem(itemId.value, {
      power_type: powerType.value as PowerType,
      remote_type: remoteType.value as RemoteType,
      wall_control_type: wallControlType.value as WallControlType,
      wall_control_quantity: wallControlType.value > 0 ? wallControlQty.value : 0,
      use_inventory: useInventory.value ? 1 : 0,
    });
    return true;
  } catch {
    return false;
  } finally {
    uni.hideLoading();
  }
}

/** 上一步 */
function goPrev() {
  uni.navigateBack();
}

/** 下一步 */
async function goNext() {
  if (nextDisabled.value) return;
  const success = await saveAccessories();
  if (success) {
    uni.navigateTo({ url: '/pages/order/create-step5' });
  }
}

/**
 * 加载墙面控制器商品列表
 * API 失败时墙控价格显示 "--" 并禁用提交按钮
 */
async function loadWallControlProducts(): Promise<void> {
  try {
    wallControlProducts.value = await getWallControllerProducts();
  } catch {
    wallControlProducts.value = [];
  }
}

/** 加载配件价格、套件信息与可用库存 */
async function loadPricing(): Promise<void> {
  try {
    // 并行获取配件、套件信息和库存
    const [accRes, kitRes, invRes] = await Promise.all([
      getAccessories(),
      getKitInfo(),
      getInventoryKit(),
    ]);

    accessories.value = accRes?.list || [];

    // 从配件列表中提取锂电池与专业遥控器加价（非标准款）
    const powerItem = accessories.value.find(a =>
      ['power', '电源'].includes(a.config_group) && Number(a.option_type) !== 1
    );
    const remoteItem = accessories.value.find(a =>
      ['remote', '遥控器'].includes(a.config_group) && Number(a.option_type) !== 1
    );
    powerSurchargeUnit.value = powerItem ? Number(powerItem.surcharge_cent) / 100 : 0;
    remoteSurchargeUnit.value = remoteItem ? Number(remoteItem.surcharge_cent) / 100 : 0;

    // 套件价格（兼容 price_cent 与 kit_price 两种返回格式）
    kitInfo.value = kitRes;
    if (kitRes?.price_cent) {
      kitPrice.value = Number(kitRes.price_cent) / 100;
    } else {
      kitPrice.value = Number(kitRes?.kit_price || 0);
    }

    // 可用库存（多套件时合计）
    const invList = invRes?.list || [];
    availableCount.value = invList.reduce((sum, item) => sum + Number(item.available || 0), 0);
  } catch {
    priceError.value = true;
  }
}

onMounted(async () => {
  try {
    await Promise.all([loadWallControlProducts(), loadPricing()]);
  } finally {
    priceLoading.value = false;
  }
});

onLoad((options) => {
  if (options?.item_id) itemId.value = Number(options.item_id);
  if (options?.sequence) sequence.value = Number(options.sequence);
  if (options?.position) installPosition.value = options.position;
  if (options?.width) width.value = options.width;
  if (options?.height) height.value = options.height;
});
</script>

<style lang="scss" scoped>
.step4-page {
  min-height: 100vh;
  background-color: $color-neutral-50;
  padding: $spacing-md;
}

/* 窗帘摘要 */
.curtain-summary {
  border-left: 6rpx solid $color-primary-500;

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: $spacing-xs;
  }

  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }

  &__position {
    font-size: $font-size-sm;
    color: $color-primary-500;
    font-weight: $font-weight-medium;
  }

  &__info {
    margin-top: 4rpx;
  }

  &__size {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    font-family: $font-family-mono;
  }
}

/* 区块标题 */
.section-title {
  font-size: $font-size-base;
  font-weight: $font-weight-semibold;
  color: $color-neutral-900;
  margin-bottom: $spacing-md;
}

/* 选项组 */
.option-group {
  display: flex;
  gap: $spacing-sm;

  &--three {
    .option-item { flex: 1; }
  }
}

.option-item {
  flex: 1;
  padding: $spacing-md $spacing-sm;
  text-align: center;
  background-color: $color-neutral-50;
  border-radius: $radius-md;
  border: 2rpx solid transparent;
  transition: all 0.2s ease;

  &--active {
    border-color: $color-primary-500;
    background-color: $color-primary-50;
  }

  &__name {
    display: block;
    font-size: $font-size-sm;
    color: $color-neutral-700;
    margin-bottom: 4rpx;
  }

  &__price {
    display: block;
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }

  &--active &__name {
    color: $color-primary-500;
    font-weight: $font-weight-medium;
  }

  &--active &__price {
    color: $color-primary-400;
  }
}

/* 价格提示 */
.price-hint {
  margin-top: $spacing-sm;

  &__text {
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }
}

/* 价格加载失败提示 */
.price-error {
  margin-bottom: $spacing-sm;
  padding: $spacing-sm $spacing-md;
  background-color: $color-warning-light;
  border-radius: $radius-sm;
  font-size: $font-size-xs;
  color: $color-warning;
}

/* 数量行 */
.quantity-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: $spacing-md;
  padding-top: $spacing-md;
  border-top: 2rpx solid $color-neutral-100;

  &__label {
    font-size: $font-size-sm;
    color: $color-neutral-600;
  }
}

/* 步进器 */
.stepper {
  display: flex;
  align-items: center;

  &__btn {
    width: 56rpx;
    height: 56rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: $color-neutral-100;
    border-radius: $radius-sm;
    font-size: $font-size-lg;
    color: $color-neutral-700;

    &--disabled {
      color: $color-neutral-300;
      pointer-events: none;
    }
  }

  &__value {
    width: 72rpx;
    text-align: center;
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
  }
}

/* 库存区域 */
.inventory-row {
  display: flex;
  justify-content: space-between;
  align-items: center;

  &__label {
    font-size: $font-size-sm;
    color: $color-neutral-700;
  }
}

.inventory-detail {
  margin-top: $spacing-md;
  padding-top: $spacing-md;
  border-top: 2rpx solid $color-neutral-100;

  &__stock {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    display: block;
    margin-bottom: $spacing-sm;
  }

  &__warning {
    padding: $spacing-sm $spacing-md;
    background-color: $color-warning-light;
    border-radius: $radius-sm;
    font-size: $font-size-xs;
    color: $color-warning;
  }

  &__save {
    margin-top: $spacing-sm;
    font-size: $font-size-xs;
    color: $color-success;
  }
}

/* 价格预览 */
.price-preview {
  background-color: $color-neutral-50;
  border: 2rpx solid $color-neutral-200;
}

.price-row {
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
  }

  &--success &__value {
    color: $color-success;
    font-weight: $font-weight-medium;
  }
}

/* 底部操作栏 */
.bottom-placeholder {
  height: 140rpx;
}

.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  gap: $spacing-md;
  padding: $spacing-md $spacing-lg;
  background-color: $color-neutral-0;
  box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.05);

  &__prev {
    flex: 1;
    height: 88rpx;
    line-height: 88rpx;
    background-color: $color-neutral-0;
    color: $color-neutral-700;
    font-size: $font-size-base;
    font-weight: $font-weight-medium;
    border: 2rpx solid $color-neutral-200;
    border-radius: $radius-md;
  }

  &__next {
    flex: 2;
    height: 88rpx;
    line-height: 88rpx;
    background-color: $color-primary-500;
    color: $color-neutral-0;
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    border-radius: $radius-md;
    border: none;

    &--disabled {
      background-color: $color-neutral-300;
      pointer-events: none;
    }
  }
}
</style>
