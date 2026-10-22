<template>
  <div class="account">
    <h1>Личный кабинет</h1>

    <div class="grid">
      <!-- Профиль -->
      <section class="card">
        <h2>👤 Профиль</h2>
        <dl class="profile">
          <div><dt>Имя</dt><dd>{{ auth.user?.name || '—' }}</dd></div>
          <div><dt>Email</dt><dd>{{ auth.user?.email || '—' }}</dd></div>
          <div><dt>Роль</dt><dd>{{ auth.isAdmin ? 'Администратор' : 'Пользователь' }}</dd></div>
          <div><dt>Регистрация</dt><dd>{{ formatDate(auth.user?.created_at) }}</dd></div>
        </dl>
        <button class="btn outline" type="button" :disabled="auth.loading" @click="logout">Выйти из аккаунта</button>
      </section>

      <!-- Смена пароля -->
      <section class="card">
        <h2>🔑 Смена пароля</h2>
        <form class="form" @submit.prevent="changePassword">
          <label>
            <span>Текущий пароль</span>
            <input v-model="pw.current_password" type="password" autocomplete="current-password" />
          </label>
          <p v-if="fieldError('current_password')" class="error">{{ fieldError('current_password') }}</p>

          <label>
            <span>Новый пароль</span>
            <input v-model="pw.password" type="password" autocomplete="new-password" placeholder="Минимум 8 символов" />
          </label>
          <p v-if="fieldError('password')" class="error">{{ fieldError('password') }}</p>

          <label>
            <span>Повторите новый пароль</span>
            <input v-model="pw.password_confirmation" type="password" autocomplete="new-password" />
          </label>
          <p v-if="fieldError('password_confirmation')" class="error">{{ fieldError('password_confirmation') }}</p>

          <p v-if="pwError" class="error">{{ pwError }}</p>
          <p v-if="pwOk" class="ok">Пароль обновлён.</p>

          <button class="btn" type="submit" :disabled="auth.loading">
            {{ auth.loading ? 'Сохраняем…' : 'Сменить пароль' }}
          </button>
        </form>
      </section>

      <!-- Восстановление пароля -->
      <section class="card">
        <h2>📨 Забыли пароль?</h2>
        <p class="note">Отправим ссылку для смены пароля на указанный email.</p>
        <form class="form" @submit.prevent="sendReset">
          <label>
            <span>Email</span>
            <input v-model.trim="reset.email" type="email" autocomplete="email" placeholder="you@example.com" />
          </label>
          <p v-if="resetError" class="error">{{ resetError }}</p>

          <div v-if="resetUrl" class="reset-box">
            <p>Ссылка для смены пароля:</p>
            <a :href="resetUrl">{{ resetUrl }}</a>
          </div>
          <p v-else-if="resetOk" class="ok">Письмо со ссылкой отправлено на {{ reset.email }}.</p>

          <button class="btn" type="submit" :disabled="auth.loading">Отправить ссылку</button>
        </form>
      </section>
    </div>
  </div>
</template>

<script setup>
/* Личный кабинет: профиль, смена пароля, восстановление пароля, выход. */
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import { useAuthStore } from '../stores/auth'
import { useFavoritesStore } from '../stores/favorites'

const auth = useAuthStore()
const favorites = useFavoritesStore()
const router = useRouter()

useHead({ title: 'Личный кабинет — Что приготовить?' })

const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwError = ref('')
const pwOk = ref(false)

const reset = reactive({ email: '' })
const resetError = ref('')
const resetUrl = ref('')
const resetOk = ref(false)

const dateFormat = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? '—' : dateFormat.format(d)
}

function fieldError(field) {
  const errors = auth.errors?.[field]
  if (!errors) return ''
  return Array.isArray(errors) ? errors[0] : String(errors)
}

async function changePassword() {
  pwError.value = ''
  pwOk.value = false
  try {
    await auth.changePassword({ ...pw })
    pwOk.value = true
    pw.current_password = ''
    pw.password = ''
    pw.password_confirmation = ''
  } catch {
    pwError.value = Object.keys(auth.errors || {}).length ? '' : auth.error
  }
}

async function sendReset() {
  resetError.value = ''
  resetUrl.value = ''
  resetOk.value = false
  try {
    const data = await auth.forgotPassword(reset.email)
    const url = data?.data?.reset_url || null
    if (url) resetUrl.value = url
    else resetOk.value = true
  } catch {
    resetError.value = Object.keys(auth.errors || {}).length ? '' : auth.error
  }
}

async function logout() {
  await auth.logout()
  favorites.resetServerState()
  router.push('/')
}
</script>

<style scoped>
.account h1 { margin-bottom: 20px; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px; align-items: start; }
.card { background: #fff; border: 1px solid #eee; border-radius: 16px; padding: 20px; box-shadow: 0 4px 16px rgba(0,0,0,.05); }
.card h2 { margin: 0 0 14px; font-size: 1.15rem; }
.profile { margin: 0 0 18px; display: flex; flex-direction: column; gap: 8px; }
.profile div { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px dashed #eee; padding-bottom: 6px; }
.profile dt { color: #777; font-size: .9rem; }
.profile dd { margin: 0; font-weight: 600; text-align: right; word-break: break-all; }
.form { display: flex; flex-direction: column; gap: 6px; }
label { display: flex; flex-direction: column; gap: 5px; margin-top: 8px; }
label span { font-size: .85rem; color: #555; font-weight: 600; }
input { padding: 10px 12px; border: 1px solid #ddd; border-radius: 10px; font: inherit; }
input:focus { outline: none; border-color: #ff9800; }
.note { margin: 0 0 6px; color: #777; font-size: .88rem; }
.error { color: #dc2626; font-size: .84rem; margin: 2px 0 0; }
.ok { color: #16a34a; font-size: .86rem; margin: 4px 0 0; }
.btn { margin-top: 14px; padding: 11px 18px; border: none; border-radius: 10px; background: #ff9800; color: #fff; font-weight: 600; cursor: pointer; }
.btn:disabled { opacity: .6; cursor: default; }
.btn.outline { background: #fff; color: #e65100; border: 1px solid #ffcc80; width: 100%; }
.reset-box { margin-top: 10px; background: #fff8e1; border: 1px dashed #ffd54f; border-radius: 10px; padding: 12px; font-size: .85rem; word-break: break-all; }
.reset-box p { margin: 0 0 6px; color: #8d6e00; }
.reset-box a { color: #e65100; }
@media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
</style>
