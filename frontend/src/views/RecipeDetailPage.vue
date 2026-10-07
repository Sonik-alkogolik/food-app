<template>
  <div v-if="store.detailLoading" class="loading">Загрузка рецепта…</div>

  <div v-else-if="!recipe || recipe.notFound" class="notfound">
    <h1>Рецепт не найден 😔</h1>
    <router-link to="/recipes" class="btn">Вернуться в каталог</router-link>
  </div>

  <article v-else class="detail">
    <!-- Хлебные крошки -->
    <nav class="crumbs">
      <router-link to="/">Главная</router-link> ›
      <router-link :to="`/categories/${recipe.category?.slug}`">{{ recipe.category?.name }}</router-link> ›
      <span>{{ recipe.title }}</span>
    </nav>

    <div class="top">
      <div class="photo-box" @click="zoom = !zoom">
        <img :src="recipe.main_image_url || '/img/placeholder.svg'" :alt="recipe.title" :class="{ zoomed: zoom }" />
        <small class="hint">🔍 нажмите для увеличения</small>
      </div>
      <div class="summary">
        <h1>{{ recipe.title }}</h1>
        <p class="desc">{{ recipe.description }}</p>

        <ul class="facts">
          <li>⏱️ Время: <b>{{ recipe.cook_time_minutes }} мин</b></li>
          <li>🍽️ Порции: <b>{{ recipe.servings }}</b></li>
          <li>📊 Сложность: <b :class="recipe.difficulty">{{ diffLabel }}</b></li>
          <li>🔥 Калории: <b>{{ recipe.calories_per_serving }} ккал/порция</b></li>
          <li>👁️ Просмотры: <b>{{ recipe.views_count }}</b></li>
          <li>👤 Автор: <b>{{ recipe.created_by }}</b></li>
        </ul>

        <div class="actions">
          <button class="btn fav" :class="{ active: isFav }" @click="toggleFav">
            {{ isFav ? '❤️ В избранном' : '🤍 В избранное' }}
          </button>
          <button class="btn" @click="share">📤 Поделиться</button>
          <button class="btn" @click="print">🖨️ Распечатать</button>
        </div>
        <p v-if="copied" class="copied">Ссылка скопирована!</p>
      </div>
    </div>

    <!-- Ингредиенты с чекбоксами -->
    <section class="section">
      <h2>🧺 Ингредиенты <small>(отмечайте то, что уже есть)</small></h2>
      <ul class="ingr">
        <li v-for="(ing, i) in recipe.ingredients_list" :key="i">
          <label>
            <input type="checkbox" v-model="checked[i]" />
            <span :class="{ done: checked[i] }">{{ ing }}</span>
          </label>
        </li>
      </ul>
      <p class="have"><b>{{ haveCount }}</b> из <b>{{ recipe.ingredients_list.length }}</b> ингредиентов отмечено</p>
    </section>

    <!-- Пошаговое приготовление с фото -->
    <section class="section">
      <h2>👨‍🍳 Пошаговое приготовление</h2>
      <RecipeSteps :steps="recipe.steps || []" />
    </section>

    <!-- Галерея промежуточных фото -->
    <section class="section" v-if="(recipe.steps || []).some(s => s.image_url)">
      <h2>📸 Галерея приготовления</h2>
      <div class="gallery">
        <img
          v-for="s in recipe.steps.filter(x => x.image_url)"
          :key="'g' + s.id"
          v-lazy="s.image_url"
          :alt="`${s.step_number}. ${s.title}`"
          loading="lazy"
          @click="openFromGallery(s)"
        />
      </div>
    </section>

    <!-- Похожие рецепты -->
    <section class="section" v-if="recipe.similar?.length">
      <h2>💡 Похожие рецепты</h2>
      <div class="similar-grid">
        <RecipeCard v-for="r in recipe.similar.slice(0, 6)" :key="r.id" :recipe="{ ...r, category: recipe.category }" />
      </div>
    </section>

    <!-- Комментарии (заглушка) -->
    <section class="section comments">
      <h2>💬 Комментарии</h2>
      <p class="soon">Раздел комментариев скоро появится — мы работаем над ним! 🚧</p>
    </section>
  </article>
</template>

<script setup>
/* ШАГ 34: детальная страница рецепта — breadcrumbs, большое фото, ингредиенты с чекбоксами,
   шаги с фото (RecipeSteps), галерея, похожие рецепты, избранное/поделиться/печать, SEO meta */
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import { useRecipeStore } from '../stores/recipes'
import RecipeCard from '../components/RecipeCard.vue'
import RecipeSteps from '../components/RecipeSteps.vue'

const store = useRecipeStore()
const route = useRoute()
const checked = ref([])
const zoom = ref(false)
const copied = ref(false)

const recipe = computed(() => store.currentRecipe)
const diffLabel = computed(() => ({ easy: 'Легко', medium: 'Средне', hard: 'Сложно' }[recipe.value?.difficulty] || ''))
const haveCount = computed(() => checked.value.filter(Boolean).length)

// Избранное — localStorage (без авторизации)
const FAV_KEY = 'fav-slugs'
const favs = ref(JSON.parse(localStorage.getItem(FAV_KEY) || '[]'))
const isFav = computed(() => favs.value.includes(route.params.slug))
function toggleFav() {
  favs.value = isFav.value ? favs.value.filter((s) => s !== route.params.slug) : [...favs.value, route.params.slug]
  localStorage.setItem(FAV_KEY, JSON.stringify(favs.value))
}

async function share() {
  const url = location.href
  if (navigator.share) { try { await navigator.share({ title: recipe.value.title, url }); return } catch {} }
  await navigator.clipboard.writeText(url)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

function print() { window.print() }

function openFromGallery(step) {
  document.getElementById(`step-${step.step_number}`)?.scrollIntoView({ behavior: 'smooth' })
}

// ШАГ 42: SEO meta / OG / Schema.org на странице рецепта
useHead(computed(() => {
  const r = recipe.value
  if (!r || r.notFound) return { title: 'Рецепт не найден — Что приготовить?' }
  return {
    title: `${r.title} — Что приготовить?`,
    meta: [
      { name: 'description', content: r.description },
      { property: 'og:title', content: r.title },
      { property: 'og:description', content: r.description },
      { property: 'og:image', content: r.main_image_url },
      { property: 'og:type', content: 'article' },
    ],
    script: [{
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org', '@type': 'Recipe', name: r.title, description: r.description,
        image: r.main_image_url, author: { '@type': 'Person', name: r.created_by },
        prepTime: `PT${r.cook_time_minutes}M`, recipeCategory: r.category?.name,
        recipeIngredient: r.ingredients_list,
        recipeInstructions: (r.steps || []).map((s) => ({ '@type': 'HowToStep', name: s.title, text: s.description })),
        nutrition: { '@type': 'NutritionInformation', calories: `${r.calories_per_serving} kcal` },
      }),
    }],
  }
}))

async function load(slug) {
  zoom.value = false
  checked.value = []
  try { await store.fetchRecipeBySlug(slug) } catch { /* 404 обработан стором */ }
}

onMounted(() => load(route.params.slug))
watch(() => route.params.slug, (s) => s && load(s))
</script>

<style scoped>
.loading, .notfound { text-align: center; padding: 60px 20px; color: #777; }
.btn { display: inline-block; padding: 10px 18px; border-radius: 10px; border: none; background: #ff9800; color: #fff; font-weight: 600; cursor: pointer; text-decoration: none; }
.crumbs { margin-bottom: 14px; color: #888; font-size: .9rem; }
.crumbs a { color: #ff9800; text-decoration: none; }
.top { display: grid; grid-template-columns: 1.1fr 1fr; gap: 26px; margin-bottom: 10px; }
.photo-box { position: relative; border-radius: 16px; overflow: hidden; cursor: zoom-in; background: #eee; }
.photo-box img { width: 100%; display: block; transition: transform .3s; }
.photo-box img.zoomed { transform: scale(1.6); transform-origin: center; }
.hint { position: absolute; bottom: 8px; right: 10px; background: rgba(0,0,0,.55); color: #fff; padding: 3px 8px; border-radius: 6px; }
.summary h1 { margin: 0 0 8px; font-size: 1.8rem; }
.desc { color: #555; line-height: 1.5; }
.facts { list-style: none; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; color: #444; }
.easy { color: #16a34a; } .medium { color: #ca8a04; } .hard { color: #dc2626; }
.actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
.actions .btn { background: #f3f4f6; color: #333; }
.actions .fav { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
.actions .fav.active { background: #ffe0e0; }
.copied { color: #16a34a; font-size: .85rem; }
.section { margin-top: 36px; }
.section h2 { margin-bottom: 14px; }
.section small { color: #999; font-weight: normal; }
.ingr { list-style: none; padding: 0; columns: 2; }
.ingr label { display: flex; gap: 10px; align-items: baseline; padding: 4px 0; cursor: pointer; }
.ingr .done { text-decoration: line-through; color: #aaa; }
.have { color: #666; margin-top: 10px; }
.gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.gallery img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 10px; cursor: pointer; }
.similar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.comments .soon { background: #fff8e1; border: 1px dashed #ffd54f; border-radius: 10px; padding: 18px; color: #8d6e00; }
@media (max-width: 800px) { .top { grid-template-columns: 1fr; } .ingr { columns: 1; } .facts { grid-template-columns: 1fr; } }
</style>
