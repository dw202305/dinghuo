/**
 * 格式化工具函数
 */

import dayjs from "dayjs"

/**
 * 格式化日期时间
 */
export function formatDateTime(value: string | null | undefined, fmt = "YYYY-MM-DD HH:mm:ss"): string {
  if (!value) return "-"
  return dayjs(value).format(fmt)
}

/**
 * 格式化日期
 */
export function formatDate(value: string | null | undefined): string {
  return formatDateTime(value, "YYYY-MM-DD")
}

/**
 * 格式化金额（分转元或直接格式化）
 */
export function formatMoney(value: string | number | null | undefined): string {
  if (value === null || value === undefined) return "0.00"
  const num = typeof value === "string" ? parseFloat(value) : value
  if (isNaN(num)) return "0.00"
  return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")
}

/**
 * 格式化手机号（中间4位隐藏）
 */
export function formatPhone(phone: string | null | undefined): string {
  if (!phone) return "-"
  if (phone.length !== 11) return phone
  return phone.replace(/(\d{3})\d{4}(\d{4})/, "$1****$2")
}

/**
 * 格式化文件大小
 */
export function formatFileSize(bytes: number): string {
  if (bytes === 0) return "0 B"
  const k = 1024
  const sizes = ["B", "KB", "MB", "GB"]
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
}

/**
 * 格式化面积
 */
export function formatArea(value: string | number | null | undefined): string {
  if (value === null || value === undefined) return "0"
  const num = typeof value === "string" ? parseFloat(value) : value
  if (isNaN(num)) return "0"
  return num.toFixed(4)
}
