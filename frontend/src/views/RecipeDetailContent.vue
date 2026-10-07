<template>
  <article class="detail">
    <nav class="crumbs" v-if="recipe.category">
      <router-link to="/">Главная</router-link> ›
      <router-link :to="`/categories/${recipe.category.slug}`">{{ recipe.category.name }}</router-link> ›
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
          <li>👤 Автор: <b>{{ recipe.created_by }}</b></li>
        </ul>
      </div>
    </div>

    <section class="section" v-if="ingredients.length">
      <h2>🧺 Ингредиенты</h2>
      <ul class="ingr">
        <li v-for="(ing, i) in ingredients" :key="i">
          <label>
            <input type="checkbox" v-model="checked[i]" />
            <span :class="{ done: checked[i] }">{{ ing }}</span>
          </label>
        </li>
      </ul>
    </section>

    <section class="section" v-if="recipe.steps?.length">
      <h2>👨‍🍳 Пошаговое приготовление</h2>
      <RecipeSteps :steps="recipe.steps" />
    </section>

    <section class="section" v-else>
      <h2>👨‍🍳 Приготовление</h2>
      <p class="instructions">{{ recipe.instructions }}</p>
    </section>
  </article>
</template>

<script setup>
/* Переиспользуемое содержимое карточки рецепта (используется на /random) */
import { computed, ref, watch } from 'vue'
import RecipeSteps from '../components/RecipeSteps.vue'

const props = defineProps({ recipe: { type: Object, required: true } })
const zoom = ref(false)
const checked = ref([])

const ingredients = computed(() => props.recipe.ingredients_list || [])
const diffLabel = computed(() => ({ easy: 'Легко', medium: 'Средне', hard: 'Сложно' }[props.recipe.difficulty] || ''))

watch(() => props.recipe, () => { zoom.value = false; checked.value = [] })
</script>

<style scoped>
.crumb s { margin-bottom: 14px; color: #888; font-size: .9rem; display: block; }
.crumbs a { color: #ff9800; text-decoration: none; }
.top { display: grid; grid-template-columns: 1.1fr 1fr; gap: 26px; }
.photo-box { position: relative; border-radius: 16px; overflow: hidden; cursor: zoom-in; background: #eee; }
.photo-box img { width: 100%; display: block; transition: transform .3s; }
.photo-box img.zoomed { transform: scale(1.6); }
.hint { position: absolute; bottom: 8px; right: 10px; background: rgba(0,0,0,.55); color: #fff; padding: 3px 8px; border-radius: 6px; }
.summary h1 { margin: 0 0 8px; font-size: 1.7rem; }
.desc { color: #555; line-height: 1.5; }
.facts { list-style: none; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; color: #444; }
.easy { color: #16a34a; } .medium { color: #ca8a04; } .hard { color: #dc2626; }
.section { margin-top: 30px; }
.ingr { list-style: none; padding: 0; columns: 2; }
.ingr label { display: flex; gap: 10px; align-items: baseline; padding: 4px 0; cursor: pointer; }
.ingr .done { text-decoration: line-through; color: #aaa; }
.instructions { white-space: pre-line; line-height: 1.6; color: #444; }
@media (max-width: 800px) { .top { grid-template-columns: 1fr; } .ingr { columns: 1; } }
</style>
