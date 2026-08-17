import { get, post } from './index';
import { generateIdempotencyKey } from '@/utils/idempotency';
import type {
  BalanceInfo,
  TransactionListParams,
  TransactionListResult,
  RechargeParams,
  RechargeResult,
  RechargeListParams,
  RechargeRecord,
} from '@/types/balance';
import type { PaginatedData } from '@/types/api';

/**
 * 获取账户余额
 * 对应后端路由: GET /api/v1/balance-accounts/:id（me 代表当前用户）
 * @returns 余额信息，包含可用余额和冻结金额
 */
export function getAccountBalance() {
  return get<BalanceInfo>('/balance-accounts/me');
}

/**
 * 获取余额流水列表
 * 对应后端路由: GET /api/v1/balance-accounts/:id/transactions
 * @param params - 查询参数（分页、类型、时间范围）
 * @returns 流水列表及汇总数据
 */
export function getBalanceTransactions(params: TransactionListParams) {
  return get<TransactionListResult>('/balance-accounts/me/transactions', params as unknown as Record<string, unknown>);
}

/**
 * 获取余额流水详情
 * 对应后端路由: GET /api/v1/balance-accounts/:id/transactions（带具体流水ID）
 * @param id - 流水记录ID
 * @returns 流水详情
 */
export function getTransactionDetail(id: number) {
  return get<TransactionListResult>('/balance-accounts/me/transactions/' + id);
}

/**
 * 发起储值充值
 * 对应后端路由: POST /api/v1/balance-accounts/:id/recharge
 * @param params - 充值参数（金额、渠道、幂等键）
 * @returns 充值单号及第三方支付参数
 */
export function createRechargeOrder(params: RechargeParams) {
  return post<RechargeResult>(
    '/balance-accounts/me/recharge',
    params as unknown as Record<string, unknown>,
    { idempotencyKey: params.idempotency_key }
  );
}

/**
 * 发起储值充值（自动生成幂等键）
 * @param amountCent - 充值金额，单位分
 * @param channel - 支付渠道
 * @returns 充值单号及第三方支付参数
 */
export function rechargeWithAutoKey(amountCent: number, channel: 'wechat' | 'alipay') {
  const params: RechargeParams = {
    amount_cent: amountCent,
    payment_channel: channel,
    idempotency_key: generateIdempotencyKey('recharge'),
  };
  return createRechargeOrder(params);
}

/**
 * 获取充值记录
 * 对应后端路由: GET /api/v1/balance-accounts/:id/transactions（需后端补充 recharge-records 子路由）
 * @param params - 查询参数（分页、状态筛选）
 * @returns 充值记录分页列表
 */
export function getRechargeRecords(params: RechargeListParams) {
  return get<PaginatedData<RechargeRecord>>('/balance-accounts/me/recharge-records', params as unknown as Record<string, unknown>);
}
