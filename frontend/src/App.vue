<template>
  <header class="navbar">
    <div class="nav-inner">
      <router-link to="/" class="logo">🍳 Что приготовить?</router-link>
      <nav class="links">
        <router-link to="/recipes">Рецепты</router-link>
        <router-link to="/categories">Категории</router-link>
        <router-link to="/fridge">🧊 Холодильник</router-link>
        <router-link to="/random">🎲</router-link>
      </nav>
      <!-- Поиск в шапке -->
      <form class="header-search" @submit.prevent="goSearch">
        <input v-model.trim="q" type="search" placeholder="Поиск рецептов…" aria-label="Поиск" />
        <button type="submit">🔍</button>
      </form>
      <button class="burger" @click="menuOpen = !menuOpen">☰</button>
    </div>
    <nav v-if="menuOpen" class="mobile-menu">
      <router-link to="/recipes" @click="menuOpen = false">Рецепты</router-link>
      <router-link to="/categories" @click="menuOpen = false">Категории</router-link>
      <router-link to="/fridge" @click="menuOpen = false">🧊 Холодильник</router-link>
      <router-link to="/random" @click="menuOpen = false">🎲 Случайный рецепт</router-link>
      <router-link to="/search" @click="menuOpen = false">🔍 Поиск</router-link>
    </nav>
  </header>

  <main class="page">
    <router-view />
  </main>

  <footer class="footer">
    <p>© 2026 «Что приготовить?» — 1000 домашних рецептов с пошаговыми фото</p>
  </footer>
</template>

<script setup>
/* ШАГ 27: layout — навигация, поиск в шапке, футер; мобильное меню (адаптивность) */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const q = ref('')
const menuOpen = ref(false)

function goSearch() {
  if (!q.value) return
  menuOpen.value = false
  router.push({ name: 'search', query: { q: q.value } })
}
</script>

<style scoped>
.navbar { position: sticky; top: 0; z-index: 100; background: #fff; border-bottom: 1px solid #eee; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
.nav-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; gap: 18px; padding: 12px 20px; }
.logo { font-weight: 700; font-size: 1.15rem; color: #ff5722; text-decoration: none; white-space: nowrap; }
.links { display: flex; gap: 16px; }
.links a { color: #444; text-decoration: none; font-weight: 500; }
.links a.router-link-active { color: #ff9800; }
.header-search { margin-left: auto; display: flex; gap: 6px; }
.header-search input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; width: 220px; }
.header-search button { border: none; background: #ff9800; color: #fff; border-radius: 8px; padding: 0 12px; cursor: pointer; }
.burger { display: none; border: none; background: none; font-size: 1.4rem; cursor: pointer; }
.mobile-menu { display: none; flex-direction: column; padding: 10px 20px; gap: 8px; border-top: 1px solid #eee; }
.mobile-menu a { color: #444; text-decoration: none; }
.page { max-width: 1200px; margin: 0 auto; padding: 24px 20px 60px; min-height: 70vh; }
.footer { background: #262626; color: #bbb; text-align: center; padding: 22px; font-size: .9rem; }
@media (max-width: 800px) {
  .links, .header-search { display: none; }
  .burger { display: block; margin-left: auto; }
  .mobile-menu { display: flex; }
}
</style>
