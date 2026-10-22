<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeStep;
use Database\Seeders\ImageUrlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ImageUrlSeeder ставит placeholder-фото ТОЛЬКО там, где реального фото нет.
 *
 * Регрессия: сидер затирал настоящие фотографии рецептов из интернета
 * картинками picsum.photos, из-за чего в каталоге у всех блюд были
 * случайные картинки (лес, горы, море вместо еды).
 */
class ImageUrlSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(): Category
    {
        return Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);
    }

    public function test_keeps_real_recipe_photo(): void
    {
        $cat = $this->makeCategory();

        $recipe = Recipe::create([
            'category_id' => $cat->id,
            'title' => 'Борщ',
            'slug' => 'borch',
            'ingredients' => 'Свёкла — 1 шт',
            'instructions' => '1. Сварить',
            'cook_time_minutes' => 60,
            'main_image_url' => 'https://www.themealdb.com/images/media/meals/wvpsxx1468256321.jpg',
            'is_active' => true,
        ]);

        $this->seed(ImageUrlSeeder::class);

        $this->assertSame(
            'https://www.themealdb.com/images/media/meals/wvpsxx1468256321.jpg',
            $recipe->fresh()->main_image_url,
            'Настоящее фото рецепта не должно затираться placeholder-ом'
        );
    }

    public function test_fills_placeholder_for_recipe_without_photo(): void
    {
        $cat = $this->makeCategory();

        $recipe = Recipe::create([
            'category_id' => $cat->id,
            'title' => 'Без фото',
            'slug' => 'bez-foto',
            'ingredients' => 'Соль',
            'instructions' => '1. Готово',
            'cook_time_minutes' => 10,
            'main_image_url' => null,
            'is_active' => true,
        ]);

        $this->seed(ImageUrlSeeder::class);

        $this->assertStringContainsString('picsum.photos', (string) $recipe->fresh()->main_image_url);
    }

    public function test_replaces_local_storage_path_that_does_not_exist(): void
    {
        $cat = $this->makeCategory();

        // Путь /storage/... в БД есть, а файла на диске — нет: картинка
        // не отобразится, значит нужен рабочий placeholder.
        $recipe = Recipe::create([
            'category_id' => $cat->id,
            'title' => 'Битый путь',
            'slug' => 'bityy-put',
            'ingredients' => 'Соль',
            'instructions' => '1. Готово',
            'cook_time_minutes' => 10,
            'main_image_url' => '/storage/recipes/steps/recipe-1/step-1.jpg',
            'is_active' => true,
        ]);

        $this->seed(ImageUrlSeeder::class);

        $this->assertStringContainsString('picsum.photos', (string) $recipe->fresh()->main_image_url);
    }

    public function test_keeps_real_step_photo_and_fills_missing_one(): void
    {
        $cat = $this->makeCategory();

        $recipe = Recipe::create([
            'category_id' => $cat->id,
            'title' => 'Шаги',
            'slug' => 'shagi',
            'ingredients' => 'Соль',
            'instructions' => '1. Готово',
            'cook_time_minutes' => 10,
            'is_active' => true,
        ]);

        $real = RecipeStep::create([
            'recipe_id' => $recipe->id,
            'step_number' => 1,
            'title' => 'Подготовка',
            'description' => 'Нарезать',
            'image_url' => 'https://example.com/step.jpg',
        ]);
        $empty = RecipeStep::create([
            'recipe_id' => $recipe->id,
            'step_number' => 2,
            'title' => 'Варка',
            'description' => 'Сварить',
            'image_url' => null,
        ]);

        $this->seed(ImageUrlSeeder::class);

        $this->assertSame('https://example.com/step.jpg', $real->fresh()->image_url);
        $this->assertStringContainsString('picsum.photos', (string) $empty->fresh()->image_url);
    }

    public function test_sets_theme_photo_for_category(): void
    {
        $cat = $this->makeCategory();

        $this->seed(ImageUrlSeeder::class);

        $url = (string) $cat->fresh()->image_url;

        $this->assertStringStartsWith('https://images.unsplash.com/', $url);
        $this->assertStringNotContainsString('picsum.photos', $url);
    }
}
