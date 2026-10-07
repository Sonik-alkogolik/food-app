<template>
  <div>
    <h1>Поиск рецептов</h1>
    <form class="search-form" @submit.prevent="doSearch()">
      <input v-model.trim="q" type="search" placeholder="Название блюда или ингредиент… (например: борщ, курица)" />
      <button class="btn">🔍 Искать</button>
    </form>

    <p v-if="searched && !store.loading" class="result-info">
      По запросу «<b>{{ lastQuery }}</b>» найдено: <b>{{ store.total }}</b>
    </p>

    <div v-if="store.loading" class="loading">Поиск…</div>

    <template v-else-if="searched">
      <div v-if="store.recipes.length" class="grid">
        <router-link v-for="r in store.recipes" :key="r.id" :to="`/recipes/${r.slug}`" class="res-card">
          <img v-lazy="r.main_image_url || '/img/placeholder.svg'" :alt="r.title" loading="lazy" @error="$event.target.src='/img/placeholder.svg'" />
          <div class="res-body">
            <!-- Подсветка найденных слов (v-html безопасен: сервер экранирует текст) -->
            <h3 v-html="r.highlight?.title || r.title"></h3>
            <p class="cat">{{ r.category?.name }} · ⏱️ {{ r.cook_time_minutes }} мин</p>
            <p class="ingr" v-html="truncate(r.highlight?.ingredients || r.ingredients)"></p>
          </div>
        </router-link>
      </div>
      <!-- Ничего не найдено -->
      <div v-else class="no-result">
        <div class="emoji">🍽️🔍</div>
        <h2>Ничего не найдено</h2>
        <p>Попробуйте изменить запрос — например, искать отдельный ингредиент: «курица», «творог», «грибы».</p>
        <router-link to="/fridge" class="btn alt">Подобрать рецепт по продуктам 🧊</router-link>
      </div>

      <nav v-if="store.lastPage > 1" class="pagination">
        <button :disabled="store.page <= 1" @click="go(store.page - 1)">← Назад</button>
        <span>Страница {{ store.page }} из {{ store.lastPage }}</span>
        <button :disabled="store.page >= store.lastPage" @click="go(store.page + 1)">Вперёд →</button>
      </nav>
    </template>
  </div>
</template>

<script setup>
/* ШАГ 36: поиск — строка, результаты с подсветкой, сообщение «Ничего не найдено», пагинация */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import { useRecipeStore } from '../stores/recipes'

const store = useRecipeStore()
const route = useRoute()
const router = useRouter()

const q = ref(route.query.q || '')
const lastQuery = ref('')
const searched = ref(false)

async function doSearch(query = q.value) {
  if (!query) return
  lastQuery.value = query
  searched.value = true
  router.replace({ query: { q: query } })
  await store.searchRecipes(query, 1)
}

function go(page) { store.searchRecipes(lastQuery.value, page); window.scrollTo({ top: 0, behavior: 'smooth' }) }

function truncate(text) {
  const t = (text || '').replace(/\n/g, ', ')
  return t.length > 160 ? t.slice(0, 160) + '…' : t
}

useHead(computed(() => ({
  title: lastQuery.value ? `Поиск: ${lastQuery.value} — Что приготовить?` : 'Поиск рецептов — Что приготовить?',
})))

onMounted(async () => {
  if (route.query.q) await doSearch(String(route.query.q))
})
</script>

<style scoped>
h1 { margin-bottom: 14px; }
.search-form { display: flex; gap: 10px; max-width: 640px; margin-bottom: 18px; }
.search-form input { flex: 1; padding: 12px 16px; font-size: 1rem; border: 2px solid #ddd; border-radius: 10px; }
.search-form input:focus { outline: none; border-color: #ff9800; }
.btn { padding: 12px 20px; background: #ff9800; color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; }
.btn.alt { background: #f3f4f6; color: #333; display: inline-block; margin-top: 10px; }
.result-info { color: #555; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.res-card { display: flex; gap: 14px; background: #fff; border: 1px solid #eee; border-radius: 14px; overflow: hidden; text-decoration: none; color: inherit; transition: box-shadow .2s; }
.res-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,.12); }
.res-card img { width: 120px; height: 100px; object-fit: cover; flex: none; }
.res-body { padding: 10px 12px 10px 0; min-width: 0; }
.res-body h3 { margin: 0 0 4px; font-size: 1rem; }
.cat { margin: 0 0 6px; color: #ff9800; font-size: .82rem; }
.ingr { margin: 0; color: #666; font-size: .82rem; line-height: 1.4; }
:deep(mark) { background: #ffe082; padding: 0 2px; border-radius: 3px; }
.no-result { text-align: center; padding: 50px 20px; color: #666; }
.no-result .emoji { font-size: 3rem; }
.loading { color: #888; padding: 40px; text-align: center; }
.pagination { display: flex; gap: 16px; justify-content: center; margin-top: 24px; }
.pagination button { padding: 8px 16px; border: 1px solid #ddd; background: #fff; border-radius: 8px; cursor: pointer; }
</style>
