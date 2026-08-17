import { get, post, put, del } from './index';
import type { AddressItem, AddressFormData } from '@/types/address';

/**
 * 获取地址列表
 * @returns 当前门店所有收货地址
 */
export function getAddressList(): Promise<AddressItem[]> {
  return get<AddressItem[]>('/store/address/list');
}

/**
 * 获取地址详情
 * 对应后端路由: GET /api/v1/store/address/detail
 * @param id 地址ID
 */
export function getAddressDetail(id: number): Promise<AddressItem> {
  return get<AddressItem>('/store/address/detail', { id } as Record<string, unknown>);
}

/**
 * 新增地址
 * @param params 地址表单数据
 */
export function createAddress(params: AddressFormData): Promise<AddressItem> {
  return post<AddressItem>(
    '/store/address/create',
    params as unknown as Record<string, unknown>
  );
}

/**
 * 编辑地址
 * @param id 地址ID
 * @param params 地址表单数据
 */
export function updateAddress(id: number, params: AddressFormData): Promise<AddressItem> {
  return put<AddressItem>(
    '/store/address/update',
    { id, ...params } as unknown as Record<string, unknown>
  );
}

/**
 * 删除地址
 * @param id 地址ID
 */
export function deleteAddress(id: number): Promise<void> {
  return del<void>('/store/address/delete', { id } as Record<string, unknown>);
}

/**
 * 设为默认地址
 * @param id 地址ID
 */
export function setDefaultAddress(id: number): Promise<void> {
  return put<void>(
    '/store/address/set-default',
    { id } as unknown as Record<string, unknown>
  );
}
