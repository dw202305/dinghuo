/** 地址项 */
export interface AddressItem {
  id: number
  receiver_name: string
  receiver_phone: string
  province: string
  city: string
  district: string
  detail_address: string
  /** 后端拼接的完整地址 */
  full_address: string
  is_default: boolean
  created_at: string
  updated_at: string
}

/** 地址表单数据 */
export interface AddressFormData {
  receiver_name: string
  receiver_phone: string
  province: string
  city: string
  district: string
  detail_address: string
  is_default: boolean
}

/** 订单收货地址快照 */
export interface ShippingAddressSnapshot {
  receiver_name: string
  receiver_phone: string
  province: string
  city: string
  district: string
  detail_address: string
  full_address: string
}
