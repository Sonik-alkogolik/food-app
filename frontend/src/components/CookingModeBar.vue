<template>
  <div v-if="cookingActive" class="cooking-bar">
    <span class="label">👨‍🍳 <b>Режим готовки</b></span>
    <span class="awake">
      {{ screenAwake ? '☀️ экран не гаснет' : '🌙 экран может погаснуть' }}
    </span>
    <button type="button" class="exit" @click="disableCooking()">✖️ Выйти</button>
  </div>
</template>

<script setup>
/* Панель режима готовки: висит поверх страницы, пока режим включён. */
import { useCookingMode } from '../composables/useCookingMode'

const { active: cookingActive, screenAwake, disable: disableCooking } = useCookingMode()
</script>

<style scoped>
.cooking-bar {
  position: fixed;
  left: 50%;
  bottom: 16px;
  transform: translateX(-50%);
  z-index: 900;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: center;
  padding: 10px 16px;
  border-radius: 999px;
  background: #ff9800;
  color: #fff;
  box-shadow: 0 6px 20px rgba(0, 0, 0, .25);
  font-size: .95rem;
}
.awake { opacity: .9; }
.exit {
  border: none;
  background: rgba(0, 0, 0, .2);
  color: #fff;
  border-radius: 999px;
  padding: 6px 12px;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
.exit:hover { background: rgba(0, 0, 0, .35); }
@media (max-width: 800px) {
  .cooking-bar { left: 12px; right: 12px; transform: none; border-radius: 16px; }
}
</style>
