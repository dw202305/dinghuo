import { get, post } from './index';
import type { PaginatedData } from '@/types/api';

/** 发票申请项 */
export interface InvoiceListItem {
  request_id: number;
  request_no: string;
  order_no: string;
  invoice_type: number;
  invoice_type_text: string;
  title: string;
  invoice_amount: string;
  status: number;
  status_text: string;
  created_at: string;
}

/** 可开票订单项 */
export interface InvoiceableOrder {
  order_id: number;
  order_no: string;
  total_amount: string;
  invoiced_amount: string;
  uninvoiced_amount: string;
  created_at: string;
}

/** 创建发票申请参数 */
export interface CreateInvoiceParams {
  order_ids: number[];
  invoice_type: number;
  title: string;
  tax_no: string;
  invoice_amount: number;
  bank_name?: string;
  bank_account?: string;
  company_address?: string;
  company_phone?: string;
  delivery_method?: number;
  email?: string;
  delivery_address?: string;
}

/** 获取发票申请列表 */
export function getInvoiceList(params: { status?: number; page?: number; page_size?: number }) {
  return get<PaginatedData<InvoiceListItem>>('/store/invoice/list', params as Record<string, unknown>);
}

// 后端需补路由：后端旧版没有 /store/invoice/uninvoiced-orders
/** 获取可开票订单列表 */
export function getInvoiceableOrders() {
  return get<InvoiceableOrder[]>('/store/invoice/uninvoiced-orders');
}

/** 创建发票申请 */
export function createInvoice(data: CreateInvoiceParams) {
  return post<{ request_id: number; request_no: string }>('/store/invoice/create', data as unknown as Record<string, unknown>);
}
