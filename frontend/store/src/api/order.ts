import { get, post, put, del } from './index';
import { generateIdempotencyKey } from '@/utils/idempotency';
import type { PaginatedData } from '@/types/api';
import type {
  OrderListItem, OrderDetail, CreateOrderParams, CreateOrderResult,
  AddOrderItemParams, AddOrderItemResult, OrderPreviewData,
  SubmitOrderParams, OrderListParams,
  BalancePaymentResult, PreAuditResult
} from '@/types/order';

/** 获取订单列表 */
export function getOrderList(params: OrderListParams) {
  return get<PaginatedData<OrderListItem>>('/store/order/list', params as unknown as Record<string, unknown>);
}

/** 获取订单详情 */
export function getOrderDetail(orderId: number) {
  return get<OrderDetail>('/store/order/detail', { order_id: orderId } as Record<string, unknown>);
}

/** 创建订单（草稿）— 携带幂等键防止重复创建 */
export function createOrder(data: CreateOrderParams) {
  return post<CreateOrderResult>(
    '/store/order/create',
    data as unknown as Record<string, unknown>,
    { idempotencyKey: generateIdempotencyKey('order') }
  );
}

/** 更新订单基本信息 */
export function updateOrder(orderId: number, data: Partial<CreateOrderParams>) {
  return put<null>('/store/order/update', { order_id: orderId, ...data } as unknown as Record<string, unknown>);
}

/** 新增窗帘明细 */
export function addOrderItem(data: AddOrderItemParams) {
  return post<AddOrderItemResult>('/store/order/item/add', data as unknown as Record<string, unknown>);
}

/** 更新窗帘明细 */
export function updateOrderItem(itemId: number, data: Partial<AddOrderItemParams>) {
  return put<AddOrderItemResult>('/store/order/item/update', { item_id: itemId, ...data } as unknown as Record<string, unknown>);
}

/** 删除窗帘明细 */
export function deleteOrderItem(itemId: number) {
  return del<null>('/store/order/item/delete', { item_id: itemId } as Record<string, unknown>);
}

/** 复制窗帘明细 */
export function copyOrderItem(sourceItemId: number, copyDimensions = true) {
  return post<{ item_id: number; item_no: string; item_total: string }>(
    '/store/order/item/copy',
    { source_item_id: sourceItemId, copy_dimensions: copyDimensions ? 1 : 0 } as Record<string, unknown>
  );
}

/** 获取订单预览 */
export function getOrderPreview(orderId: number) {
  return get<OrderPreviewData>('/store/order/preview', { order_id: orderId } as Record<string, unknown>);
}

/** 提交订单 */
export function submitOrder(data: SubmitOrderParams) {
  return post<{ order_id: number; order_no: string; order_status: number; order_status_text: string; total_amount: string; price_locked_until: string }>(
    '/store/order/submit',
    data as unknown as Record<string, unknown>
  );
}

/** 取消订单 */
export function cancelOrder(orderId: number, reason: string) {
  return put<null>('/store/order/cancel', { order_id: orderId, cancel_reason: reason } as unknown as Record<string, unknown>);
}

/** 确认签收（对应后端 POST /api/v1/store/order/confirm-receive） */
export function confirmReceipt(orderId: number) {
  return post<null>('/store/order/confirm-receive', { order_id: orderId } as unknown as Record<string, unknown>);
}

/** 删除订单 */
export function deleteOrder(orderId: number) {
  return del<null>('/store/order/delete', { order_id: orderId } as Record<string, unknown>);
}

/** 创建支付 */
export function createPayment(orderId: number, payChannel: number, payMethod: string) {
  return post<PaymentCreateResult>('/store/payment/create', {
    order_id: orderId,
    pay_channel: payChannel,
    pay_method: payMethod,
  } as unknown as Record<string, unknown>);
}

/** 查询支付状态 */
export function getPaymentStatus(orderId: number) {
  return get<PaymentStatusResult>('/store/payment/status', { order_id: orderId } as Record<string, unknown>);
}

/** 支付创建结果 */
export interface PaymentCreateResult {
  payment_no: string;
  pay_amount: string;
  pay_channel: number;
  pay_channel_text: string;
  wechat_params?: {
    timeStamp: string;
    nonceStr: string;
    package: string;
    signType: string;
    paySign: string;
  };
  alipay_params?: {
    order_string: string;
  };
  expire_seconds: number;
}

/** 支付状态查询结果 */
export interface PaymentStatusResult {
  order_id: number;
  order_no: string;
  payment_status: number;
  payment_status_text: string;
  paid_amount: string;
  paid_at: string;
  payment_no: string;
  pay_channel: number;
  pay_channel_text: string;
  order_status: number;
  order_status_text: string;
}

/** 余额支付 */
export function payByBalance(orderNo: string, idempotencyKey: string) {
  return post<BalancePaymentResult>('/store/payment/balance-pay', {
    order_no: orderNo,
    idempotency_key: idempotencyKey
  } as unknown as Record<string, unknown>);
}

/** 申请支付前预审（提交订单进行预审核） */
export function requestPreAudit(orderNo: string) {
  return post<PreAuditResult>('/store/order/submit', { order_no: orderNo } as unknown as Record<string, unknown>);
}
