import { defineStore } from 'pinia'
import axios from 'axios'

const LS_KEY = 'fridge-items'
const LS_STAPLES = 'fridge-include-staples'

export const useFridgeStore = defineStore('fridge', {
  state: () => ({
    items: JSON.parse(localStorage.getItem(LS_KEY) || '[]'),
    ingredients: [],
    matches: [],
    searched: false,
    loading: false,
    // Учитывать ли базовые продукты (соль, перец, масло, сахар, мука, вода)
    // при подборе. По умолчанию нет: они есть в любой кухне, и требовать
    // их отметить значит занижать совпадение у всех рецептов подряд.
    includePantryStaples: localStorage.getItem(LS_STAPLES) === '1',
    // Номер последнего отправленного запроса: ответ с меньшим номером
    // считается устаревшим и игнорируется.
    requestId: 0,
  }),
  getters: {
    /** Базовые продукты из справочника — их не нужно искать в холодильнике. */
    pantryStaples: (state) => state.ingredients.filter((i) => i.is_pantry_staple),
    /** Названия продуктов, добавленных в холодильник. */
    stapleNames: (state) => new Set(
      state.ingredients.filter((i) => i.is_pantry_staple).map((i) => i.name.toLowerCase()),
    ),
  },
  actions: {
    persist() {
      localStorage.setItem(LS_KEY, JSON.stringify(this.items))
    },
    setIncludePantryStaples(value) {
      this.includePantryStaples = !!value
      localStorage.setItem(LS_STAPLES, this.includePantryStaples ? '1' : '0')
      this.search()
    },
    addItem(name) {
      const clean = (name || '').trim().toLowerCase()
      if (!clean || this.items.includes(clean)) return false
      this.items.push(clean)
      this.persist()
      this.search()
      return true
    },
    removeItem(name) {
      this.items = this.items.filter((i) => i !== name)
      this.persist()
      this.search()
    },
    clear() {
      this.items = []
      this.matches = []
      this.searched = false
      this.persist()
      // Гасим запрос, который ещё в пути: его ответ уже не актуален.
      this.requestId++
    },
    async fetchIngredients() {
      if (this.ingredients.length) return
      const res = await axios.get('/api/ingredients')
      this.ingredients = res.data
    },
    async search() {
      if (this.items.length === 0) {
        this.matches = []
        this.searched = false
        return
      }

      // Пользователь добавляет продукты по одному, и на каждый клик уходит
      // свой запрос. Ответы приходят не по порядку, и без этой проверки
      // старый результат мог перезаписать свежий: в списке оказывались
      // блюда по неполному набору продуктов.
      const requestId = ++this.requestId
      this.loading = true
      try {
        const res = await axios.post('/api/fridge/match', {
          ingredients: this.items,
          include_pantry_staples: this.includePantryStaples,
        })
        if (requestId !== this.requestId) return
        this.matches = res.data.recipes
        this.searched = true
      } catch (e) {
        if (requestId === this.requestId) {
          this.matches = []
          this.searched = false
        }
        throw e
      } finally {
        if (requestId === this.requestId) this.loading = false
      }
    },
  },
})
