import { defineStore } from 'pinia'
import axios from 'axios'

const LS_KEY = 'fridge-items'

export const useFridgeStore = defineStore('fridge', {
  state: () => ({
    items: JSON.parse(localStorage.getItem(LS_KEY) || '[]'),
    ingredients: [],
    matches: [],
    searched: false,
    loading: false,
  }),
  actions: {
    persist() {
      localStorage.setItem(LS_KEY, JSON.stringify(this.items))
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
      this.loading = true
      try {
        const res = await axios.post('/api/fridge/match', { ingredients: this.items })
        this.matches = res.data.recipes
        this.searched = true
      } finally {
        this.loading = false
      }
    },
  },
})
