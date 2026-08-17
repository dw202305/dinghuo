<template>
  <view class="fabric-selector">
    <!-- ═══ 顶部搜索栏 ═══ -->
    <view class="search-bar">
      <view class="search-bar__inner">
        <text class="search-bar__icon">🔍</text>
        <input
          v-model="keyword"
          class="search-bar__input"
          placeholder="搜索面料编号 / 名称 / 色号"
          confirm-type="search"
          @input="onSearchInput"
          @confirm="doSearch"
        />
        <view v-if="keyword" class="search-bar__clear" @tap="clearSearch">
          <text class="search-bar__clear-icon">×</text>
        </view>
      </view>
    </view>

    <!-- ═══ 筛选标签栏（横向滚动） ═══ -->
    <scroll-view scroll-x class="filter-bar" :show-scrollbar="false">
      <view class="filter-bar__inner">
        <!-- 全部 -->
        <view
          class="filter-tag"
          :class="{ active: activeSeries === '' }"
          @tap="handleSeriesTap('')"
        >
          <text>全部</text>
        </view>
        <!-- 系列标签 -->
        <view
          v-for="s in seriesOptions"
          :key="'s-' + s"
          class="filter-tag"
          :class="{ active: activeSeries === s }"
          @tap="handleSeriesTap(s)"
        >
          <text>{{ s }}</text>
        </view>
        <!-- 分隔 -->
        <view class="filter-divider" />
        <!-- 材质标签 -->
        <view
          v-for="m in materialOptions"
          :key="'m-' + m"
          class="filter-tag"
          :class="{ active: activeMaterial === m }"
          @tap="handleMaterialTap(m)"
        >
          <text>{{ m }}</text>
        </view>
        <!-- 分隔 -->
        <view class="filter-divider" />
        <!-- 功能标签 -->
        <view
          v-for="f in functionOptions"
          :key="'f-' + f"
          class="filter-tag"
          :class="{ active: activeFunction === f }"
          @tap="handleFunctionTap(f)"
        >
          <text>{{ f }}</text>
        </view>
        <!-- 分隔 -->
        <view class="filter-divider" />
        <!-- 价格区间 -->
        <view
          v-for="p in priceOptions"
          :key="p.label"
          class="filter-tag"
          :class="{ active: activePriceLabel === p.label }"
          @tap="handlePriceTap(p)"
        >
          <text>{{ p.label }}</text>
        </view>
      </view>
    </scroll-view>

    <!-- ═══ 清除筛选提示 ═══ -->
    <view v-if="hasActiveFilter" class="clear-filter" @tap="clearAllFilters">
      <text class="clear-filter__text">清除所有筛选条件</text>
    </view>

    <!-- ═══ 主内容滚动区 ═══ -->
    <scroll-view
      scroll-y
      class="main-scroll"
      :refresher-enabled="true"
      :refresher-triggered="refreshing"
      @refresherrefresh="handleRefresh"
      @scrolltolower="loadMore"
    >
      <!-- ── 最近使用区域 ── -->
      <view v-if="recentFabrics.length > 0 && !hasActiveFilter" class="section">
        <view class="section__header">
          <text class="section__title">🕐 最近使用</text>
        </view>
        <scroll-view scroll-x class="recent-scroll" :show-scrollbar="false">
          <view class="recent-scroll__inner">
            <view
              v-for="fabric in recentFabrics"
              :key="'r-' + fabric.fabric_no"
              class="recent-card"
              :class="{ disabled: fabric.listing_status === 0 }"
              @tap="showDetail(fabric)"
            >
              <image
                class="recent-card__img"
                :src="fabric.main_image || '/static/images/fabric-placeholder.png'"
                mode="aspectFill"
                lazy-load
              />
              <text class="recent-card__no">{{ fabric.fabric_no }}</text>
            </view>
          </view>
        </scroll-view>
      </view>

      <!-- ── 我的收藏区域（可折叠） ── -->
      <view v-if="favoriteFabrics.length > 0 && !hasActiveFilter" class="section">
        <view class="section__header" @tap="favoritesCollapsed = !favoritesCollapsed">
          <text class="section__title">⭐ 我的收藏</text>
          <text class="section__toggle">{{ favoritesCollapsed ? '展开' : '收起' }}</text>
        </view>
        <view v-if="!favoritesCollapsed" class="section__body">
          <scroll-view scroll-x class="recent-scroll" :show-scrollbar="false">
            <view class="recent-scroll__inner">
              <view
                v-for="fabric in favoriteFabrics"
                :key="'fav-' + fabric.fabric_no"
                class="recent-card"
                :class="{ disabled: fabric.listing_status === 0 }"
                @tap="showDetail(fabric)"
              >
                <image
                  class="recent-card__img"
                  :src="fabric.main_image || '/static/images/fabric-placeholder.png'"
                  mode="aspectFill"
                  lazy-load
                />
                <text class="recent-card__no">{{ fabric.fabric_no }}</text>
              </view>
            </view>
          </scroll-view>
        </view>
      </view>

      <!-- ── 面料网格列表（双列卡片） ── -->
      <view class="fabric-grid">
        <view
          v-for="fabric in fabrics"
          :key="fabric.id"
          class="fabric-card"
          :class="{ disabled: fabric.listing_status === 0 }"
          @tap="showDetail(fabric)"
        >
          <!-- 图片区 -->
          <view class="fabric-card__image-wrap">
            <image
              class="fabric-card__image"
              :src="fabric.main_image || '/static/images/fabric-placeholder.png'"
              mode="aspectFill"
              lazy-load
            />
            <!-- 下架遮罩 -->
            <view v-if="fabric.listing_status === 0" class="fabric-card__offline">
              <text class="fabric-card__offline-text">已下架</text>
            </view>
            <!-- 收藏按钮 -->
            <view class="fabric-card__fav" @tap.stop="handleFavorite(fabric)">
              <text class="fabric-card__fav-icon" :class="{ favorited: favoriteSet.has(fabric.fabric_no) }">
                {{ favoriteSet.has(fabric.fabric_no) ? '★' : '☆' }}
              </text>
            </view>
          </view>

          <!-- 信息区 -->
          <view class="fabric-card__info">
            <text class="fabric-card__no">{{ fabric.fabric_no }}</text>
            <text class="fabric-card__name">{{ fabric.name }}</text>
            <!-- 颜色色块 + 颜色名 -->
            <view v-if="fabric.color_name" class="fabric-card__color-row">
              <view
                class="fabric-card__color-dot"
                :style="{ backgroundColor: fabric.color_code || '#999' }"
              />
              <text class="fabric-card__color-name">{{ fabric.color_name }}</text>
            </view>
            <!-- 价格 -->
            <text class="fabric-card__price">¥{{ fabric.price_per_sqm }}/㎡</text>
            <!-- 供货状态标签 -->
            <view
              v-if="supplyStatusInfo(fabric.stock_status)"
              class="fabric-card__status"
              :style="{
                color: supplyStatusInfo(fabric.stock_status)!.color,
                backgroundColor: supplyStatusInfo(fabric.stock_status)!.bg,
              }"
            >
              <text class="fabric-card__status-text">{{ supplyStatusInfo(fabric.stock_status)!.label }}</text>
            </view>
          </view>
        </view>
      </view>

      <!-- 加载状态 -->
      <view v-if="loading" class="loading-state">
        <text class="loading-state__text">加载中...</text>
      </view>
      <view v-else-if="fabrics.length === 0" class="empty-state">
        <text class="empty-state__icon">🧵</text>
        <text class="empty-state__text">未找到相关面料</text>
        <view v-if="hasActiveFilter" class="empty-state__action" @tap="clearAllFilters">
          <text class="empty-state__action-text">清除筛选条件</text>
        </view>
      </view>
      <view v-else-if="noMore" class="no-more-state">
        <text class="no-more-state__text">— 已加载全部面料 —</text>
      </view>

      <!-- 底部安全区占位 -->
      <view class="safe-area-placeholder" />
    </scroll-view>

    <!-- ═══ 面料详情预览弹窗（底部弹窗） ═══ -->
    <view v-if="detailVisible" class="detail-modal" @tap.self="closeDetail">
      <view class="detail-modal__content">
        <!-- 大图 -->
        <view class="detail-modal__gallery">
          <swiper
            class="detail-modal__swiper"
            :indicator-dots="true"
            indicator-color="rgba(255,255,255,0.4)"
            indicator-active-color="#FFFFFF"
          >
            <swiper-item v-for="(img, i) in detailImages" :key="i">
              <image class="detail-modal__img" :src="img" mode="aspectFill" />
            </swiper-item>
          </swiper>
        </view>

        <!-- 信息区 -->
        <scroll-view scroll-y class="detail-modal__info-scroll">
          <view class="detail-modal__info">
            <!-- 编号 + 名称 -->
            <view class="detail-modal__header">
              <text class="detail-modal__no">{{ detailFabric?.fabric_no }}</text>
              <text class="detail-modal__name">{{ detailFabric?.name }}</text>
            </view>

            <!-- 价格 -->
            <text class="detail-modal__price">¥{{ detailFabric?.price_per_sqm }}/㎡</text>

            <!-- 属性列表 -->
            <view class="detail-modal__attrs">
              <view v-if="detailFabric?.series" class="detail-modal__attr">
                <text class="detail-modal__attr-label">系列</text>
                <text class="detail-modal__attr-value">{{ detailFabric?.series }}</text>
              </view>
              <view v-if="detailFabric?.material" class="detail-modal__attr">
                <text class="detail-modal__attr-label">材质</text>
                <text class="detail-modal__attr-value">{{ detailFabric?.material }}</text>
              </view>
              <view v-if="detailFabric?.color_name" class="detail-modal__attr">
                <text class="detail-modal__attr-label">颜色</text>
                <view class="detail-modal__attr-color">
                  <view
                    class="detail-modal__color-dot"
                    :style="{ backgroundColor: detailFabric?.color_code || '#999' }"
                  />
                  <text class="detail-modal__attr-value">{{ detailFabric?.color_name }}</text>
                </view>
              </view>
              <view v-if="detailFabric?.fabric_width" class="detail-modal__attr">
                <text class="detail-modal__attr-label">幅宽</text>
                <text class="detail-modal__attr-value">{{ detailFabric?.fabric_width }}m</text>
              </view>
              <view v-if="detailFabric?.min_billing_area" class="detail-modal__attr">
                <text class="detail-modal__attr-label">最小计费</text>
                <text class="detail-modal__attr-value">{{ detailFabric?.min_billing_area }}㎡</text>
              </view>
              <view v-if="detailFabric?.function_tags && detailFabric.function_tags.length > 0" class="detail-modal__attr">
                <text class="detail-modal__attr-label">功能</text>
                <view class="detail-modal__tags">
                  <text
                    v-for="tag in detailFabric.function_tags"
                    :key="tag"
                    class="detail-modal__tag"
                  >{{ tag }}</text>
                </view>
              </view>
              <view class="detail-modal__attr">
                <text class="detail-modal__attr-label">供货状态</text>
                <view
                  v-if="detailFabric && supplyStatusInfo(detailFabric.stock_status)"
                  class="detail-modal__status"
                  :style="{
                    color: supplyStatusInfo(detailFabric.stock_status)!.color,
                    backgroundColor: supplyStatusInfo(detailFabric.stock_status)!.bg,
                  }"
                >
                  <text>{{ supplyStatusInfo(detailFabric.stock_status)!.label }}</text>
                </view>
              </view>
            </view>

            <!-- 描述 -->
            <view v-if="detailFabric?.description" class="detail-modal__desc">
              <text class="detail-modal__desc-text">{{ detailFabric.description }}</text>
            </view>
          </view>
        </scroll-view>

        <!-- 底部操作 -->
        <view class="detail-modal__footer safe-area-bottom">
          <view class="detail-modal__fav-btn" @tap="handleFavoriteDetail">
            <text class="detail-modal__fav-icon" :class="{ favorited: detailFabric && favoriteSet.has(detailFabric.fabric_no) }">
              {{ detailFabric && favoriteSet.has(detailFabric.fabric_no) ? '★' : '☆' }}
            </text>
            <text class="detail-modal__fav-label">收藏</text>
          </view>
          <button
            class="detail-modal__confirm-btn"
            :disabled="!detailFabric || detailFabric.listing_status === 0"
            @tap="confirmSelect"
          >
            {{ detailFabric?.listing_status === 0 ? '该面料已下架' : '确认选择' }}
          </button>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import {
  getFabricList,
  getFabricSeries,
  getFabricFilterOptions,
  getFabricDetail,
  toggleFabricFavorite,
  getRecentFabrics,
  getFavoriteFabrics,
  recordFabricUsage,
} from '@/api/fabric';
import type { FabricListItem, FabricListParams, FabricDetail, FabricSelectResult } from '@/types/fabric';
import { SupplyStatusMap } from '@/types/fabric';
import { getStorage, setStorage, STORAGE_KEYS } from '@/utils/storage';

// ═══════════════════════════════════════════
// 搜索 & 筛选状态
// ═══════════════════════════════════════════

/** 搜索关键词 */
const keyword = ref<string>('');
/** 当前系列筛选 */
const activeSeries = ref<string>('');
/** 当前材质筛选 */
const activeMaterial = ref<string>('');
/** 当前功能筛选 */
const activeFunction = ref<string>('');
/** 价格区间标签 */
const activePriceLabel = ref<string>('');
const priceMin = ref<number | undefined>(undefined);
const priceMax = ref<number | undefined>(undefined);

/** 系列选项 */
const seriesOptions = ref<string[]>([]);
/** 材质选项 */
const materialOptions = ref<string[]>([]);
/** 功能选项 */
const functionOptions = ref<string[]>([]);
/** 价格区间选项 */
const priceOptions = ref<Array<{ label: string; min?: number; max?: number }>>([
  { label: '¥0-50', min: 0, max: 50 },
  { label: '¥50-100', min: 50, max: 100 },
  { label: '¥100-200', min: 100, max: 200 },
  { label: '¥200+', min: 200, max: undefined },
]);

/** 是否有激活的筛选 */
const hasActiveFilter = computed<boolean>(() => {
  return (
    keyword.value.trim() !== '' ||
    activeSeries.value !== '' ||
    activeMaterial.value !== '' ||
    activeFunction.value !== '' ||
    activePriceLabel.value !== ''
  );
});

/** 搜索防抖定时器 */
let searchTimer: ReturnType<typeof setTimeout> | null = null;

// ═══════════════════════════════════════════
// 列表 & 分页状态
// ═══════════════════════════════════════════

/** 面料列表 */
const fabrics = ref<FabricListItem[]>([]);
/** 当前页 */
const currentPage = ref<number>(1);
/** 总数 */
const total = ref<number>(0);
/** 加载中 */
const loading = ref<boolean>(false);
/** 下拉刷新中 */
const refreshing = ref<boolean>(false);
/** 无更多数据 */
const noMore = computed<boolean>(() => fabrics.value.length >= total.value && total.value > 0);

/** 每页数量 */
const PAGE_SIZE = 20;

// ═══════════════════════════════════════════
// 最近使用 & 收藏
// ═══════════════════════════════════════════

/** 最近使用面料 */
const recentFabrics = ref<FabricListItem[]>([]);
/** 收藏面料 */
const favoriteFabrics = ref<FabricListItem[]>([]);
/** 收藏ID集合 */
const favoriteSet = ref<Set<string>>(new Set());
/** 收藏区折叠状态 */
const favoritesCollapsed = ref<boolean>(false);

/** 本地最近使用 key */
const RECENT_FABRIC_KEY = 'ss_recent_fabrics';
/** 最多保存数量 */
const MAX_RECENT = 20;

/**
 * 加载本地收藏列表
 */
function loadFavoriteIds(): void {
  const saved = getStorage<string[]>(STORAGE_KEYS.FAVORITE_FABRICS);
  if (saved) {
    favoriteSet.value = new Set(saved);
  }
}

/**
 * 加载最近使用面料（优先本地缓存，降级到 API）
 */
async function loadRecent(): Promise<void> {
  try {
    const data = await getRecentFabrics();
    recentFabrics.value = data.slice(0, 6);
  } catch {
    // API 失败时尝试本地缓存
    const cached = getStorage<FabricListItem[]>(RECENT_FABRIC_KEY);
    if (cached) {
      recentFabrics.value = cached.slice(0, 6);
    }
  }
}

/**
 * 加载收藏面料
 */
async function loadFavorites(): Promise<void> {
  try {
    const data = await getFavoriteFabrics(1, 10);
    favoriteFabrics.value = data.list;
    // 同步收藏集合
    data.list.forEach((f) => favoriteSet.value.add(f.fabric_no));
    setStorage(STORAGE_KEYS.FAVORITE_FABRICS, [...favoriteSet.value]);
  } catch {
    /* 收藏加载失败不阻塞主流程 */
  }
}

/**
 * 保存最近使用到本地
 */
function saveRecentLocal(fabric: FabricListItem): void {
  const cached = getStorage<FabricListItem[]>(RECENT_FABRIC_KEY) || [];
  const filtered = cached.filter((f) => f.fabric_no !== fabric.fabric_no);
  filtered.unshift(fabric);
  if (filtered.length > MAX_RECENT) filtered.length = MAX_RECENT;
  setStorage(RECENT_FABRIC_KEY, filtered);
}

// ═══════════════════════════════════════════
// 详情弹窗
// ═══════════════════════════════════════════

/** 详情弹窗可见 */
const detailVisible = ref<boolean>(false);
/** 当前查看详情的面料 */
const detailFabric = ref<FabricDetail | null>(null);
/** 详情图片列表 */
const detailImages = computed<string[]>(() => {
  if (!detailFabric.value) return [];
  const mainImg = detailFabric.value.main_image;
  const detailImgs = detailFabric.value.detail_images || [];
  return mainImg ? [mainImg, ...detailImgs] : detailImgs;
});

/**
 * 显示面料详情
 */
async function showDetail(fabric: FabricListItem): Promise<void> {
  try {
    const detail = await getFabricDetail(fabric.fabric_no);
    detailFabric.value = detail;
  } catch {
    // 降级：用列表数据填充
    detailFabric.value = { ...fabric, detail_images: null, description: '' };
  }
  detailVisible.value = true;
}

/**
 * 关闭详情弹窗
 */
function closeDetail(): void {
  detailVisible.value = false;
  detailFabric.value = null;
}

/**
 * 确认选择面料
 */
function confirmSelect(): void {
  if (!detailFabric.value || detailFabric.value.listing_status === 0) return;

  const fabric = detailFabric.value;

  // 记录最近使用
  saveRecentLocal(fabric);
  recordFabricUsage(fabric.fabric_no).catch(() => {});

  // 构造选择结果
  const result: FabricSelectResult = {
    fabric_no: fabric.fabric_no,
    name: fabric.name,
    series: fabric.series || '',
    price: fabric.price_per_sqm,
    image: fabric.main_image || '',
    id: fabric.id,
  };

  // 通过 eventChannel 传回 step2
  const pages = getCurrentPages();
  const currentPageInstance = pages[pages.length - 1];
  const eventChannel = (currentPageInstance as { getOpenerEventChannel?: () => { emit: (event: string, data: unknown) => void } }).getOpenerEventChannel?.();

  if (eventChannel) {
    eventChannel.emit('onFabricSelected', result);
  }

  // 同时通过 uni.$emit 传回（兼容方案）
  uni.$emit('fabric:selected', result);

  closeDetail();
  uni.navigateBack();
}

// ═══════════════════════════════════════════
// 数据获取
// ═══════════════════════════════════════════

/**
 * 加载筛选选项
 */
async function loadFilterOptions(): Promise<void> {
  // 先加载系列（兼容旧接口）
  try {
    const series = await getFabricSeries();
    seriesOptions.value = series;
  } catch {
    /* 使用默认空 */
  }

  // 尝试加载完整筛选选项（新接口）
  try {
    const options = await getFabricFilterOptions();
    if (options.series.length > 0) seriesOptions.value = options.series;
    if (options.materials.length > 0) materialOptions.value = options.materials;
    if (options.functions.length > 0) functionOptions.value = options.functions;
    if (options.price_ranges.length > 0) priceOptions.value = options.price_ranges;
  } catch {
    // 新接口不可用时使用默认值
    if (materialOptions.value.length === 0) {
      materialOptions.value = ['涤纶', '棉麻', '丝绒', '遮光布'];
    }
  }
}

/**
 * 获取面料列表
 * @param page 页码
 * @param append 是否追加（加载更多）
 */
async function fetchData(page: number, append = false): Promise<void> {
  if (loading.value) return;
  loading.value = true;

  try {
    const params: FabricListParams = {
      page,
      page_size: PAGE_SIZE,
    };

    if (keyword.value.trim()) {
      params.keyword = keyword.value.trim();
    }
    if (activeSeries.value) {
      params.series = activeSeries.value;
    }
    if (activeMaterial.value) {
      params.material = activeMaterial.value;
    }
    if (activeFunction.value) {
      params.function_tag = activeFunction.value;
    }
    if (priceMin.value !== undefined) {
      params.price_min = priceMin.value;
    }
    if (priceMax.value !== undefined) {
      params.price_max = priceMax.value;
    }

    const data = await getFabricList(params);
    total.value = data.total;
    currentPage.value = page;

    if (append) {
      fabrics.value.push(...data.list);
    } else {
      fabrics.value = data.list;
    }
  } catch {
    /* handled */
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

// ═══════════════════════════════════════════
// 搜索处理
// ═══════════════════════════════════════════

/**
 * 搜索输入防抖
 */
function onSearchInput(): void {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    doSearch();
  }, 300);
}

/**
 * 执行搜索
 */
function doSearch(): void {
  // 保存搜索历史
  if (keyword.value.trim()) {
    const history = getStorage<string[]>(STORAGE_KEYS.SEARCH_HISTORY) || [];
    const idx = history.indexOf(keyword.value.trim());
    if (idx > -1) history.splice(idx, 1);
    history.unshift(keyword.value.trim());
    if (history.length > 10) history.pop();
    setStorage(STORAGE_KEYS.SEARCH_HISTORY, history);
  }
  fetchData(1);
}

/**
 * 清除搜索
 */
function clearSearch(): void {
  keyword.value = '';
  fetchData(1);
}

/**
 * 下拉刷新
 */
async function handleRefresh(): Promise<void> {
  refreshing.value = true;
  await Promise.all([fetchData(1), loadRecent(), loadFavorites()]);
}

/**
 * 上拉加载更多
 */
function loadMore(): void {
  if (noMore.value || loading.value) return;
  fetchData(currentPage.value + 1, true);
}

// ═══════════════════════════════════════════
// 筛选处理
// ═══════════════════════════════════════════

function handleSeriesTap(s: string): void {
  activeSeries.value = activeSeries.value === s ? '' : s;
  fetchData(1);
}

function handleMaterialTap(m: string): void {
  activeMaterial.value = activeMaterial.value === m ? '' : m;
  fetchData(1);
}

function handleFunctionTap(f: string): void {
  activeFunction.value = activeFunction.value === f ? '' : f;
  fetchData(1);
}

function handlePriceTap(p: { label: string; min?: number; max?: number }): void {
  if (activePriceLabel.value === p.label) {
    activePriceLabel.value = '';
    priceMin.value = undefined;
    priceMax.value = undefined;
  } else {
    activePriceLabel.value = p.label;
    priceMin.value = p.min;
    priceMax.value = p.max;
  }
  fetchData(1);
}

/**
 * 清除所有筛选条件
 */
function clearAllFilters(): void {
  keyword.value = '';
  activeSeries.value = '';
  activeMaterial.value = '';
  activeFunction.value = '';
  activePriceLabel.value = '';
  priceMin.value = undefined;
  priceMax.value = undefined;
  fetchData(1);
}

// ═══════════════════════════════════════════
// 收藏操作
// ═══════════════════════════════════════════

/**
 * 收藏/取消收藏面料（乐观更新）
 */
async function handleFavorite(fabric: FabricListItem): Promise<void> {
  const isFav = favoriteSet.value.has(fabric.fabric_no);
  // 乐观更新
  if (isFav) {
    favoriteSet.value.delete(fabric.fabric_no);
  } else {
    favoriteSet.value.add(fabric.fabric_no);
  }
  favoriteSet.value = new Set(favoriteSet.value);
  setStorage(STORAGE_KEYS.FAVORITE_FABRICS, [...favoriteSet.value]);

  try {
    const result = await toggleFabricFavorite(fabric.fabric_no);
    // 根据服务端实际结果同步
    if (result.is_favorited) {
      favoriteSet.value.add(fabric.fabric_no);
    } else {
      favoriteSet.value.delete(fabric.fabric_no);
    }
    favoriteSet.value = new Set(favoriteSet.value);
    setStorage(STORAGE_KEYS.FAVORITE_FABRICS, [...favoriteSet.value]);
  } catch {
    // 回滚
    if (!isFav) {
      favoriteSet.value.delete(fabric.fabric_no);
    } else {
      favoriteSet.value.add(fabric.fabric_no);
    }
    favoriteSet.value = new Set(favoriteSet.value);
    setStorage(STORAGE_KEYS.FAVORITE_FABRICS, [...favoriteSet.value]);
  }
}

/**
 * 在详情弹窗中收藏/取消
 */
function handleFavoriteDetail(): void {
  if (!detailFabric.value) return;
  handleFavorite(detailFabric.value);
}

// ═══════════════════════════════════════════
// 辅助函数
// ═══════════════════════════════════════════

/**
 * 获取供货状态信息
 */
function supplyStatusInfo(status: number): { label: string; color: string; bg: string } | undefined {
  return SupplyStatusMap[status];
}

// ═══════════════════════════════════════════
// 页面目标索引（从 step2 传入）
// ═══════════════════════════════════════════

/** 目标索引 */
const targetIndex = ref<number>(-1);

// ═══════════════════════════════════════════
// 初始化
// ═══════════════════════════════════════════

onMounted(() => {
  // 获取页面参数
  const pages = getCurrentPages();
  const currentPageInstance = pages[pages.length - 1] as { options: { index?: string } };
  const options = currentPageInstance.options || {};
  if (options.index) {
    targetIndex.value = parseInt(options.index, 10);
  }

  // 并行加载
  loadFavoriteIds();
  loadFilterOptions();
  loadRecent();
  loadFavorites();
  fetchData(1);
});
</script>

<style lang="scss" scoped>
.fabric-selector {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background-color: $color-neutral-50;
}

// ── 搜索栏 ──
.search-bar {
  padding: $spacing-md;
  background-color: $color-neutral-0;

  &__inner {
    display: flex;
    align-items: center;
    background-color: $color-neutral-100;
    border-radius: $radius-full;
    padding: 0 $spacing-lg;
    height: 72rpx;
  }
  &__icon {
    font-size: 28rpx;
    margin-right: $spacing-sm;
  }
  &__input {
    flex: 1;
    font-size: $font-size-sm;
    color: $color-neutral-900;
    height: 72rpx;
  }
  &__clear {
    width: 40rpx;
    height: 40rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: $spacing-sm;
  }
  &__clear-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32rpx;
    height: 32rpx;
    border-radius: $radius-full;
    background-color: $color-neutral-300;
    color: $color-neutral-0;
    font-size: 20rpx;
  }
}

// ── 筛选标签栏 ──
.filter-bar {
  white-space: nowrap;
  background-color: $color-neutral-0;
  border-top: 2rpx solid $color-neutral-100;
  padding: $spacing-sm $spacing-md;

  &__inner {
    display: inline-flex;
    align-items: center;
    gap: $spacing-sm;
  }
}

.filter-tag {
  display: inline-flex;
  align-items: center;
  padding: $spacing-xs $spacing-lg;
  font-size: $font-size-sm;
  color: $color-neutral-600;
  background-color: $color-neutral-100;
  border-radius: $radius-full;
  transition: all 0.2s;
  flex-shrink: 0;

  &.active {
    background-color: $color-primary-500;
    color: $color-neutral-0;
  }
}

.filter-divider {
  width: 2rpx;
  height: 32rpx;
  background-color: $color-neutral-200;
  margin: 0 $spacing-xs;
  flex-shrink: 0;
}

// ── 清除筛选 ──
.clear-filter {
  padding: $spacing-sm $spacing-md;
  text-align: center;
  background-color: $color-accent-50;
  border-bottom: 2rpx solid $color-accent-100;

  &__text {
    font-size: $font-size-sm;
    color: $color-primary-500;
  }
}

// ── 主内容滚动区 ──
.main-scroll {
  flex: 1;
  overflow: hidden;
}

// ── 区块（最近使用 / 收藏） ──
.section {
  padding: $spacing-md;
  background-color: $color-neutral-0;
  margin-bottom: $spacing-sm;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: $spacing-md;
  }
  &__title {
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
  }
  &__toggle {
    font-size: $font-size-sm;
    color: $color-primary-500;
  }
}

// ── 最近使用 / 收藏 水平滚动 ──
.recent-scroll {
  white-space: nowrap;

  &__inner {
    display: inline-flex;
    gap: $spacing-md;
  }
}

.recent-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 140rpx;
  flex-shrink: 0;

  &.disabled {
    opacity: 0.5;
  }

  &__img {
    width: 140rpx;
    height: 140rpx;
    border-radius: $radius-md;
    border: 2rpx solid $color-neutral-200;
  }
  &__no {
    font-size: $font-size-xs;
    color: $color-neutral-600;
    font-family: $font-family-mono;
    margin-top: $spacing-xs;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 140rpx;
    text-align: center;
  }
}

// ── 面料网格（双列卡片） ──
.fabric-grid {
  display: flex;
  flex-wrap: wrap;
  padding: $spacing-md;
  gap: $spacing-md;
}

.fabric-card {
  width: calc(50% - #{$spacing-md} / 2);
  background-color: $color-neutral-0;
  border-radius: $radius-lg;
  overflow: hidden;
  box-shadow: $shadow-1;
  transition: all 0.2s;

  &.disabled {
    opacity: 0.55;
  }

  &:active {
    transform: scale(0.98);
  }

  &__image-wrap {
    position: relative;
    width: 100%;
    height: 300rpx;
    overflow: hidden;
  }
  &__image {
    width: 100%;
    height: 100%;
  }
  &__offline {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;

    &-text {
      color: $color-neutral-0;
      font-size: $font-size-sm;
      font-weight: $font-weight-medium;
      background-color: rgba(0, 0, 0, 0.3);
      padding: 4rpx 16rpx;
      border-radius: $radius-sm;
    }
  }
  &__fav {
    position: absolute;
    top: $spacing-sm;
    right: $spacing-sm;
    width: 52rpx;
    height: 52rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(255, 255, 255, 0.85);
    border-radius: $radius-full;
    box-shadow: $shadow-1;
  }
  &__fav-icon {
    font-size: 32rpx;
    color: $color-neutral-400;

    &.favorited {
      color: $color-accent-500;
    }
  }

  &__info {
    padding: $spacing-sm $spacing-md $spacing-md;
  }
  &__no {
    font-size: $font-size-sm;
    font-weight: $font-weight-semibold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  &__name {
    font-size: $font-size-xs;
    color: $color-neutral-500;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 4rpx;
  }
  &__color-row {
    display: flex;
    align-items: center;
    margin-top: 6rpx;
  }
  &__color-dot {
    width: 20rpx;
    height: 20rpx;
    border-radius: $radius-full;
    border: 2rpx solid $color-neutral-200;
    margin-right: $spacing-xs;
    flex-shrink: 0;
  }
  &__color-name {
    font-size: $font-size-xs;
    color: $color-neutral-500;
  }
  &__price {
    font-size: $font-size-base;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    display: block;
    margin-top: 6rpx;
    font-family: $font-family-mono;
  }
  &__status {
    display: inline-flex;
    align-items: center;
    padding: 2rpx 12rpx;
    border-radius: $radius-sm;
    margin-top: 6rpx;

    &-text {
      font-size: 20rpx;
      font-weight: $font-weight-medium;
    }
  }
}

// ── 加载 / 空 / 结束状态 ──
.loading-state {
  text-align: center;
  padding: $spacing-xl;

  &__text {
    font-size: $font-size-sm;
    color: $color-neutral-400;
  }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: $spacing-2xl;

  &__icon {
    font-size: 80rpx;
    margin-bottom: $spacing-md;
  }
  &__text {
    font-size: $font-size-base;
    color: $color-neutral-500;
    margin-bottom: $spacing-md;
  }
  &__action {
    padding: $spacing-sm $spacing-lg;
    background-color: $color-primary-50;
    border-radius: $radius-md;

    &-text {
      font-size: $font-size-sm;
      color: $color-primary-500;
    }
  }
}

.no-more-state {
  text-align: center;
  padding: $spacing-lg;

  &__text {
    font-size: $font-size-xs;
    color: $color-neutral-400;
  }
}

.safe-area-placeholder {
  height: calc(#{$spacing-xl} + env(safe-area-inset-bottom));
}

// ═══════════════════════════════════════════
// 面料详情弹窗（底部抽屉）
// ═══════════════════════════════════════════
.detail-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: flex-end;
  z-index: 999;

  &__content {
    width: 100%;
    max-height: 85vh;
    background-color: $color-neutral-0;
    border-radius: $radius-2xl $radius-2xl 0 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  // 大图轮播
  &__gallery {
    width: 100%;
    height: 480rpx;
    flex-shrink: 0;
    background-color: $color-neutral-100;
  }
  &__swiper {
    width: 100%;
    height: 100%;
  }
  &__img {
    width: 100%;
    height: 100%;
  }

  // 信息滚动区
  &__info-scroll {
    flex: 1;
    max-height: 50vh;
    overflow: hidden;
  }
  &__info {
    padding: $spacing-lg;
  }
  &__header {
    margin-bottom: $spacing-sm;
  }
  &__no {
    font-size: $font-size-sm;
    color: $color-neutral-500;
    font-family: $font-family-mono;
    display: block;
  }
  &__name {
    font-size: $font-size-lg;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    display: block;
    margin-top: 4rpx;
  }
  &__price {
    font-size: $font-size-2xl;
    font-weight: $font-weight-bold;
    color: $color-neutral-900;
    font-family: $font-family-mono;
    display: block;
    margin-bottom: $spacing-lg;
  }

  // 属性列表
  &__attrs {
    background-color: $color-neutral-50;
    border-radius: $radius-md;
    padding: $spacing-md;
  }
  &__attr {
    display: flex;
    align-items: center;
    padding: $spacing-sm 0;
    border-bottom: 2rpx solid $color-neutral-100;

    &:last-child {
      border-bottom: none;
    }

    &-label {
      width: 140rpx;
      font-size: $font-size-sm;
      color: $color-neutral-500;
      flex-shrink: 0;
    }
    &-value {
      font-size: $font-size-sm;
      color: $color-neutral-900;
      flex: 1;
    }
    &-color {
      display: flex;
      align-items: center;
      flex: 1;
    }
  }
  &__color-dot {
    width: 28rpx;
    height: 28rpx;
    border-radius: $radius-full;
    border: 2rpx solid $color-neutral-200;
    margin-right: $spacing-sm;
    flex-shrink: 0;
  }
  &__tags {
    display: flex;
    flex-wrap: wrap;
    gap: $spacing-sm;
    flex: 1;
  }
  &__tag {
    font-size: $font-size-xs;
    color: $color-primary-500;
    background-color: $color-primary-50;
    padding: 4rpx 16rpx;
    border-radius: $radius-sm;
  }
  &__status {
    display: inline-flex;
    padding: 4rpx 16rpx;
    border-radius: $radius-sm;
    font-size: $font-size-xs;
    font-weight: $font-weight-medium;
  }

  // 描述
  &__desc {
    margin-top: $spacing-md;
    padding: $spacing-md;
    background-color: $color-neutral-50;
    border-radius: $radius-md;

    &-text {
      font-size: $font-size-sm;
      color: $color-neutral-600;
      line-height: 1.6;
    }
  }

  // 底部操作栏
  &__footer {
    display: flex;
    align-items: center;
    padding: $spacing-md $spacing-lg;
    border-top: 2rpx solid $color-neutral-100;
    background-color: $color-neutral-0;
  }
  &__fav-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 $spacing-lg;
  }
  &__fav-icon {
    font-size: 40rpx;
    color: $color-neutral-300;

    &.favorited {
      color: $color-accent-500;
    }
  }
  &__fav-label {
    font-size: $font-size-xs;
    color: $color-neutral-500;
    margin-top: 2rpx;
  }
  &__confirm-btn {
    flex: 1;
    height: 88rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: $color-primary-500;
    color: $color-neutral-0;
    font-size: $font-size-base;
    font-weight: $font-weight-semibold;
    border-radius: $radius-md;
    border: none;
    margin-left: $spacing-lg;

    &[disabled] {
      background-color: $color-neutral-200;
      color: $color-neutral-400;
    }
  }
}
</style>
