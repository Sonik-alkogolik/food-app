<template>
  <div class="kitchen">
    <!-- «Кухня»: обои в клетку -->
    <header class="counter-top">
      <h1>🍳 Моя кухня</h1>

      <!-- «записка на холодильнике» с магнитом -->
      <form class="note" @submit.prevent="onAdd">
        <span class="note-magnet">🧿</span>
        <input
          v-model="newItem"
          list="ingredient-suggestions"
          placeholder="Что купил? Например: яйца, молоко, сыр…"
          autofocus
        />
        <button type="submit" :disabled="!newItem.trim()">Прибрать 🧲</button>
        <datalist id="ingredient-suggestions">
          <option v-for="ing in store.ingredients" :key="ing.id" :value="ing.name"></option>
        </datalist>
      </form>
    </header>

    <div class="kitchen-grid">
      <!-- левая часть: холодильник -->
      <section class="left">
        <FridgeDoor :items="fridgeItems" :shelf-names="onShelf" @toggle="toggleItem" />
        <p class="tip">💡 Клик по продукту на дверце — положить его на кухонную полку (и обратно). Так ты собираешь «что я буду готовить сегодня».</p>
        <button v-if="store.items.length" class="clear" @click="onClear">🗑 Вынести всё (очистить)</button>
      </section>

      <!-- правая часть: кухонная полка + горелки -->
      <section class="right">
        <h2>🍽 На столке (готовим из этого)</h2>
        <KitchenShelf :items="shelfItems" @remove="removeFromShelf" />

        <div class="stove">
          <div class="burner" v-for="(b, i) in burners" :key="i" :class="{ on: shelfItems.length > 0 }">
            <div class="coil"></div>
          </div>
        </div>

        <div v-if="store.loading" class="status">Ищем рецепты… ⏳</div>
        <div v-else-if="store.searched && store.matches.length === 0" class="status empty">
          😕 Из этих продуктов ничего не готовится. Добавь ещё базовое: мука, масло, яйца, рис…
        </div>

        <h2 v-if="store.matches.length">📖 Что можно приготовить <small>({{ store.matches.length }})</small></h2>
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
      </section>
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
/* «Кухня + холодильник»: вместо выпадающего списка — интерактивный холодильник
   с магнитами и кухонная полка с банками. */
import { computed, onMounted, ref } from 'vue'
import { useFridgeStore } from '../stores/fridge'
import FridgeDoor from '../components/FridgeDoor.vue'
import KitchenShelf from '../components/KitchenShelf.vue'

const store = useFridgeStore()
const newItem = ref('')
const selected = ref(null)
const burners = [0, 1, 2, 3]

// «Полка» — какие продукты пользователь достал из холодильника (хранится локально)
const SHELF_KEY = 'fridge-on-shelf'
const onShelf = ref(JSON.parse(localStorage.getItem(SHELF_KEY) || '[]'))
function persistShelf() { localStorage.setItem(SHELF_KEY, JSON.stringify(onShelf.value)) }

const fridgeItems = computed(() => store.items.map((name) => ({ name, expired: false })))

const shelfItems = computed(() =>
  onShelf.value
    .filter((n) => store.items.includes(n))
    .map((n) => ({ name: n, expired: false }))
)

const isHave = (name) => store.items.includes(name.toLowerCase())

function onAdd() {
  if (store.addItem(newItem.value)) newItem.value = ''
}

function toggleItem(name) {
  if (onShelf.value.includes(name)) {
    onShelf.value = onShelf.value.filter((n) => n !== name)
  } else {
    onShelf.value = [...onShelf.value, name]
  }
  persistShelf()
}

function removeFromShelf(name) {
  // убрать с полки = также удалить из холодильника (продукт «использован/выброшен»)
  onShelf.value = onShelf.value.filter((n) => n !== name)
  persistShelf()
  store.removeItem(name)
}

function onClear() {
  onShelf.value = []
  persistShelf()
  store.clear()
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
.kitchen { max-width: 1280px; margin: 0 auto; padding: 24px 16px; font-family: system-ui, sans-serif; }

/* обои-«кухня» */
.counter-top { background:
    linear-gradient(rgba(255,255,255,.9), rgba(255,255,255,.9)),
    repeating-linear-gradient(0deg, #f7e8d5 0 24px, #fff 24px 48px);
  border-radius: 18px; padding: 22px 22px 26px; margin-bottom: 22px; box-shadow: inset 0 -6px 12px rgba(0,0,0,.06); }
.counter-top h1 { margin: 0 0 14px; color: #6b4a2b; }

/* записка под магнитом */
.note { position: relative; display: flex; gap: 10px; background: #fffbc7; padding: 18px 14px 14px; border-radius: 4px; box-shadow: 0 6px 14px rgba(0,0,0,.18); transform: rotate(-.6deg); max-width: 640px; }
.note::before { content: ''; position: absolute; inset: 0; background: repeating-linear-gradient(transparent 0 27px, rgba(200,60,60,.14) 27px 28px); pointer-events: none; }
.note-magnet { position: absolute; top: -14px; left: 50%; transform: translateX(-50%); font-size: 26px; filter: drop-shadow(0 3px 3px rgba(0,0,0,.3)); }
.note input { flex: 1; padding: 10px 12px; border: 1px dashed #b9a94f; border-radius: 8px; background: transparent; font-size: 15px; font-family: 'Segoe Print', 'Comic Sans MS', cursive; }
.note button { padding: 10px 16px; border: none; border-radius: 8px; background: #ff9800; color: #fff; font-weight: 600; cursor: pointer; }
.note button:disabled { opacity: .5; cursor: default; }

.kitchen-grid { display: grid; grid-template-columns: minmax(320px, 560px) 1fr; gap: 26px; align-items: start; }
@media (max-width: 980px) { .kitchen-grid { grid-template-columns: 1fr; } }

.tip { color: #7a6a58; font-size: .9rem; text-align: center; }
.clear { display: block; margin: 10px auto 0; border: none; background: none; color: #999; cursor: pointer; text-decoration: underline; }

.right h2 { color: #6b4a2b; margin: 6px 0 10px; }
.right h2 small { color: #a08b76; font-weight: 400; }

/* плита */
.stove { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; background: linear-gradient(#e8e8ea, #d4d4d8); border-radius: 12px; padding: 12px; margin-bottom: 20px; box-shadow: inset 0 2px 6px rgba(0,0,0,.15); }
.burner { aspect-ratio: 1; border-radius: 50%; background: radial-gradient(circle at 40% 35%, #555, #2b2b2b); display: flex; align-items: center; justify-content: center; }
.coil { width: 70%; height: 70%; border-radius: 50%; border: 4px double #666; transition: all .4s; }
.burner.on .coil { border-color: #ff5722; box-shadow: 0 0 14px 4px rgba(255,87,34,.55), inset 0 0 10px rgba(255,87,34,.6); animation: glow 1.6s ease-in-out infinite alternate; }
@keyframes glow { to { box-shadow: 0 0 22px 8px rgba(255,87,34,.75), inset 0 0 14px rgba(255,87,34,.8); } }

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
