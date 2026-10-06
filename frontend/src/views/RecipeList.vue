<template>
  <div class="container">
    <h1>🍳 Что приготовить сегодня?</h1>
    <div v-if="store.recipes.length === 0" class="loading">Загрузка рецептов...</div>
    <div class="grid">
      <div v-for="recipe in store.recipes" :key="recipe.id" class="card">
        <h3>{{ recipe.title }}</h3>
        <span class="badge">{{ recipe.category?.name }}</span>
        <p class="time">⏱️ {{ recipe.cook_time_minutes }} мин.</p>
        <p class="ingredients">{{ recipe.ingredients.substring(0, 60) }}...</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRecipeStore } from '../stores/recipes'

const store = useRecipeStore()

onMounted(async () => {
  await store.fetchRecipes()
})
</script>

<style scoped>
.container { max-width: 1200px; margin: 0 auto; padding: 20px; font-family: sans-serif; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.card { border: 1px solid #eee; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.badge { background: #ff9800; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
.time { color: #666; font-weight: bold; }
.ingredients { color: #555; font-size: 0.9em; line-height: 1.4; }
.loading { color: #888; padding: 40px; text-align: center; }
</style>
