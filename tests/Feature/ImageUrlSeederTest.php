<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeStep;
use Database\Seeders\ImageUrlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ImageUrlSeeder подставляет настоящие фотографии блюд.
 *
 * Регрессия: сидер затирал настоящие фотографии рецептов из интернета
 * картинками picsum.photos, из-за чего в каталоге у всех блюд были
 * случайные картинки (лес, горы, море вместо еды), а в карточке
 * «Яичница праздничная» — фотография машины.
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
            'Настоящее фото рецепта не должно затираться'
        );
    }

    public function test_sets_dish_photo_by_title(): void
    {
        $cat = $this->makeCategory();

        $recipe = Recipe::create([
            'category_id' => $cat->id,
            'title' => 'Яичница праздничная',
            'slug' => 'yaichnitsa-prazdnichnaya',
            'ingredients' => 'Яйца — 4 шт',
            'instructions' => '1. Пожарить',
            'cook_time_minutes' => 15,
            'main_image_url' => null,
            'is_active' => true,
        ]);

        $this->seed(ImageUrlSeeder::class);

        $url = (string) $recipe->fresh()->main_image_url;

        $this->assertStringStartsWith('https://www.themealdb.com/', $url, 'У блюда должно быть своё фото');
        $this->assertStringNotContainsString('picsum.photos', $url);
    }

    public function test_replaces_placeholder_photo_with_real_one(): void
    {
        $cat = $this->makeCategory();

        // picsum — это как раз тот сервис, из которого приходили машины.
        $recipe = Recipe::create([
            'category_id' => $cat->id,
            'title' => 'Без фото',
            'slug' => 'bez-foto',
            'ingredients' => 'Соль',
            'instructions' => '1. Готово',
            'cook_time_minutes' => 10,
            'main_image_url' => 'https://picsum.photos/seed/recipe-1/800/600',
            'is_active' => true,
        ]);

        $this->seed(ImageUrlSeeder::class);

        $url = (string) $recipe->fresh()->main_image_url;

        $this->assertStringNotContainsString('picsum.photos', $url);
        $this->assertStringStartsWith('https://www.themealdb.com/', $url, 'Без своего блюда берём фото из категории');
    }

    public function test_replaces_local_storage_path_that_does_not_exist(): void
    {
        $cat = $this->makeCategory();

        // Путь /storage/... в БД есть, а файла на диске — нет: картинка
        // не отобразится, значит нужна рабочая ссылка.
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

        $url = (string) $recipe->fresh()->main_image_url;

        $this->assertStringStartsWith('https://', $url);
        $this->assertStringNotContainsString('/storage/', $url);
    }

    public function test_keeps_real_step_photo_and_leaves_step_without_photo_empty(): void
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
        // Шаг без настоящего фото остаётся без картинки: машина вместо
        // фотографии процесса — хуже, чем просто шаг текстом.
        $this->assertNull($empty->fresh()->image_url);
    }

    public function test_replaces_placeholder_step_photo(): void
    {
        $cat = $this->makeCategory();

        $recipe = Recipe::create([
            'category_id' => $cat->id,
            'title' => 'Шаги с заглушкой',
            'slug' => 'shagi-zaglushka',
            'ingredients' => 'Соль',
            'instructions' => '1. Готово',
            'cook_time_minutes' => 10,
            'is_active' => true,
        ]);

        $step = RecipeStep::create([
            'recipe_id' => $recipe->id,
            'step_number' => 1,
            'title' => 'Подготовка',
            'description' => 'Нарезать',
            'image_url' => 'https://picsum.photos/seed/step-1-1/640/480',
        ]);

        $this->seed(ImageUrlSeeder::class);

        $this->assertNull($step->fresh()->image_url);
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
