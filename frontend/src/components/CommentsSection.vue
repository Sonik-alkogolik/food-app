<template>
  <div class="comments">
    <div v-if="loading && !comments.length" class="loading">Загрузка комментариев…</div>

    <p v-else-if="!comments.length && !loadError" class="empty">
      Пока нет комментариев — будьте первым! 💬
    </p>
    <p v-else-if="!comments.length && loadError" class="empty">{{ loadError }}</p>

    <ul v-if="comments.length" class="list">
      <li v-for="c in comments" :key="c.id" class="comment">
        <div class="head">
          <span class="author">{{ c.user?.name || 'Аноним' }}</span>
          <time class="date" :datetime="c.created_at">{{ formatDate(c.created_at) }}</time>
          <button v-if="c.can_delete" class="del" type="button" title="Удалить комментарий" @click="remove(c)">
            🗑
          </button>
        </div>
        <p class="text">{{ c.body }}</p>
      </li>
    </ul>

    <!-- Форма: только для авторизованных -->
    <form v-if="auth.isAuthenticated" class="form" @submit.prevent="submit">
      <textarea
        v-model="text"
        rows="3"
        maxlength="2000"
        placeholder="Поделитесь мнением о рецепте…"
        aria-label="Текст комментария"
      ></textarea>
      <p v-if="formError" class="error">{{ formError }}</p>
      <div class="form-foot">
        <span class="counter">{{ text.length }}/2000</span>
        <button class="btn" type="submit" :disabled="sending || !text.trim()">
          {{ sending ? 'Отправка…' : 'Отправить' }}
        </button>
      </div>
    </form>

    <div v-else class="guest">
      <p>Чтобы оставить комментарий, войдите в аккаунт.</p>
      <router-link class="btn" :to="loginLink">Войти</router-link>
    </div>

    <button v-if="page < lastPage" class="more" type="button" :disabled="loading" @click="loadMore">
      {{ loading ? 'Загрузка…' : 'Показать ещё' }}
    </button>
  </div>
</template>

<script setup>
/* Комментарии к рецепту: список, форма добавления, удаление, пагинация «Показать ещё». */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'

const props = defineProps({
  recipe: { type: Object, required: true },
})

const auth = useAuthStore()
const route = useRoute()

const comments = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const sending = ref(false)
const loadError = ref('')
const formError = ref('')
const text = ref('')

const slug = computed(() => props.recipe?.slug || route.params.slug)
const loginLink = computed(() => ({ path: '/login', query: { redirect: route.fullPath } }))

const dateFormat = new Intl.DateTimeFormat('ru-RU', {
  day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
})

function formatDate(value) {
  if (!value) return ''
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? '' : dateFormat.format(d)
}

async function load(targetPage = 1) {
  if (!slug.value) return
  loading.value = true
  loadError.value = ''
  try {
    const res = await api.get(`/api/recipes/${encodeURIComponent(slug.value)}/comments`, {
      params: { page: targetPage },
      // Комментарии читаются без авторизации — при 401 не уводим гостя со страницы.
      skipAuthRedirect: true,
    })
    const data = res.data?.data || []
    comments.value = targetPage === 1 ? data : [...comments.value, ...data]
    page.value = res.data?.current_page ?? targetPage
    lastPage.value = res.data?.last_page ?? 1
  } catch (e) {
    if (e.response?.status === 401) {
      loadError.value = 'Войдите в аккаунт, чтобы видеть комментарии.'
    } else {
      loadError.value = e.response?.data?.message || 'Не удалось загрузить комментарии.'
    }
  } finally {
    loading.value = false
  }
}

function loadMore() {
  load(page.value + 1)
}

async function submit() {
  const body = text.value.trim()
  if (!body) return
  sending.value = true
  formError.value = ''
  try {
    const res = await api.post(`/api/recipes/${encodeURIComponent(slug.value)}/comments`, { body })
    const comment = res.data?.data?.comment || res.data?.data
    if (comment) comments.value = [{ ...comment, can_delete: true }, ...comments.value]
    text.value = ''
  } catch (e) {
    if (e.response?.status === 401) {
      formError.value = 'Войдите в аккаунт, чтобы оставить комментарий.'
    } else if (e.response?.status === 422) {
      formError.value = e.response?.data?.errors?.body?.[0] || e.response?.data?.message || 'Проверьте текст комментария.'
    } else {
      formError.value = e.response?.data?.message || 'Не удалось отправить комментарий.'
    }
  } finally {
    sending.value = false
  }
}

async function remove(comment) {
  if (!window.confirm('Удалить комментарий?')) return
  try {
    await api.delete(`/api/comments/${comment.id}`)
    comments.value = comments.value.filter((c) => c.id !== comment.id)
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Не удалось удалить комментарий.'
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.comments { margin-top: 6px; }
.loading, .empty { color: #888; padding: 16px 0; }
.list { list-style: none; padding: 0; margin: 0 0 18px; display: flex; flex-direction: column; gap: 12px; }
.comment { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 12px 14px; }
.head { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.author { font-weight: 600; color: #333; }
.date { color: #999; font-size: .8rem; }
.del { margin-left: auto; border: none; background: none; cursor: pointer; font-size: .95rem; opacity: .6; }
.del:hover { opacity: 1; }
.text { margin: 0; color: #444; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
.form { display: flex; flex-direction: column; gap: 8px; }
.form textarea {
  width: 100%; border: 1px solid #ddd; border-radius: 10px; padding: 10px 12px;
  font: inherit; resize: vertical; box-sizing: border-box;
}
.form textarea:focus { outline: none; border-color: #ff9800; }
.form-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.counter { color: #999; font-size: .78rem; }
.btn {
  display: inline-block; padding: 10px 18px; border-radius: 10px; border: none;
  background: #ff9800; color: #fff; font-weight: 600; cursor: pointer; text-decoration: none;
}
.btn:disabled { opacity: .6; cursor: default; }
.error { color: #dc2626; font-size: .85rem; margin: 0; }
.guest { background: #fff8e1; border: 1px dashed #ffd54f; border-radius: 12px; padding: 16px; color: #8d6e00; }
.guest p { margin: 0 0 10px; }
.more { margin-top: 14px; padding: 9px 16px; border: 1px solid #ddd; background: #fff; border-radius: 10px; cursor: pointer; }
.more:disabled { opacity: .6; cursor: default; }
@media (max-width: 800px) { .btn { width: 100%; text-align: center; } }
</style>
