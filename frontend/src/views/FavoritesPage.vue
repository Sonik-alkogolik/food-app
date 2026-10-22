<template>
  <div class="favorites-page">
    <div class="head-row">
      <h1>❤️ Избранное</h1>
      <span v-if="favorites.loaded" class="count">Рецептов: <b>{{ favorites.total }}</b></span>
    </div>

    <div v-if="favorites.loading && !favorites.items.length" class="loading">Загрузка избранного…</div>

    <p v-else-if="favorites.error" class="empty error">{{ favorites.error }}</p>

    <template v-else>
      <div v-if="favorites.items.length" class="grid">
        <RecipeCard v-for="r in favorites.items" :key="r.id" :recipe="r" />
      </div>
      <div v-else class="empty-box">
        <p class="empty">Здесь пока пусто. Отмечайте сердечком рецепты, которые хотите приготовить! ❤️</p>
        <router-link to="/recipes" class="btn">Перейти в каталог</router-link>
      </div>
    </template>

    <nav v-if="favorites.page < favorites.lastPage" class="pagination">
      <button :disabled="favorites.loading" @click="loadMore">
        {{ favorites.loading ? 'Загрузка…' : 'Показать ещё' }}
      </button>
    </nav>
  </div>
</template>

<script setup>
/* Страница избранного: сетка карточек, состояние загрузки и пустое состояние. */
import { onMounted } from 'vue'
import { useHead } from '@unhead/vue'
import { useFavoritesStore } from '../stores/favorites'
import RecipeCard from '../components/RecipeCard.vue'

const favorites = useFavoritesStore()

useHead({ title: 'Избранное — Что приготовить?' })

function loadMore() {
  favorites.fetchFavorites(favorites.page + 1)
}

onMounted(() => favorites.fetchFavorites(1))
</script>

<style scoped>
.head-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 16px; }
.head-row h1 { margin: 0; }
.count { color: #666; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 18px; }
.loading, .empty { color: #888; padding: 30px; text-align: center; }
.empty.error { color: #dc2626; }
.empty-box { display: flex; flex-direction: column; align-items: center; gap: 14px; background: #fff; border: 1px dashed #ddd; border-radius: 16px; padding: 40px 20px; }
.btn { display: inline-block; padding: 11px 20px; border-radius: 10px; background: #ff9800; color: #fff; font-weight: 600; text-decoration: none; }
.pagination { display: flex; justify-content: center; margin-top: 24px; }
.pagination button { padding: 9px 16px; border: 1px solid #ddd; background: #fff; border-radius: 8px; cursor: pointer; }
.pagination button:disabled { opacity: .5; cursor: default; }
</style>
