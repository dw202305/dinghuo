/**
 * 面料 API 接口
 * @module api/fabric
 */

import { get, post } from './index';
import type { PaginatedData } from '@/types/api';
import type {
  FabricListItem,
  FabricDetail,
  FabricListParams,
  FabricFilterOptions,
} from '@/types/fabric';

/**
 * 获取面料列表（支持搜索/筛选/分页）
 * @param params 查询参数
 * @returns 分页面料列表
 */
export function getFabricList(params: FabricListParams) {
  return get<PaginatedData<FabricListItem>>(
    '/store/fabric/list',
    params as unknown as Record<string, unknown>
  );
}

/**
 * 获取面料详情
 * @param fabricNo 面料编号
 * @returns 面料详情
 */
export function getFabricDetail(fabricNo: string) {
  return get<FabricDetail>('/store/fabric/detail', { fabric_no: fabricNo } as Record<string, unknown>);
}

/**
 * 收藏/取消收藏面料（toggle）
 * @param fabricNo 面料编号
 * @returns 收藏状态
 */
export function toggleFabricFavorite(fabricNo: string) {
  return post<{ is_favorited: boolean }>(
    '/store/fabric/favorite',
    { fabric_no: fabricNo } as Record<string, unknown>
  );
}

/**
 * 获取收藏面料列表
 * @param page 页码
 * @param pageSize 每页数量
 * @returns 分页收藏列表
 */
export function getFavoriteFabrics(page = 1, pageSize = 20) {
  return get<PaginatedData<FabricListItem>>(
    '/store/fabric/favorites',
    { page, page_size: pageSize } as Record<string, unknown>
  );
}

/**
 * 获取面料系列列表（筛选用）
 * 对应后端路由: GET /api/v1/store/fabric/series
 * @returns 系列名称数组
 */
export function getFabricSeries() {
  return get<string[]>('/store/fabric/series');
}

/**
 * 获取筛选条件选项（系列/材质/功能/价格区间）
 * 对应后端路由: GET /api/v1/store/fabric/filter-options
 * @returns 筛选选项
 */
export function getFabricFilterOptions() {
  return get<FabricFilterOptions>('/store/fabric/filter-options');
}

/**
 * 获取最近使用面料
 * @returns 最近使用的面料列表（最多20条）
 */
export function getRecentFabrics() {
  return get<FabricListItem[]>('/store/fabric/recent');
}

// 后端需补路由：后端旧版仅有 GET /store/fabric/recent，无 POST
/**
 * 记录面料使用（选择后面料时调用，写入最近使用）
 * @param fabricNo 面料编号
 */
export function recordFabricUsage(fabricNo: string) {
  return post<void>(
    '/store/fabric/recent',
    { fabric_no: fabricNo } as Record<string, unknown>
  );
}
