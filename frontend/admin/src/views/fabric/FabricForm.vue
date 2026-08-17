<template>
  <div class="fabric-form-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <el-button @click="$router.back()">
        <el-icon><ArrowLeft /></el-icon>
        返回
      </el-button>
      <h2 class="page-title">{{ isEdit ? "编辑面料" : "新增面料" }}</h2>
    </div>

    <el-form
      ref="formRef"
      :model="formData"
      :rules="formRules"
      label-width="120px"
      class="fabric-form"
    >
      <!-- 基本信息 -->
      <el-card class="form-section">
        <template #header>
          <span class="card-title">基本信息</span>
        </template>
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="面料编号" prop="fabric_no">
              <el-input
                v-model="formData.fabric_no"
                placeholder="如 SS-F-20260101"
                :disabled="isEdit"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="面料名称" prop="name">
              <el-input v-model="formData.name" placeholder="请输入面料名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="分类" prop="category">
              <el-select v-model="formData.category" placeholder="请选择分类" style="width: 100%">
                <el-option label="窗帘布" value="curtain" />
                <el-option label="纱帘" value="sheer" />
                <el-option label="遮光布" value="blackout" />
                <el-option label="成品帘" value="finished" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="系列">
              <el-input v-model="formData.series" placeholder="请输入系列名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="材质">
              <el-input v-model="formData.material" placeholder="如 涤纶、棉麻混纺" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="成分">
              <el-input v-model="formData.composition" placeholder="如 65%涤纶 35%棉" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="颜色名称">
              <el-input v-model="formData.color_name" placeholder="如 深灰" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="色号">
              <el-input v-model="formData.color_code" placeholder="如 #333333" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-card>

      <!-- 价格与库存 -->
      <el-card class="form-section">
        <template #header>
          <span class="card-title">价格与库存</span>
        </template>
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="单价(元/㎡)" prop="price_per_sqm">
              <el-input-number
                v-model="formData.price_per_sqm"
                :min="0"
                :precision="2"
                style="width: 100%"
                placeholder="请输入单价"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="库存面积(㎡)">
              <el-input-number
                v-model="formData.stock_area"
                :min="0"
                :precision="2"
                style="width: 100%"
                placeholder="请输入库存面积"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="面料幅宽(cm)">
              <el-input-number
                v-model="formData.fabric_width"
                :min="0"
                :precision="1"
                style="width: 100%"
                placeholder="请输入面料幅宽"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="最小计费面积(㎡)">
              <el-input-number
                v-model="formData.min_billing_area"
                :min="0"
                :precision="2"
                style="width: 100%"
                placeholder="请输入最小计费面积"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="损耗系数">
              <el-input-number
                v-model="formData.loss_coefficient"
                :min="0"
                :max="10"
                :precision="2"
                :step="0.01"
                style="width: 100%"
                placeholder="默认1.0"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="生效日期">
              <el-date-picker
                v-model="formData.effective_date"
                type="date"
                placeholder="请选择生效日期"
                value-format="YYYY-MM-DD"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
      </el-card>

      <!-- 图片上传 -->
      <el-card class="form-section">
        <template #header>
          <span class="card-title">面料图片</span>
        </template>
        <el-form-item label="主图">
          <el-upload
            class="image-uploader"
            :show-file-list="false"
            :auto-upload="false"
            accept="image/*"
            :on-change="(file: UploadFile) => handleMainImageChange(file)"
          >
            <el-image
              v-if="formData.main_image"
              :src="formData.main_image"
              fit="cover"
              class="preview-image"
            />
            <div v-else class="upload-placeholder">
              <el-icon :size="28"><Plus /></el-icon>
              <span>上传主图</span>
            </div>
          </el-upload>
        </el-form-item>
        <el-form-item label="详情图片">
          <el-upload
            v-model:file-list="detailImageList"
            list-type="picture-card"
            :auto-upload="false"
            accept="image/*"
            :limit="10"
            :on-preview="handlePicturePreview"
          >
            <el-icon><Plus /></el-icon>
          </el-upload>
        </el-form-item>
      </el-card>

      <!-- 供应商关联 -->
      <el-card class="form-section">
        <template #header>
          <span class="card-title">供应商关联</span>
        </template>
        <el-form-item label="供应商">
          <el-select
            v-model="formData.supplier_id"
            placeholder="请选择供应商"
            filterable
            clearable
            style="width: 100%"
          >
            <el-option
              v-for="supplier in supplierList"
              :key="supplier.id"
              :label="supplier.supplier_name"
              :value="supplier.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="供应商面料编号">
          <el-input v-model="formData.supplier_fabric_no" placeholder="请输入供应商处的面料编号" />
        </el-form-item>
      </el-card>

      <!-- 描述与标签 -->
      <el-card class="form-section">
        <template #header>
          <span class="card-title">描述与标签</span>
        </template>
        <el-form-item label="质感标签">
          <el-select
            v-model="formData.texture_tags"
            multiple
            filterable
            allow-create
            default-first-option
            placeholder="选择或输入质感标签"
            style="width: 100%"
          >
            <el-option label="柔软" value="柔软" />
            <el-option label="挺括" value="挺括" />
            <el-option label="厚实" value="厚实" />
            <el-option label="轻薄" value="轻薄" />
            <el-option label="丝滑" value="丝滑" />
            <el-option label="粗糙" value="粗糙" />
          </el-select>
        </el-form-item>
        <el-form-item label="功能标签">
          <el-select
            v-model="formData.function_tags"
            multiple
            filterable
            allow-create
            default-first-option
            placeholder="选择或输入功能标签"
            style="width: 100%"
          >
            <el-option label="遮光" value="遮光" />
            <el-option label="隔热" value="隔热" />
            <el-option label="隔音" value="隔音" />
            <el-option label="防紫外线" value="防紫外线" />
            <el-option label="阻燃" value="阻燃" />
            <el-option label="防水" value="防水" />
            <el-option label="抗菌" value="抗菌" />
          </el-select>
        </el-form-item>
        <el-form-item label="面料描述">
          <el-input
            v-model="formData.description"
            type="textarea"
            :rows="4"
            placeholder="请输入面料描述信息"
            maxlength="1000"
            show-word-limit
          />
        </el-form-item>
      </el-card>

      <!-- 状态设置 -->
      <el-card class="form-section">
        <template #header>
          <span class="card-title">状态设置</span>
        </template>
        <el-row :gutter="24">
          <el-col :span="8">
            <el-form-item label="上架状态">
              <el-switch v-model="formData.listing_status" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="允许订货">
              <el-switch v-model="formData.orderable" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="排序权重">
              <el-input-number
                v-model="formData.sort_weight"
                :min="0"
                :max="9999"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
      </el-card>

      <!-- 操作按钮 -->
      <div class="form-footer">
        <el-button type="primary" :loading="saving" size="large" @click="handleSubmit">
          {{ isEdit ? "保存修改" : "创建面料" }}
        </el-button>
        <el-button size="large" @click="$router.back()">取消</el-button>
      </div>
    </el-form>

    <!-- 图片预览 -->
    <el-dialog v-model="previewVisible" title="图片预览" width="600px">
      <el-image :src="previewUrl" fit="contain" style="width: 100%" />
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import { ElMessage } from "element-plus"
import { ArrowLeft, Plus } from "@element-plus/icons-vue"
import type { FormInstance, FormRules, UploadFile } from "element-plus"
import { getFabricDetail, saveFabric } from "@/api/fabric"
import type { FabricSaveParams } from "@/types/fabric"

const route = useRoute()
const router = useRouter()
const formRef = ref<FormInstance>()
const saving = ref<boolean>(false)

const editId = computed<number | null>(() => {
  const id = route.params.id
  return id ? Number(id) : null
})
const isEdit = computed<boolean>(() => editId.value !== null)

/** 表单数据 */
const formData = reactive<{
  fabric_no: string
  name: string
  category: string
  series: string
  material: string
  composition: string
  color_name: string
  color_code: string
  price_per_sqm: number
  stock_area: number
  fabric_width: number
  min_billing_area: number
  loss_coefficient: number
  effective_date: string
  main_image: string
  description: string
  texture_tags: string[]
  function_tags: string[]
  supplier_id: number | undefined
  supplier_fabric_no: string
  listing_status: 0 | 1
  orderable: 0 | 1
  sort_weight: number
}>({
  fabric_no: "",
  name: "",
  category: "",
  series: "",
  material: "",
  composition: "",
  color_name: "",
  color_code: "",
  price_per_sqm: 0,
  stock_area: 0,
  fabric_width: 0,
  min_billing_area: 0,
  loss_coefficient: 1.0,
  effective_date: "",
  main_image: "",
  description: "",
  texture_tags: [],
  function_tags: [],
  supplier_id: undefined,
  supplier_fabric_no: "",
  listing_status: 1,
  orderable: 1,
  sort_weight: 0
})

const formRules: FormRules = {
  fabric_no: [{ required: true, message: "请输入面料编号", trigger: "blur" }],
  name: [{ required: true, message: "请输入面料名称", trigger: "blur" }],
  price_per_sqm: [{ required: true, message: "请输入单价", trigger: "blur" }]
}

/** 详情图片列表 */
const detailImageList = ref<UploadFile[]>([])

/** 供应商列表 */
const supplierList = ref<{ id: number; supplier_name: string }[]>([])

/** 图片预览 */
const previewVisible = ref<boolean>(false)
const previewUrl = ref<string>("")

/** 主图变更 */
function handleMainImageChange(file: UploadFile): void {
  if (file.raw) {
    const url = URL.createObjectURL(file.raw)
    formData.main_image = url
  }
}

/** 图片预览 */
function handlePicturePreview(file: UploadFile): void {
  if (file.url) {
    previewUrl.value = file.url
    previewVisible.value = true
  }
}

/** 加载面料详情 */
onMounted(async () => {
  if (editId.value) {
    try {
      const data = await getFabricDetail(editId.value)
      Object.assign(formData, {
        fabric_no: data.fabric_no,
        name: data.name,
        series: data.series || "",
        material: data.material || "",
        color_name: data.color_name || "",
        color_code: data.color_code || "",
        price_per_sqm: parseFloat(data.price_per_sqm),
        fabric_width: data.fabric_width ? parseFloat(data.fabric_width) : 0,
        min_billing_area: data.min_billing_area ? parseFloat(data.min_billing_area) : 0,
        loss_coefficient: data.loss_coefficient ? parseFloat(data.loss_coefficient) : 1.0,
        main_image: data.main_image || "",
        texture_tags: data.texture_tags || [],
        function_tags: data.function_tags || [],
        listing_status: data.listing_status,
        orderable: data.orderable,
        sort_weight: data.sort_weight,
        effective_date: data.effective_date || ""
      })
    } catch {
      ElMessage.error("加载面料信息失败")
    }
  }
})

/** 提交 */
async function handleSubmit(): Promise<void> {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  saving.value = true
  try {
    const params: FabricSaveParams = {
      fabric_no: formData.fabric_no,
      name: formData.name,
      series: formData.series || undefined,
      material: formData.material || undefined,
      color_name: formData.color_name || undefined,
      color_code: formData.color_code || undefined,
      price_per_sqm: formData.price_per_sqm,
      main_image: formData.main_image || undefined,
      fabric_width: formData.fabric_width || undefined,
      min_billing_area: formData.min_billing_area || undefined,
      loss_coefficient: formData.loss_coefficient,
      listing_status: formData.listing_status,
      orderable: formData.orderable,
      sort_weight: formData.sort_weight,
      effective_date: formData.effective_date || undefined,
      texture_tags: formData.texture_tags.length > 0 ? formData.texture_tags : undefined,
      function_tags: formData.function_tags.length > 0 ? formData.function_tags : undefined
    }

    if (isEdit.value) {
      params.id = editId.value as number
    }

    await saveFabric(params)
    ElMessage.success("保存成功")
    router.push("/fabric/list")
  } catch {
    // handled by interceptor
  } finally {
    saving.value = false
  }
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

.fabric-form {
  max-width: 900px;
}

.form-section {
  margin-bottom: 16px;
}

.card-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-neutral-800);
}

/* 图片上传 */
.image-uploader {
  display: inline-block;
}

.preview-image {
  width: 150px;
  height: 150px;
  border-radius: var(--radius-md);
  object-fit: cover;
}

.upload-placeholder {
  width: 150px;
  height: 150px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px dashed var(--color-neutral-300);
  border-radius: var(--radius-md);
  color: var(--color-neutral-400);
  cursor: pointer;
  transition: border-color 0.2s;
}

.upload-placeholder:hover {
  border-color: var(--color-primary-400);
  color: var(--color-primary-500);
}

/* 底部按钮 */
.form-footer {
  display: flex;
  gap: 12px;
  padding: 20px 0;
}
</style>
