<template>
  <el-dialog
    :model-value="visible"
    :title="title"
    :width="width"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <div class="confirm-dialog__content">
      <el-icon v-if="type === 'warning'" class="confirm-dialog__icon--warning">
        <WarningFilled />
      </el-icon>
      <el-icon v-else-if="type === 'danger'" class="confirm-dialog__icon--danger">
        <CircleCloseFilled />
      </el-icon>
      <p>{{ message }}</p>
    </div>
    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button :type="type === 'danger' ? 'danger' : 'primary'" @click="handleConfirm">
        确定
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  visible: boolean
  title?: string
  message: string
  type?: "warning" | "danger"
  width?: string
}>(), {
  title: "确认操作",
  type: "warning",
  width: "420px"
})

const emit = defineEmits<{
  (e: "update:visible", value: boolean): void
  (e: "confirm"): void
}>()

function handleClose(): void {
  emit("update:visible", false)
}

function handleConfirm(): void {
  emit("confirm")
  emit("update:visible", false)
}
</script>

<style scoped>
.confirm-dialog__content {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 8px 0;
}

.confirm-dialog__icon--warning {
  font-size: 24px;
  color: var(--color-warning);
  flex-shrink: 0;
  margin-top: 2px;
}

.confirm-dialog__icon--danger {
  font-size: 24px;
  color: var(--color-error);
  flex-shrink: 0;
  margin-top: 2px;
}
</style>
