import { get, post } from './index';
import type { PaginatedData } from '@/types/api';

/** 售后申请项 */
export interface AfterSaleListItem {
  after_sale_id: number;
  after_sale_no: string;
  order_no: string;
  item_no: string;
  problem_type: number;
  problem_type_text: string;
  problem_desc: string;
  status: number;
  status_text: string;
  created_at: string;
}

/** 售后详情 */
export interface AfterSaleDetail extends AfterSaleListItem {
  images: string[];
  videos: string[];
  install_date: string;
  affect_usage: number;
  contact_name: string;
  contact_phone: string;
  expected_solution: string;
  diagnosis: string | null;
  responsibility: string | null;
  solution: string | null;
  handler_name: string | null;
}

/** 可选订单项（售后选择用） */
export interface AfterSaleOrderItem {
  order_id: number;
  order_no: string;
  item_count: number;
  total_amount: string;
  created_at: string;
}

/** 创建售后申请参数 */
export interface CreateAfterSaleParams {
  order_id: number;
  item_id?: number;
  problem_type: number;
  problem_desc: string;
  images?: string[];
  videos?: string[];
  install_date?: string;
  affect_usage: number;
  contact_name: string;
  contact_phone: string;
  expected_solution?: string;
}

/** 获取售后列表 */
export function getAfterSaleList(params: { status?: number; page?: number; page_size?: number }) {
  return get<PaginatedData<AfterSaleListItem>>('/store/after-sale/list', params as Record<string, unknown>);
}

/** 获取售后详情 */
export function getAfterSaleDetail(afterSaleId: number) {
  return get<AfterSaleDetail>('/store/after-sale/detail', { after_sale_id: afterSaleId } as Record<string, unknown>);
}

/** 获取可售后订单列表 */
export function getAfterSaleOrders(keyword?: string) {
  const params: Record<string, unknown> = { page: 1, page_size: 50 };
  if (keyword) params.keyword = keyword;
  return get<PaginatedData<AfterSaleOrderItem>>('/store/order/list', { ...params, order_status: 14 } as Record<string, unknown>);
}

/** 创建售后申请 */
export function createAfterSale(data: CreateAfterSaleParams) {
  return post<{ after_sale_id: number; after_sale_no: string }>('/store/after-sale/create', data as unknown as Record<string, unknown>);
}
