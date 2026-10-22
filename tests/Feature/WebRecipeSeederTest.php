<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use Database\Seeders\WebRecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Сидер рецептов из интернета должен быть идемпотентным: повторный запуск
 * обновляет уже загруженный рецепт по ключу source + source_id, а не создаёт
 * второй с суффиксом "-2" в slug.
 */
class WebRecipeSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Пишем во временный файл и подменяем путь у сидера: настоящий
        // data/web_recipes.json (его собирает fetch_web_recipes.mjs) тест
        // не должен ни затирать, ни удалять.
        $this->jsonPath = tempnam(sys_get_temp_dir(), 'web_recipes_test_');
        WebRecipeSeeder::$dataPathOverride = $this->jsonPath;

        file_put_contents($this->jsonPath, json_encode([$this->sampleRecipe()], JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        WebRecipeSeeder::$dataPathOverride = null;
        if (is_file($this->jsonPath)) {
            @unlink($this->jsonPath);
        }
        parent::tearDown();
    }

    private function sampleRecipe(): array
    {
        return [
            'source' => 'mealdb',
            'sourceId' => '99999',
            'title' => 'Тестовое блюдо',
            'titleRu' => 'Тестовое блюдо из интернета',
            'description' => 'Описание блюда.',
            'category' => 'Chicken',
            'tagsRu' => [],
            'image' => 'https://example.com/photo.jpg',
            'video' => 'https://www.youtube.com/embed/abc123XYZ',
            'ingredients' => [
                ['name' => 'chicken breast', 'measure' => '500g'],
                ['name' => 'garlic', 'measure' => '3 cloves'],
            ],
            'ingredientNamesRu' => ['Куриная грудка', 'Чеснок'],
            'steps' => ['Cook the chicken thoroughly.'],
            'stepsRu' => ['Хорошо прожарьте куриную грудку.'],
            'cookTimeMinutes' => 45,
            'servings' => 4,
            'calories' => 320,
        ];
    }

    public function test_creates_recipe_with_photo_and_video(): void
    {
        Category::firstOrCreate(['slug' => 'vtorye-blyuda'], ['name' => 'Вторые блюда']);

        $this->seed(WebRecipeSeeder::class);

        $recipe = Recipe::query()->where('source', 'mealdb')->where('source_id', '99999')->first();

        $this->assertNotNull($recipe, 'Рецепт из интернета должен сохраниться');
        $this->assertSame('https://example.com/photo.jpg', $recipe->main_image_url);
        $this->assertSame('https://www.youtube.com/embed/abc123XYZ', $recipe->video_url);
        // Формат строки — «Название — количество», как ждёт IngredientParser.
        $this->assertSame('Куриная грудка — 500g', explode("\n", $recipe->ingredients)[0]);
        $this->assertNotEmpty($recipe->steps()->get(), 'У рецепта должны быть шаги');
    }

    public function test_rerun_does_not_create_duplicates(): void
    {
        Category::firstOrCreate(['slug' => 'vtorye-blyuda'], ['name' => 'Вторые блюда']);

        $this->seed(WebRecipeSeeder::class);
        $firstSlug = Recipe::query()->where('source', 'mealdb')->first()->slug;

        // Второй запуск сидера — с теми же данными.
        $this->seed(WebRecipeSeeder::class);

        $this->assertSame(1, Recipe::query()->where('source', 'mealdb')->count());
        $this->assertSame($firstSlug, Recipe::query()->where('source', 'mealdb')->first()->slug);
    }

    public function test_rerun_updates_changed_content_in_place(): void
    {
        Category::firstOrCreate(['slug' => 'vtorye-blyuda'], ['name' => 'Вторые блюда']);

        $this->seed(WebRecipeSeeder::class);
        $recipeId = Recipe::query()->where('source', 'mealdb')->first()->id;

        // Меняем фото в исходных данных.
        $data = $this->sampleRecipe();
        $data['image'] = 'https://example.com/new-photo.jpg';
        $data['titleRu'] = 'Обновлённое название';
        file_put_contents($this->jsonPath, json_encode([$data], JSON_UNESCAPED_UNICODE));

        $this->seed(WebRecipeSeeder::class);

        $recipe = Recipe::query()->where('source', 'mealdb')->first();

        $this->assertSame($recipeId, $recipe->id, 'Рецепт обновляется, а не пересоздаётся');
        $this->assertSame('https://example.com/new-photo.jpg', $recipe->main_image_url);
        $this->assertSame('Обновлённое название', $recipe->title);
    }

    public function test_does_not_duplicate_steps_on_rerun(): void
    {
        Category::firstOrCreate(['slug' => 'vtorye-blyuda'], ['name' => 'Вторые блюда']);

        $this->seed(WebRecipeSeeder::class);
        $recipeId = Recipe::query()->where('source', 'mealdb')->first()->id;
        $stepsAfterFirst = DB::table('recipe_steps')->where('recipe_id', $recipeId)->count();

        $this->seed(WebRecipeSeeder::class);
        $stepsAfterSecond = DB::table('recipe_steps')->where('recipe_id', $recipeId)->count();

        $this->assertSame($stepsAfterFirst, $stepsAfterSecond, 'Шаги не должны дублироваться');
    }

    public function test_skips_recipe_from_excluded_category(): void
    {
        Category::firstOrCreate(['slug' => 'vtorye-blyuda'], ['name' => 'Вторые блюда']);

        // «chocolate cake» — в списке исключённых: такое блюдо в русскую
        // кухню не тащим, и category_id у него быть не может (NOT NULL).
        $data = $this->sampleRecipe();
        $data['category'] = 'chocolate cake';
        file_put_contents($this->jsonPath, json_encode([$data], JSON_UNESCAPED_UNICODE));

        $this->seed(WebRecipeSeeder::class);

        $this->assertSame(0, Recipe::query()->where('source', 'mealdb')->count());
    }

    public function test_all_imported_recipes_have_category(): void
    {
        Category::firstOrCreate(['slug' => 'vtorye-blyuda'], ['name' => 'Вторые блюда']);

        $this->seed(WebRecipeSeeder::class);

        $recipe = Recipe::query()->where('source', 'mealdb')->first();

        $this->assertNotNull($recipe);
        $this->assertNotNull($recipe->category_id, 'У рецепта обязана быть категория');
    }
}
