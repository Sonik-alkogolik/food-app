<template>
  <div class="random-page">
    <div class="head">
      <h1>🎲 Случайный рецепт</h1>
      <button class="btn" @click="load" :disabled="store.detailLoading">Другой случайный рецепт</button>
    </div>
    <div v-if="store.detailLoading || !recipe" class="loading">Ищем рецепт…</div>
    <RecipeDetailContent v-else :recipe="recipe" />
  </div>
</template>

<script setup>
/* Страница /random — случайный рецепт с полной карточкой */
import { computed, onMounted } from 'vue'
import { useHead } from '@unhead/vue'
import { useRecipeStore } from '../stores/recipes'
import RecipeDetailContent from './RecipeDetailContent.vue'

const store = useRecipeStore()
const recipe = computed(() => store.randomRecipe)
useHead({ title: 'Случайный рецепт — Что приготовить?' })

function load() { store.fetchRandomRecipe() }
onMounted(load)
</script>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.head h1 { margin: 0; }
.btn { padding: 10px 18px; background: #ff9800; color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
.btn:disabled { opacity: .5; }
.loading { color: #888; padding: 40px; text-align: center; }
</style>
