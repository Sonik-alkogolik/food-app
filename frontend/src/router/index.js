import { createRouter, createWebHistory } from 'vue-router'

/** ШАГ 26: маршруты SPA — главная, каталог, рецепт, категории, поиск, холодильник, случайный */
const routes = [
  { path: '/', name: 'home', component: () => import('../views/HomePage.vue') },
  { path: '/recipes', name: 'recipes', component: () => import('../views/RecipeListPage.vue') },
  { path: '/recipes/:slug', name: 'recipe', component: () => import('../views/RecipeDetailPage.vue') },
  { path: '/categories', name: 'categories', component: () => import('../views/CategoriesPage.vue') },
  { path: '/categories/:slug', name: 'category', component: () => import('../views/CategoryPage.vue') },
  { path: '/search', name: 'search', component: () => import('../views/SearchPage.vue') },
  { path: '/fridge', name: 'fridge', component: () => import('../views/FridgeView.vue') },
  { path: '/random', name: 'random', component: () => import('../views/RandomPage.vue') },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, saved) { return saved || { top: 0 } },
})
