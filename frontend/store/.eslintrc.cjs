/**
 * 最小 ESLint 配置（批次5，uni-app 门店端）
 *
 * 说明：
 * - 采用 ESLint 8 传统 .eslintrc 格式（与 package.json 的 lint script `--ext` 参数兼容）；
 * - 规则集保持最小：eslint:recommended + vue3-recommended + TS 推荐规则；
 * - 规范 6.2：TS 禁 any（@typescript-eslint/no-explicit-any 开为 error）；
 * - 注入 uni-app 全局对象，避免 no-undef 误报；
 * - 不影响现有构建（uni build 不读取本配置）；
 * - 依赖尚未安装（devDependencies 已声明），安装后方可运行 `npm run lint`。
 */
module.exports = {
  root: true,
  env: {
    browser: true,
    node: true,
    es2022: true,
  },
  globals: {
    uni: 'readonly',
    wx: 'readonly',
    my: 'readonly',
    getApp: 'readonly',
    getCurrentPages: 'readonly',
    plus: 'readonly',
  },
  extends: [
    'eslint:recommended',
    'plugin:vue/vue3-recommended',
    '@vue/eslint-config-typescript',
  ],
  parserOptions: {
    ecmaVersion: 'latest',
    sourceType: 'module',
  },
  rules: {
    // 规范 6.2：禁止使用 any
    '@typescript-eslint/no-explicit-any': 'error',
    // uni-app 页面文件名常为 index.vue，不强制多单词组件名
    'vue/multi-word-component-names': 'off',
    // 允许未使用变量以下划线开头
    '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
  },
  ignorePatterns: [
    'dist',
    'node_modules',
    'unpackage',
  ],
}
