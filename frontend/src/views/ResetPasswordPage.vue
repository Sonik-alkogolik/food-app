<template>
  <div class="reset-page">
    <div class="card">
      <h1>Новый пароль</h1>
      <p class="sub">Придумайте новый пароль для вашего аккаунта.</p>

      <form v-if="!done" class="form" @submit.prevent="submit">
        <label>
          <span>Новый пароль</span>
          <input v-model="password" type="password" autocomplete="new-password" placeholder="Минимум 8 символов" />
        </label>
        <p v-if="fieldError('password')" class="error">{{ fieldError('password') }}</p>

        <label>
          <span>Повторите пароль</span>
          <input v-model="password_confirmation" type="password" autocomplete="new-password" />
        </label>
        <p v-if="fieldError('password_confirmation')" class="error">{{ fieldError('password_confirmation') }}</p>

        <p v-if="formError" class="error">{{ formError }}</p>

        <button class="btn" type="submit" :disabled="auth.loading">
          {{ auth.loading ? 'Сохраняем…' : 'Сохранить пароль' }}
        </button>
      </form>

      <div v-else class="done-box">
        <p class="ok">Пароль обновлён. Теперь можно войти.</p>
        <router-link class="btn" to="/login">Войти</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
/* Смена пароля по ссылке из письма: /reset-password?token=...&email=... */
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()

useHead({ title: 'Новый пароль — Что приготовить?' })

const password = ref('')
const password_confirmation = ref('')
const formError = ref('')
const done = ref(false)

function fieldError(field) {
  const errors = auth.errors?.[field]
  if (!errors) return ''
  return Array.isArray(errors) ? errors[0] : String(errors)
}

async function submit() {
  formError.value = ''
  try {
    await auth.resetPassword({
      token: String(route.query.token || ''),
      email: String(route.query.email || ''),
      password: password.value,
      password_confirmation: password_confirmation.value,
    })
    done.value = true
  } catch {
    // Сервер отдаёт ошибку сброса в поле 'email', которого на этой
    // странице нет, поэтому показываем общий текст ответа.
    formError.value = auth.error || 'Не удалось сбросить пароль.'
  }
}
</script>

<style scoped>
.reset-page { display: flex; justify-content: center; padding: 20px 0 40px; }
.card { width: 100%; max-width: 420px; background: #fff; border: 1px solid #eee; border-radius: 16px; padding: 26px 24px; box-shadow: 0 6px 22px rgba(0,0,0,.06); }
.card h1 { margin: 0 0 6px; font-size: 1.5rem; }
.sub { margin: 0 0 18px; color: #777; font-size: .9rem; }
.form { display: flex; flex-direction: column; gap: 6px; }
label { display: flex; flex-direction: column; gap: 5px; margin-top: 8px; }
label span { font-size: .85rem; color: #555; font-weight: 600; }
input { padding: 10px 12px; border: 1px solid #ddd; border-radius: 10px; font: inherit; }
input:focus { outline: none; border-color: #ff9800; }
.error { color: #dc2626; font-size: .84rem; margin: 2px 0 0; }
.ok { color: #16a34a; font-size: .9rem; margin: 0 0 12px; }
.btn { margin-top: 14px; padding: 12px 18px; border: none; border-radius: 10px; background: #ff9800; color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer; display: inline-block; text-align: center; text-decoration: none; }
.done-box { display: flex; flex-direction: column; }
</style>
