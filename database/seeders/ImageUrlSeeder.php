<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ШАГ 38: сидер URL изображений.
 * Реальных фотографий 1000 рецептов пока нет, поэтому для каждого рецепта
 * и шага генерируется стабильный placeholder-URL (picsum.photos — кухонные
 * фото по seed), а категории получают тематические изображения Unsplash.
 * Повторный запуск безопасно обновляет существующие записи.
 */
class ImageUrlSeeder extends Seeder
{
    /** Тематические фото категорий (Unsplash CDN) */
    private const CATEGORY_IMAGES = [
        'zavtraki'      => 'https://images.unsplash.com/photo-1533089860892-a7c6f3a4912f?w=640&q=70&auto=format&fit=crop',
        'supy'          => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=640&q=70&auto=format&fit=crop',
        'vtorye-blyuda' => 'https://images.unsplash.com/photo-1544025162-d76694292afb?w=640&q=70&auto=format&fit=crop',
        'salaty'        => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=640&q=70&auto=format&fit=crop',
        'deserty'       => 'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=640&q=70&auto=format&fit=crop',
        'vypechka'      => 'https://images.unsplash.com/photo-1509440159596-0249083771ff?w=640&q=70&auto=format&fit=crop',
        'zakuski'       => 'https://images.unsplash.com/photo-1541529086556-db70313eb15d?w=640&q=70&auto=format&fit=crop',
        'napitki'       => 'https://images.unsplash.com/photo-1544145945-f90425340c7b?w=640&q=70&auto=format&fit=crop',
        'soussy'        => 'https://images.unsplash.com/photo-1472476443507-c7a6a6f35d7d?w=640&q=70&auto=format&fit=crop',
        'garniry'       => 'https://images.unsplash.com/photo-1518977957834-638253ca6cc9?w=640&q=70&auto=format&fit=crop',
    ];

    public function run(): void
    {
        // Категории: сначала тематическое фото, иначе placeholder
        Category::query()->each(function (Category $category) {
            $category->image_url = self::CATEGORY_IMAGES[$category->slug]
                ?? 'https://picsum.photos/seed/cat-' . $category->slug . '/640/400';
            $category->save();
        });

        // Рецепты: основное фото (placeholder с уникальным seed)
        Recipe::query()->chunkById(100, function ($recipes) {
            $updates = [];
            foreach ($recipes as $recipe) {
                $updates[$recipe->id] = 'https://picsum.photos/seed/recipe-' . $recipe->id . '/800/600';
            }
            foreach ($updates as $id => $url) {
                DB::table('recipes')->where('id', $id)->update(['main_image_url' => $url]);
            }
        });

        // Шаги: фото каждого шага
        RecipeStep::query()->chunkById(500, function ($steps) {
            $grouped = [];
            foreach ($steps as $step) {
                $grouped[$step->id] = 'https://picsum.photos/seed/step-' . $step->recipe_id . '-' . $step->step_number . '/640/480';
            }
            foreach ($grouped as $id => $url) {
                DB::table('recipe_steps')->where('id', $id)->update(['image_url' => $url]);
            }
        });

        $this->command?->info('ImageUrlSeeder: проставлены placeholder-изображения для категорий, рецептов и шагов.');
    }
}
