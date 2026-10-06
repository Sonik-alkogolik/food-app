import { createRouter, createWebHistory } from 'vue-router'
import RecipeList from '../views/RecipeList.vue'
import FridgeView from '../views/FridgeView.vue'

const routes = [
  { path: '/', component: FridgeView },
  { path: '/fridge', component: FridgeView },
  { path: '/recipes', component: RecipeList },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})
