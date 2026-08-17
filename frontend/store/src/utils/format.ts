import Decimal from 'decimal.js';

/**
 * 格式化金额（带千分位）— 基于 Decimal 精确计算
 * @param amount 金额，单位为元（字符串或数字）
 * @param decimals 小数位数，默认2
 * @returns 格式化后的金额字符串（不含 ¥ 符号）
 */
export function formatMoney(amount: string | number, decimals = 2): string {
  const d = new Decimal(amount);
  if (d.isNaN()) return '0.00';
  const fixed = d.toFixed(decimals);
  const parts = fixed.split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return parts.join('.');
}

/**
 * 格式化日期
 * @param dateStr 日期字符串 YYYY-MM-DD HH:mm:ss
 * @param format 格式化模板，默认 'YYYY-MM-DD'
 * @returns 格式化后的日期字符串
 */
export function formatDate(dateStr: string, format = 'YYYY-MM-DD'): string {
  if (!dateStr) return '';
  const date = new Date(dateStr.replace(/-/g, '/'));
  if (isNaN(date.getTime())) return dateStr;

  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const seconds = String(date.getSeconds()).padStart(2, '0');

  return format
    .replace('YYYY', String(year))
    .replace('MM', month)
    .replace('DD', day)
    .replace('HH', hours)
    .replace('mm', minutes)
    .replace('ss', seconds);
}

/**
 * 手机号脱敏
 * @param phone 手机号
 * @returns 脱敏后的手机号，如 138****8888
 */
export function maskPhone(phone: string): string {
  if (!phone || phone.length !== 11) return phone;
  return `${phone.slice(0, 3)}****${phone.slice(7)}`;
}

/**
 * 格式化面积（平方米）— 基于 Decimal 精确计算
 * @param area 面积
 * @param decimals 小数位数，默认4
 */
export function formatArea(area: string | number, decimals = 4): string {
  const d = new Decimal(area);
  if (d.isNaN()) return '0.0000';
  return d.toFixed(decimals);
}

/**
 * 格式化尺寸（厘米）— 基于 Decimal 精确计算
 * @param size 尺寸值
 */
export function formatSize(size: string | number): string {
  const d = new Decimal(size);
  if (d.isNaN()) return '0.0';
  return d.toFixed(1);
}
