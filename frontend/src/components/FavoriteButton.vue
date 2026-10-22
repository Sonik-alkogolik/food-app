<template>
  <div class="fav-wrap">
    <button
      class="fav-btn"
      :class="{ active: isFav, compact }"
      type="button"
      :title="isFav ? 'Убрать из избранного' : 'Добавить в избранное'"
      :aria-label="isFav ? 'Убрать из избранного' : 'Добавить в избранное'"
      :aria-pressed="isFav"
      @click.stop.prevent="onToggle"
    >
      <span class="icon">{{ isFav ? '❤️' : '🤍' }}</span>
      <span v-if="!compact" class="label">{{ isFav ? 'В избранном' : 'В избранное' }}</span>
    </button>

    <!-- Подсказка для гостя: локальное избранное не синхронизируется между устройствами -->
    <div v-if="hint" class="hint" @click.stop>
      <span>{{ hint }}</span>
      <router-link v-if="!auth.isAuthenticated" :to="loginLink">Войти</router-link>
    </div>
  </div>
</template>

<script setup>
/* Кнопка-сердечко избранного. Работает и для гостя (локально, в localStorage),
   и для авторизованного пользователя (на сервере). */
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useFavoritesStore } from '../stores/favorites'

const props = defineProps({
  recipe: { type: Object, required: true },
  compact: { type: Boolean, default: false },
})

const auth = useAuthStore()
const favorites = useFavoritesStore()
const route = useRoute()

const hint = ref('')
let hintTimer = null

const isFav = computed(() => favorites.isFavorite(props.recipe))
const loginLink = computed(() => ({ path: '/login', query: { redirect: route.fullPath } }))

function showHint(text) {
  hint.value = text
  clearTimeout(hintTimer)
  hintTimer = setTimeout(() => (hint.value = ''), 4000)
}

async function onToggle() {
  try {
    const added = await favorites.toggle(props.recipe)
    if (!auth.isAuthenticated) {
      showHint(
        added
          ? 'Добавлено в избранное этого браузера. Войдите, чтобы синхронизировать между устройствами.'
          : 'Убрано из избранного этого браузера.',
      )
    }
  } catch (e) {
    showHint(e.response?.data?.message || 'Не удалось изменить избранное. Попробуйте ещё раз.')
  }
}
</script>

<style scoped>
.fav-wrap { position: relative; display: inline-block; }
.fav-btn {
  display: inline-flex; align-items: center; gap: 6px;
  border: 1px solid #ffcc80; background: #fff3e0; color: #e65100;
  border-radius: 999px; padding: 8px 14px; font-weight: 600; font-size: .9rem;
  cursor: pointer; transition: background .2s, transform .15s, box-shadow .2s;
}
.fav-btn:hover { background: #ffe7c2; }
.fav-btn:active { transform: scale(.94); }
.fav-btn.active { background: #ffe0e0; border-color: #ff8a80; }
.fav-btn.compact { padding: 6px 10px; font-size: .95rem; line-height: 1; }
.fav-btn .icon { font-size: 1.05rem; }
.hint {
  position: absolute; top: calc(100% + 8px); right: 0; z-index: 20;
  width: 250px; padding: 10px 12px; border-radius: 10px;
  background: #333; color: #fff; font-size: .8rem; line-height: 1.4;
  box-shadow: 0 8px 20px rgba(0,0,0,.25);
}
.hint a { color: #ffb74d; font-weight: 600; margin-left: 6px; }
@media (max-width: 800px) {
  .hint { width: 210px; }
}
</style>
