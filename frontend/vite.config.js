import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import { fileURLToPath } from 'node:url'

// Корень сайта (public/) — там, где Laravel раздаёт статику.
const publicDir = fileURLToPath(new URL('../public', import.meta.url))

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      // Регистрацию делаем сами в main.js: оболочка приложения — не
      // index.html Vite, а blade-шаблон, и вставка скрипта в неё не попала бы.
      injectRegister: null,
      filename: 'sw.js',
      // Манифест написан руками (public/manifest.webmanifest): он статичен,
      // а плагин клал бы его в precache по пути относительно себя — из
      // /frontend/, а service worker живёт в корне.
      manifest: false,
      workbox: {
        // Service worker лежит в корне сайта: из /frontend/ его scope был бы
        // /frontend/, и страницы рецептов он бы не контролировал.
        swDest: fileURLToPath(new URL('../public/sw.js', import.meta.url)),
        globDirectory: publicDir,
        globPatterns: ['frontend/**/*.{js,css,html,svg,png,ico,webp,woff2}'],
        additionalManifestEntries: [
          { url: '/manifest.webmanifest', revision: null },
          { url: '/img/icon-192.png', revision: null },
          { url: '/img/icon-512.png', revision: null },
          { url: '/img/icon-maskable-512.png', revision: null },
          { url: '/img/placeholder.svg', revision: null },
          { url: '/favicon.svg', revision: null },
        ],
        // Оболочка приложения — собранный Vite index.html. Её содержимое
        // меняется вместе со сборкой, и workbox перезаписывает кеш сам.
        navigateFallback: '/frontend/index.html',
        cleanupOutdatedCaches: true,
        skipWaiting: true,
        clientsClaim: true,
        runtimeCaching: [
          {
            // Данные API: сеть вперёд, из кеша — когда сервера нет.
            urlPattern: ({ url, request }) => {
              if (request.method !== 'GET') return false
              if (!url.pathname.startsWith('/api/')) return false
              // Личное не кешируем: профиль, избранное, токены.
              return !url.pathname.startsWith('/api/auth')
                && !url.pathname.startsWith('/api/favorites')
            },
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api',
              networkTimeoutSeconds: 3,
              expiration: { maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
          {
            // Картинки: с фотографиями блюд сеть не главный источник.
            urlPattern: ({ request }) => request.destination === 'image',
            handler: 'CacheFirst',
            options: {
              cacheName: 'images',
              expiration: { maxEntries: 300, maxAgeSeconds: 60 * 60 * 24 * 30 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
        ],
      },
    }),
  ],
  base: '/frontend/',
  build: {
    outDir: '../public/frontend',
    emptyOutDir: true,
    manifest: true,
  },
  server: {
    port: 5173,
    proxy: {
      // В dev-режиме проксируем API и загруженные изображения на Laravel
      '/api': 'http://127.0.0.1:8000',
      '/storage': 'http://127.0.0.1:8000',
    },
  },
})
