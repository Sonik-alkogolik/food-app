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
import { useRoute, useRouter } from 'vue-router'
import { useRecipeStore } from '../stores/recipes'
import RecipeCard from '../components/RecipeCard.vue'
import RecipeFilters from '../components/RecipeFilters.vue'

const store = useRecipeStore()
const route = useRoute()
const router = useRouter()

// Адрес, который мы сами записали в строку браузера (см. syncQuery).
let ownNavigation = null

/**
 * Читает фильтры из адресной строки.
 * Раньше отсюда брались только category_id, sort и difficulty, а
 * max_time игнорировался, и главное — фильтры применялись к выдаче, но
 * не попадали в панель: по клику «Применить фильтры» всё сбрасывалось.
 */
function filtersFromQuery() {
  const q = route.query
  const maxTime = Number(q.max_time)
  return {
    category_id: q.category_id ? Number(q.category_id) : null,
    difficulty: q.difficulty ? String(q.difficulty).split(',').filter(Boolean) : [],
    max_time: maxTime >= 15 && maxTime <= 180 ? maxTime : 180,
    sort: q.sort ? String(q.sort) : 'new',
  }
}

/** Записывает применённые фильтры в адрес — так ссылку можно переслать. */
function syncQuery(filters) {
  const q = {}
  if (filters.category_id) q.category_id = String(filters.category_id)
  if (filters.difficulty.length) q.difficulty = filters.difficulty.join(',')
  if (filters.max_time < 180) q.max_time = String(filters.max_time)
  if (filters.sort && filters.sort !== 'new') q.sort = filters.sort

  // Адрес обновляется асинхронно, и до него ещё может дойти предыдущая
  // навигация. Запоминаем, какой именно адрес мы записали, чтобы
  // отличить свою запись от чужой в watcher ниже.
  ownNavigation = router.resolve({ query: q }).fullPath
  router.replace({ query: q })
}

function applyFilters(f) {
  store.filters = { ...f }
  syncQuery(store.filters)
  store.fetchRecipes(1)
}

function resetFilters() {
  store.resetFilters()
  syncQuery(store.filters)
  store.fetchRecipes(1)
}

function go(page) {
  store.fetchRecipes(page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(async () => {
  await store.fetchCategories()
  store.filters = filtersFromQuery()
  store.fetchRecipes(Number(route.query.page) || 1)
})

// Если фильтры изменились в адресной строке (например, переход по ссылке
// «Смотреть все» с ?sort=popular) — применяем их и в выдаче, и в панели.
watch(() => route.query, () => {
  const next = filtersFromQuery()

  // Пока в строке браузера не наш адрес, любой другой — устаревший:
  // применять его нельзя, иначе только что снятый фильтр вернётся.
  if (ownNavigation !== null) {
    if (route.fullPath !== ownNavigation) return
    ownNavigation = null
    return
  }

  const current = store.filters
  const same =
    next.category_id === current.category_id &&
    next.max_time === current.max_time &&
    next.sort === current.sort &&
    next.difficulty.join(',') === current.difficulty.join(',')
  if (same) return
  store.filters = next
  store.fetchRecipes(1)
}, { deep: true })
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
