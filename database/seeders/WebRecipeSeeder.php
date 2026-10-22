<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeStep;
use App\Support\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * СИДЕР ИЗ ИНТЕРНЕТА: загружает реальные рецепты с фотографиями.
 *
 * Данные готовит скрипт database/seeders/data/fetch_web_recipes.mjs:
 * он тянет блюда из открытых источников (TheMealDB и Food.com),
 * переводит названия, ингредиенты и шаги на русский и складывает
 * результат в data/web_recipes.json.
 *
 * Особенность: у каждого рецепта СВОЯ фотография, соответствующая именно
 * этому блюду (main_image_url), а не случайная заглушка. У блюд TheMealDB
 * ещё и ссылка на видео (strYoutube) — она попадает в video_url.
 *
 * Сидер идемпотентен: рецепт опознаётся по паре source + source_id
 * (ключ блюда в источнике). Повторный запуск обновляет существующий рецепт
 * по этому ключу и не создаёт дублей со суффиксом "-2".
 */
class WebRecipeSeeder extends Seeder
{
    /** Сколько рецептов обрабатывать за одну транзакцию. */
    private const CHUNK_SIZE = 50;

    /**
     * Раскладка иностранных категорий по нашим десяти.
     * Ключ — slug нашей категории, значение — категории источника,
     * которые в неё попадают.
     */
    private const CATEGORY_MAP = [
        'zavtraki'      => ['breakfast', 'brunch', 'pancake', 'eggs'],
        'supy'          => ['soup', 'soups', 'stew', 'broth', 'chili', 'bisque'],
        'vtorye-blyuda' => ['beef', 'chicken', 'lamb', 'pork', 'goat', 'main course', 'meat', 'steak', 'curry', 'kebab', 'roast', 'grill', 'seafood', 'fish', 'pasta', 'risotto', 'dinner', 'lunch', 'main dish', 'pizza', 'burger', 'braise', 'casserole', 'stir-fry', 'taco', 'noodle', 'gnocchi', 'lasagna', 'omelette', 'omelet', 'macaroni', 'spaghetti', 'carbonara', 'chowder'],
        'salaty'        => ['salad', 'slaw', 'side salad', 'green salad', 'coleslaw', 'pasta salad'],
        'deserty'       => ['dessert', 'cake', 'cookies', 'cookie', 'ice cream', 'pudding', 'pie', 'tart', 'brownie', 'mousse', 'sorbet', 'cheesecake', 'custard', 'fudge', 'sweets', 'sweet', 'chocolate'],
        'vypechka'      => ['bread', 'baking', 'bake', 'pastry', 'bun', 'roll', 'scones', 'muffin', 'cupcake', 'doughnut', 'donut', 'pancakes', 'waffle', 'crepe', 'pancake'],
        'zakuski'       => ['starter', 'appetizer', 'appetiser', 'snack', 'finger food', 'dip', 'tapas', 'antipasti', 'canape', 'savoury', 'savory'],
        'napitki'       => ['drink', 'beverage', 'cocktail', 'smoothie', 'juice', 'coffee', 'tea', 'sherbet'],
        'soussy'        => ['sauce', 'dressing', 'marinade', 'condiment', 'spread'],
        'garniry'       => ['side', 'accompaniment', 'garnish', 'fries', 'rice', 'potato', 'vegetable side', 'mashed potato', 'polenta'],
    ];

    /**
     * Категории источника, которые мы НЕ переносим: слишком специфичные
     * (например, «mcdonalds», «chocolate cake» из American-раздела food.com),
     * их лучше не тащить в русскую кухню.
     */
    private const EXCLUDE_CATEGORIES = [
        'chocolate cake', 'mcdonalds', 'crock pot', 'slow cooker', 'air fryer',
        'instant pot', 'pressure cooker', 'kid friendly', '5 ingredients or less',
        '3 ingredients or less', '4 ingredients or less', '60 minutes or less',
        '30 minutes or less', 'no cook', 'vegan', 'gluten free', 'keto',
        'low carb', 'high protein', 'low fat', 'weight loss', 'diabetic',
        'omni', 'crockpot', 'slowcooker', 'blender', 'deep dish', 'bundt',
    ];

    /** Ключ для поиска уже загруженного рецепта — source + source_id. */
    private const SOURCE_COLUMN = 'source_id';

    /**
     * Путь к файлу с рецептами. Тесты подменяют его, чтобы не трогать
     * настоящий файл data/web_recipes.json, который собирает скрипт
     * fetch_web_recipes.mjs.
     */
    public static ?string $dataPathOverride = null;

    /** Откуда читать рецепты: подмена из тестов или обычный файл данных. */
    private function dataPath(): string
    {
        return self::$dataPathOverride ?? __DIR__ . '/data/web_recipes.json';
    }

    public function run(): void
    {
        $path = $this->dataPath();

        if (!is_file($path)) {
            $this->command?->warn(
                'Файл data/web_recipes.json не найден. Сначала выполните: ' .
                'node database/seeders/data/fetch_web_recipes.mjs 1100'
            );
            return;
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw) || $raw === []) {
            $this->command?->warn('Файл data/web_recipes.json пуст или повреждён.');
            return;
        }

        $this->command?->info('Загружаю рецептов из интернета: ' . count($raw) . '…');

        $categoryIds = Category::pluck('id', 'slug')->toArray();

        // Уже загруженные рецепты из интернета — по ключу источника.
        $existingBySource = Recipe::query()
            ->whereNotNull('source')
            ->whereNotNull('source_id')
            ->get(['id', 'source', 'source_id', 'slug'])
            ->keyBy(fn (Recipe $r) => $r->source . ':' . $r->source_id);

        // Slug'ы всех рецептов — чтобы не выдать дубликат уже существующему.
        // Именно как ключи (flip): uniqueSlug() проверяет isset($existing[$slug]),
        // поэтому список со сдвигом на единицу не сработал бы.
        $existingSlugs = Recipe::query()->pluck('slug')->flip()->all();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        // Обрабатываем пачками, чтобы не держать в памяти всё сразу.
        foreach (array_chunk($raw, self::CHUNK_SIZE) as $chunk) {
            DB::transaction(function () use (
                $chunk, $categoryIds, $existingBySource, &$existingSlugs, &$created, &$updated, &$skipped
            ) {
                foreach ($chunk as $item) {
                    try {
                        $result = $this->importRecipe(
                            $item,
                            $categoryIds,
                            $existingBySource,
                            $existingSlugs
                        );
                    } catch (QueryException $e) {
                        // Одно битое блюдо не должно рушить загрузку всей
                        // партии: пропускаем его и идём дальше.
                        $result = 'skipped';
                        $this->command?->warn(
                            'Пропущено «' . ($item['titleRu'] ?? '?') . '»: ' . $e->getMessage()
                        );
                    }

                    if ($result === 'created') {
                        $created++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            });
        }

        $this->command?->info(
            "Готово: добавлено {$created}, обновлено {$updated}, пропущено {$skipped}."
        );
    }

    /**
     * Импортирует один рецепт. Возвращает created | updated | skipped.
     */
    private function importRecipe(
        array $item,
        array $categoryIds,
        \Illuminate\Support\Collection $existingBySource,
        array &$existingSlugs
    ): string {
        $title = trim((string) ($item['titleRu'] ?? ''));
        if ($title === '') {
            return 'skipped';
        }

        $source = (string) ($item['source'] ?? '');
        $sourceId = (string) ($item['sourceId'] ?? '');

        if ($source === '' || $sourceId === '') {
            return 'skipped';
        }

        $key = $source . ':' . $sourceId;

        $ingredients = $item['ingredients'] ?? [];
        $namesRu = $item['ingredientNamesRu'] ?? [];

        // Текстовый список ингредиентов — в том же формате, что ожидает
        // IngredientParser: «Название — количество» на каждой строке.
        $lines = [];
        $pairs = [];
        foreach ($ingredients as $index => $ing) {
            $name = trim((string) ($namesRu[$index] ?? $ing['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $measure = trim((string) ($ing['measure'] ?? ''));
            $lines[] = $measure !== '' ? "{$name} — {$measure}" : $name;
            $pairs[] = ['name' => $name, 'quantity' => $measure];
        }
        if ($lines === []) {
            return 'skipped';
        }

        $steps = $this->buildSteps($item);

        $existingRecipe = $existingBySource->get($key);

        // Slug: для нового рецепта — по названию с проверкой уникальности;
        // для уже загруженного — тот же, что был, чтобы ссылка не сломалась.
        $slug = $existingRecipe?->slug
            ?? $this->uniqueSlug($title, $existingSlugs);
        if ($slug === null) {
            return 'skipped';
        }

        // Категория может оказаться исключённой (например, «chocolate cake»
        // или «mcdonalds») — такое блюдо в русскую кухню не тащим.
        $categoryId = $this->pickCategory($item, $categoryIds);
        if ($categoryId === null) {
            return 'skipped';
        }

        $attributes = [
            'category_id'      => $categoryId,
            'title'            => $title,
            'slug'             => $slug,
            'description'      => trim((string) ($item['description'] ?? '')) ?: null,
            'ingredients'      => implode("\n", $lines),
            'instructions'     => $this->formatInstructions($steps),
            'cook_time_minutes' => $this->pickCookTime($item),
            'servings'         => (int) ($item['servings'] ?? 4) ?: 4,
            'difficulty'       => $this->pickDifficulty($item),
            'calories_per_serving' => isset($item['calories']) ? (int) round($item['calories']) : null,
            'main_image_url'   => (string) ($item['image'] ?? ''),
            'video_url'        => (string) ($item['video'] ?? ''),
            'is_active'        => true,
            'created_by'       => 'Кулинарный архив',
            'source'           => $source,
            'source_id'        => $sourceId,
            'updated_at'       => now(),
        ];

        // Рецепт уже есть (повторный запуск сидера) — обновляем, не плодим дубли.
        if ($existingRecipe) {
            $existingRecipe->update($attributes);
            $this->syncIngredients($existingRecipe, $pairs);
            $this->syncSteps($existingRecipe, $steps);
            return 'updated';
        }

        $recipe = Recipe::create($attributes);
        $this->syncIngredients($recipe, $pairs);
        $this->syncSteps($recipe, $steps);

        $existingSlugs[$slug] = true;
        $existingBySource->put($key, $recipe);
        return 'created';
    }

    /**
     * Привязывает ингредиенты к рецепту, создавая новые при необходимости.
     * Название нормализуется, чтобы «Куриная грудка» и «куриная грудка»
     * не превращались в два разных ингредиента.
     */
    private function syncIngredients(Recipe $recipe, array $pairs): void
    {
        $ids = [];

        foreach ($pairs as $pair) {
            $name = trim($pair['name']);
            if ($name === '') {
                continue;
            }

            $slug = Slug::make($name);
            if ($slug === '') {
                continue;
            }

            $ingredient = Ingredient::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name]
            );

            $ids[$ingredient->id] = ['quantity' => $pair['quantity']];
        }

        $recipe->ingredientsRelation()->sync($ids);
    }

    /** Перезаписывает шаги рецепта. */
    private function syncSteps(Recipe $recipe, array $steps): void
    {
        $recipe->steps()->delete();

        $rows = [];
        $now = now()->toDateTimeString();
        $number = 0;

        foreach ($steps as $step) {
            $number++;
            $rows[] = [
                'recipe_id'        => $recipe->id,
                'step_number'      => $number,
                'title'            => $step['title'],
                'description'      => $step['description'],
                'image_url'        => null,
                'duration_minutes' => $step['duration'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        if ($rows !== []) {
            RecipeStep::query()->insert($rows);
        }
    }

    /**
     * Раскладывает шаги источника на типовые этапы с названиями.
     * У источников инструкции — это плоский список предложений, поэтому
     * разбиваем их на шаги и даём каждому осмысленный заголовок.
     */
    private function buildSteps(array $item): array
    {
        $raw = $item['stepsRu'] ?? [];
        $titles = [
            'Подготовка ингредиентов',
            'Подготовка продуктов',
            'Приготовление',
            'Обжарка и тушение',
            'Доведение до готовности',
            'Финальная сборка',
            'Подача к столу',
        ];

        $steps = [];
        foreach ($raw as $index => $text) {
            $text = trim((string) $text);
            if ($text === '' || mb_strlen($text) < 10) {
                continue;
            }

            $steps[] = [
                'title'       => $titles[min($index, count($titles) - 1)],
                'description' => $text,
                'duration'    => 5 + ($index % 4) * 5,
            ];
        }

        if ($steps === []) {
            $steps[] = [
                'title'       => 'Приготовление',
                'description' => 'Приготовьте блюдо согласно описанию рецепта.',
                'duration'    => 20,
            ];
        }

        return array_slice($steps, 0, 10);
    }

    /** Форматирует шаги в текстовый блок с нумерацией. */
    private function formatInstructions(array $steps): string
    {
        $lines = [];
        foreach ($steps as $index => $step) {
            $lines[] = ($index + 1) . '. ' . $step['title'] . ': ' . $step['description'];
        }
        return implode("\n", $lines);
    }

    /**
     * Выбирает нашу категорию по категории источника.
     * Точное совпадение важнее, чем вхождение подстроки.
     *
     * Возвращает null, когда блюдо попало в исключённые категории — тогда
     * рецепт пропускается целиком (в recipes.category_id NOT NULL).
     */
    private function pickCategory(array $item, array $categoryIds): ?int
    {
        $sourceCategory = mb_strtolower(trim((string) ($item['category'] ?? '')));

        foreach (self::EXCLUDE_CATEGORIES as $excluded) {
            if (str_contains($sourceCategory, $excluded)) {
                // Исключённые блюда всё же полезны, если это просто
                // «Keto Beef» — но чистые «chocolate cake» пропускаем.
                if (in_array($sourceCategory, self::EXCLUDE_CATEGORIES, true)) {
                    return null;
                }
            }
        }

        foreach (self::CATEGORY_MAP as $slug => $needles) {
            foreach ($needles as $needle) {
                if ($sourceCategory === $needle) {
                    return $categoryIds[$slug] ?? null;
                }
            }
        }

        // Подстрока — второй проход, когда категория источника длиннее
        // («Chicken Breast» → «chicken»).
        foreach (self::CATEGORY_MAP as $slug => $needles) {
            foreach ($needles as $needle) {
                if (mb_strlen($needle) >= 4 && str_contains($sourceCategory, $needle)) {
                    return $categoryIds[$slug] ?? null;
                }
            }
        }

        // Теги иногда точнее самой категории. Сверяем НЕПЕРЕВЕДЁННЫЕ теги
        // (tags): переведённые (tagsRu) с английскими образцами не совпадут.
        foreach (($item['tags'] ?? []) as $tag) {
            $tag = mb_strtolower(trim((string) $tag));
            foreach (self::CATEGORY_MAP as $slug => $needles) {
                if (in_array($tag, $needles, true)) {
                    return $categoryIds[$slug] ?? null;
                }
            }
        }

        // Не разобрали — кладём во «Вторые блюда».
        return $categoryIds['vtorye-blyuda'] ?? null;
    }

    /** Время приготовления: из источника, иначе — оценка по числу шагов. */
    private function pickCookTime(array $item): int
    {
        $time = (int) ($item['cookTimeMinutes'] ?? 0);
        if ($time > 0 && $time < 60 * 24) {
            return $time;
        }

        $steps = count($item['stepsRu'] ?? []);
        return max(10, min(180, $steps * 8 + 10));
    }

    /** Сложность — по времени готовки. */
    private function pickDifficulty(array $item): string
    {
        $time = $this->pickCookTime($item);
        return match (true) {
            $time <= 30 => 'easy',
            $time <= 75 => 'medium',
            default => 'hard',
        };
    }

    /**
     * Генерирует уникальный slug. Если slug занят другим рецептом,
     * добавляем короткий суффикс.
     */
    private function uniqueSlug(string $title, array $existing): ?string
    {
        $base = Slug::make($title);
        if ($base === '') {
            return null;
        }

        $slug = $base;
        $suffix = 2;

        while (isset($existing[$slug])) {
            $slug = $base . '-' . $suffix;
            $suffix++;
            if ($suffix > 50) {
                return null;
            }
        }

        return $slug;
    }
}
