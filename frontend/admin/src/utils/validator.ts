/**
 * 表单验证规则工具
 */

/**
 * 手机号验证
 */
export function isPhone(value: string): boolean {
  return /^1[3-9]\d{9}$/.test(value)
}

/**
 * 邮箱验证
 */
export function isEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
}

/**
 * 非空验证
 */
export function isNotEmpty(value: unknown): boolean {
  if (value === null || value === undefined) return false
  if (typeof value === "string") return value.trim().length > 0
  if (Array.isArray(value)) return value.length > 0
  return true
}

/**
 * Element Plus 表单验证 - 手机号
 */
export const phoneRule = {
  validator: (_rule: unknown, value: string, callback: (error?: Error) => void) => {
    if (!value) {
      callback(new Error("请输入手机号"))
    } else if (!isPhone(value)) {
      callback(new Error("手机号格式不正确"))
    } else {
      callback()
    }
  },
  trigger: "blur"
}

/**
 * Element Plus 表单验证 - 必填
 */
export function requiredRule(message: string, trigger: "blur" | "change" = "blur") {
  return { required: true, message, trigger }
}
