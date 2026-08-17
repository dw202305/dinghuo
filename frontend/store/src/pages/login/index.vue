<template>
  <view class="login-page">
    <!-- 顶部品牌区域 -->
    <view class="login-page__header">
      <image class="login-page__logo" src="/static/images/logo.png" mode="aspectFit" />
      <text class="login-page__brand">世尚智能</text>
      <text class="login-page__subtitle">悬浮卷帘门店订货系统</text>
    </view>

    <!-- 表单区域 -->
    <view class="login-page__form">
      <!-- 手机号输入 -->
      <view class="input-group">
        <view class="input-group__prefix">
          <text class="input-group__area-code">+86</text>
        </view>
        <view class="input-group__body">
          <input
            v-model="phone"
            class="input-group__input"
            type="number"
            maxlength="11"
            placeholder="请输入手机号"
            :focus="phoneFocus"
            @focus="phoneFocus = true"
            @blur="phoneFocus = false"
          />
        </view>
        <view v-if="phone.length > 0" class="input-group__clear" @tap="phone = ''">
          <text class="clear-icon">×</text>
        </view>
      </view>

      <!-- 验证码输入 -->
      <view class="input-group">
        <view class="input-group__body">
          <input
            v-model="verifyCode"
            class="input-group__input"
            type="number"
            maxlength="6"
            placeholder="请输入验证码"
          />
        </view>
        <view
          class="input-group__code-btn"
          :class="{ disabled: codeCooldown > 0 || !isPhoneValid }"
          @tap="handleSendCode"
        >
          <text class="code-btn__text">
            {{ codeCooldown > 0 ? `${codeCooldown}s 后重发` : '获取验证码' }}
          </text>
        </view>
      </view>

      <!-- 登录按钮 -->
      <button
        class="login-btn"
        :class="{ loading: loginLoading }"
        :disabled="!canLogin || loginLoading"
        @tap="handleLogin"
      >
        <text v-if="loginLoading" class="login-btn__text">登录中...</text>
        <text v-else class="login-btn__text">登 录</text>
      </button>
    </view>

    <!-- 微信登录（仅小程序） -->
    <!-- #ifdef MP-WEIXIN -->
    <view class="login-page__divider">
      <view class="divider-line" />
      <text class="divider-text">其他登录方式</text>
      <view class="divider-line" />
    </view>
    <button
      class="wechat-btn"
      open-type="getPhoneNumber"
      @getphonenumber="handleWechatLogin"
    >
      <text class="wechat-btn__icon">💬</text>
      <text class="wechat-btn__text">微信快捷登录</text>
    </button>
    <!-- #endif -->

    <!-- 底部区域 -->
    <view class="login-page__footer">
      <view class="agreement-row">
        <view
          class="agreement-checkbox"
          :class="{ checked: agreedTerms }"
          @tap="agreedTerms = !agreedTerms"
        >
          <view class="checkbox-inner">
            <text v-if="agreedTerms" class="checkbox-icon">✓</text>
          </view>
        </view>
        <text class="agreement-text">
          我已阅读并同意
          <text class="agreement-link" @tap.stop="openAgreement('user')">《用户协议》</text>
          和
          <text class="agreement-link" @tap.stop="openAgreement('privacy')">《隐私政策》</text>
        </text>
      </view>
      <text class="login-page__register-hint" @tap="goRegister">
        新用户？联系城市合伙人获取邀请码
      </text>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { isValidPhone } from '@/utils/validator';
import { sendVerifyCode, loginByPhone, loginByWechat } from '@/api/auth';
import { useAuthStore } from '@/stores/auth';
import type { LoginResult, WechatBindPhoneResult } from '@/types/user';

/** 手机号 */
const phone = ref<string>('');
/** 验证码 */
const verifyCode = ref<string>('');
/** 验证码倒计时 */
const codeCooldown = ref<number>(0);
/** 登录loading */
const loginLoading = ref<boolean>(false);
/** 手机号输入框焦点 */
const phoneFocus = ref<boolean>(false);
/** 同意协议 */
const agreedTerms = ref<boolean>(false);

let cooldownTimer: ReturnType<typeof setInterval> | null = null;

/** 手机号是否合法 */
const isPhoneValid = computed<boolean>(() => isValidPhone(phone.value));
/** 是否可以登录 */
const canLogin = computed<boolean>(() => isPhoneValid.value && verifyCode.value.length === 6 && agreedTerms.value);

/**
 * 发送验证码
 */
async function handleSendCode(): Promise<void> {
  if (codeCooldown.value > 0 || !isPhoneValid.value) return;
  try {
    await sendVerifyCode({ phone: phone.value, scene: 'login' });
    uni.showToast({ title: '验证码已发送', icon: 'success' });
    codeCooldown.value = 60;
    cooldownTimer = setInterval(() => {
      codeCooldown.value -= 1;
      if (codeCooldown.value <= 0 && cooldownTimer) {
        clearInterval(cooldownTimer);
        cooldownTimer = null;
      }
    }, 1000);
  } catch {
    /* handled in request interceptor */
  }
}

/**
 * 手机号+验证码登录
 */
async function handleLogin(): Promise<void> {
  if (!canLogin.value || loginLoading.value) return;
  if (!agreedTerms.value) {
    uni.showToast({ title: '请先同意用户协议和隐私政策', icon: 'none' });
    return;
  }
  loginLoading.value = true;
  try {
    const authStore = useAuthStore();
    const result: LoginResult | WechatBindPhoneResult = await loginByPhone({
      phone: phone.value,
      verify_code: verifyCode.value,
    });
    if ('token' in result) {
      authStore.setLoginInfo({
        token: result.token,
        account_id: result.account_id,
        real_name: result.real_name,
        account_role: result.account_role,
        stores: result.stores,
      });
      uni.switchTab({ url: '/pages/index/index' });
    }
  } catch {
    /* handled in request interceptor */
  } finally {
    loginLoading.value = false;
  }
}

/**
 * 微信快捷登录
 */
function handleWechatLogin(e: { detail: { errMsg: string; code?: string; encryptedData?: string; iv?: string } }): void {
  // #ifdef MP-WEIXIN
  if (e.detail.errMsg !== 'getPhoneNumber:ok') {
    uni.showToast({ title: '授权已取消', icon: 'none' });
    return;
  }
  uni.login({
    provider: 'weixin',
    success: async (loginRes) => {
      try {
        const authStore = useAuthStore();
        const result = await loginByWechat({
          code: loginRes.code,
          encrypted_data: e.detail.encryptedData,
          iv: e.detail.iv,
        });
        if ('token' in result) {
          authStore.setLoginInfo({
            token: result.token,
            account_id: result.account_id,
            real_name: result.real_name,
            account_role: result.account_role,
            stores: result.stores,
          });
          uni.switchTab({ url: '/pages/index/index' });
        } else if ('need_bindphone' in result) {
          uni.showToast({ title: '请先绑定手机号', icon: 'none' });
        }
      } catch {
        /* handled */
      }
    },
    fail: () => {
      uni.showToast({ title: '微信登录失败', icon: 'none' });
    },
  });
  // #endif
}

/**
 * 打开协议页面
 */
function openAgreement(type: 'user' | 'privacy'): void {
  uni.showToast({ title: type === 'user' ? '用户协议' : '隐私政策', icon: 'none' });
}

/**
 * 跳转注册页
 */
function goRegister(): void {
  uni.showToast({ title: '请联系城市合伙人获取邀请码', icon: 'none' });
}
</script>

<style lang="scss" scoped>
.login-page {
  min-height: 100vh;
  padding: 0 $spacing-xl;
  background-color: $color-neutral-0;
  display: flex;
  flex-direction: column;
  align-items: center;

  // ── 品牌头部 ──
  &__header {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: 180rpx;
    margin-bottom: 100rpx;
  }
  &__logo {
    width: 140rpx;
    height: 140rpx;
    margin-bottom: $spacing-lg;
  }
  &__brand {
    font-size: $font-size-2xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    letter-spacing: 4rpx;
  }
  &__subtitle {
    font-size: $font-size-sm;
    color: $color-neutral-400;
    margin-top: $spacing-xs;
    letter-spacing: 2rpx;
  }

  // ── 表单 ──
  &__form {
    width: 100%;
    max-width: 650rpx;
  }

  // ── 分割线 ──
  &__divider {
    display: flex;
    align-items: center;
    width: 100%;
    max-width: 650rpx;
    margin-top: $spacing-2xl;
  }

  // ── 底部 ──
  &__footer {
    margin-top: auto;
    padding-bottom: $safe-area-bottom;
    width: 100%;
    max-width: 650rpx;
  }
  &__register-hint {
    display: block;
    text-align: center;
    font-size: $font-size-sm;
    color: $color-neutral-400;
    margin-top: $spacing-xl;
    padding-bottom: $spacing-lg;
  }
}

// ── 输入框组 ──
.input-group {
  display: flex;
  align-items: center;
  background-color: $color-neutral-50;
  border: 2rpx solid $color-neutral-200;
  border-radius: $radius-md;
  padding: 0 $spacing-lg;
  margin-bottom: $spacing-lg;
  height: 100rpx;
  transition: border-color 0.2s;

  &__prefix {
    display: flex;
    align-items: center;
    padding-right: $spacing-md;
    border-right: 2rpx solid $color-neutral-200;
    margin-right: $spacing-md;
  }
  &__area-code {
    font-size: $font-size-base;
    color: $color-neutral-700;
    font-weight: $font-weight-medium;
  }
  &__body {
    flex: 1;
    display: flex;
    align-items: center;
  }
  &__input {
    width: 100%;
    font-size: $font-size-base;
    color: $color-neutral-900;
    height: 100rpx;
  }
  &__clear {
    width: 48rpx;
    height: 48rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: $spacing-sm;
  }
  &__code-btn {
    padding-left: $spacing-md;
    border-left: 2rpx solid $color-neutral-200;
    margin-left: $spacing-md;
    &.disabled {
      opacity: 0.4;
    }
  }
}

.clear-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36rpx;
  height: 36rpx;
  border-radius: $radius-full;
  background-color: $color-neutral-300;
  color: $color-neutral-0;
  font-size: 24rpx;
  line-height: 1;
}

.code-btn__text {
  font-size: $font-size-sm;
  color: $color-primary-500;
  white-space: nowrap;
  font-weight: $font-weight-medium;
}

// ── 分割线 ──
.divider-line {
  flex: 1;
  height: 2rpx;
  background-color: $color-neutral-200;
}
.divider-text {
  padding: 0 $spacing-lg;
  font-size: $font-size-sm;
  color: $color-neutral-400;
}

// ── 登录按钮 ──
.login-btn {
  width: 100%;
  height: 100rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: $color-primary-500;
  border-radius: $radius-md;
  border: none;
  margin-top: $spacing-lg;

  &.loading {
    opacity: 0.7;
  }
  &[disabled] {
    background-color: $color-neutral-200;
  }
  &__text {
    font-size: $font-size-lg;
    font-weight: $font-weight-semibold;
    color: $color-neutral-0;
  }
}

// ── 微信登录按钮 ──
.wechat-btn {
  width: 100%;
  max-width: 650rpx;
  height: 96rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #07C160;
  border-radius: $radius-md;
  border: none;
  margin-top: $spacing-lg;

  &__icon {
    font-size: 40rpx;
    margin-right: $spacing-sm;
  }
  &__text {
    font-size: $font-size-base;
    color: $color-neutral-0;
    font-weight: $font-weight-medium;
  }
}

// ── 协议勾选 ──
.agreement-row {
  display: flex;
  align-items: flex-start;
  padding: $spacing-lg 0;
}
.agreement-checkbox {
  margin-right: $spacing-sm;
  margin-top: 4rpx;
  .checkbox-inner {
    width: 36rpx;
    height: 36rpx;
    border-radius: $radius-sm;
    border: 2rpx solid $color-neutral-300;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
  }
  &.checked .checkbox-inner {
    background-color: $color-primary-500;
    border-color: $color-primary-500;
  }
}
.checkbox-icon {
  color: $color-neutral-0;
  font-size: 22rpx;
  font-weight: $font-weight-bold;
}
.agreement-text {
  font-size: $font-size-sm;
  color: $color-neutral-500;
  line-height: 1.5;
}
.agreement-link {
  color: $color-primary-500;
}
</style>
