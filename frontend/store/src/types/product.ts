/** 墙面控制器类型标识 */
export type WallControllerType = 'standard' | 'pro';

/** 墙面控制器商品 */
export interface WallControllerProduct {
  /** 商品ID */
  id: number;
  /** 商品名称，如 "标准墙面控制器" / "墙面控制器Pro款" */
  name: string;
  /** 类型标识 */
  type: WallControllerType;
  /** 单价，单位：分 */
  unit_price_cent: number;
  /** 是否上架 */
  is_active: boolean;
}
