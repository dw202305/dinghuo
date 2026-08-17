/**
 * 面料相关类型定义
 * @module types/fabric
 */

/** 面料列表项（列表中展示） */
export interface FabricListItem {
  /** 面料ID */
  id: number;
  /** 世尚面料编号 */
  fabric_no: string;
  /** 面料系列 */
  series: string | null;
  /** 面料名称 */
  name: string;
  /** 材质 */
  material: string | null;
  /** 颜色名称 */
  color_name: string | null;
  /** 色号（十六进制） */
  color_code: string | null;
  /** 单价（元/㎡） */
  price_per_sqm: string;
  /** 主图URL */
  main_image: string | null;
  /** 库存状态：1-充足 2-紧张 3-缺货 */
  stock_status: number;
  /** 上下架状态：0-已下架 1-已上架 */
  listing_status: number;
  /** 是否可订货：0-不可 1-可以 */
  orderable: number;
  /** 纹理标签 */
  texture_tags: string[] | null;
  /** 功能标签 */
  function_tags: string[] | null;
  /** 是否已收藏 */
  is_favorited?: boolean;
}

/** 面料详情 */
export interface FabricDetail extends FabricListItem {
  /** 详情图片 */
  detail_images: string[] | null;
  /** 面料幅宽（米） */
  fabric_width: string | null;
  /** 最小计费面积（㎡） */
  min_billing_area: string | null;
  /** 损耗系数 */
  loss_coefficient: string;
  /** 排序权重 */
  sort_weight: number;
  /** 生效日期 */
  effective_date: string | null;
  /** 价格版本 */
  price_version: number;
  /** 面料描述 */
  description?: string;
  /** 创建时间 */
  created_at?: string;
}

/** 面料列表查询参数 */
export interface FabricListParams {
  /** 搜索关键词：编号/名称/色号 */
  keyword?: string;
  /** 系列筛选 */
  series?: string;
  /** 材质筛选 */
  material?: string;
  /** 颜色筛选 */
  color?: string;
  /** 功能标签筛选 */
  function_tag?: string;
  /** 最低价（元/㎡） */
  price_min?: number;
  /** 最高价（元/㎡） */
  price_max?: number;
  /** 仅查可订货面料 */
  orderable_only?: boolean;
  /** 排序方式 */
  sort?: 'default' | 'price_asc' | 'price_desc' | 'popular';
  /** 页码 */
  page?: number;
  /** 每页数量 */
  page_size?: number;
}

/** 面料列表结果（扩展分页信息） */
export interface FabricListResult {
  /** 面料列表 */
  list: FabricListItem[];
  /** 当前页码 */
  page: number;
  /** 每页数量 */
  page_size: number;
  /** 总数量 */
  total: number;
}

/** 筛选选项（从 API 获取） */
export interface FabricFilterOptions {
  /** 系列列表 */
  series: string[];
  /** 材质列表 */
  materials: string[];
  /** 功能标签列表 */
  functions: string[];
  /** 价格区间选项 */
  price_ranges: Array<{ label: string; min?: number; max?: number }>;
}

/** 供货状态映射 */
export const SupplyStatusMap: Record<number, { label: string; color: string; bg: string }> = {
  1: { label: '充足', color: '#059669', bg: '#ECFDF5' },
  2: { label: '紧张', color: '#D97706', bg: '#FFFBEB' },
  3: { label: '缺货', color: '#DC2626', bg: '#FEF2F2' },
};

/** 面料选择结果（用于页面间传递） */
export interface FabricSelectResult {
  /** 面料编号 */
  fabric_no: string;
  /** 面料名称 */
  name: string;
  /** 面料系列 */
  series: string;
  /** 单价（元/㎡） */
  price: string;
  /** 主图URL */
  image: string;
  /** 面料ID */
  id?: number;
}
