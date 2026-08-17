/**
 * 校验手机号格式（11位中国大陆手机号）
 */
export function isValidPhone(phone: string): boolean {
  return /^1[3-9]\d{9}$/.test(phone);
}

/**
 * 校验验证码格式（6位数字）
 */
export function isValidVerifyCode(code: string): boolean {
  return /^\d{6}$/.test(code);
}

/**
 * 校验宽度范围（90.0 ~ 350.0 cm）
 */
export function isValidWidth(width: number): boolean {
  return width >= 90.0 && width <= 350.0;
}

/**
 * 校验高度范围（50.0 ~ 600.0 cm）
 */
export function isValidHeight(height: number): boolean {
  return height >= 50.0 && height <= 600.0;
}

/**
 * 校验必填字符串
 */
export function isNonEmpty(value: string | null | undefined): boolean {
  return typeof value === 'string' && value.trim().length > 0;
}

/**
 * 校验日期格式 YYYY-MM-DD
 */
export function isValidDate(date: string): boolean {
  return /^\d{4}-\d{2}-\d{2}$/.test(date);
}

/**
 * 校验税号格式（15-20位字母数字）
 */
export function isValidTaxNo(taxNo: string): boolean {
  return /^[A-Za-z0-9]{15,20}$/.test(taxNo);
}
