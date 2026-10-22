<template>
  <aside class="filters">
    <h3>Фильтры</h3>
    <p class="hint">Применяются сразу, как только вы что-то изменили.</p>

    <!-- Активные фильтры: видно, что именно применено, и любой можно снять -->
    <div v-if="chips.length" class="chips">
      <button
        v-for="chip in chips"
        :key="chip.key"
        type="button"
        class="chip"
        :title="`Убрать фильтр «${chip.label}»`"
        @click="removeChip(chip)"
      >
        {{ chip.label }} <span class="x">✕</span>
      </button>
    </div>

    <label v-if="showCategory" class="group">
      <span>Категория</span>
      <select v-model.number="local.category_id">
        <option :value="null">Все категории</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }} ({{ c.recipes_count }})</option>
      </select>
    </label>

    <div class="group">
      <span>Сложность</span>
      <label class="check"><input type="checkbox" value="easy" v-model="local.difficulty" /> Легко</label>
      <label class="check"><input type="checkbox" value="medium" v-model="local.difficulty" /> Средне</label>
      <label class="check"><input type="checkbox" value="hard" v-model="local.difficulty" /> Сложно</label>
    </div>

    <label class="group">
      <span>Время приготовления: до <b>{{ local.max_time >= 180 ? '180+ мин' : local.max_time + ' мин' }}</b></span>
      <input type="range" min="15" max="180" step="15" v-model.number="local.max_time" />
    </label>

    <label class="group">
      <span>Сортировка</span>
      <select v-model="local.sort">
        <option value="new">По дате добавления</option>
        <option value="popular">По популярности</option>
        <option value="time">По времени приготовления</option>
        <option value="title">По названию</option>
      </select>
    </label>

    <div class="btns">
      <button class="apply" @click="applyNow">Применить фильтры</button>
      <button class="reset" @click="$emit('reset')">Сбросить фильтры</button>
    </div>
  </aside>
</template>

<script setup>
/* Фильтры каталога — категория, сложность (чекбоксы), ползунок времени, сортировка.
   Применяются сразу: раньше выдача менялась только по кнопке «Применить
   фильтры», и без её нажатия фильтр выглядел неработающим. */
import { computed, reactive, watch } from 'vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  modelValue: { type: Object, required: true },
  // На странице категории выбор категории не нужен — она уже задана.
  showCategory: { type: Boolean, default: true },
})
const emit = defineEmits(['apply', 'reset'])

const local = reactive({
  category_id: props.modelValue.category_id ?? null,
  difficulty: [...(props.modelValue.difficulty || [])],
  max_time: props.modelValue.max_time ?? 180,
  sort: props.modelValue.sort || 'new',
})

/**
 * Синхронизируем поля по отдельности, а не по объекту modelValue.
 * Страница может менять фильтры и заменой объекта, и мутацией свойств —
 * watch на объект во втором случае не срабатывал, и панель фильтров
 * показывала пустое состояние вместо применённого.
 */
watch(() => props.modelValue.category_id, (v) => { local.category_id = v ?? null })
watch(() => props.modelValue.difficulty, (v) => { local.difficulty = [...(v || [])] }, { deep: true })
watch(() => props.modelValue.max_time, (v) => { local.max_time = v ?? 180 })
watch(() => props.modelValue.sort, (v) => { local.sort = v || 'new' })

const DIFFICULTY_LABELS = { easy: 'Легко', medium: 'Средне', hard: 'Сложно' }
const SORT_LABELS = { new: 'По дате', popular: 'По популярности', time: 'По времени', title: 'По названию' }

/** Снимок состояния — по нему решаем, изменилось ли что-то по-настоящему. */
function snapshot(state) {
  return JSON.stringify({
    category_id: state.category_id ?? null,
    difficulty: [...(state.difficulty || [])].sort(),
    max_time: state.max_time ?? 180,
    sort: state.sort || 'new',
  })
}

/** Применённые фильтры — видно, что именно сейчас отсекает выдачу. */
const chips = computed(() => {
  const list = []

  if (props.showCategory && local.category_id) {
    const category = props.categories.find((c) => c.id === local.category_id)
    list.push({ key: 'category', label: category ? category.name : 'Категория' })
  }

  for (const value of local.difficulty) {
    list.push({ key: 'difficulty:' + value, label: DIFFICULTY_LABELS[value] || value })
  }

  if (local.max_time < 180) {
    list.push({ key: 'max_time', label: `до ${local.max_time} мин` })
  }

  if (local.sort && local.sort !== 'new') {
    list.push({ key: 'sort', label: SORT_LABELS[local.sort] || local.sort })
  }

  return list
})

let timer = null

function applyNow() {
  clearTimeout(timer)
  emit('apply', {
    category_id: local.category_id ?? null,
    difficulty: [...local.difficulty],
    max_time: local.max_time,
    sort: local.sort,
  })
}

/**
 * Любое изменение в панели сразу применяется к выдаче.
 * Ползунок тянут непрерывно, поэтому ждём паузу в 200 мс, а для
 * остальных элементов хватает того же ожидания.
 */
watch(
  local,
  () => {
    clearTimeout(timer)

    // Панель повторяет то, что пришло извне (из адреса страницы или после
    // сброса) — выдачу трогать не нужно, иначе получится бесконечный цикл.
    if (snapshot(local) === snapshot(props.modelValue)) return

    timer = setTimeout(applyNow, 200)
  },
  { deep: true },
)

/** Снятие отдельного фильтра из «чипсов». */
function removeChip(chip) {
  if (chip.key === 'category') local.category_id = null
  else if (chip.key === 'max_time') local.max_time = 180
  else if (chip.key === 'sort') local.sort = 'new'
  else if (chip.key.startsWith('difficulty:')) {
    const value = chip.key.slice('difficulty:'.length)
    local.difficulty = local.difficulty.filter((d) => d !== value)
  }

  applyNow()
}
</script>

<style scoped>
.filters { background: #fff; border: 1px solid #eee; border-radius: 14px; padding: 16px; position: sticky; top: 80px; }
.filters h3 { margin: 0 0 6px; }
.hint { margin: 0 0 12px; font-size: .78rem; color: #888; line-height: 1.35; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
.chip {
  display: inline-flex; align-items: center; gap: 6px;
  border: 1px solid #ffcc80; background: #fff3e0; color: #e65100;
  border-radius: 999px; padding: 4px 10px; font: inherit; font-size: .78rem;
  cursor: pointer;
}
.chip:hover { background: #ffe7c2; }
.chip .x { font-weight: 700; opacity: .7; }
.chip:hover .x { opacity: 1; }
.group { display: block; margin-bottom: 16px; font-size: .9rem; color: #444; }
.group > span { display: block; font-weight: 600; margin-bottom: 6px; }
select, input[type='range'] { width: 100%; }
select { padding: 8px; border: 1px solid #ddd; border-radius: 8px; background: #fff; }
.check { display: flex; gap: 8px; align-items: center; margin: 4px 0; cursor: pointer; }
.btns { display: flex; flex-direction: column; gap: 8px; }
.apply { background: #ff9800; color: #fff; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; }
.reset { background: #f3f4f6; color: #555; border: none; padding: 10px; border-radius: 8px; cursor: pointer; }
</style>
