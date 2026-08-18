/**
 * 最小 ESLint 配置（批次5）
 *
 * 说明：
 * - 采用 ESLint 8 传统 .eslintrc 格式（与 package.json 的 lint script `--ext` 参数兼容）；
 * - 规则集保持最小：eslint:recommended + vue3-recommended + TS 推荐规则；
 * - 规范 6.2：TS 禁 any（@typescript-eslint/no-explicit-any 开为 error）；
 * - unplugin 自动生成的声明文件不参与 lint；
 * - 依赖尚未安装（devDependencies 已声明），安装后方可运行 `pnpm lint`。
 */
module.exports = {
  root: true,
  env: {
    browser: true,
    node: true,
    es2022: true,
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
    // 项目内大量单单词组件名（Login.vue 等），不强制
    'vue/multi-word-component-names': 'off',
    // 允许未使用变量以下划线开头
    '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
  },
  ignorePatterns: [
    'dist',
    'node_modules',
    'src/auto-imports.d.ts',
    'src/components.d.ts',
  ],
}
