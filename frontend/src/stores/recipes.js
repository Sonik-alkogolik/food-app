import { defineStore } from 'pinia'
import axios from 'axios'

export const useRecipeStore = defineStore('recipes', {
  state: () => ({ recipes: [], categories: [] }),
  actions: {
    async fetchCategories() {
      const res = await axios.get('/api/categories')
      this.categories = res.data
    },
    async fetchRecipes() {
      const res = await axios.get('/api/recipes')
      this.recipes = res.data.data
    }
  }
})
