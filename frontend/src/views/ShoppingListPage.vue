<template>
  <div class="shopping">
    <h1>🛒 Список покупок</h1>
    <p class="lead">Что купить к рецептам: отмечайте галочкой, что уже куплено.</p>

    <p v-if="store.error" class="error">{{ store.error }}</p>
    <p v-if="store.message" class="ok">{{ store.message }}</p>

    <div v-if="store.loading" class="loading">Загрузка списка…</div>

    <template v-else-if="store.activeList">
      <!-- Списки: переключение, создание, переименование, удаление -->
      <div class="lists">
        <button
          v-for="list in store.lists"
          :key="list.id"
          type="button"
          class="list-tab"
          :class="{ active: list.id === store.activeList.id }"
          @click="store.activeId = list.id"
        >
          {{ list.title }}
          <small>{{ list.items.filter((i) => !i.checked).length }}</small>
        </button>

        <button type="button" class="list-tab add" @click="startNewList">＋ Список</button>
      </div>

      <form v-if="newListTitle !== null" class="new-list" @submit.prevent="createList">
        <input v-model.trim="newListTitle" type="text" placeholder="Название списка" maxlength="120" />
        <button type="submit" class="btn">Создать</button>
        <button type="button" class="btn ghost" @click="newListTitle = null">Отмена</button>
      </form>

      <div class="head">
        <h2>
          {{ store.activeList.title }}
          <small>{{ store.remaining }} из {{ store.total }} не куплено</small>
        </h2>
        <div class="head-actions">
          <button type="button" class="btn ghost" @click="startRename">Переименовать</button>
          <button type="button" class="btn ghost" @click="removeList">Удалить список</button>
        </div>
      </div>

      <form v-if="renaming" class="new-list" @submit.prevent="renameList">
        <input v-model.trim="renameValue" type="text" maxlength="120" />
        <button type="submit" class="btn">Сохранить</button>
        <button type="button" class="btn ghost" @click="renaming = false">Отмена</button>
      </form>

      <!-- Позиции -->
      <ul v-if="store.activeList.items.length" class="items">
        <li v-for="item in store.activeList.items" :key="item.id" :class="{ done: item.checked }">
          <label>
            <input type="checkbox" :checked="item.checked" @change="toggle(item)" />
            <span class="name">{{ item.name }}</span>
            <span v-if="item.amount_label && item.amount !== null" class="amount">{{ item.amount_label }}</span>
          </label>
          <button type="button" class="remove" title="Убрать" @click="remove(item)">✖️</button>
        </li>
      </ul>
      <p v-else class="empty">В списке пока пусто. Добавьте продукты вручную или из рецепта.</p>

      <!-- Добавление позиции вручную -->
      <form class="add-item" @submit.prevent="addItem">
        <input v-model.trim="newItem.name" type="text" placeholder="Что купить?" maxlength="150" />
        <input v-model.trim="newItem.amount" type="text" placeholder="Кол-во" class="amount-input" />
        <input v-model.trim="newItem.unit" type="text" placeholder="Ед." maxlength="30" class="unit-input" />
        <button type="submit" class="btn" :disabled="!newItem.name">Добавить</button>
      </form>

      <p class="hint">
        На странице рецепта есть кнопка «🛒 В список покупок» — она добавит все
        ингредиенты рецепта разом.
      </p>
    </template>
  </div>
</template>

<script setup>
/* Список покупок: свои списки, позиции с галочками, добавление продуктов
   вручную и целиком из рецепта. */
import { onMounted, reactive, ref } from 'vue'
import { useShoppingListStore } from '../stores/shoppingList'

const store = useShoppingListStore()

const newListTitle = ref(null)
const renaming = ref(false)
const renameValue = ref('')
const newItem = reactive({ name: '', amount: '', unit: '' })

onMounted(async () => {
  try {
    await store.fetchLists()
  } catch {
    /* сообщение об ошибке уже в сторе */
  }
})

function startNewList() {
  newListTitle.value = ''
  renaming.value = false
}

async function createList() {
  if (!newListTitle.value) return
  try {
    await store.createList(newListTitle.value)
    newListTitle.value = null
  } catch {
    /* сообщение об ошибке уже в сторе */
  }
}

function startRename() {
  renameValue.value = store.activeList.title
  renaming.value = true
  newListTitle.value = null
}

async function renameList() {
  if (!renameValue.value) return
  try {
    await store.renameList(store.activeList.id, renameValue.value)
    renaming.value = false
  } catch {
    /* сообщение об ошибке уже в сторе */
  }
}

async function removeList() {
  if (!window.confirm(`Удалить список «${store.activeList.title}»?`)) return
  try {
    await store.deleteList(store.activeList.id)
  } catch {
    /* сообщение об ошибке уже в сторе */
  }
}

async function toggle(item) {
  try {
    await store.updateItem(store.activeList.id, item.id, { checked: !item.checked })
  } catch {
    /* сообщение об ошибке уже в сторе */
  }
}

async function remove(item) {
  try {
    await store.removeItem(store.activeList.id, item.id)
  } catch {
    /* сообщение об ошибке уже в сторе */
  }
}

async function addItem() {
  if (!newItem.name) return
  const amount = newItem.amount.replace(',', '.')
  try {
    await store.addItem(store.activeList.id, {
      name: newItem.name,
      amount: amount === '' ? null : Number(amount),
      unit: newItem.unit || null,
    })
    newItem.name = ''
    newItem.amount = ''
    newItem.unit = ''
  } catch {
    /* сообщение об ошибке уже в сторе */
  }
}
</script>

<style scoped>
.shopping { max-width: 760px; margin: 0 auto; }
h1 { margin: 0 0 6px; }
.lead { color: #666; margin: 0 0 18px; }
.loading, .empty { color: #777; padding: 18px 0; }
.error { color: #dc2626; background: #fef2f2; padding: 10px 14px; border-radius: 10px; }
.ok { color: #16a34a; background: #f0fdf4; padding: 10px 14px; border-radius: 10px; }
.lists { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.list-tab {
  border: 1px solid #e5e5e5; background: #fff; border-radius: 999px; padding: 7px 14px;
  font: inherit; cursor: pointer; color: #444;
}
.list-tab.active { border-color: #ff9800; color: #e65100; font-weight: 600; }
.list-tab small { color: #999; margin-left: 4px; }
.list-tab.add { color: #ff9800; border-style: dashed; }
.new-list { display: flex; gap: 8px; margin-bottom: 16px; }
.new-list input { flex: 1; padding: 9px 12px; border: 1px solid #ddd; border-radius: 10px; font: inherit; }
.head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.head h2 { margin: 0; }
.head small { color: #888; font-weight: normal; font-size: .9rem; margin-left: 8px; }
.head-actions { display: flex; gap: 8px; }
.items { list-style: none; padding: 0; margin: 16px 0; }
.items li {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  padding: 10px 4px; border-bottom: 1px solid #f0f0f0;
}
.items label { display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1; }
.items .done .name { text-decoration: line-through; color: #aaa; }
.items .amount { color: #888; font-size: .9rem; }
.remove { border: none; background: none; cursor: pointer; font-size: .95rem; opacity: .6; }
.remove:hover { opacity: 1; }
.add-item { display: flex; gap: 8px; margin-top: 18px; }
.add-item input { padding: 9px 12px; border: 1px solid #ddd; border-radius: 10px; font: inherit; }
.add-item input:first-child { flex: 1; }
.amount-input { width: 90px; }
.unit-input { width: 80px; }
.btn { border: none; background: #ff9800; color: #fff; border-radius: 10px; padding: 9px 16px; font: inherit; font-weight: 600; cursor: pointer; }
.btn:disabled { opacity: .5; cursor: default; }
.btn.ghost { background: #f3f4f6; color: #444; }
.hint { color: #888; font-size: .9rem; margin-top: 22px; }
@media (max-width: 600px) {
  .add-item { flex-wrap: wrap; }
  .amount-input, .unit-input { flex: 1; }
}
</style>
