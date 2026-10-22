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
          <li>🍽️ Порции: <b>{{ servings }}</b></li>
          <li>📊 Сложность: <b :class="recipe.difficulty">{{ diffLabel }}</b></li>
          <li>🔥 Калории: <b>{{ recipe.calories_per_serving }} ккал/порция</b></li>
          <li>👁️ Просмотры: <b>{{ recipe.views_count }}</b></li>
          <li>👤 Автор: <b>{{ recipe.created_by }}</b></li>
        </ul>

        <div class="actions">
          <FavoriteButton :recipe="recipe" />
          <button class="btn" @click="share">📤 Поделиться</button>
          <button class="btn" @click="print">🖨️ Распечатать</button>
          <button class="btn cooking-btn" :class="{ on: cookingActive }" @click="toggleCooking">
            {{ cookingActive ? '✖️ Выйти из режима готовки' : '👨‍🍳 Готовим' }}
          </button>
        </div>
        <p v-if="copied" class="copied">Ссылка скопирована!</p>
      </div>
    </div>

    <!-- Видео приготовления -->
    <section class="section" v-if="recipe.video_url">
      <h2>🎬 Как готовить — видео</h2>
      <div class="video-wrap">
        <iframe
          :src="videoEmbedUrl"
          :title="`Видео приготовления: ${recipe.title}`"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
        ></iframe>
      </div>
      <a class="video-link" :href="recipe.video_url" target="_blank" rel="noopener">
        Открыть видео на YouTube ↗
      </a>
    </section>

    <!-- Ингредиенты с чекбоксами -->
    <section class="section">
      <h2>🧺 Ингредиенты <small>(отмечайте то, что уже есть)</small></h2>

      <!-- Порции: пересчёт количеств на клиенте, без запроса к серверу -->
      <div class="servings">
        <span class="servings-label">Порции:</span>
        <button type="button" class="serv-btn" @click="changeServings(-1)" :disabled="servings <= 1" aria-label="Меньше порций">−</button>
        <b class="serv-value">{{ servings }}</b>
        <button type="button" class="serv-btn" @click="changeServings(1)" :disabled="servings >= MAX_SERVINGS" aria-label="Больше порций">+</button>
        <button v-if="servings !== baseServings" type="button" class="serv-reset" @click="servings = baseServings">
          Сбросить ({{ baseServings }})
        </button>
      </div>

      <ul class="ingr">
        <li v-for="(ing, i) in scaledIngredients" :key="i">
          <label>
            <input type="checkbox" v-model="checked[i]" />
            <span :class="{ done: checked[i] }">{{ ing }}</span>
          </label>
        </li>
      </ul>
      <p class="have"><b>{{ haveCount }}</b> из <b>{{ recipe.ingredients_list.length }}</b> ингредиентов отмечено</p>
    </section>

    <!-- Пошаговое приготовление с фото -->
    <section class="section" id="steps">
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

    <!-- Фотоотчёты: как блюдо выглядит в реальности -->
    <section class="section">
      <RecipeResults :recipe="recipe" />
    </section>

    <!-- Похожие рецепты -->
    <section class="section" v-if="recipe.similar?.length">
      <h2>💡 Похожие рецепты</h2>
      <div class="similar-grid">
        <RecipeCard v-for="r in recipe.similar.slice(0, 6)" :key="r.id" :recipe="{ ...r, category: recipe.category }" />
      </div>
    </section>

    <!-- Комментарии -->
    <section class="section comments">
      <h2>💬 Комментарии</h2>
      <CommentsSection :recipe="recipe" />
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
import FavoriteButton from '../components/FavoriteButton.vue'
import CommentsSection from '../components/CommentsSection.vue'
import RecipeResults from '../components/RecipeResults.vue'
import { useCookingMode } from '../composables/useCookingMode'
import { scaleIngredients } from '../utils/ingredients'

const store = useRecipeStore()
const route = useRoute()
// Режим готовки — общее состояние приложения, поэтому его рефы
// разворачиваем в setup: так они корректно подставляются в шаблон.
const { active: cookingActive, toggle: toggleCookingMode } = useCookingMode()
const checked = ref([])
const zoom = ref(false)
const copied = ref(false)
const servings = ref(1)

// Больше 50 порций никто не готовит, а бесконечный счётчик мешал бы.
const MAX_SERVINGS = 50

const recipe = computed(() => store.currentRecipe)
const diffLabel = computed(() => ({ easy: 'Легко', medium: 'Средне', hard: 'Сложно' }[recipe.value?.difficulty] || ''))
const haveCount = computed(() => checked.value.filter(Boolean).length)

// Порции, на которые рассчитан рецепт, и пересчитанные под выбранное
// число количества. Всё считается на клиенте: сервер отдаёт рецепт как есть.
const baseServings = computed(() => Number(recipe.value?.servings) || 1)
const scaledIngredients = computed(() => {
  const list = recipe.value?.ingredients_list || []
  return scaleIngredients(list, servings.value / baseServings.value)
})

function changeServings(delta) {
  const next = servings.value + delta
  if (next < 1 || next > MAX_SERVINGS) return
  servings.value = next
}

/**
 * Адрес видео для встраивания в плеер.
 * Источники отдают ссылку в разных видах (watch?v=, /shorts/, /embed/) —
 * приводим всё к /embed/VIDEO_ID, иначе iframe refuses показывать ролик.
 */
const videoEmbedUrl = computed(() => {
  const url = (recipe.value?.video_url || '').trim()
  if (!url) return ''
  const match = url.match(/(?:v=|youtu\.be\/|shorts\/|embed\/)([A-Za-z0-9_-]{6,})/)
  return match ? `https://www.youtube.com/embed/${match[1]}` : url
})

// Избранное живёт в сторе избранного (локально для гостя, на сервере — для пользователя)

async function share() {
  const url = location.href
  if (navigator.share) { try { await navigator.share({ title: recipe.value.title, url }); return } catch {} }
  await navigator.clipboard.writeText(url)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

function print() { window.print() }

/**
 * Режим готовки: крупный шрифт и негаснущий экран. Включаем — сразу
 * показываем шаги, чтобы не искать их по странице.
 */
async function toggleCooking() {
  await toggleCookingMode()
  if (cookingActive.value) {
    document.getElementById('steps')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

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
        ...(r.video_url ? {
          video: [{ '@type': 'VideoObject', name: `Как готовить: ${r.title}`, embedUrl: r.video_url, description: r.description }],
        } : {}),
      }),
    }],
  }
}))

async function load(slug) {
  zoom.value = false
  checked.value = []
  try { await store.fetchRecipeBySlug(slug) } catch { /* 404 обработан стором */ }
  // Новый рецепт — прежнее число порций не подходит.
  servings.value = Number(store.currentRecipe?.servings) || 1
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
.actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 14px; }
.actions .btn { background: #f3f4f6; color: #333; }
.cooking-btn.on { background: #ff9800; color: #fff; }
.copied { color: #16a34a; font-size: .85rem; }
.section { margin-top: 36px; }
.section h2 { margin-bottom: 14px; }
.video-wrap { position: relative; aspect-ratio: 16/9; border-radius: 14px; overflow: hidden; background: #000; }
.video-wrap iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.video-link { display: inline-block; margin-top: 10px; color: #ff9800; font-weight: 600; text-decoration: none; }
.video-link:hover { text-decoration: underline; }
.section small { color: #999; font-weight: normal; }
.ingr { list-style: none; padding: 0; columns: 2; }
.servings { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.servings-label { color: #555; font-weight: 600; }
.serv-btn {
  width: 34px; height: 34px; border-radius: 50%; border: 1px solid #ddd; background: #fff;
  font-size: 1.2rem; line-height: 1; cursor: pointer; color: #333;
}
.serv-btn:hover:not(:disabled) { border-color: #ff9800; color: #e65100; }
.serv-btn:disabled { opacity: .4; cursor: default; }
.serv-value { min-width: 30px; text-align: center; font-size: 1.2rem; }
.serv-reset { border: none; background: none; color: #ff9800; font: inherit; font-size: .9rem; cursor: pointer; text-decoration: underline; }
.ingr label { display: flex; gap: 10px; align-items: baseline; padding: 4px 0; cursor: pointer; }
.ingr .done { text-decoration: line-through; color: #aaa; }
.have { color: #666; margin-top: 10px; }
.gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.gallery img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 10px; cursor: pointer; }
.similar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
@media (max-width: 800px) { .top { grid-template-columns: 1fr; } .ingr { columns: 1; } .facts { grid-template-columns: 1fr; } }
</style>
