<template>
  <div>
    <h1>Категории блюд</h1>
    <div class="cats-grid">
      <router-link v-for="c in store.categories" :key="c.id" :to="`/categories/${c.slug}`" class="cat-card">
        <img v-lazy="c.image_url || '/img/placeholder.svg'" :alt="c.name" loading="lazy" />
        <div class="cat-info">
          <b>{{ c.name }}</b>
          <small>{{ c.description }}</small>
          <span class="cnt">{{ c.recipes_count }} рецептов</span>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useHead } from '@unhead/vue'
import { useRecipeStore } from '../stores/recipes'

const store = useRecipeStore()
useHead({ title: 'Категории рецептов — Что приготовить?' })
onMounted(() => store.fetchCategories())
</script>

<style scoped>
h1 { margin-bottom: 18px; }
.cats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.cat-card { position: relative; border-radius: 14px; overflow: hidden; text-decoration: none; color: #fff; aspect-ratio: 4/3; background: #eee; transition: transform .2s, box-shadow .2s; }
.cat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,.18); }
.cat-card img { width: 100%; height: 100%; object-fit: cover; }
.cat-info { position: absolute; inset: auto 0 0 0; padding: 34px 14px 12px; background: linear-gradient(transparent, rgba(0,0,0,.8)); }
.cat-info small { display: block; opacity: .85; font-size: .8rem; }
.cnt { font-size: .78rem; color: #ffd54f; }
</style>
