<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ШАГ 38: сидер URL изображений.
 *
 * Заполняет placeholder-ссылками только те записи, у которых реального
 * фото нет. Рецепты, загруженные из интернета (WebRecipeSeeder), приходят
 * уже со своей настоящей фотографией — их этот сидер не трогает, иначе
 * все картинки снова станут одинаковыми случайными.
 *
 * Повторный запуск безопасно обновляет существующие записи.
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

    public function run(): void
    {
        // Категории: сначала тематическое фото, иначе placeholder
        Category::query()->each(function (Category $category) {
            $category->image_url = self::CATEGORY_IMAGES[$category->slug]
                ?? 'https://picsum.photos/seed/cat-' . $category->slug . '/640/400';
            $category->save();
        });

        // Рецепты: ставим placeholder ТОЛЬКО там, где реального фото нет.
        // У рецептов из интернета main_image_url уже заполнен настоящей
        // фотографией блюда — такие не трогаем.
        Recipe::query()->chunkById(100, function ($recipes) {
            $updates = [];
            foreach ($recipes as $recipe) {
                if (self::hasRealPhoto($recipe->main_image_url)) {
                    continue; // настоящее фото на месте — не затираем
                }
                $updates[$recipe->id] = 'https://picsum.photos/seed/recipe-' . $recipe->id . '/800/600';
            }
            foreach ($updates as $id => $url) {
                DB::table('recipes')->where('id', $id)->update(['main_image_url' => $url]);
            }
        });

        // Шаги: фото каждого шага — только там, где фото ещё нет.
        RecipeStep::query()->chunkById(500, function ($steps) {
            $grouped = [];
            foreach ($steps as $step) {
                if (self::hasRealPhoto($step->image_url)) {
                    continue;
                }
                $grouped[$step->id] = 'https://picsum.photos/seed/step-' . $step->recipe_id . '-' . $step->step_number . '/640/480';
            }
            foreach ($grouped as $id => $url) {
                DB::table('recipe_steps')->where('id', $id)->update(['image_url' => $url]);
            }
        });

        $this->command?->info('ImageUrlSeeder: проставлены placeholder-изображения там, где реальных фото нет.');
    }

    /**
     * Настоящее фото — это ссылка на существующий файл в интернете.
     * Пустая строка и локальные пути вида /storage/... (которых на диске
     * нет) настоящими не считаем: для них нужен placeholder.
     */
    private static function hasRealPhoto(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }
}
