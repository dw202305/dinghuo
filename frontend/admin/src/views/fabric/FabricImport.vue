<template>
  <div class="fabric-import-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <el-button @click="$router.back()">
        <el-icon><ArrowLeft /></el-icon>
        返回
      </el-button>
      <h2 class="page-title">批量导入面料</h2>
    </div>

    <el-row :gutter="20">
      <!-- 左侧：上传区域 -->
      <el-col :span="importResult ? 14 : 24">
        <!-- 步骤说明 -->
        <el-card class="import-section">
          <template #header>
            <span class="card-title">导入步骤</span>
          </template>
          <el-steps :active="currentStep" finish-status="success" align-center>
            <el-step title="下载模板" description="获取标准导入模板" />
            <el-step title="填写数据" description="按模板格式填写" />
            <el-step title="上传文件" description="上传 .xlsx 文件" />
            <el-step title="确认导入" description="预览并确认" />
          </el-steps>
          <div class="template-action">
            <el-button type="primary" plain @click="handleDownloadTemplate">
              <el-icon><Download /></el-icon>
              下载导入模板
            </el-button>
            <span class="template-tip">请先下载模板，按格式填写后再上传</span>
          </div>
        </el-card>

        <!-- 文件上传 -->
        <el-card class="import-section">
          <template #header>
            <span class="card-title">上传文件</span>
          </template>
          <el-upload
            ref="uploadRef"
            class="upload-area"
            drag
            :auto-upload="false"
            :limit="1"
            accept=".xlsx,.xls,.csv"
            :on-change="handleFileChange"
            :on-remove="handleFileRemove"
            :file-list="fileList"
          >
            <el-icon class="upload-icon"><UploadFilled /></el-icon>
            <div class="el-upload__text">
              将文件拖到此处，或<em>点击上传</em>
            </div>
            <template #tip>
              <div class="el-upload__tip">
                仅支持 .xlsx / .xls / .csv 格式，文件大小不超过 10MB
              </div>
            </template>
          </el-upload>

          <!-- 预览表格 -->
          <div v-if="previewData.length > 0" class="preview-section">
            <div class="preview-header">
              <span class="preview-title">数据预览（前 {{ previewData.length }} 行）</span>
              <el-tag v-if="previewData.length > 0" type="info" size="small">
                共 {{ previewData.length }} 条数据
              </el-tag>
            </div>
            <el-table
              :data="previewData"
              stripe
              border
              size="small"
              max-height="360"
              :row-class-name="getRowClassName"
            >
              <el-table-column type="index" label="行号" width="60" align="center" />
              <el-table-column prop="fabric_no" label="面料编号" width="140" />
              <el-table-column prop="name" label="名称" min-width="120" />
              <el-table-column prop="series" label="系列" width="100" />
              <el-table-column prop="material" label="材质" width="100" />
              <el-table-column prop="color_name" label="颜色" width="80" />
              <el-table-column prop="price_per_sqm" label="单价/㎡" width="90" align="right" />
              <el-table-column label="状态" width="80" align="center">
                <template #default="{ row }">
                  <el-tag
                    v-if="row._status === 'valid'"
                    type="success"
                    size="small"
                    effect="light"
                  >
                    有效
                  </el-tag>
                  <el-tooltip
                    v-else-if="row._status === 'error'"
                    :content="row._error"
                    placement="top"
                  >
                    <el-tag type="danger" size="small" effect="light">
                      异常
                    </el-tag>
                  </el-tooltip>
                  <el-tag v-else type="warning" size="small" effect="light">
                    待检
                  </el-tag>
                </template>
              </el-table-column>
            </el-table>

            <!-- 错误提示 -->
            <div v-if="validationErrors.length > 0" class="validation-errors">
              <el-alert title="数据校验发现问题" type="warning" show-icon :closable="false">
                <template #default>
                  <ul class="error-list">
                    <li v-for="(err, idx) in validationErrors" :key="idx">
                      第 {{ err.row }} 行：{{ err.message }}
                    </li>
                  </ul>
                </template>
              </el-alert>
            </div>
          </div>

          <!-- 导入按钮 -->
          <div class="import-action">
            <el-button
              type="primary"
              :loading="importing"
              :disabled="!selectedFile"
              size="large"
              @click="handleImport"
            >
              <el-icon v-if="!importing"><Upload /></el-icon>
              {{ importing ? "导入中..." : "确认导入" }}
            </el-button>
          </div>

          <!-- 导入进度 -->
          <div v-if="importing" class="progress-section">
            <el-progress
              :percentage="importProgress"
              :stroke-width="12"
              :format="() => `${importProgress}%`"
            />
            <span class="progress-text">正在导入，请稍候...</span>
          </div>
        </el-card>
      </el-col>

      <!-- 右侧：导入结果 -->
      <el-col :span="10" v-if="importResult">
        <el-card class="result-section">
          <template #header>
            <span class="card-title">导入结果</span>
          </template>
          <div class="result-summary">
            <div class="result-stat">
              <div class="result-stat__icon result-stat__icon--success">
                <el-icon :size="24"><CircleCheck /></el-icon>
              </div>
              <div class="result-stat__content">
                <span class="result-stat__value">{{ importResult.success_count }}</span>
                <span class="result-stat__label">成功导入</span>
              </div>
            </div>
            <div class="result-stat">
              <div class="result-stat__icon result-stat__icon--error">
                <el-icon :size="24"><CircleClose /></el-icon>
              </div>
              <div class="result-stat__content">
                <span class="result-stat__value">{{ importResult.fail_count }}</span>
                <span class="result-stat__label">导入失败</span>
              </div>
            </div>
          </div>

          <!-- 错误详情 -->
          <div v-if="importResult.errors.length > 0" class="result-errors">
            <h4 class="result-errors__title">失败原因：</h4>
            <ul class="result-errors__list">
              <li v-for="(err, idx) in importResult.errors" :key="idx">{{ err }}</li>
            </ul>
          </div>

          <div class="result-actions">
            <el-button type="primary" @click="$router.push('/fabric/list')">
              前往面料列表
            </el-button>
            <el-button @click="handleReset">继续导入</el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue"
import { ElMessage } from "element-plus"
import { ArrowLeft, Download, UploadFilled, Upload, CircleCheck, CircleClose } from "@element-plus/icons-vue"
import type { UploadFile } from "element-plus"
import { importFabrics } from "@/api/fabric"

/** 上传相关 */
const selectedFile = ref<File | null>(null)
const fileList = ref<UploadFile[]>([])
const importing = ref<boolean>(false)
const importProgress = ref<number>(0)

/** 预览数据 */
interface PreviewRow {
  fabric_no: string
  name: string
  series: string
  material: string
  color_name: string
  price_per_sqm: string
  _status: "valid" | "error" | "pending"
  _error: string
}

const previewData = ref<PreviewRow[]>([])
const validationErrors = ref<{ row: number; message: string }[]>([])

/** 导入结果 */
const importResult = ref<{ success_count: number; fail_count: number; errors: string[] } | null>(null)

/** 步骤 */
const currentStep = computed<number>(() => {
  if (importResult.value) return 3
  if (previewData.value.length > 0) return 2
  if (selectedFile.value) return 1
  return 0
})

/** 文件变更 */
function handleFileChange(file: UploadFile): void {
  selectedFile.value = file.raw ?? null
  // 模拟预览（实际项目中应解析 xlsx）
  generatePreview()
}

/** 文件移除 */
function handleFileRemove(): void {
  selectedFile.value = null
  previewData.value = []
  validationErrors.value = []
}

/** 生成预览（模拟） */
function generatePreview(): void {
  // 实际项目中使用 xlsx.js 解析文件，这里模拟一些预览数据
  const mockData: PreviewRow[] = [
    { fabric_no: "SS-F-20260101", name: "丝绒深灰", series: "经典系列", material: "涤纶", color_name: "深灰", price_per_sqm: "128.00", _status: "valid", _error: "" },
    { fabric_no: "SS-F-20260102", name: "棉麻米白", series: "自然系列", material: "棉麻", color_name: "米白", price_per_sqm: "98.00", _status: "valid", _error: "" },
    { fabric_no: "", name: "测试面料", series: "", material: "", color_name: "", price_per_sqm: "0", _status: "error", _error: "面料编号不能为空" }
  ]
  previewData.value = mockData.slice(0, 10)
  validationErrors.value = [
    { row: 3, message: "面料编号不能为空" }
  ]
}

/** 行样式 */
function getRowClassName({ row }: { row: PreviewRow }): string {
  if (row._status === "error") return "row-error"
  return ""
}

/** 下载模板 */
function handleDownloadTemplate(): void {
  // 实际项目中应从后端下载模板文件
  ElMessage.success("模板下载中...")
  // 模拟下载
  const link = document.createElement("a")
  link.href = "#"
  link.download = "面料导入模板.xlsx"
  link.click()
}

/** 确认导入 */
async function handleImport(): Promise<void> {
  if (!selectedFile.value) return

  importing.value = true
  importProgress.value = 0
  importResult.value = null

  // 模拟进度
  const progressTimer = setInterval(() => {
    if (importProgress.value < 90) {
      importProgress.value += Math.random() * 20
    }
  }, 300)

  try {
    const result = await importFabrics(selectedFile.value)
    clearInterval(progressTimer)
    importProgress.value = 100
    importResult.value = result as unknown as { success_count: number; fail_count: number; errors: string[] }
    ElMessage.success("导入完成")
  } catch {
    clearInterval(progressTimer)
    ElMessage.error("导入失败，请重试")
  } finally {
    importing.value = false
  }
}

/** 重置继续导入 */
function handleReset(): void {
  selectedFile.value = null
  fileList.value = []
  previewData.value = []
  validationErrors.value = []
  importResult.value = null
  importProgress.value = 0
}
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-neutral-800);
  margin: 0;
}

.card-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-neutral-800);
}

.import-section {
  margin-bottom: 16px;
}

.template-action {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 16px;
}

.template-tip {
  font-size: 13px;
  color: var(--color-neutral-400);
}

.upload-area {
  width: 100%;
}

.upload-icon {
  font-size: 48px;
  color: var(--color-neutral-300);
  margin-bottom: 8px;
}

/* 预览 */
.preview-section {
  margin-top: 20px;
}

.preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.preview-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-neutral-700);
}

/* 错误行高亮 */
:deep(.row-error) {
  background-color: var(--color-error-light) !important;
}

.validation-errors {
  margin-top: 12px;
}

.error-list {
  margin: 8px 0 0;
  padding-left: 20px;
  font-size: 13px;
  line-height: 1.6;
}

/* 导入按钮 */
.import-action {
  margin-top: 20px;
  display: flex;
  justify-content: center;
}

/* 进度 */
.progress-section {
  margin-top: 16px;
  text-align: center;
}

.progress-text {
  display: block;
  margin-top: 8px;
  font-size: 13px;
  color: var(--color-neutral-500);
}

/* 结果 */
.result-section {
  position: sticky;
  top: 20px;
}

.result-summary {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}

.result-stat {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-radius: var(--radius-md);
  background: var(--color-neutral-50);
}

.result-stat__icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.result-stat__icon--success {
  background: var(--color-success-light);
  color: var(--color-success);
}

.result-stat__icon--error {
  background: var(--color-error-light);
  color: var(--color-error);
}

.result-stat__content {
  display: flex;
  flex-direction: column;
}

.result-stat__value {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-neutral-800);
  font-variant-numeric: tabular-nums;
}

.result-stat__label {
  font-size: 13px;
  color: var(--color-neutral-500);
}

.result-errors {
  margin-bottom: 20px;
}

.result-errors__title {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-neutral-700);
  margin: 0 0 8px;
}

.result-errors__list {
  padding-left: 20px;
  font-size: 13px;
  color: var(--color-error);
  line-height: 1.8;
}

.result-actions {
  display: flex;
  gap: 12px;
}
</style>
