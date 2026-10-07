<template>
  <div v-if="!category" class="loading">Загрузка…</div>

  <div v-else>
    <!-- Заголовок категории с описанием и изображением -->
    <section class="cat-hero">
      <img v-lazy="category.image_url || '/img/placeholder.svg'" :alt="category.name" />
      <div class="cat-hero-info">
        <h1>{{ category.name }}</h1>
        <p>{{ category.description }}</p>
        <span class="counter">🍲 Рецептов в категории: <b>{{ store.total }}</b></span>
      </div>
    </section>

    <!-- Фильтры только внутри этой категории -->
    <div class="layout">
      <RecipeFilters :categories="[]" :model-value="localFilters" @apply="apply" @reset="reset" />
      <div class="content">
        <div v-if="store.loading" class="loading">Загрузка рецептов…</div>
        <template v-else>
          <div class="grid">
            <RecipeCard v-for="r in store.recipes" :key="r.id" :recipe="{ ...r, category }" />
          </div>
          <p v-if="!store.recipes.length" class="empty">В категории пока нет рецептов по выбранным фильтрам.</p>
          <nav v-if="store.lastPage > 1" class="pagination">
            <button :disabled="store.page <= 1" @click="go(store.page - 1)">← Назад</button>
            <span>Страница {{ store.page }} из {{ store.lastPage }}</span>
            <button :disabled="store.page >= store.lastPage" @click="go(store.page + 1)">Вперёд →</button>
          </nav>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
/* ШАГ 35: страница категории — шапка с фото/описанием, счётчик, сетка рецептов, локальные фильтры */
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import { useRecipeStore } from '../stores/recipes'
import RecipeCard from '../components/RecipeCard.vue'
import RecipeFilters from '../components/RecipeFilters.vue'

const store = useRecipeStore()
const route = useRoute()

const category = computed(() => store.categories.find((c) => c.slug === route.params.slug) || null)

const localFilters = reactive({ category_id: null, difficulty: [], max_time: 180, sort: 'new' })

function fetch(page = 1) {
  if (!category.value) return
  store.filters = { ...localFilters, category_id: category.value.id }
  store.fetchRecipes(page)
}

function apply(f) { Object.assign(localFilters, f); fetch(1) }
function reset() { Object.assign(localFilters, { category_id: null, difficulty: [], max_time: 180, sort: 'new' }); fetch(1) }
function go(p) { fetch(p); window.scrollTo({ top: 0, behavior: 'smooth' }) }

useHead(computed(() => category.value ? {
  title: `${category.value.name} — рецепты с фото | Что приготовить?`,
  meta: [
    { name: 'description', content: category.value.description || `Рецепты категории «${category.value.name}»` },
    { property: 'og:title', content: category.value.name },
    { property: 'og:image', content: category.value.image_url },
  ],
} : {}))

watch(() => route.params.slug, async () => {
  await store.fetchCategories()
  fetch(1)
}, { immediate: true })
</script>

<style scoped>
.cat-hero { position: relative; border-radius: 18px; overflow: hidden; margin-bottom: 24px; min-height: 220px; display: flex; align-items: flex-end; background: #eee; }
.cat-hero img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.cat-hero-info { position: relative; padding: 26px; color: #fff; background: linear-gradient(transparent, rgba(0,0,0,.7)); width: 100%; }
.cat-hero-info h1 { margin: 0 0 6px; }
.counter { opacity: .9; }
.layout { display: grid; grid-template-columns: 260px 1fr; gap: 24px; align-items: start; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 18px; }
.loading, .empty { color: #888; padding: 40px; text-align: center; }
.pagination { display: flex; gap: 16px; justify-content: center; margin-top: 24px; }
.pagination button { padding: 8px 16px; border: 1px solid #ddd; background: #fff; border-radius: 8px; cursor: pointer; }
@media (max-width: 800px) { .layout { grid-template-columns: 1fr; } }
</style>
