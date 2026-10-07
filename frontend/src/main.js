import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createHead } from '@unhead/vue/client'
import VueLazyload from 'vue-lazyload'
import { router } from './router'
import App from './App.vue'
import './assets/main.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(createHead())
// Ленивая загрузка изображений (ШАГ 43): плейсхолдер до появления в viewport
app.use(VueLazyload, {
  loading: '/img/placeholder.svg',
  error: '/img/placeholder.svg',
})
app.mount('#app')
