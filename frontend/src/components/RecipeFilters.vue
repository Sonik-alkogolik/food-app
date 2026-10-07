<template>
  <aside class="filters">
    <h3>Фильтры</h3>

    <label class="group">
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
      <button class="apply" @click="$emit('apply', local)">Применить фильтры</button>
      <button class="reset" @click="$emit('reset')">Сбросить фильтры</button>
    </div>
  </aside>
</template>

<script setup>
/* ШАГ 31: фильтры каталога — категория, сложность (чекбоксы), ползунок времени, сортировка */
import { reactive, watch } from 'vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  modelValue: { type: Object, required: true },
})
defineEmits(['apply', 'reset'])

const local = reactive({
  category_id: props.modelValue.category_id ?? null,
  difficulty: [...(props.modelValue.difficulty || [])],
  max_time: props.modelValue.max_time ?? 180,
  sort: props.modelValue.sort || 'new',
})

watch(() => props.modelValue, (v) => {
  local.category_id = v.category_id ?? null
  local.difficulty = [...(v.difficulty || [])]
  local.max_time = v.max_time ?? 180
  local.sort = v.sort || 'new'
})
</script>

<style scoped>
.filters { background: #fff; border: 1px solid #eee; border-radius: 14px; padding: 16px; position: sticky; top: 80px; }
.filters h3 { margin: 0 0 12px; }
.group { display: block; margin-bottom: 16px; font-size: .9rem; color: #444; }
.group > span { display: block; font-weight: 600; margin-bottom: 6px; }
select, input[type='range'] { width: 100%; }
select { padding: 8px; border: 1px solid #ddd; border-radius: 8px; background: #fff; }
.check { display: flex; gap: 8px; align-items: center; margin: 4px 0; cursor: pointer; }
.btns { display: flex; flex-direction: column; gap: 8px; }
.apply { background: #ff9800; color: #fff; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; }
.reset { background: #f3f4f6; color: #555; border: none; padding: 10px; border-radius: 8px; cursor: pointer; }
</style>
