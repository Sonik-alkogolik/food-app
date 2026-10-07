<template>
  <router-link :to="`/recipes/${recipe.slug}`" class="recipe-card">
    <div class="photo-wrap">
      <img v-lazy="photoUrl" :alt="recipe.title" class="photo" loading="lazy" @error="onError" />
      <span v-if="recipe.category" class="badge" :style="{ background: categoryColor }">
        {{ recipe.category.name }}
      </span>
    </div>
    <div class="body">
      <h3 class="title">{{ recipe.title }}</h3>
      <div class="meta">
        <span title="Время приготовления">⏱️ {{ recipe.cook_time_minutes }} мин</span>
        <span :class="['diff', recipe.difficulty]" title="Сложность">
          {{ difficultyLabel }}
        </span>
        <span v-if="recipe.calories_per_serving" title="Калории на порцию">🔥 {{ recipe.calories_per_serving }} ккал</span>
      </div>
      <div class="views" title="Просмотры">👁️ {{ formatViews(recipe.views_count) }}</div>
    </div>
  </router-link>
</template>

<script setup>
/* ШАГ 29: карточка рецепта — фото (lazy), категория, время, сложность, калории, просмотры */
import { computed } from 'vue'

const props = defineProps({ recipe: { type: Object, required: true } })

const photoUrl = computed(() => props.recipe.main_image_url || '/img/placeholder.svg')

const CATEGORY_COLORS = {
  Завтраки: '#f59e0b', Супы: '#3b82f6', 'Вторые блюда': '#8b5cf6', Салаты: '#22c55e',
  Десерты: '#ec4899', Выпечка: '#d97706', Закуски: '#14b8a6', Напитки: '#6366f1',
  Соусы: '#ef4444', Гарниры: '#84cc16',
}
const categoryColor = computed(() => CATEGORY_COLORS[props.recipe.category?.name] || '#ff9800')

const DIFFICULTY = { easy: ['Легко', 'green'], medium: ['Средне', 'yellow'], hard: ['Сложно', 'red'] }
const difficultyLabel = computed(() => DIFFICULTY[props.recipe.difficulty]?.[0] || '')

function formatViews(n) {
  if (!n) return 0
  return n >= 1000 ? (n / 1000).toFixed(1).replace('.', ',') + 'k' : n
}

function onError(e) {
  e.target.src = '/img/placeholder.svg'
}
</script>

<style scoped>
.recipe-card {
  display: block; background: #fff; border-radius: 14px; overflow: hidden;
  text-decoration: none; color: inherit; border: 1px solid #eee;
  box-shadow: 0 2px 8px rgba(0,0,0,.05); transition: transform .2s ease, box-shadow .2s ease;
}
.recipe-card:hover { transform: scale(1.03); box-shadow: 0 10px 28px rgba(0,0,0,.15); }
.photo-wrap { position: relative; aspect-ratio: 4/3; background: #f3f4f6; }
.photo { width: 100%; height: 100%; object-fit: cover; display: block; }
.badge { position: absolute; top: 10px; left: 10px; color: #fff; padding: 4px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; }
.body { padding: 12px 14px 14px; }
.title { margin: 0 0 8px; font-size: 1.02rem; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.meta { display: flex; gap: 10px; flex-wrap: wrap; font-size: .82rem; color: #555; }
.diff.green { color: #16a34a; } .diff.yellow { color: #ca8a04; } .diff.red { color: #dc2626; }
.views { margin-top: 8px; font-size: .78rem; color: #999; }
</style>
