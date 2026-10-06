<template>
  <div class="container">
    <h1>🧊 Мой холодильник</h1>
    <p class="hint">Добавьте ингредиенты, которые у вас есть — мы подберём блюда, которые можно приготовить.</p>

    <form class="add-form" @submit.prevent="onAdd">
      <input
        v-model="newItem"
        list="ingredient-suggestions"
        placeholder="Например: яйца, молоко, сыр…"
        autofocus
      />
      <button type="submit" :disabled="!newItem.trim()">Добавить</button>
      <datalist id="ingredient-suggestions">
        <option v-for="ing in store.ingredients" :key="ing.id" :value="ing.name"></option>
      </datalist>
    </form>

    <div class="chips" v-if="store.items.length">
      <span v-for="item in store.items" :key="item" class="chip">
        {{ item }}
        <button class="chip-x" @click="store.removeItem(item)" aria-label="Удалить">×</button>
      </span>
      <button class="clear" @click="store.clear()">Очистить всё</button>
    </div>

    <div v-if="store.loading" class="status">Ищем рецепты…</div>
    <div v-else-if="store.searched && store.matches.length === 0" class="status empty">
      😕 Подходящих блюд не найдено. Попробуйте добавить ещё ингредиентов (мука, масло, рис…).
    </div>

    <div class="grid">
      <div
        v-for="recipe in store.matches"
        :key="recipe.id"
        class="card"
        :class="{ full: recipe.can_cook }"
        @click="openRecipe(recipe)"
      >
        <div class="top">
          <h3>{{ recipe.title }}</h3>
          <span class="badge" :class="recipe.can_cook ? 'ok' : 'partial'">
            {{ recipe.can_cook ? '✅ Можно готовить!' : recipe.match_percent + '% совпадение' }}
          </span>
        </div>
        <p class="meta">📂 {{ recipe.category }} · ⏱️ {{ recipe.cook_time_minutes }} мин.</p>
        <p class="matched" v-if="recipe.matched_ingredients.length">
          Есть: {{ recipe.matched_ingredients.join(', ') }}
        </p>
        <p class="missing" v-if="recipe.missing_ingredients.length">
          Не хватает: {{ recipe.missing_ingredients.map(m => m.name).join(', ') }}
        </p>
        <button class="more">Смотреть рецепт →</button>
      </div>
    </div>

    <!-- Модалка рецепта -->
    <div v-if="selected" class="overlay" @click.self="selected = null">
      <div class="modal">
        <button class="close" @click="selected = null">×</button>
        <h2>{{ selected.title }}</h2>
        <p class="meta">📂 {{ selected.category }} · ⏱️ {{ selected.cook_time_minutes }} мин. · Совпадение: {{ selected.match_percent }}%</p>
        <h4>Ингредиенты</h4>
        <ul>
          <li v-for="ing in selected.all_ingredients" :key="ing.name" :class="{ have: isHave(ing.name) }">
            <span v-if="isHave(ing.name)">✅</span><span v-else>⬜</span>
            {{ ing.name }}<template v-if="ing.quantity"> — {{ ing.quantity }}</template>
          </li>
        </ul>
        <h4>Приготовление</h4>
        <pre class="steps">{{ selected.instructions }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useFridgeStore } from '../stores/fridge'

const store = useFridgeStore()
const newItem = ref('')
const selected = ref(null)

const isHave = (name) => store.items.includes(name.toLowerCase())

function onAdd() {
  if (store.addItem(newItem.value)) newItem.value = ''
}

function openRecipe(recipe) {
  selected.value = recipe
}

onMounted(() => {
  store.fetchIngredients()
  if (store.items.length) store.search()
})
</script>

<style scoped>
.container { max-width: 1100px; margin: 0 auto; padding: 24px 16px; font-family: system-ui, sans-serif; }
.hint { color: #666; margin-top: -8px; }
.add-form { display: flex; gap: 10px; margin: 16px 0; }
.add-form input { flex: 1; padding: 12px 14px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px; }
.add-form input:focus { border-color: #ff9800; outline: none; }
.add-form button { padding: 12px 22px; background: #ff9800; color: #fff; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; }
.add-form button:disabled { opacity: .5; cursor: default; }
.chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 18px; }
.chip { background: #fff3e0; border: 1px solid #ffc980; padding: 6px 12px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px; }
.chip-x { border: none; background: none; cursor: pointer; font-size: 16px; color: #b26a00; }
.clear { border: none; background: none; color: #999; cursor: pointer; text-decoration: underline; }
.status { padding: 14px; border-radius: 10px; background: #f5f5f5; margin-bottom: 16px; }
.status.empty { background: #ffecec; color: #b04040; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.card { border: 1px solid #eee; border-radius: 14px; padding: 18px; cursor: pointer; transition: box-shadow .15s; background: #fff; }
.card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.card.full { border-color: #7dc97d; background: #f4fff4; }
.top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.badge { padding: 4px 10px; border-radius: 8px; font-size: .8em; white-space: nowrap; }
.badge.ok { background: #2e9e2e; color: #fff; }
.badge.partial { background: #ffcc80; color: #6b4a00; }
.meta { color: #777; font-size: .9em; }
.matched { color: #2e7d32; font-size: .9em; }
.missing { color: #c62828; font-size: .9em; }
.more { margin-top: 8px; border: none; background: none; color: #ff9800; cursor: pointer; font-size: .95em; padding: 0; }
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 50; }
.modal { background: #fff; border-radius: 16px; padding: 26px; max-width: 640px; width: 100%; max-height: 85vh; overflow-y: auto; position: relative; }
.close { position: absolute; top: 10px; right: 14px; border: none; background: none; font-size: 26px; cursor: pointer; color: #999; }
.modal h4 { margin: 18px 0 6px; }
.modal ul { list-style: none; padding: 0; margin: 0; }
.modal li { padding: 3px 0; }
.modal li.have { color: #2e7d32; }
.steps { white-space: pre-wrap; font-family: inherit; line-height: 1.5; background: #fafafa; padding: 12px; border-radius: 8px; }
</style>
