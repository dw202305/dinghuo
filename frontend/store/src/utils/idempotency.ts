/** 幂等键支持的前缀 */
type IdempotencyPrefix = 'order' | 'payment' | 'balance' | 'stock' | 'refund' | 'recharge';

/**
 * 生成幂等键，格式：{prefix}_{timestamp}_{random}
 * 用于防止重复提交，确保请求幂等性
 * @param prefix - 业务前缀，限定为 order / payment / balance / stock / refund / recharge
 * @returns 全局唯一的幂等键字符串
 */
export function generateIdempotencyKey(prefix: IdempotencyPrefix): string {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(2, 10);
  return `${prefix}_${timestamp}_${random}`;
}
