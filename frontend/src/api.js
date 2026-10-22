import axios from 'axios'

/**
 * Общий axios-инстанс с авторизацией.
 *
 * Токен Sanctum лежит в localStorage под ключом 'auth-token' и подставляется
 * в заголовок Authorization при каждом запросе. Если сервер отвечает 401
 * (токен протух или отозван) — сессия чистится, а пользователь уводится
 * на страницу входа.
 */
const TOKEN_KEY = 'auth-token'
const USER_KEY = 'auth-user'

// Адреса, на которых 401 — это ожидаемый ответ, а не протухшая сессия
const NO_REDIRECT_URLS = ['/api/auth/login', '/api/auth/register']

export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || ''
}

export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY)
}

export function getUser() {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY) || 'null')
  } catch {
    return null
  }
}

export function setUser(user) {
  if (user) localStorage.setItem(USER_KEY, JSON.stringify(user))
  else localStorage.removeItem(USER_KEY)
}

export function clearUser() {
  localStorage.removeItem(USER_KEY)
}

export const api = axios.create({
  baseURL: '/',
  headers: { Accept: 'application/json' },
})

/**
 * Обработчик «сессия протухла». Регистрируется в main.js, чтобы api.js
 * не тянул роутер напрямую (иначе получается циклический импорт).
 */
let unauthorizedHandler = null

export function setUnauthorizedHandler(fn) {
  unauthorizedHandler = fn
}

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers = config.headers || {}
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const url = error.config?.url || ''
    // Запрос может сам попросить не выкидывать пользователя на страницу входа
    // (так работает чтение комментариев, доступное и гостю, и проверка
    // сохранённой сессии при загрузке приложения).
    const skip = error.config?.skipAuthRedirect
    if (status === 401 && !NO_REDIRECT_URLS.some((u) => url.includes(u))) {
      // Сессия протухла — токен всё равно больше не работает, чистим его.
      // Редирект делаем не всегда: при восстановлении сессии на старте
      // страницы выкидывать гостя на форму входа незачем.
      clearToken()
      clearUser()
      if (!skip) {
        try {
          unauthorizedHandler?.()
        } catch {
          /* переход на страницу входа — не критичная операция */
        }
      }
    }
    return Promise.reject(error)
  },
)
