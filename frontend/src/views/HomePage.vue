<template>
  <div class="home">
    <!-- Приветственный баннер -->
    <section class="hero">
      <h1>Найди свой идеальный рецепт!</h1>
      <p>1000 домашних рецептов с пошаговыми фотографиями — от быстрых завтраков до праздничной выпечки</p>
      <div class="hero-btns">
        <router-link to="/recipes" class="btn primary">Все рецепты</router-link>
        <router-link to="/random" class="btn ghost">🎲 Случайный рецепт</router-link>
      </div>
    </section>

    <!-- Категории -->
    <section class="block">
      <h2>Категории</h2>
      <div class="cats-grid">
        <router-link v-for="c in store.categories" :key="c.id" :to="`/categories/${c.slug}`" class="cat-card">
          <img v-lazy="c.image_url || '/img/placeholder.svg'" :alt="c.name" loading="lazy" />
          <div class="cat-info">
            <b>{{ c.name }}</b>
            <small>{{ c.recipes_count }} рецептов</small>
          </div>
        </router-link>
      </div>
    </section>

    <!-- Популярные рецепты (top-12 по просмотрам) -->
    <section class="block">
      <div class="block-head">
        <h2>🔥 Популярные рецепты</h2>
        <router-link to="/recipes?sort=popular" class="more">Смотреть все →</router-link>
      </div>
      <div v-if="loadingPopular" class="loading">Загрузка…</div>
      <div v-else class="grid">
        <RecipeCard v-for="r in popular" :key="r.id" :recipe="r" />
      </div>
    </section>

    <!-- Новые рецепты -->
    <section class="block">
      <div class="block-head">
        <h2>✨ Новые рецепты</h2>
        <router-link to="/recipes" class="more">Смотреть все →</router-link>
      </div>
      <div v-if="loadingNew" class="loading">Загрузка…</div>
      <div v-else class="grid">
        <RecipeCard v-for="r in fresh" :key="r.id" :recipe="r" />
      </div>
    </section>

    <!-- Рецепт дня -->
    <section class="block" v-if="daily">
      <h2>⭐ Рецепт дня</h2>
      <div class="daily">
        <router-link :to="`/recipes/${daily.slug}`" class="daily-photo">
          <img v-lazy="daily.main_image_url || '/img/placeholder.svg'" :alt="daily.title" loading="lazy" />
        </router-link>
        <div class="daily-info">
          <h3><router-link :to="`/recipes/${daily.slug}`">{{ daily.title }}</router-link></h3>
          <p>{{ daily.description }}</p>
          <ul class="daily-meta">
            <li>⏱️ {{ daily.cook_time_minutes }} мин</li>
            <li>🍽️ {{ daily.servings }} порции</li>
            <li>🔥 {{ daily.calories_per_serving }} ккал/порция</li>
            <li>👤 {{ daily.created_by }}</li>
          </ul>
          <router-link :to="`/recipes/${daily.slug}`" class="btn primary">Готовить!</router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
/* ШАГ 32: главная страница — баннер, популярные, новые, категории, рецепт дня */
import { onMounted, ref } from 'vue'
import axios from 'axios'
import { useRecipeStore } from '../stores/recipes'
import RecipeCard from '../components/RecipeCard.vue'

const store = useRecipeStore()
const popular = ref([])
const fresh = ref([])
const daily = ref(null)
const loadingPopular = ref(true)
const loadingNew = ref(true)

onMounted(async () => {
  store.fetchCategories()
  try {
    const [p, n, d] = await Promise.all([
      axios.get('/api/recipes', { params: { sort: 'popular', page: 1 } }),
      axios.get('/api/recipes', { params: { sort: 'new', page: 1 } }),
      axios.get('/api/random'),
    ])
    popular.value = p.data.data.slice(0, 12)
    fresh.value = n.data.data.slice(0, 12)
    daily.value = d.data.data
  } finally {
    loadingPopular.value = false
    loadingNew.value = false
  }
})
</script>

<style scoped>
.hero { background: linear-gradient(135deg, #ff9800, #ff5722); color: #fff; border-radius: 18px; padding: 48px 24px; text-align: center; margin-bottom: 32px; }
.hero h1 { margin: 0 0 10px; font-size: 2.1rem; }
.hero p { margin: 0 0 22px; opacity: .95; font-size: 1.05rem; }
.hero-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.btn { padding: 12px 22px; border-radius: 10px; text-decoration: none; font-weight: 600; }
.btn.primary { background: #fff; color: #ff5722; }
.btn.ghost { background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.6); }
.block { margin-bottom: 40px; }
.block h2 { margin: 0 0 16px; }
.block-head { display: flex; justify-content: space-between; align-items: baseline; }
.more { color: #ff9800; text-decoration: none; font-weight: 600; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
.cats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 14px; }
.cat-card { position: relative; border-radius: 12px; overflow: hidden; text-decoration: none; color: #fff; aspect-ratio: 4/3; background: #eee; }
.cat-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.cat-card:hover img { transform: scale(1.08); }
.cat-info { position: absolute; inset: auto 0 0 0; padding: 24px 12px 10px; background: linear-gradient(transparent, rgba(0,0,0,.75)); }
.cat-info small { display: block; opacity: .85; }
.daily { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #fff; border: 1px solid #eee; border-radius: 16px; overflow: hidden; }
.daily-photo img { width: 100%; height: 100%; object-fit: cover; min-height: 240px; display: block; }
.daily-info { padding: 20px 20px 20px 0; }
.daily-info h3 a { color: inherit; text-decoration: none; }
.daily-meta { list-style: none; padding: 0; display: flex; flex-wrap: wrap; gap: 14px; color: #555; }
.loading { color: #888; padding: 30px; text-align: center; }
@media (max-width: 700px) { .daily { grid-template-columns: 1fr; } .daily-info { padding: 16px; } }
</style>
