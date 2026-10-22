import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

/** ШАГ 26: маршруты SPA — главная, каталог, рецепт, категории, поиск, холодильник, случайный,
 *  личный кабинет, избранное, вход/регистрация. */
const routes = [
  { path: '/', name: 'home', component: () => import('../views/HomePage.vue') },
  { path: '/recipes', name: 'recipes', component: () => import('../views/RecipeListPage.vue') },
  { path: '/recipes/:slug', name: 'recipe', component: () => import('../views/RecipeDetailPage.vue') },
  { path: '/categories', name: 'categories', component: () => import('../views/CategoriesPage.vue') },
  { path: '/categories/:slug', name: 'category', component: () => import('../views/CategoryPage.vue') },
  { path: '/search', name: 'search', component: () => import('../views/SearchPage.vue') },
  { path: '/fridge', name: 'fridge', component: () => import('../views/FridgeView.vue') },
  { path: '/random', name: 'random', component: () => import('../views/RandomPage.vue') },
  { path: '/login', name: 'login', component: () => import('../views/LoginPage.vue') },
  { path: '/reset-password', name: 'reset-password', component: () => import('../views/ResetPasswordPage.vue') },
  { path: '/account', name: 'account', component: () => import('../views/AccountPage.vue'), meta: { requiresAuth: true } },
  { path: '/favorites', name: 'favorites', component: () => import('../views/FavoritesPage.vue'), meta: { requiresAuth: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, saved) { return saved || { top: 0 } },
})

/**
 * Guard: /account и /favorites доступны только авторизованным.
 * Перед проверкой сессия восстанавливается (токен из localStorage),
 * иначе после перезагрузки страницы пользователь был бы выкинут с /account.
 */
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.ready) await auth.restore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
})
