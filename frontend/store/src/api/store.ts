import { get, post } from './index';
import type { StoreAddress } from '@/types/user';

/** 工作台数据 */
export interface DashboardData {
  store_info: {
    store_id: number;
    store_no: string;
    store_name: string;
    customer_level: number;
    customer_level_text: string;
    kit_price: number;
    primary_contact: { name: string; phone: string };
  };
  inventory: { kit_available: number; kit_locked: number };
  order_stats: {
    pending_payment: number;
    pending_confirm: number;
    in_production: number;
    pending_receive: number;
    completed: number;
    after_sale: number;
  };
  notices: { type: string; message: string; link: string }[];
}

/** 获取工作台数据 */
export function getDashboard() {
  return get<DashboardData>('/store/home/dashboard');
}

/** 获取收货地址列表 */
export function getAddressList() {
  return get<StoreAddress[]>('/store/address/list');
}

/** 新增收货地址 */
export function createAddress(data: Omit<StoreAddress, 'id' | 'store_id'>) {
  return post<{ id: number }>('/store/address/create', data as unknown as Record<string, unknown>);
}

/** 更新收货地址 */
export function updateAddress(id: number, data: Partial<StoreAddress>) {
  return post<null>('/store/address/update', { id, ...data } as unknown as Record<string, unknown>);
}

/** 删除收货地址 */
export function deleteAddress(id: number) {
  return post<null>('/store/address/delete', { id } as Record<string, unknown>);
}
