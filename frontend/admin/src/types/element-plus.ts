/**
 * element-plus 2.14.x 未从包顶层导出 el-table 插槽的通用行类型 DefaultRow，
 * 此处从包 exports 声明允许的 es 深路径再导出官方类型，
 * 供列表页模板适配函数收口行数据类型使用（避免显式 any）。
 */
export type { DefaultRow } from "element-plus/es/components/table/src/table/defaults"
