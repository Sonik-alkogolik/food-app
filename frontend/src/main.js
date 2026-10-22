import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createHead } from '@unhead/vue/client'
import VueLazyload from 'vue-lazyload'
import { router } from './router'
import { setUnauthorizedHandler } from './api'
import { useAuthStore } from './stores/auth'
import { useFavoritesStore } from './stores/favorites'
import App from './App.vue'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(createHead())
// Ленивая загрузка изображений (ШАГ 43): плейсхолдер до появления в viewport
app.use(VueLazyload, {
  loading: '/img/placeholder.svg',
  error: '/img/placeholder.svg',
})

// Токен протух (401) — чистим сессию и уводим на страницу входа.
// Обработчик регистрируется здесь, чтобы api.js не импортировал роутер
// (иначе получается циклическая зависимость api.js -> router -> stores -> api.js).
setUnauthorizedHandler(() => {
  // Сессия протухла: чистим состояние сторов, иначе интерфейс продолжит
  // считать пользователя авторизованным (токен в localStorage уже удалён).
  useAuthStore(pinia).clearSession()
  useFavoritesStore(pinia).resetServerState()
  const current = router.currentRoute.value
  if (current.path === '/login') return
  router.push({ path: '/login', query: { redirect: current.fullPath } }).catch(() => {})
})

app.use(router)
app.mount('#app')

// Service worker: приложение ставится на домашний экран и работает без сети.
// В dev-режиме его нет — иначе каждый перезапуск сборки отдавал бы
// браузеру старые файлы из кеша.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
      // Без service worker приложение всё равно работает — просто без офлайна.
    })
  })
}
