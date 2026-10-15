<template>
  <!-- Полка «как на кухне»: деревянная доска с стоящими на ней банками/коробками -->
  <div class="shelf-wrap">
    <div class="shelf-items">
      <div v-for="item in items" :key="item.name" class="jar-box" :class="{ expired: item.expired }" :title="item.expired ? 'Просрочено!' : ''">
        <button class="jar-x" @click="$emit('remove', item.name)" aria-label="Убрать с полки">×</button>
        <div class="jar" :style="{ background: jarColor(item.name) }">
          <div class="jar-lid"></div>
          <div class="jar-label">{{ emojiFor(item.name) }}</div>
        </div>
        <span class="jar-name">{{ item.name }}</span>
      </div>

      <div v-if="items.length === 0" class="empty-shelf">🕸️ Полка пуста — загляни в холодильник!</div>
    </div>
    <div class="shelf-board"><span class="screw left"></span><span class="screw right"></span></div>
  </div>
</template>

<script setup>
defineProps({
  items: { type: Array, default: () => [] }, // [{ name, expired }]
})
defineEmits(['remove'])

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

// Детерминированный цвет «банки» по названию продукта
function jarColor(name) {
  let h = 0
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) % 360
  return `linear-gradient(180deg, hsl(${h},45%,88%), hsl(${h},50%,72%))`
}
</script>

<style scoped>
.shelf-wrap { margin: 6px 0 26px; }
.shelf-items { display: flex; flex-wrap: wrap; gap: 14px 10px; align-items: flex-end; padding: 0 10px; min-height: 96px; }
.jar-box { position: relative; width: 74px; display: flex; flex-direction: column; align-items: center; cursor: default; animation: put 0.25s ease-out; }
@keyframes put { from { transform: translateY(-14px); opacity: 0; } to { transform: none; opacity: 1; } }
.jar { width: 58px; height: 62px; border-radius: 8px 8px 12px 12px; position: relative; box-shadow: inset -6px 0 10px rgba(0,0,0,.12), inset 6px 0 8px rgba(255,255,255,.5), 0 3px 6px rgba(0,0,0,.18); }
.jar-lid { position: absolute; top: -8px; left: 5px; right: 5px; height: 12px; border-radius: 5px; background: #b98a5e; box-shadow: 0 1px 2px rgba(0,0,0,.25); }
.jar-label { position: absolute; inset: 14px 6px 8px; background: rgba(255,255,255,.88); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
.jar-name { margin-top: 6px; font-size: .72rem; color: #5b4636; max-width: 74px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: center; }
.jar-x { position: absolute; top: -14px; right: 2px; z-index: 2; width: 20px; height: 20px; border-radius: 50%; border: none; background: #c0392b; color: #fff; font-size: 13px; line-height: 1; cursor: pointer; opacity: 0; transition: opacity .15s; }
.jar-box:hover .jar-x { opacity: 1; }
.jar-box.expired .jar-label { outline: 2px dashed #c0392b; }
.jar-box.expired::after { content: '⏰'; position: absolute; bottom: 20px; right: 0; font-size: 14px; }
.empty-shelf { color: #a08b76; font-style: italic; padding-bottom: 18px; }
.shelf-board { position: relative; height: 16px; margin-top: 4px; border-radius: 3px; background: repeating-linear-gradient(90deg, #a9744f 0 26px, #9c6a48 26px 30px, #b07c56 30px 58px); box-shadow: 0 6px 10px rgba(0,0,0,.28), inset 0 2px 3px rgba(255,255,255,.25); }
.screw { position: absolute; top: 4px; width: 8px; height: 8px; border-radius: 50%; background: radial-gradient(circle at 35% 35%, #eee, #777); }
.screw.left { left: 10px; }
.screw.right { right: 10px; }
</style>
