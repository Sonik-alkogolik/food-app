<template>
  <nav style="padding: 15px; background: #ff9800; color: white; display:flex; gap:20px; align-items:center;">
    <router-link to="/" style="color: white; text-decoration: none; font-weight: bold;">🍳 Что приготовить?</router-link>
    <router-link to="/fridge" style="color: white; text-decoration: none;">🧊 Холодильник</router-link>
    <router-link to="/recipes" style="color: white; text-decoration: none;">📖 Все рецепты</router-link>
  </nav>
  <main>
    <router-view />
  </main>
</template>
