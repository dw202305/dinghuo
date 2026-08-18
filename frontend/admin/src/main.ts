import { createApp } from "vue"
import { createPinia } from "pinia"
import ElementPlus from "element-plus"
import zhCn from "element-plus/es/locale/lang/zh-cn"
import * as ElementPlusIconsVue from "@element-plus/icons-vue"
import router from "@/router"
import App from "@/App.vue"
// Element Plus 完整样式（含 el-zoom-in/el-collapse-transition 等过渡类，缺失会导致内置动画卡死不结束）
import "element-plus/dist/index.css"
import "@/assets/styles/variables.css"
import "@/assets/styles/element-override.css"
import "@/assets/styles/global.css"

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(ElementPlus, { locale: zhCn })

// 注册所有 Element Plus 图标
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
}

app.mount("#app")
