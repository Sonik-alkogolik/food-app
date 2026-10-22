<template>
  <div class="results">
    <div class="head">
      <h2>📸 Фотоотчёты <small v-if="results.length">({{ results.length }})</small></h2>
      <button class="btn add" type="button" @click="openModal">🍳 Я приготовил это!</button>
    </div>

    <p v-if="loading && !results.length" class="empty">Загрузка фотоотчётов…</p>
    <p v-else-if="!results.length && !loadError" class="empty">
      Пока никто не прислал фото этого блюда. Приготовьте — и покажите, как получилось! 📷
    </p>
    <p v-else-if="!results.length && loadError" class="empty">{{ loadError }}</p>

    <ul v-if="results.length" class="grid">
      <li v-for="r in results" :key="r.id" class="card">
        <img :src="r.image_url" :alt="`Фотоотчёт: ${r.user?.name || 'Аноним'}`" loading="lazy" @click="zoom = r.image_url" />
        <div class="meta">
          <span class="author">{{ r.user?.name || 'Аноним' }}</span>
          <span v-if="r.is_guest" class="guest-mark" title="Гость">гость</span>
          <time class="date" :datetime="r.created_at">{{ formatDate(r.created_at) }}</time>
          <button v-if="r.can_delete" class="del" type="button" title="Удалить фотоотчёт" @click="remove(r)">🗑</button>
        </div>
        <p v-if="r.comment" class="comment">{{ r.comment }}</p>
      </li>
    </ul>

    <button v-if="page < lastPage" class="more" type="button" :disabled="loading" @click="loadMore">
      {{ loading ? 'Загрузка…' : 'Показать ещё' }}
    </button>

    <!-- Увеличенная фотография -->
    <div v-if="zoom" class="lightbox" @click="zoom = null">
      <img :src="zoom" alt="Фотоотчёт" />
    </div>

    <!-- Форма добавления фотоотчёта -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal" role="dialog" aria-modal="true" aria-label="Фотоотчёт">
        <h3>🍳 Я приготовил это!</h3>

        <label v-if="!auth.isAuthenticated" class="field">
          <span>Ваше имя</span>
          <input
            id="result-guest-name"
            v-model="guestName"
            type="text"
            maxlength="50"
            placeholder="Как вас зовут"
            autocomplete="nickname"
          />
          <small>Без аккаунта отчёт останется под этим именем. Гостям доступно 3 фото за 15 минут.</small>
        </label>

        <label class="field">
          <span>Фотография блюда</span>
          <input
            id="result-image"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @change="onFile"
          />
          <small>jpg, png или webp, до 5 МБ.</small>
        </label>

        <div v-if="preview" class="preview">
          <img :src="preview" alt="Предпросмотр" />
        </div>

        <label class="field">
          <span>Комментарий <small>(необязательно)</small></span>
          <textarea
            id="result-comment"
            v-model="comment"
            rows="3"
            maxlength="1000"
            placeholder="Что получилось, какие нюансы…"
          ></textarea>
        </label>

        <p v-if="formError" class="error">{{ formError }}</p>

        <div class="modal-foot">
          <button class="btn ghost" type="button" @click="closeModal">Отмена</button>
          <button
            class="btn"
            type="button"
            :disabled="sending || !file || (!auth.isAuthenticated && !guestName.trim())"
            @click="submit"
          >
            {{ sending ? 'Отправка…' : 'Отправить' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/* Фотоотчёты: как блюдо выглядит в реальности. Список, форма с предпросмотром
   выбранного файла (URL.createObjectURL) и удаление своего отчёта. */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api'
import { useAuthStore } from '../stores/auth'

const props = defineProps({
  recipe: { type: Object, required: true },
})

const auth = useAuthStore()
const route = useRoute()

const results = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const sending = ref(false)
const loadError = ref('')
const formError = ref('')

const showModal = ref(false)
const file = ref(null)
const preview = ref('')
const comment = ref('')
// Имя гостя запоминаем — как и в комментариях.
const guestName = ref(localStorage.getItem('comment-guest-name') || '')

const zoom = ref(null)

const slug = computed(() => props.recipe?.slug || route.params.slug)
const MAX_SIZE = 5 * 1024 * 1024

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
    const res = await api.get(`/api/recipes/${encodeURIComponent(slug.value)}/results`, {
      params: { page: targetPage },
      skipAuthRedirect: true,
    })
    const data = res.data?.data || []
    results.value = targetPage === 1 ? data : [...results.value, ...data]
    page.value = res.data?.current_page ?? targetPage
    lastPage.value = res.data?.last_page ?? 1
  } catch (e) {
    if (e.response?.status === 401) {
      loadError.value = 'Войдите в аккаунт, чтобы видеть фотоотчёты.'
    } else {
      loadError.value = e.response?.data?.message || 'Не удалось загрузить фотоотчёты.'
    }
  } finally {
    loading.value = false
  }
}

function loadMore() {
  load(page.value + 1)
}

function openModal() {
  formError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  file.value = null
  comment.value = ''
  formError.value = ''
  releasePreview()
}

/** Освобождаем объектный URL — иначе файл держится в памяти до перезагрузки. */
function releasePreview() {
  if (preview.value) {
    URL.revokeObjectURL(preview.value)
    preview.value = ''
  }
}

function onFile(e) {
  formError.value = ''
  releasePreview()

  const picked = e.target.files?.[0]
  if (!picked) {
    file.value = null
    return
  }

  if (picked.size > MAX_SIZE) {
    file.value = null
    e.target.value = ''
    formError.value = 'Файл больше 5 МБ — выберите поменьше.'
    return
  }

  file.value = picked
  preview.value = URL.createObjectURL(picked)
}

async function submit() {
  if (!file.value) {
    formError.value = 'Прикрепите фотографию блюда.'
    return
  }
  if (!auth.isAuthenticated && !guestName.value.trim()) {
    formError.value = 'Напишите, как вас зовут.'
    return
  }

  sending.value = true
  formError.value = ''
  try {
    const body = new FormData()
    body.append('image', file.value)
    if (comment.value.trim()) body.append('comment', comment.value.trim())
    if (!auth.isAuthenticated) body.append('guest_name', guestName.value.trim())

    // Content-Type не задаём: границу multipart-запроса проставляет сам
    // браузер, а вручную заданный заголовок остался бы без неё.
    const res = await api.post(`/api/recipes/${encodeURIComponent(slug.value)}/results`, body)
    const result = res.data?.data?.result
    if (result) {
      results.value = [{ ...result, can_delete: !!result.can_delete }, ...results.value]
    }
    if (!auth.isAuthenticated) {
      localStorage.setItem('comment-guest-name', guestName.value.trim())
    }
    closeModal()
  } catch (e) {
    if (e.response?.status === 429) {
      formError.value = e.response?.data?.message || 'Слишком много фотоотчётов. Попробуйте позже.'
    } else if (e.response?.status === 422) {
      formError.value =
        e.response?.data?.errors?.image?.[0]
        || e.response?.data?.errors?.guest_name?.[0]
        || e.response?.data?.errors?.comment?.[0]
        || e.response?.data?.message
        || 'Проверьте фотографию.'
    } else {
      formError.value = e.response?.data?.message || 'Не удалось отправить фотоотчёт.'
    }
  } finally {
    sending.value = false
  }
}

async function remove(result) {
  if (!window.confirm('Удалить фотоотчёт?')) return
  try {
    await api.delete(`/api/recipes/${encodeURIComponent(slug.value)}/results/${result.id}`)
    results.value = results.value.filter((r) => r.id !== result.id)
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Не удалось удалить фотоотчёт.'
  }
}

onMounted(() => load(1))
onBeforeUnmount(releasePreview)
</script>

<style scoped>
.head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.head h2 { margin: 0 0 14px; }
.head small { color: #999; font-weight: normal; }
.btn { display: inline-block; padding: 10px 18px; border-radius: 10px; border: none; background: #ff9800; color: #fff; font-weight: 600; cursor: pointer; text-decoration: none; }
.btn:disabled { opacity: .6; cursor: default; }
.btn.ghost { background: #f3f4f6; color: #333; }
.empty { color: #888; padding: 16px 0; }
.grid { list-style: none; padding: 0; margin: 0 0 16px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 8px; }
.card img { width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: 8px; cursor: zoom-in; display: block; }
.meta { display: flex; align-items: center; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
.author { font-weight: 600; color: #333; font-size: .9rem; }
.guest-mark { font-size: .7rem; color: #9aa0a8; border: 1px solid #e2e5ea; border-radius: 999px; padding: 1px 7px; }
.date { color: #999; font-size: .78rem; }
.del { margin-left: auto; border: none; background: none; cursor: pointer; font-size: .95rem; opacity: .6; }
.del:hover { opacity: 1; }
.comment { margin: 6px 0 2px; color: #444; font-size: .88rem; line-height: 1.4; white-space: pre-wrap; word-break: break-word; }
.more { margin-top: 6px; padding: 9px 16px; border: 1px solid #ddd; background: #fff; border-radius: 10px; cursor: pointer; }
.more:disabled { opacity: .6; cursor: default; }
.lightbox { position: fixed; inset: 0; background: rgba(0,0,0,.85); display: flex; align-items: center; justify-content: center; z-index: 60; cursor: zoom-out; }
.lightbox img { max-width: 92vw; max-height: 92vh; border-radius: 10px; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.55); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 70; }
.modal { background: #fff; border-radius: 16px; padding: 20px; width: 100%; max-width: 460px; max-height: 90vh; overflow-y: auto; }
.modal h3 { margin: 0 0 14px; }
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.field > span { font-size: .85rem; color: #666; }
.field input[type="text"], .field textarea { width: 100%; border: 1px solid #ddd; border-radius: 10px; padding: 9px 12px; font: inherit; box-sizing: border-box; }
.field input[type="text"]:focus, .field textarea:focus { outline: none; border-color: #ff9800; }
.field textarea { resize: vertical; }
.field small { color: #999; font-size: .78rem; }
.preview img { width: 100%; max-height: 220px; object-fit: contain; border-radius: 10px; background: #f6f6f6; }
.error { color: #dc2626; font-size: .85rem; margin: 0 0 10px; }
.modal-foot { display: flex; justify-content: flex-end; gap: 10px; }
@media (max-width: 800px) { .grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); } }
</style>
