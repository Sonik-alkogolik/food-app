<template>
  <!-- «Холодильник»: дверца с магнитиками-продуктами. Клик по продукту — убрать в полку / обратно -->
  <div class="fridge" :class="{ open: doorOpen }">
    <div class="fridge-body">
      <!-- внутренность -->
      <div class="fridge-inside">
        <div class="light"></div>
        <div v-for="(row, ri) in shelves" :key="ri" class="int-shelf">
          <span v-for="item in row" :key="item.name" class="food" :class="{ expired: item.expired }" @click="toggle(item.name)">
            {{ emojiFor(item.name) }}
          </span>
          <span v-if="!row.length" class="row-empty"></span>
        </div>
        <div class="crisper">
          <span class="crisper-label">🥕 Ящик для овощей</span>
        </div>
      </div>

      <!-- дверца -->
      <div class="door" @click.self="doorOpen = !doorOpen">
        <div class="door-top">
          <button class="door-handle" @click.stop="doorOpen = !doorOpen" :aria-label="doorOpen ? 'Закрыть дверцу' : 'Открыть дверцу'">
            {{ doorOpen ? '🚪 Закрыть' : 'Открой дверцу →' }}
          </button>
        </div>
        <div class="magnets">
          <button
            v-for="item in items"
            :key="item.name"
            class="magnet"
            :class="{ expired: item.expired, taken: isOnShelf(item.name) }"
            :style="{ '--rot': rotFor(item.name) + 'deg', '--dl': delayFor(item.name) + 'ms' }"
            @click.stop="toggle(item.name)"
            :title="isOnShelf(item.name) ? 'Уже на полке — кликни, чтобы вернуть в холодильник' : 'Достать на полку' + (item.expired ? ' (просрочено!)' : '')"
          >
            <span class="m-emoji">{{ emojiFor(item.name) }}</span>
            <span class="m-name">{{ item.name }}</span>
            <span v-if="item.expired" class="m-exp">⏰</span>
          </button>
          <div v-if="!items.length" class="no-magnets">
            🛒 В холодильнике пусто.<br />Добавь продукты сверху ⬆️
          </div>
        </div>
        <div class="brand">❄️ FRESH-1000</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] }, // [{ name, expired }]
  shelfNames: { type: Array, default: () => [] }, // что сейчас стоит на кухонной полке
})
const emit = defineEmits(['toggle'])

const doorOpen = ref(false)

const isOnShelf = (name) => props.shelfNames.includes(name)
const toggle = (name) => emit('toggle', name)

const EMOJI = [
  [['яйц'], '🥚'], [['мука'], '🌾'], [['масло'], '🧈'], [['молоко'], '🥛'],
  [['сыр'], '🧀'], [['куриц', 'мяс', 'говяд', 'свинин', 'индейк'], '🍗'],
  [['рыб', 'морепрод', 'тунц', 'лосос'], '🐟'], [['помидор', 'томат'], '🍅'],
  [['огурц'], '🥒'], [['морков'], '🥕'], [['лук'], '🧅'], [['чеснок'], '🧄'],
  [['картоф', 'картош'], '🥔'], [['капуст'], '🥬'], [['перец'], '🫑'],
  [['гриб', 'шампинь'], '🍄'], [['рис', 'гречк', 'овсян', 'булгур', 'макарон', 'крупа'], '🍚'],
  [['сахар', 'мёд', 'варенье'], '🍯'], [['соль', 'специ', 'приправ'], '🧂'],
  [['консерв', 'горошк', 'кукуруз'], '🥫'], [['лимон'], '🍋'], [['банан'], '🍌'],
  [['яблок'], '🍎'], [['груш'], '🍐'], [['виноград'], '🍇'], [['клубник'], '🍓'],
  [['черник'], '🫐'], [['апельсин'], '🍊'], [['орех', 'миндал', 'фундук'], '🥜'],
  [['шоколад', 'какао'], '🍫'], [['хлеб', 'батон', 'лаваш'], '🍞'],
  [['сметан', 'кефир', 'творог', 'йогурт'], '🥣'], [['бекон', 'колбас', 'сосиск', 'ветчин'], '🥓'],
]

function emojiFor(name) {
  const n = name.toLowerCase()
  for (const [keys, e] of EMOJI) if (keys.some((k) => n.includes(k))) return e
  return '🥘'
}

// лёгкий случайный, но стабильный наклон магнита
function rotFor(name) {
  let h = 0
  for (let i = 0; i < name.length; i++) h += name.charCodeAt(i)
  return ((h % 9) - 4) * 1.6
}
function delayFor(name) {
  let h = 0
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) % 997
  return h % 400
}

// продукты внутри холодильника — раскладываем по 3 стеклянным полкам
const shelves = computed(() => {
  const rows = [[], [], []]
  props.items.forEach((it, i) => rows[i % 3].push(it))
  return rows
})
</script>

<style scoped>
.fridge { max-width: 560px; margin: 8px auto 26px; perspective: 1200px; }
.fridge-body { position: relative; height: 430px; border-radius: 18px; background: linear-gradient(160deg, #dfe9ef, #c3d2dc); box-shadow: 0 14px 30px rgba(0,0,0,.25), inset 0 0 0 3px #aab9c4; overflow: hidden; }

/* внутренность */
.fridge-inside { position: absolute; inset: 0; background: repeating-linear-gradient(180deg, #f3f8fb 0 130px, #e6eef4 130px 138px); padding: 14px; display: flex; flex-direction: column; gap: 8px; }
.light { height: 4px; border-radius: 2px; background: linear-gradient(90deg, transparent, #fff9c4, transparent); }
.int-shelf { flex: 1; display: flex; align-items: flex-end; gap: 10px; padding: 0 8px 4px; border-bottom: 4px solid rgba(140,160,175,.55); border-radius: 0 0 4px 4px; font-size: 26px; min-height: 70px; }
.food { cursor: pointer; filter: drop-shadow(0 2px 2px rgba(0,0,0,.2)); transition: transform .15s; }
.food:hover { transform: translateY(-4px) scale(1.15); }
.food.expired { opacity: .45; }
.crisper { height: 56px; border-radius: 10px; background: linear-gradient(180deg, rgba(180,205,220,.7), rgba(150,180,200,.85)); display: flex; align-items: center; justify-content: center; }
.crisper-label { color: #4a6272; font-size: .8rem; }

/* дверца */
.door { position: absolute; inset: 0; background: linear-gradient(150deg, #eef4f7 0%, #d5e2ea 55%, #c2d3de 100%); box-shadow: inset -6px 0 14px rgba(0,0,0,.12), inset 0 0 0 2px #b7c6d1; transform-origin: left center; transition: transform .6s cubic-bezier(.4,.1,.2,1); display: flex; flex-direction: column; padding: 14px 18px; z-index: 2; }
.fridge.open .door { transform: rotateY(-118deg); box-shadow: inset -12px 0 20px rgba(0,0,0,.2); }
.door-top { display: flex; justify-content: flex-end; }
.door-handle { border: none; background: #ff9800; color: #fff; padding: 8px 14px; border-radius: 10px; font-size: .9rem; cursor: pointer; box-shadow: 0 3px 8px rgba(0,0,0,.2); }
.magnets { flex: 1; display: flex; flex-wrap: wrap; align-content: flex-start; gap: 12px; padding: 18px 6px; overflow-y: auto; }
.magnet { position: relative; width: 84px; padding: 10px 6px 8px; border: none; border-radius: 10px; background: #fffdf5; box-shadow: 0 4px 8px rgba(0,0,0,.22), inset 0 0 0 1px #e8e2cf; cursor: pointer; transform: rotate(var(--rot, 0deg)); animation: stick .35s ease-out both; animation-delay: var(--dl, 0ms); display: flex; flex-direction: column; align-items: center; gap: 4px; }
@keyframes stick { from { transform: translateY(-26px) rotate(var(--rot, 0deg)); opacity: 0; } to { transform: rotate(var(--rot, 0deg)); opacity: 1; } }
.magnet:hover { transform: rotate(0deg) scale(1.07); z-index: 3; }
.m-emoji { font-size: 26px; line-height: 1; }
.m-name { font-size: .68rem; color: #4a4a4a; max-width: 78px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.m-exp { position: absolute; top: -6px; right: -4px; font-size: 13px; }
.magnet.expired { background: #ffecec; box-shadow: 0 4px 8px rgba(180,40,40,.25), inset 0 0 0 1.5px #e2a0a0; }
.magnet.taken { opacity: .45; filter: grayscale(.7); }
.magnet.taken::after { content: '🍽'; position: absolute; top: -8px; left: -6px; font-size: 14px; }
.no-magnets { margin: auto; text-align: center; color: #7d8b96; line-height: 1.7; }
.brand { text-align: center; color: #8fa2ae; font-size: .75rem; letter-spacing: 2px; }
</style>
