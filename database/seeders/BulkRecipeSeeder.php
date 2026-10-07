<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Recipe;
use App\Support\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ШАГ 15: массовый сидер — генерирует ~1000 реалистичных рецептов.
 *
 * Берёт 85 базовых блюд из data/recipes_catalog.php (реальные ингредиенты и
 * пошаговые инструкции) и комбинирует их с прилагательными и вариациями,
 * доводя количество примерно до 1000 уникальных записей.
 * Вставка выполняется пакетами (chunk по 100 строк) через DB::table()->insert()
 * для скорости и экономии памяти.
 */
class BulkRecipeSeeder extends Seeder
{
    private const TARGET_COUNT = 1000;
    private const CHUNK_SIZE   = 100;

    /**
     * Прилагательные-вариации. Полный пул (9 штук по плану ШАГ 15):
     * домашний, классический, быстрый, праздничный, диетический,
     * острый, нежный, хрустящий, ароматный.
     */
    private const ADJECTIVES_MASC  = ['домашний', 'классический', 'быстрый', 'праздничный', 'диетический', 'острый', 'нежный', 'хрустящий', 'ароматный'];
    private const ADJECTIVES_FEM   = ['домашняя', 'классическая', 'быстрая', 'праздничная', 'диетическая', 'острая', 'нежная', 'хрустящая', 'ароматная'];
    private const ADJECTIVES_NEUT  = ['домашнее', 'классическое', 'быстрое', 'праздничное', 'диетическое', 'острое', 'нежное', 'хрустящее', 'ароматное'];
    private const ADJECTIVES_PLURAL = ['домашние', 'классические', 'быстрые', 'праздничные', 'диетические', 'острые', 'нежные', 'хрустящие', 'ароматные'];

    /** Уникальные суффиксы-стили — дают до 13 вариантов на блюдо без дублей slug */
    private const SUFFIX_STYLES = [
        'по-старинному', 'по-домашнему', 'с секретом', 'на скорую руку',
        'как у бабушки', 'постный вариант', 'детский вариант', 'для пикника',
        'летний', 'зимний', 'праздничный стол', 'быстрый ужин', 'лайт',
    ];

    /** Имена авторов (ШАГ 15: массив из 10–20 имён) */
    private const AUTHORS = [
        'Иван Петров', 'Мария Сидорова', 'Анна Кузнецова', 'Елена Соколова',
        'Ольга Морозова', 'Дмитрий Волков', 'Наталья Лебедева', 'Сергей Козлов',
        'Татьяна Новикова', 'Алексей Попов', 'Екатерина Зайцева', 'Павел Орлов',
        'Ирина Фёдорова', 'Андрей Никитин', 'Светлана Павлова', 'Роман Семёнов',
        'Юлия Голикова', 'Максим Виноградов', 'Вера Богданова', 'Николай Титов',
    ];

    public function run(): void
    {
        $catalog = require __DIR__ . '/data/recipes_catalog.php';

        // slug категории -> id
        $categoryIds = Category::pluck('id', 'slug')->toArray();

        // Детерминированный генератор — одинаковый результат при любом перезапуске
        mt_srand(20261007);

        $now = now()->toDateTimeString();
        $rows = [];
        $usedSlugs = [];

        foreach ($catalog as $baseName => $dish) {
            $categoryId = $categoryIds[$dish['category']] ?? null;
            if (!$categoryId) {
                continue; // категория ещё не засеяна — сначала запускайте CategorySeeder
            }

            $variations = $this->buildVariations($baseName);

            foreach ($variations as $i => $title) {
                $slug = Slug::make($title);
                if (isset($usedSlugs[$slug])) {
                    $slug .= '-' . (++$usedSlugs[$slug]);
                } else {
                    $usedSlugs[$slug] = 1;
                }

                [$difficulty, $cookTime, $calories] = $this->tweakDishParams($dish);

                $rows[] = [
                    'category_id'          => $categoryId,
                    'title'                => $title,
                    'slug'                 => $slug,
                    'description'          => $this->buildDescription($title, $dish),
                    'ingredients'          => $dish['ingredients'],
                    'instructions'         => $this->numberInstructions($dish['steps']),
                    'cook_time_minutes'    => $cookTime,
                    'servings'             => rand(2, 8),
                    'difficulty'           => $difficulty,
                    'calories_per_serving' => $calories,
                    'main_image_url'       => null, // заполнит ImageUrlSeeder (ШАГ 38)
                    'video_url'            => null,
                    'is_active'            => true,
                    'views_count'          => rand(0, 5000),
                    'favorites_count'      => rand(0, 500),
                    'created_by'           => self::AUTHORS[array_rand(self::AUTHORS)],
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
        }

        shuffle($rows); // перемешивание детерминировано mt_srand выше

        // Если каталог даёт меньше TARGET_COUNT — дополняем вариациями с автором,
        // чтобы в базе всегда было ровно 1000 рецептов (гарантия цели ШАГА 15).
        if (count($rows) < self::TARGET_COUNT) {
            $needed = self::TARGET_COUNT - count($rows);
            for ($k = 0; $k < $needed; $k++) {
                $src = $rows[$k % max(1, count($rows))];
                $author = self::AUTHORS[$k % count(self::AUTHORS)];
                $title = $src['title'] . ' от ' . $author;
                $slug = Slug::make($title);
                if (isset($usedSlugs[$slug])) {
                    $slug .= '-' . (++$usedSlugs[$slug]);
                } else {
                    $usedSlugs[$slug] = 1;
                }
                $src['title'] = $title;
                $src['slug'] = $slug;
                $src['created_by'] = $author;
                $rows[] = $src;
            }
        }

        $rows = array_slice($rows, 0, self::TARGET_COUNT);

        // Пакетная вставка chunk по 100 записей (оптимизация скорости)
        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table('recipes')->insert($chunk);
        }

        $this->command?->info('BulkRecipeSeeder: создано ' . count($rows) . ' рецептов.');
    }

    /**
     * Базовое название + все вариации (9 прилагательных + 13 стилей).
     * @return string[]
     */
    private function buildVariations(string $baseName): array
    {
        $titles = [$baseName];

        $gender = match (true) {
            (bool) preg_match('/ы$|и$/u', $baseName)                                    => 'plural',
            (bool) preg_match('/а$|я$|ь$|ня$|чь$|шь$/u', $baseName)                      => 'fem',
            (bool) preg_match('/ое$|ее$|е$/u', $baseName)                                => 'neut',
            default                                                                      => 'masc',
        };

        $pool = match ($gender) {
            'fem'    => self::ADJECTIVES_FEM,
            'neut'   => self::ADJECTIVES_NEUT,
            'plural' => self::ADJECTIVES_PLURAL,
            default  => self::ADJECTIVES_MASC,
        };

        // Все 9 прилагательных без повторов — 9 вариантов на блюдо
        foreach ($pool as $adjective) {
            $titles[] = $baseName . ' ' . $adjective;
        }

        // Плюс уникальные стилевые суффиксы — итого до 13 записей на блюдо.
        // 85 базовых блюд × ~12 = >1000 рецептов (цель TARGET_COUNT).
        foreach (self::SUFFIX_STYLES as $style) {
            $titles[] = $baseName . ' ' . $style;
        }

        return $titles;
    }

    /**
     * Небольшая вариативность параметров блюда:
     * сложкость easy/medium/hard ≈ 40/50/10%, время 15–180 мин, калории 150–800.
     * @return array{0:string,1:int,2:int}
     */
    private function tweakDishParams(array $dish): array
    {
        $roll = mt_rand(1, 100);
        $difficulty = match (true) {
            $roll <= 40 => 'easy',
            $roll <= 90 => 'medium',
            default     => 'hard',
        };

        $timeDelta = ['easy' => -10, 'medium' => 0, 'hard' => 20][$difficulty];
        $cookTime = max(15, min(180, $dish['time'] + $timeDelta + rand(-5, 15)));

        $calories = max(150, min(800, $dish['calories'] + rand(-40, 60)));

        return [$difficulty, $cookTime, $calories];
    }

    /** Краткое описание на 1–2 предложения */
    private function buildDescription(string $title, array $dish): string
    {
        $templates = [
            "{$title} — проверенный рецепт с насыщенным вкусом и простой подготовкой.",
            "Любимый {$title}: готовится из доступных продуктов, всегда получается вкусным.",
            "{$title} порадует семью ароматом и нежной текстурой — идеальный вариант для будней и праздника.",
            "Классический {$title} с гарантированным результатом даже у начинающих хозяюшек.",
            "{$title} — сытное блюдо с богатым вкусом, которое легко повторить дома.",
        ];

        return $templates[mt_rand(0, count($templates) - 1)];
    }

    /** Инструкции из шагов каталога — нумерованный текст */
    private function numberInstructions(array $steps): string
    {
        $out = [];
        foreach ($steps as $i => [$title, $description]) {
            $out[] = ($i + 1) . '. ' . $title . ': ' . $description;
        }

        return implode("\n", $out);
    }
}
