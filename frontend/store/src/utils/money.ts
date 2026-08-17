import Decimal from 'decimal.js';

/**
 * 分转元，返回格式化的元字符串（带2位小数）
 * @param fen - 金额，单位为分
 * @returns 元字符串，保留2位小数
 */
export function fenToYuan(fen: number | string): string {
  const d = new Decimal(fen);
  return d.dividedBy(100).toFixed(2);
}

/**
 * 元转分，返回整数
 * @param yuan - 金额，单位为元
 * @returns 整数分值
 */
export function yuanToFen(yuan: number | string): number {
  const d = new Decimal(yuan);
  return d.times(100).round().toNumber();
}

/**
 * 计算面积：输入 cm，返回 m² 字符串（4位小数）
 * @param widthCm - 宽度，单位厘米
 * @param heightCm - 高度，单位厘米
 * @returns 面积，单位平方米，保留4位小数
 */
export function calcArea(widthCm: number | string, heightCm: number | string): string {
  const w = new Decimal(widthCm).dividedBy(100);
  const h = new Decimal(heightCm).dividedBy(100);
  return w.times(h).toFixed(4);
}

/**
 * 计算价格：面积(m²) × 单价(元/m²)，返回分
 * @param areaM2 - 面积，单位平方米
 * @param pricePerM2 - 单价，元/平方米
 * @returns 金额，单位为分
 */
export function calcPrice(areaM2: string, pricePerM2: number): number {
  const area = new Decimal(areaM2);
  const price = new Decimal(pricePerM2);
  return area.times(price).times(100).round().toNumber();
}

/**
 * 格式化金额：分 → "¥1,234.56" 格式
 * @param fen - 金额，单位为分
 * @returns 带货币符号和千分位的格式化字符串
 */
export function formatMoney(fen: number): string {
  const yuan = new Decimal(fen).dividedBy(100).toFixed(2);
  const parts = yuan.split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return `¥${parts.join('.')}`;
}

/**
 * 判断可用余额是否足够支付指定金额
 * @param availableCent - 可用余额，单位为分
 * @param requiredCent - 需要支付的金额，单位为分
 * @returns true 表示余额足够
 */
export function isBalanceSufficient(availableCent: number, requiredCent: number): boolean {
  return new Decimal(availableCent).greaterThanOrEqualTo(new Decimal(requiredCent));
}

/**
 * 多个分值的精确求和，返回分
 * @param values - 分值数组
 * @returns 求和结果，单位为分
 */
export function sumFen(values: number[]): number {
  let total = new Decimal(0);
  for (const v of values) {
    total = total.plus(new Decimal(v));
  }
  return total.toNumber();
}
