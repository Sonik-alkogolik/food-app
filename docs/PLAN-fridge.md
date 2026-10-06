# План: «Холодильник» — подбор рецептов по ингредиентам

## Модель данных
1. `ingredients` — справочник ингредиентов (id, name UNIQUE, slug UNIQUE).
2. `ingredient_recipe` — pivot: рецепт ↔ ингредиент.
3. Миграция + backfill: распарсить текстовое поле `recipes.ingredients` ("Мука - 200г\n...")
   и создать связи для существующих рецептов. Поле оставляем как человекочитаемое
   описание, но матчинг делаем по структурированным связям.
4. «Холодильник» хранится на фронте (Pinia + localStorage), отдельная таблица не нужна.

## API
- GET  /api/ingredients            — список справочника (для datalist/автодополнения)
- POST /api/fridge/match           — { ingredients: ["мука","яйца",...] } →
  рецепты с match_percent, matched_ingredients, missing_ingredients,
  сортировка: сначала полные совпадения.

## Фронтенд (Vue 3 + Pinia)
- stores/fridge.js: items[], add/remove/clear, persist в localStorage.
- View FridgeView.vue: input + datalist-автодополнение, чипсы, авто-поиск (debounce),
  карточки рецептов с % совпадения и «не хватает», модалка полного рецепта.
- Router: /fridge; RecipeList остаётся.

## Тестирование
1. Feature-тесты Laravel: IngredientController, match (полное/частичное/пустое).
2. Ручной прогон: migrate:fresh --seed, artisan serve + curl /api/fridge/match.
3. npm run build — без ошибок.
