<template>
  <div class="login-page">
    <div class="login-page__card">
      <div class="login-page__header">
        <h1 class="login-page__title">世尚智能悬浮卷帘</h1>
        <p class="login-page__subtitle">后台管理系统</p>
      </div>

      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        class="login-page__form"
        @submit.prevent="handleLogin"
      >
        <el-form-item prop="username">
          <el-input
            v-model="formData.username"
            placeholder="请输入用户名"
            size="large"
            prefix-icon="User"
          />
        </el-form-item>

        <el-form-item prop="password">
          <el-input
            v-model="formData.password"
            type="password"
            placeholder="请输入密码"
            size="large"
            prefix-icon="Lock"
            show-password
            @keyup.enter="handleLogin"
          />
        </el-form-item>

        <el-form-item>
          <el-button
            type="primary"
            size="large"
            :loading="loading"
            class="login-page__submit"
            @click="handleLogin"
          >
            登 录
          </el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue"
import { useRouter, useRoute } from "vue-router"
import { ElMessage } from "element-plus"
import type { FormInstance, FormRules } from "element-plus"
import { useAuthStore } from "@/stores/auth"

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const formRef = ref<FormInstance>()
const loading = ref<boolean>(false)

const formData = reactive({
  username: "",
  password: ""
})

const formRules: FormRules = {
  username: [{ required: true, message: "请输入用户名", trigger: "blur" }],
  password: [{ required: true, message: "请输入密码", trigger: "blur" }]
}

/**
 * 处理登录
 */
async function handleLogin(): Promise<void> {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  loading.value = true
  try {
    await authStore.login(formData.username, formData.password)
    ElMessage.success("登录成功")
    const redirect = (route.query.redirect as string) || "/"
    router.push(redirect)
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : "登录失败"
    ElMessage.error(errMsg)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: linear-gradient(135deg, var(--color-primary-700) 0%, var(--color-primary-500) 100%);
}

.login-page__card {
  width: 420px;
  padding: 48px 40px;
  background: #fff;
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-3);
}

.login-page__header {
  text-align: center;
  margin-bottom: 40px;
}

.login-page__title {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-primary-700);
  margin: 0 0 8px;
}

.login-page__subtitle {
  font-size: 14px;
  color: var(--color-neutral-500);
  margin: 0;
}

.login-page__form {
  width: 100%;
}

.login-page__submit {
  width: 100%;
  height: 44px;
  font-size: 16px;
  letter-spacing: 4px;
}
</style>
