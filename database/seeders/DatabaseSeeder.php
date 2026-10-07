<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ШАГ 17: порядок наполнения базы данных:
 * 1) категории -> 2) 1000 рецептов -> 3) шаги -> 4) URL изображений.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,   // ШАГ 14
            BulkRecipeSeeder::class, // ШАГ 15 (~1000 рецептов)
            RecipeStepSeeder::class, // ШАГ 16 (3–8 шагов с фото на рецепт)
            ImageUrlSeeder::class,   // ШАГ 38 (placeholder-изображения)
            IngredientSeeder::class, // «Холодильник»: структурированные ингредиенты
        ]);
    }
}
