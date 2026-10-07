import { defineStore } from 'pinia'
import axios from 'axios'

/**
 * ШАГ 25: Pinia store рецептов.
 * Состояние: recipes, categories, currentRecipe, filters, loading + пагинация/поиск.
 */
export const useRecipeStore = defineStore('recipes', {
  state: () => ({
    recipes: [],
    categories: [],
    currentRecipe: null,
    randomRecipe: null,
    total: 0,
    page: 1,
    lastPage: 1,
    perPage: 12,
    loading: false,
    detailLoading: false,
    filters: {
      category_id: null,
      difficulty: [],       // массив: ['easy','medium','hard']
      max_time: 180,        // ползунок 0..180 минут (180 = без ограничения)
      sort: 'new',          // new | popular | time | title
    },
  }),

  getters: {
    activeFiltersCount: (s) =>
      (s.filters.category_id ? 1 : 0) +
      (s.filters.difficulty.length ? 1 : 0) +
      (s.filters.max_time < 180 ? 1 : 0),
  },

  actions: {
    /** Список категорий с фото и счётчиком рецептов */
    async fetchCategories() {
      if (this.categories.length) return
      const res = await axios.get('/api/categories')
      this.categories = res.data.data
    },

    /** Каталог рецептов с фильтрами и пагинацией */
    async fetchRecipes(page = 1) {
      this.loading = true
      try {
        const params = { page, sort: this.filters.sort }
        if (this.filters.category_id) params.category_id = this.filters.category_id
        if (this.filters.difficulty.length) params.difficulty = this.filters.difficulty.join(',')
        if (this.filters.max_time < 180) params.max_time = this.filters.max_time

        const res = await axios.get('/api/recipes', { params })
        this.recipes = res.data.data
        this.total = res.data.total
        this.page = res.data.current_page
        this.lastPage = res.data.last_page
        this.perPage = res.data.per_page
      } finally {
        this.loading = false
      }
    },

    /** Полный рецепт по slug (включая шаги с фото и похожие) */
    async fetchRecipeBySlug(slug) {
      this.detailLoading = true
      this.currentRecipe = null
      try {
        const res = await axios.get(`/api/recipes/${slug}`)
        this.currentRecipe = res.data.data
        return this.currentRecipe
      } catch (e) {
        if (e.response?.status === 404) this.currentRecipe = { notFound: true }
        throw e
      } finally {
        this.detailLoading = false
      }
    },

    /** Поиск по названию и ингредиентам */
    async searchRecipes(q, page = 1) {
      this.loading = true
      try {
        const res = await axios.get('/api/search', { params: { q, page } })
        this.recipes = res.data.data
        this.total = res.data.total
        this.page = res.data.current_page
        this.lastPage = res.data.last_page
      } finally {
        this.loading = false
      }
    },

    /** Случайный рецепт (страница /random) */
    async fetchRandomRecipe() {
      this.detailLoading = true
      try {
        const res = await axios.get('/api/random')
        this.randomRecipe = res.data.data
        return this.randomRecipe
      } finally {
        this.detailLoading = false
      }
    },

    resetFilters() {
      this.filters = { category_id: null, difficulty: [], max_time: 180, sort: 'new' }
    },
  },
})
