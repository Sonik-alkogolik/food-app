<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ШАГ 38: сидер изображений.
 *
 * Раньше сидер подставлял в пустые места случайные с picsum.photos — в
 * каталоге «Яичница праздничная» показывалась фотография машины. Теперь
 * настоящие фотографии блюд:
 *
 * - для рецептов из локального каталога — фото именно этого блюда из
 *   data/dish_photos.json (85 базовых блюд → TheMealDB), а если блюдо не
 *   опознано по названию — фото из запасного пула его категории;
 * - для рецептов из интернета — их собственные фотографии, они не трогаются;
 * - для шагов — дополнительные фотографии из Food.com
 *   (data/foodcom_images.json), а где их нет — шаг остаётся без картинки.
 *
 * Пустые ссылки picsum/loremflickr считаются заглушками: настоящим фото
 * считается только ссылка на конкретную картинку блюда.
 *
 * Повторный запуск безопасен: настоящие фото не затираются.
 */
class ImageUrlSeeder extends Seeder
{
    /**
     * Тематические фото категорий (Unsplash CDN).
     * Ссылки проверены — все отдают 200 с картинкой нужной категории.
     */
    private const CATEGORY_IMAGES = [
        'zavtraki'      => 'https://images.unsplash.com/photo-1536935338788-846bb9981813?w=640&q=70&auto=format&fit=crop',
        'supy'          => 'https://images.unsplash.com/photo-1534939561126-855b8675edd7?w=640&q=70&auto=format&fit=crop',
        'vtorye-blyuda' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=640&q=70&auto=format&fit=crop',
        'salaty'        => 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=640&q=70&auto=format&fit=crop',
        'deserty'       => 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=640&q=70&auto=format&fit=crop',
        'vypechka'      => 'https://images.unsplash.com/photo-1607453998774-d533f65dac99?w=640&q=70&auto=format&fit=crop',
        'zakuski'       => 'https://images.unsplash.com/photo-1600289031464-74d374b64991?w=640&q=70&auto=format&fit=crop',
        'napitki'       => 'https://images.unsplash.com/photo-1607330289024-1535c6b4e1c1?w=640&q=70&auto=format&fit=crop',
        'soussy'        => 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=640&q=70&auto=format&fit=crop',
        'garniry'       => 'https://images.unsplash.com/photo-1559847844-5315695dadae?w=640&q=70&auto=format&fit=crop',
    ];

    /**
     * Сервисы-заглушки: картинка оттуда — это «случайная фотография»,
     * а не фотография блюда. Такие ссылки сидер заменяет.
     */
    private const PLACEHOLDER_HOSTS = [
        'picsum.photos',
        'loremflickr.com',
        'placekitten.com',
        'dummyimage.com',
        'via.placeholder.com',
    ];

    /** Длина колонки image_url — более длинные ссылки пропускаем. */
    private const MAX_URL_LENGTH = 255;

    public function run(): void
    {
        $photos = $this->loadJson(__DIR__ . '/data/dish_photos.json');
        $dishes = $photos['dishes'] ?? [];
        $pools  = $photos['pools'] ?? [];
        $stepImages = $this->loadJson(__DIR__ . '/data/foodcom_images.json');

        if (!$dishes) {
            $this->command?->warn('ImageUrlSeeder: не найден data/dish_photos.json — фото блюд не подставлены.');
        }

        // 1. Категории: тематическое фото, иначе — фото из пула категории.
        $poolSlugs = array_keys($pools);
        Category::query()->each(function (Category $category) use ($pools, $poolSlugs) {
            $category->image_url = self::CATEGORY_IMAGES[$category->slug]
                ?? ($pools[$category->slug][0] ?? null);
            $category->save();
        });

        // 2. Рецепты: фотография именно этого блюда.
        $categorySlugs = Category::pluck('slug', 'id')->toArray();
        Recipe::query()->chunkById(100, function ($recipes) use ($dishes, $pools, $categorySlugs) {
            $updates = [];
            foreach ($recipes as $recipe) {
                if (self::hasRealPhoto($recipe->main_image_url)) {
                    continue; // настоящее фото на месте — не затираем
                }
                $url = $this->photoFor($recipe, $dishes, $pools, $categorySlugs);
                if ($url !== null) {
                    $updates[$recipe->id] = $url;
                }
            }
            foreach ($updates as $id => $url) {
                DB::table('recipes')->where('id', $id)->update(['main_image_url' => $url]);
            }
        });

        // 3. Шаги: настоящие фото процесса там, где они есть.
        $this->applyStepImages($stepImages);

        $this->command?->info('ImageUrlSeeder: подставлены настоящие фотографии блюд.');
    }

    /**
     * Фотография для рецепта: сперва по названию блюда, иначе — из пула
     * категории (выбор детерминированный, чтобы повторный запуск сидера
     * не переставлял картинки).
     */
    private function photoFor(Recipe $recipe, array $dishes, array $pools, array $categorySlugs): ?string
    {
        $base = $this->matchBaseDish($recipe->title, $dishes);
        if ($base !== null) {
            $photo = $dishes[$base]['photo'] ?? null;
            if ($photo) {
                return $photo;
            }
        }

        $slug = $categorySlugs[$recipe->category_id] ?? null;
        $pool = $slug !== null ? ($pools[$slug] ?? []) : [];
        if (!$pool) {
            return null;
        }

        return $pool[crc32($recipe->title) % count($pool)];
    }

    /**
     * Ищет базовое блюдо по названию рецепта.
     *
     * В базе названия встречаются в разных формах — «Блины домашний»,
     * «Домашние блины», «Яичница праздничная» — поэтому ищем вхождение
     * названия блюда целиком, а при нескольких совпадениях берём самое
     * длинное («Плов с курицей», а не «Плов»).
     */
    private function matchBaseDish(string $title, array $dishes): ?string
    {
        $best = null;

        foreach (array_keys($dishes) as $name) {
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($name, '/') . '(?![\p{L}\p{N}])/iu';
            if (preg_match($pattern, $title) !== 1) {
                continue;
            }
            if ($best === null || mb_strlen($name) > mb_strlen($best)) {
                $best = $name;
            }
        }

        return $best;
    }

    /**
     * Фото шагов: у рецептов из Food.com в наборе несколько фотографий —
     * первая идёт на обложку, остальные показывают процесс.
     */
    private function applyStepImages(array $stepImages): void
    {
        if (!$stepImages) {
            $this->clearPlaceholderStepImages();
            return;
        }

        $recipes = Recipe::query()
            ->get(['id', 'source', 'source_id', 'main_image_url'])
            ->keyBy('id');

        RecipeStep::query()->chunkById(500, function ($steps) use ($stepImages, $recipes) {
            $grouped = [];

            foreach ($steps as $step) {
                $recipe = $recipes[$step->recipe_id] ?? null;
                $index = max(0, (int) $step->step_number - 1);

                if (self::hasRealPhoto($step->image_url)) {
                    continue; // фото шага уже настоящее
                }

                $url = null;
                if ($recipe && $recipe->source === 'foodcom') {
                    $url = $this->stepPhoto($stepImages, $recipe, $index);
                }

                if (($step->image_url ?? null) !== $url) {
                    $grouped[$step->id] = $url;
                }
            }

            foreach ($grouped as $id => $url) {
                DB::table('recipe_steps')->where('id', $id)->update(['image_url' => $url]);
            }
        });
    }

    /**
     * Фотография шага по индексу: из списка фото рецепта исключается
     * обложка (она же main_image_url), остальные идут по шагам.
     */
    private function stepPhoto(array $stepImages, Recipe $recipe, int $index): ?string
    {
        $list = $stepImages[(string) $recipe->source_id] ?? null;
        if (!$list) {
            return null;
        }

        $main = trim((string) $recipe->main_image_url);
        $extra = [];
        foreach ($list as $url) {
            $url = trim((string) $url);
            if ($url === '' || strlen($url) > self::MAX_URL_LENGTH) {
                // Food.com иногда вкладывает в имя файла весь текст рецепта —
                // такая ссылка не влезает в колонку и просто пропускается.
                continue;
            }
            if ($main !== '' && $url === $main) {
                continue; // это обложка — она уже в карточке
            }
            $extra[] = $url;
        }

        return $extra[$index] ?? null;
    }

    /**
     * Убирает заглушки из шагов, которым не досталось настоящего фото:
     * лучше шаг без картинки, чем фотография машины.
     */
    private function clearPlaceholderStepImages(): void
    {
        RecipeStep::query()->chunkById(500, function ($steps) {
            $grouped = [];
            foreach ($steps as $step) {
                if (!self::isPlaceholder($step->image_url)) {
                    continue;
                }
                $grouped[$step->id] = null;
            }
            foreach ($grouped as $id => $url) {
                DB::table('recipe_steps')->where('id', $id)->update(['image_url' => $url]);
            }
        });
    }

    /**
     * Читает JSON-файл с данными. Отсутствующий файл — не ошибка:
     * сидер просто ничего не подставит.
     */
    private function loadJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Настоящее фото — это ссылка на конкретную картинку блюда.
     * Пустая строка, локальные пути вида /storage/... (которых на диске
     * нет) и ссылки сервисов-заглушек настоящими не считаем.
     */
    private static function hasRealPhoto(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return false;
        }

        return !self::isPlaceholder($url);
    }

    /** Ссылка ведёт на сервис случайных картинок, а не на фото блюда. */
    private static function isPlaceholder(?string $url): bool
    {
        $url = strtolower(trim((string) $url));
        if ($url === '') {
            return false;
        }

        foreach (self::PLACEHOLDER_HOSTS as $host) {
            if (str_contains($url, $host)) {
                return true;
            }
        }

        return false;
    }
}
