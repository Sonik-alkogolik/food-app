<template>
  <div class="auth-page">
    <div class="card">
      <h1>{{ title }}</h1>
      <p class="sub">{{ subtitle }}</p>

      <!-- Вкладки Вход / Регистрация -->
      <div v-if="mode !== 'forgot'" class="tabs">
        <button
          type="button"
          class="tab"
          :class="{ active: mode === 'login' }"
          @click="switchMode('login')"
        >Вход</button>
        <button
          type="button"
          class="tab"
          :class="{ active: mode === 'register' }"
          @click="switchMode('register')"
        >Регистрация</button>
      </div>

      <!-- Восстановление пароля -->
      <form v-if="mode === 'forgot'" class="form" @submit.prevent="sendReset">
        <p class="note">Укажите email — пришлём ссылку для смены пароля.</p>
        <label>
          <span>Email</span>
          <input v-model.trim="form.email" type="email" autocomplete="email" placeholder="you@example.com" />
        </label>
        <p v-if="fieldError('email')" class="error">{{ fieldError('email') }}</p>
        <p v-if="formError" class="error">{{ formError }}</p>

        <div v-if="resetUrl" class="reset-box">
          <p>Ссылка для смены пароля:</p>
          <a :href="resetUrl" target="_blank" rel="noopener">{{ resetUrl }}</a>
        </div>
        <p v-else-if="resetSent" class="ok">Письмо со ссылкой отправлено на {{ form.email }}.</p>

        <button class="btn" type="submit" :disabled="auth.loading">
          {{ auth.loading ? 'Отправка…' : 'Отправить ссылку' }}
        </button>
        <button class="link-btn" type="button" @click="switchMode('login')">← Назад ко входу</button>
      </form>

      <!-- Вход -->
      <form v-else class="form" @submit.prevent="submit">
        <label v-if="mode === 'register'">
          <span>Имя</span>
          <input v-model.trim="form.name" type="text" autocomplete="name" placeholder="Как вас зовут?" />
        </label>
        <p v-if="fieldError('name')" class="error">{{ fieldError('name') }}</p>

        <label>
          <span>Email</span>
          <input v-model.trim="form.email" type="email" autocomplete="email" placeholder="you@example.com" />
        </label>
        <p v-if="fieldError('email')" class="error">{{ fieldError('email') }}</p>

        <label>
          <span>Пароль</span>
          <input
            v-model="form.password"
            type="password"
            :autocomplete="mode === 'register' ? 'new-password' : 'current-password'"
            placeholder="Минимум 8 символов"
          />
        </label>
        <p v-if="fieldError('password')" class="error">{{ fieldError('password') }}</p>

        <label v-if="mode === 'register'">
          <span>Повторите пароль</span>
          <input v-model="form.password_confirmation" type="password" autocomplete="new-password" placeholder="Ещё раз" />
        </label>
        <p v-if="fieldError('password_confirmation')" class="error">{{ fieldError('password_confirmation') }}</p>

        <p v-if="formError" class="error">{{ formError }}</p>

        <button class="btn" type="submit" :disabled="auth.loading">
          {{ auth.loading ? 'Отправка…' : (mode === 'register' ? 'Создать аккаунт' : 'Войти') }}
        </button>

        <div class="links">
          <button class="link-btn" type="button" @click="switchMode('forgot')">Забыли пароль?</button>
          <span v-if="mode === 'login'">
            Нет аккаунта?
            <button class="link-btn" type="button" @click="switchMode('register')">Зарегистрируйтесь</button>
          </span>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
/* Страница входа/регистрации: две вкладки, показ ошибок валидации (422),
   восстановление пароля, редирект на ?redirect= после входа. */
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const mode = ref(route.query.tab === 'register' ? 'register' : 'login')
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const formError = ref('')
const resetUrl = ref('')
const resetSent = ref(false)

useHead({ title: 'Вход — Что приготовить?' })

const title = computed(() => ({
  login: 'Вход в аккаунт',
  register: 'Регистрация',
  forgot: 'Восстановление пароля',
}[mode.value]))

const subtitle = computed(() => ({
  login: 'Войдите, чтобы синхронизировать избранное между устройствами.',
  register: 'Создайте аккаунт — комментарии и избранное будут доступны на всех устройствах.',
  forgot: 'Пришлём ссылку, по которой можно задать новый пароль.',
}[mode.value]))

function switchMode(next) {
  mode.value = next
  formError.value = ''
  auth.error = null
  auth.errors = {}
  if (next === 'forgot') {
    resetUrl.value = ''
    resetSent.value = false
  }
}

function fieldError(field) {
  const errors = auth.errors?.[field]
  if (!errors) return ''
  return Array.isArray(errors) ? errors[0] : String(errors)
}

async function submit() {
  formError.value = ''
  try {
    if (mode.value === 'register') {
      await auth.register({ ...form })
    } else {
      await auth.login({ email: form.email, password: form.password })
    }
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    router.push(redirect || '/')
  } catch (e) {
    // Ошибки полей показываются рядом с полями; если их нет (например,
    // 429 или 500) — показываем общий текст ответа сервера.
    formError.value = Object.keys(auth.errors || {}).length ? '' : auth.error
  }
}

async function sendReset() {
  formError.value = ''
  resetUrl.value = ''
  resetSent.value = false
  try {
    const data = await auth.forgotPassword(form.email)
    const url = data?.data?.reset_url || null
    if (url) resetUrl.value = url
    else resetSent.value = true
  } catch (e) {
    formError.value = Object.keys(auth.errors || {}).length ? '' : auth.error
  }
}
</script>

<style scoped>
.auth-page { display: flex; justify-content: center; padding: 20px 0 40px; }
.card { width: 100%; max-width: 420px; background: #fff; border: 1px solid #eee; border-radius: 16px; padding: 26px 24px; box-shadow: 0 6px 22px rgba(0,0,0,.06); }
.card h1 { margin: 0 0 6px; font-size: 1.5rem; }
.sub { margin: 0 0 18px; color: #777; font-size: .9rem; }
.tabs { display: flex; gap: 8px; margin-bottom: 18px; background: #f5f5f5; padding: 4px; border-radius: 12px; }
.tab { flex: 1; padding: 9px 12px; border: none; background: transparent; border-radius: 9px; font: inherit; font-weight: 600; color: #666; cursor: pointer; }
.tab.active { background: #fff; color: #ff9800; box-shadow: 0 2px 6px rgba(0,0,0,.08); }
.form { display: flex; flex-direction: column; gap: 6px; }
label { display: flex; flex-direction: column; gap: 5px; margin-top: 8px; }
label span { font-size: .85rem; color: #555; font-weight: 600; }
input { padding: 10px 12px; border: 1px solid #ddd; border-radius: 10px; font: inherit; }
input:focus { outline: none; border-color: #ff9800; }
.note { margin: 0 0 4px; color: #777; font-size: .88rem; }
.error { color: #dc2626; font-size: .84rem; margin: 2px 0 0; }
.ok { color: #16a34a; font-size: .86rem; margin: 4px 0 0; }
.btn { margin-top: 14px; padding: 12px 18px; border: none; border-radius: 10px; background: #ff9800; color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer; }
.btn:disabled { opacity: .6; cursor: default; }
.links { margin-top: 14px; display: flex; flex-direction: column; gap: 6px; align-items: center; color: #777; font-size: .88rem; }
.link-btn { border: none; background: none; color: #ff9800; font: inherit; cursor: pointer; padding: 0; text-decoration: underline; }
.reset-box { margin-top: 10px; background: #fff8e1; border: 1px dashed #ffd54f; border-radius: 10px; padding: 12px; font-size: .85rem; word-break: break-all; }
.reset-box p { margin: 0 0 6px; color: #8d6e00; }
.reset-box a { color: #e65100; }
</style>
