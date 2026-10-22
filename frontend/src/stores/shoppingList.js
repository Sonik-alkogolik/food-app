import { defineStore } from 'pinia'
import { api } from '../api'

/**
 * Стор списка покупок.
 *
 * Список — личный, поэтому всё живёт на сервере: локального кэша для
 * гостя нет (гость вообще не работает с этим разделом).
 */
export const useShoppingListStore = defineStore('shoppingList', {
  state: () => ({
    lists: [],
    activeId: null,
    loading: false,
    saving: false,
    error: null,
    message: null,
  }),

  getters: {
    /** Активный список (первый, если пользователь не выбрал другой). */
    activeList: (s) => s.lists.find((l) => l.id === s.activeId) || s.lists[0] || null,

    /** Сколько позиций осталось купить. */
    remaining: (s) => {
      const list = s.lists.find((l) => l.id === s.activeId) || s.lists[0]
      if (!list) return 0
      return list.items.filter((i) => !i.checked).length
    },

    total: (s) => {
      const list = s.lists.find((l) => l.id === s.activeId) || s.lists[0]
      return list ? list.items.length : 0
    },
  },

  actions: {
    /** GET /api/shopping-list — списки пользователя с позициями. */
    async fetchLists() {
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/api/shopping-list')
        this.lists = res.data?.data || []
        if (!this.lists.some((l) => l.id === this.activeId)) {
          this.activeId = this.lists[0]?.id ?? null
        }
        return this.lists
      } catch (e) {
        this.error = e.response?.data?.message || 'Не удалось загрузить список покупок.'
        throw e
      } finally {
        this.loading = false
      }
    },

    /** POST /api/shopping-list — создать список. */
    async createList(title) {
      this.saving = true
      this.error = null
      try {
        const res = await api.post('/api/shopping-list', { title })
        this.lists.push(res.data.data)
        this.activeId = res.data.data.id
        this.message = res.data.message
        return res.data.data
      } catch (e) {
        this.error = e.response?.data?.message || 'Не удалось создать список.'
        throw e
      } finally {
        this.saving = false
      }
    },

    /** PATCH /api/shopping-list/{id} — переименовать список. */
    async renameList(id, title) {
      this.error = null
      try {
        const res = await api.patch(`/api/shopping-list/${id}`, { title })
        this.replaceList(res.data.data)
        this.message = res.data.message
      } catch (e) {
        this.error = e.response?.data?.message || 'Не удалось переименовать список.'
        throw e
      }
    },

    /** DELETE /api/shopping-list/{id} — удалить список. */
    async deleteList(id) {
      this.error = null
      try {
        const res = await api.delete(`/api/shopping-list/${id}`)
        this.lists = this.lists.filter((l) => l.id !== id)
        if (this.activeId === id) this.activeId = this.lists[0]?.id ?? null
        this.message = res.data.message
      } catch (e) {
        this.error = e.response?.data?.message || 'Не удалось удалить список.'
        throw e
      }
    },

    /** POST /api/shopping-list/{id}/items — добавить позицию. */
    async addItem(listId, payload) {
      this.error = null
      try {
        const res = await api.post(`/api/shopping-list/${listId}/items`, payload)
        this.appendItem(listId, res.data.data)
        this.message = res.data.message
        return res.data.data
      } catch (e) {
        this.error = e.response?.data?.message || 'Не удалось добавить позицию.'
        throw e
      }
    },

    /** PATCH /api/shopping-list/{id}/items/{itemId} — изменить позицию. */
    async updateItem(listId, itemId, payload) {
      this.error = null
      try {
        const res = await api.patch(`/api/shopping-list/${listId}/items/${itemId}`, payload)
        this.replaceItem(listId, res.data.data)
        return res.data.data
      } catch (e) {
        this.error = e.response?.data?.message || 'Не удалось изменить позицию.'
        throw e
      }
    },

    /** DELETE /api/shopping-list/{id}/items/{itemId} — убрать позицию. */
    async removeItem(listId, itemId) {
      this.error = null
      try {
        await api.delete(`/api/shopping-list/${listId}/items/${itemId}`)
        const list = this.lists.find((l) => l.id === listId)
        if (list) list.items = list.items.filter((i) => i.id !== itemId)
      } catch (e) {
        this.error = e.response?.data?.message || 'Не удалось удалить позицию.'
        throw e
      }
    },

    /**
     * POST /api/shopping-list/{id}/items/bulk — добавить ингредиенты рецепта.
     *
     * @param {number} listId
     * @param {string} recipe slug рецепта
     * @param {number} [servings] сколько порций (по умолчанию — как в рецепте)
     */
    async addRecipeIngredients(listId, recipe, servings) {
      this.saving = true
      this.error = null
      try {
        const res = await api.post(`/api/shopping-list/${listId}/items/bulk`, { recipe, servings })
        this.replaceList(res.data.data)
        this.message = res.data.message
        return res.data
      } catch (e) {
        this.error = e.response?.data?.message || 'Не удалось добавить ингредиенты в список.'
        throw e
      } finally {
        this.saving = false
      }
    },

    /** Заменяет список в состоянии (после ответа сервера). */
    replaceList(list) {
      const index = this.lists.findIndex((l) => l.id === list.id)
      if (index === -1) this.lists.push(list)
      else this.lists[index] = list
    },

    replaceItem(listId, item) {
      const list = this.lists.find((l) => l.id === listId)
      if (!list) return
      const index = list.items.findIndex((i) => i.id === item.id)
      if (index === -1) list.items.push(item)
      else list.items[index] = item
    },

    appendItem(listId, item) {
      const list = this.lists.find((l) => l.id === listId)
      if (list) list.items.push(item)
    },

    clearMessage() {
      this.message = null
      this.error = null
    },
  },
})
