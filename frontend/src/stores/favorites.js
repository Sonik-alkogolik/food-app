import { defineStore } from 'pinia'
import { api } from '../api'
import { useAuthStore } from './auth'

/**
 * Стор избранного с двойной логикой:
 *
 *  - авторизованный пользователь — избранное хранится на сервере
 *    (POST/DELETE /api/favorites), в сторе лежит набор id рецептов;
 *  - гость — избранное хранится локально в localStorage под ключом
 *    'fav-slugs' (список slug'ов). Ключ тот же, что использовался раньше
 *    на странице рецепта, поэтому старые избранные гостя не теряются.
 *
 * При входе гостя с локальными избранными они сливаются на сервер
 * (см. syncGuestFavorites), после чего локальный список очищается.
 */
const LS_KEY = 'fav-slugs'

function readGuestSlugs() {
  try {
    const raw = JSON.parse(localStorage.getItem(LS_KEY) || '[]')
    return Array.isArray(raw) ? raw.filter((s) => typeof s === 'string') : []
  } catch {
    return []
  }
}

export const useFavoritesStore = defineStore('favorites', {
  state: () => ({
    ids: [],          // id рецептов, известных серверу как избранные
    slugs: readGuestSlugs(), // локальное избранное гостя
    items: [],        // полные объекты рецептов (для страницы избранного)
    total: 0,
    page: 1,
    lastPage: 1,
    loaded: false,    // список с сервера получен
    loading: false,
    // Локальные изменения поверх серверных данных: id → «в избранном».
    // Нужны, потому что флаг is_favorite в уже загруженной карточке
    // после переключения остаётся старым.
    overrides: {},
    error: null,
    guestSynced: false,
  }),

  getters: {
    /** Количество избранных рецептов (у гостя — локальный список). */
    count: (s) => (s.loaded ? s.total : s.ids.length + s.slugs.length),
  },

  actions: {
    /**
     * Проверяет, находится ли рецепт в избранном.
     *
     * Порядок источников важен:
     *  1. локальное изменение — самое свежее, что знает клиент;
     *  2. флаг is_favorite из ответа сервера — он считается под текущего
     *     пользователя и потому достовернее локального списка id;
     *  3. список id с сервера — запасной вариант для объектов без флага
     *     (например, похожие рецепты на странице рецепта).
     */
    isFavorite(recipe) {
      if (!recipe) return false

      const auth = useAuthStore()
      if (!auth.isAuthenticated) {
        return this.slugs.includes(recipe.slug)
      }

      if (Object.prototype.hasOwnProperty.call(this.overrides, recipe.id)) {
        return this.overrides[recipe.id]
      }

      if (typeof recipe.is_favorite === 'boolean') {
        return recipe.is_favorite
      }

      return this.ids.includes(recipe.id)
    },

    /** Сохраняет локальное избранное гостя. */
    persistGuest() {
      localStorage.setItem(LS_KEY, JSON.stringify(this.slugs))
    },

    /**
     * Переключает избранное.
     * Для авторизованного — запрос на сервер, для гостя — локально.
     */
    async toggle(recipe) {
      if (!recipe) return false
      const wasFavorite = this.isFavorite(recipe)
      const auth = useAuthStore()

      if (auth.isAuthenticated) {
        try {
          if (wasFavorite) {
            const res = await api.delete(`/api/favorites/${recipe.id}`)
            const fav = res.data?.data?.is_favorite
            this.setServerState(recipe.id, fav === undefined ? false : fav)
          } else {
            const res = await api.post('/api/favorites', { recipe_id: recipe.id })
            const fav = res.data?.data?.is_favorite
            this.setServerState(recipe.id, fav === undefined ? true : fav)
          }
        } catch (e) {
          this.error = e.response?.data?.message || 'Не удалось изменить избранное.'
          throw e
        }
        return !wasFavorite
      }

      // Гость: избранное живёт только в этом браузере
      if (wasFavorite) this.slugs = this.slugs.filter((s) => s !== recipe.slug)
      else this.slugs = [...this.slugs, recipe.slug]
      this.persistGuest()
      return !wasFavorite
    },

    /** Обновляет локальное состояние по ответу сервера. */
    setServerState(id, isFavorite) {
      this.overrides[id] = isFavorite

      if (isFavorite) {
        if (!this.ids.includes(id)) this.ids.push(id)
      } else {
        this.ids = this.ids.filter((x) => x !== id)
      }
    },

    /** GET /api/favorites — список избранного авторизованного пользователя. */
    async fetchFavorites(page = 1) {
      const auth = useAuthStore()
      if (!auth.isAuthenticated) {
        this.items = []
        return
      }
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/api/favorites', { params: { page } })
        const data = res.data?.data || []
        this.items = page === 1 ? data : [...this.items, ...data]
        const pageIds = data.map((r) => r.id)
        this.ids = page === 1 ? pageIds : [...new Set([...this.ids, ...pageIds])]
        this.total = res.data?.total ?? this.items.length
        this.page = res.data?.current_page ?? page
        this.lastPage = res.data?.last_page ?? 1
        this.loaded = true
      } catch (e) {
        this.error = e.response?.data?.message || 'Не удалось загрузить избранное.'
        throw e
      } finally {
        this.loading = false
      }
    },

    /**
     * Сливает локальное избранное гостя на сервер.
     * Вызывается сразу после входа/регистрации.
     */
    async syncGuestFavorites() {
      const auth = useAuthStore()
      if (!auth.isAuthenticated) return
      if (this.guestSynced) return

      const slugs = [...this.slugs]
      if (!slugs.length) {
        this.guestSynced = true
        await this.fetchFavorites().catch(() => {})
        return
      }

      this.loading = true
      try {
        const left = []
        for (const slug of slugs) {
          try {
            // По slug узнаём id: в избранном сервер ждёт именно recipe_id.
            const res = await api.get(`/api/recipes/${encodeURIComponent(slug)}`)
            const id = res.data?.data?.id
            if (!id) continue
            await api.post('/api/favorites', { recipe_id: id })
          } catch {
            // Рецепт мог удалиться или сеть отвалилась — оставляем его
            // в локальном списке, чтобы попробовать слить при следующем входе.
            left.push(slug)
          }
        }
        this.slugs = left
        this.persistGuest()
      } finally {
        this.loading = false
        this.guestSynced = true
      }
      await this.fetchFavorites().catch(() => {})
    },

    /** Сбрасывает состояние серверной части (например, после выхода). */
    resetServerState() {
      this.ids = []
      this.items = []
      this.total = 0
      this.page = 1
      this.lastPage = 1
      this.loaded = false
      this.overrides = {}
      this.guestSynced = false
    },
  },
})
