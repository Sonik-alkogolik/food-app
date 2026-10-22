import { defineStore } from 'pinia'
import { api, getToken, setToken, clearToken, getUser, setUser, clearUser } from '../api'

/**
 * Стор авторизации.
 *
 * Токен Sanctum хранится в localStorage под ключом 'auth-token',
 * профиль пользователя — под 'auth-user'. Состояние готово (ready)
 * становится true после первой попытки восстановить сессию.
 */
let restorePromise = null

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: getToken(),
    user: getUser(),
    ready: false,
    loading: false,
    error: null,
    errors: {},
  }),

  getters: {
    isAuthenticated: (s) => !!s.token && !!s.user,
    isAdmin: (s) => !!s.user?.is_admin,
  },

  actions: {
    /** Сбрасывает сессию локально (без запроса на сервер). */
    clearSession() {
      this.token = ''
      this.user = null
      clearToken()
      clearUser()
    },

    /**
     * Восстанавливает сессию при загрузке приложения:
     * если токен есть — спрашиваем /api/auth/me.
     * При 401 токен просто удаляется.
     */
    async restore() {
      if (this.ready) return this.user
      if (restorePromise) return restorePromise

      restorePromise = (async () => {
        try {
          const token = getToken()
          if (!token) return null
          // skipAuthRedirect: истёкший токен не должен выкидывать гостя
          // на страницу входа — он мог просто открыть каталог.
          const res = await api.get('/api/auth/me', { skipAuthRedirect: true })
          this.user = res.data?.data || null
          this.token = getToken()
          setUser(this.user)
          return this.user
        } catch (e) {
          if (e.response?.status === 401) this.clearSession()
          return null
        } finally {
          this.ready = true
          restorePromise = null
        }
      })()

      return restorePromise
    },

    /** POST /api/auth/login */
    async login(credentials) {
      this.loading = true
      this.error = null
      this.errors = {}
      try {
        const res = await api.post('/api/auth/login', credentials)
        this.applySession(res.data)
        return res.data
      } catch (e) {
        this.applyError(e)
        throw e
      } finally {
        this.loading = false
      }
    },

    /** POST /api/auth/register */
    async register(payload) {
      this.loading = true
      this.error = null
      this.errors = {}
      try {
        const res = await api.post('/api/auth/register', payload)
        this.applySession(res.data)
        return res.data
      } catch (e) {
        this.applyError(e)
        throw e
      } finally {
        this.loading = false
      }
    },

    /** POST /api/auth/logout — отзыв текущего токена на сервере. */
    async logout() {
      try {
        await api.post('/api/auth/logout')
      } catch {
        /* даже если сервер недоступен — сессию чистим у себя */
      } finally {
        this.clearSession()
        this.ready = true
      }
    },

    /** POST /api/auth/password — смена пароля. */
    async changePassword(payload) {
      this.loading = true
      this.error = null
      this.errors = {}
      try {
        const res = await api.post('/api/auth/password', payload)
        return res.data
      } catch (e) {
        this.applyError(e)
        throw e
      } finally {
        this.loading = false
      }
    },

    /** POST /api/auth/forgot-password — в локальной среде приходит reset_url. */
    async forgotPassword(email) {
      this.loading = true
      this.error = null
      this.errors = {}
      try {
        const res = await api.post('/api/auth/forgot-password', { email })
        return res.data
      } catch (e) {
        this.applyError(e)
        throw e
      } finally {
        this.loading = false
      }
    },

    /** POST /api/auth/reset-password */
    async resetPassword(payload) {
      this.loading = true
      this.error = null
      this.errors = {}
      try {
        const res = await api.post('/api/auth/reset-password', payload)
        return res.data
      } catch (e) {
        this.applyError(e)
        throw e
      } finally {
        this.loading = false
      }
    },

    /** Сохраняет токен и профиль, полученные от сервера. */
    applySession(data) {
      this.token = data?.token || getToken()
      this.user = data?.data || null
      this.ready = true
      setToken(this.token)
      setUser(this.user)
    },

    /** Раскладывает ошибку ответа по полям формы. */
    applyError(e) {
      const data = e?.response?.data
      this.error = data?.message || 'Что-то пошло не так. Попробуйте ещё раз.'
      this.errors = data?.errors || {}
    },
  },
})
