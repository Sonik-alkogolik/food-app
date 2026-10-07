<template>
  <div class="catalog">
    <RecipeFilters
      :categories="store.categories"
      :model-value="store.filters"
      @apply="applyFilters"
      @reset="resetFilters"
    />

    <div class="content">
      <div class="head-row">
        <h1>Каталог рецептов</h1>
        <span class="count">Найдено: <b>{{ store.total }}</b></span>
      </div>

      <div v-if="store.loading" class="loading">Загрузка рецептов…</div>
      <template v-else>
        <div class="grid">
          <RecipeCard v-for="r in store.recipes" :key="r.id" :recipe="r" />
        </div>
        <p v-if="!store.recipes.length" class="empty">По выбранным фильтрам рецептов не найдено.</p>

        <!-- Пагинация -->
        <nav v-if="store.lastPage > 1" class="pagination">
          <button :disabled="store.page <= 1" @click="go(store.page - 1)">← Назад</button>
          <span>Страница {{ store.page }} из {{ store.lastPage }}</span>
          <button :disabled="store.page >= store.lastPage" @click="go(store.page + 1)">Вперёд →</button>
        </nav>
      </template>
    </div>
  </div>
</template>

<script setup>
/* ШАГ 33: каталог — боковые фильтры, сетка карточек, пагинация, счётчик, индикатор загрузки */
import { onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useRecipeStore } from '../stores/recipes'
import RecipeCard from '../components/RecipeCard.vue'
import RecipeFilters from '../components/RecipeFilters.vue'

const store = useRecipeStore()
const route = useRoute()

function applyFilters(f) {
  store.filters = { ...f }
  store.fetchRecipes(1)
}

function resetFilters() {
  store.resetFilters()
  store.fetchRecipes(1)
}

function go(page) {
  store.fetchRecipes(page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(async () => {
  await store.fetchCategories()
  // поддержка ссылок вида /recipes?category_id=3&sort=popular
  if (route.query.category_id) store.filters.category_id = Number(route.query.category_id)
  if (route.query.sort) store.filters.sort = String(route.query.sort)
  if (route.query.difficulty) store.filters.difficulty = String(route.query.difficulty).split(',')
  store.fetchRecipes(Number(route.query.page) || 1)
})

watch(() => route.query.category_id, (v) => {
  if (v !== undefined) { store.filters.category_id = v ? Number(v) : null; store.fetchRecipes(1) }
})
</script>

<style scoped>
.catalog { display: grid; grid-template-columns: 260px 1fr; gap: 24px; align-items: start; }
.head-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 16px; }
.head-row h1 { margin: 0; }
.count { color: #666; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 18px; }
.loading, .empty { color: #888; padding: 40px; text-align: center; }
.pagination { display: flex; gap: 16px; justify-content: center; align-items: center; margin-top: 28px; }
.pagination button { padding: 8px 16px; border: 1px solid #ddd; background: #fff; border-radius: 8px; cursor: pointer; }
.pagination button:disabled { opacity: .4; cursor: default; }
@media (max-width: 800px) { .catalog { grid-template-columns: 1fr; } }
</style>
