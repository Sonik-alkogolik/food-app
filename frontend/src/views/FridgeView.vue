<template>
  <div class="container">
    <h1>🧊 Мой холодильник</h1>
    <p class="hint">Добавьте ингредиенты, которые у вас есть — мы подберём блюда, которые можно приготовить.</p>

    <!-- 3D-кухня: клик по холодильнику открывает его и заводит камеру внутрь -->
    <Kitchen3D :items="store.items" />

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
      <span
        v-for="item in store.items"
        :key="item"
        class="chip"
        :class="{ staple: store.stapleNames.has(item) }"
      >
        <span v-if="store.stapleNames.has(item)" class="staple-mark" title="Базовый продукт">🧂</span>
        {{ item }}
        <button class="chip-x" @click="store.removeItem(item)" aria-label="Удалить">×</button>
      </span>
      <button class="clear" @click="store.clear()">Очистить всё</button>
    </div>

    <!-- Порог совпадения: какие рецепты считать подходящими -->
    <div class="match-filter">
      <label for="min-match">Совпадение не ниже</label>
      <select
        id="min-match"
        :value="store.minMatch"
        @change="store.setMinMatch($event.target.value)"
      >
        <option value="100">100% — можно готовить</option>
        <option value="70">70% — почти готово</option>
        <option value="50">50% — половина есть</option>
        <option value="0">Любое совпадение</option>
      </select>
    </div>

    <!-- Базовые продукты: их не нужно искать в холодильнике -->
    <section class="staples" v-if="store.pantryStaples.length">
      <label class="staple-toggle">
        <input
          type="checkbox"
          :checked="store.includePantryStaples"
          @change="store.setIncludePantryStaples($event.target.checked)"
        />
        Учитывать базовые продукты
        <span class="staple-hint">
          {{ store.includePantryStaples
            ? 'соль, масло и прочее считаются недостающими, если их нет в холодильнике'
            : 'соль, перец, масло, сахар, мука и вода считаются всегда доступными' }}
        </span>
      </label>

      <div class="staple-list">
        <button
          v-for="ing in visibleStaples"
          :key="ing.id"
          type="button"
          class="staple-item"
          :class="{ on: store.items.includes(ing.name.toLowerCase()) }"
          @click="toggleStaple(ing)"
        >
          🧂 {{ ing.name }}
        </button>
        <button
          v-if="store.pantryStaples.length > limit"
          type="button"
          class="staple-more"
          @click="showAllStaples = !showAllStaples"
        >
          {{ showAllStaples ? 'Свернуть' : 'Показать все (' + store.pantryStaples.length + ')' }}
        </button>
      </div>
    </section>

    <div v-if="store.loading" class="status">Ищем рецепты…</div>
    <div v-else-if="store.searched && store.matches.length === 0" class="status empty">
      😕 Подходящих блюд не найдено. Попробуйте снизить порог совпадения или добавить ещё ингредиентов (мука, масло, рис…).
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
            {{ recipe.can_cook ? '✅ Можно готовить!' : recipe.match_percentage + '% совпадение' }}
          </span>
        </div>
        <p class="meta">📂 {{ recipe.category }} · ⏱️ {{ recipe.cook_time_minutes }} мин.</p>
        <p class="match-summary">
          Есть {{ recipe.matched_count }} из {{ recipe.total_count }} продуктов<span
            v-if="recipe.missing_ingredients.length"
            class="missing"
          >. Не хватает: {{ recipe.missing_ingredients.map(m => m.name).join(', ') }}</span>
        </p>
        <p class="ignored" v-if="recipe.ignored_pantry_staples && recipe.ignored_pantry_staples.length">
          🧂 Базовые продукты не учитываются: {{ recipe.ignored_pantry_staples.join(', ') }}
        </p>
        <button class="more">Смотреть рецепт →</button>
      </div>
    </div>

    <!-- Модалка рецепта -->
    <div v-if="selected" class="overlay" @click.self="selected = null">
      <div class="modal">
        <button class="close" @click="selected = null">×</button>
        <h2>{{ selected.title }}</h2>
        <p class="meta">📂 {{ selected.category }} · ⏱️ {{ selected.cook_time_minutes }} мин. · Совпадение: {{ selected.match_percentage }}%</p>
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
import { computed, onMounted, ref } from 'vue'
import { useFridgeStore } from '../stores/fridge'
import Kitchen3D from '../components/Kitchen3D.vue'

const store = useFridgeStore()
const newItem = ref('')
const selected = ref(null)
const showAllStaples = ref(false)
const limit = 12

const visibleStaples = computed(() =>
  showAllStaples.value ? store.pantryStaples : store.pantryStaples.slice(0, limit),
)

const isHave = (name) => store.items.includes(name.toLowerCase())

function onAdd() {
  if (store.addItem(newItem.value)) newItem.value = ''
}

function openRecipe(recipe) {
  selected.value = recipe
}

/** Базовый продукт можно добавить в холодильник и убрать обратно. */
function toggleStaple(ingredient) {
  const name = ingredient.name.toLowerCase()
  if (store.items.includes(name)) {
    store.removeItem(name)
  } else {
    store.addItem(name)
  }
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
.add-form input { flex: 1; min-width: 0; padding: 12px 14px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px; }
.add-form input:focus { border-color: #ff9800; outline: none; }
.add-form button { padding: 12px 22px; background: #ff9800; color: #fff; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; }
.add-form button:disabled { opacity: .5; cursor: default; }
.chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 18px; }
.chip { background: #fff3e0; border: 1px solid #ffc980; padding: 6px 12px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px; }
.chip.staple { background: #f3f4f6; border-color: #d6d8dd; color: #7a7f87; }
.staple-mark { font-size: .9em; }
.chip-x { border: none; background: none; cursor: pointer; font-size: 16px; color: #b26a00; }
.chip.staple .chip-x { color: #9aa0a8; }
.clear { border: none; background: none; color: #999; cursor: pointer; text-decoration: underline; }
.staples { margin: 0 0 18px; padding: 12px 14px; border: 1px dashed #d8dbe0; border-radius: 12px; background: #fafbfc; }
.staple-toggle { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-weight: 600; cursor: pointer; }
.staple-toggle input { width: 18px; height: 18px; accent-color: #ff9800; }
.staple-hint { font-weight: 400; color: #888; font-size: .85em; }
.staple-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.staple-item { border: 1px solid #e2e5ea; background: #fff; color: #8b9099; border-radius: 999px; padding: 5px 12px; font-size: .85em; cursor: pointer; }
.staple-item:hover { border-color: #ffc980; color: #b26a00; }
.staple-item.on { background: #fff3e0; border-color: #ffc980; color: #b26a00; }
.staple-more { border: none; background: none; color: #ff9800; cursor: pointer; font-size: .85em; padding: 5px 0; }
.ignored { color: #9aa0a8; font-size: .82em; }
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
.match-summary { color: #2e7d32; font-size: .9em; }
.match-summary .missing { color: #c62828; }
.match-filter { display: flex; align-items: center; gap: 8px; margin: 0 0 14px; color: #666; font-size: .9em; }
.match-filter select { padding: 7px 10px; border: 2px solid #ddd; border-radius: 10px; font-size: .95em; background: #fff; color: #333; }
.match-filter select:focus { border-color: #ff9800; outline: none; }
.more { margin-top: 8px; border: none; background: none; color: #ff9800; cursor: pointer; font-size: .95em; padding: 0; }
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 50; }
.modal { background: #fff; border-radius: 16px; padding: 26px; max-width: 640px; width: 100%; max-height: 85vh; overflow-y: auto; position: relative; }
.close { position: absolute; top: 10px; right: 14px; border: none; background: none; font-size: 26px; cursor: pointer; color: #999; }
.modal h4 { margin: 18px 0 6px; }
.modal ul { list-style: none; padding: 0; margin: 0; }
.modal li { padding: 3px 0; }
.modal li.have { color: #2e7d32; }
.steps { white-space: pre-wrap; font-family: inherit; line-height: 1.5; background: #fafafa; padding: 12px; border-radius: 8px; }
@media (max-width: 420px) {
  .add-form { flex-wrap: wrap; }
  .add-form input { flex: 1 1 100%; }
  .add-form button { flex: 1 1 100%; }
}
</style>
