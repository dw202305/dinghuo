import { get } from './index';
import type { WallControllerProduct } from '@/types/product';

/**
 * 获取墙面控制器商品列表（含有效价格）
 * 对应后端路由: GET /api/v1/store/product/wall-controller/list
 * @returns 墙面控制器商品数组
 */
export function getWallControllerProducts(): Promise<WallControllerProduct[]> {
  return get<WallControllerProduct[]>('/store/product/wall-controller/list');
}

/** 选装配件项（对应 lj_accessory 表字段） */
export interface AccessoryItem {
  id: number;
  /** 配件SKU */
  sku: string;
  /** 配件名称 */
  name: string;
  /** 配置组：power/remote/wall_control */
  config_group: string;
  /** 类型：1标准 2升级 3新增 */
  option_type: number;
  /** 加价或补差价（分） */
  surcharge_cent: number;
  [key: string]: unknown;
}

/** 套件信息 */
export interface KitInfo {
  kit_sku: string | null;
  kit_name: string | null;
  /** 套件价格（元，字符串） */
  kit_price: string;
  /** 套件价格（分），部分版本返回 */
  price_cent?: number;
  [key: string]: unknown;
}

/** 套件库存项 */
export interface KitInventoryItem {
  kit_sku: string;
  kit_name?: string;
  /** 可用库存 */
  available: number;
  [key: string]: unknown;
}

/**
 * 获取选装配件列表（公开接口）
 * 对应后端路由: GET /api/v1/accessories
 */
export function getAccessories(): Promise<{ list: AccessoryItem[] }> {
  return get<{ list: AccessoryItem[] }>('/accessories');
}

/**
 * 获取套件信息（公开接口）
 * 对应后端路由: GET /api/v1/kit-info
 */
export function getKitInfo(): Promise<KitInfo> {
  return get<KitInfo>('/kit-info');
}

/**
 * 获取门店套件库存（需认证）
 * 对应后端路由: GET /api/v1/inventory/kit
 */
export function getInventoryKit(): Promise<{ list: KitInventoryItem[] }> {
  return get<{ list: KitInventoryItem[] }>('/inventory/kit');
}
