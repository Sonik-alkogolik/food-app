<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Фильтрация каталога: категория, сложность, время готовки, сортировка.
 *
 * Регрессия: комбинация фильтров из адресной строки применялась к выдаче,
 * но не отражалась в панели фильтров — и сбрасывалась при первом клике
 * «Применить фильтры».
 */
class RecipeCatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipe(string $title, int $categoryId, string $difficulty, int $time, int $views = 0): Recipe
    {
        return Recipe::create([
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => \App\Support\Slug::make($title),
            'ingredients' => "Мука — 200г\nЯйца — 2 шт",
            'instructions' => "1. Приготовить: {$title}",
            'cook_time_minutes' => $time,
            'difficulty' => $difficulty,
            'servings' => 4,
            'is_active' => true,
            'views_count' => $views,
        ]);
    }

    public function test_filters_by_category(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);
        $other = Category::firstOrCreate(['slug' => 'salaty'], ['name' => 'Салаты']);

        $this->makeRecipe('Борщ', $cat->id, 'easy', 60);
        $this->makeRecipe('Цезарь', $other->id, 'easy', 20);

        $res = $this->getJson('/api/recipes?category_id=' . $cat->id);
        $res->assertOk()->assertJsonPath('total', 1);
        $this->assertSame('Борщ', $res->json('data.0.title'));
    }

    public function test_filters_by_multiple_difficulties(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);

        $this->makeRecipe('Лёгкий', $cat->id, 'easy', 20);
        $this->makeRecipe('Средний', $cat->id, 'medium', 50);
        $this->makeRecipe('Сложный', $cat->id, 'hard', 120);

        $res = $this->getJson('/api/recipes?difficulty=easy,hard');
        $res->assertOk()->assertJsonPath('total', 2);
    }

    public function test_filters_by_max_time(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);

        $this->makeRecipe('Быстрый', $cat->id, 'easy', 20);
        $this->makeRecipe('Долгий', $cat->id, 'hard', 120);

        $res = $this->getJson('/api/recipes?max_time=30');
        $res->assertOk()->assertJsonPath('total', 1);
        $this->assertSame('Быстрый', $res->json('data.0.title'));
    }

    public function test_combines_all_filters(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);
        $other = Category::firstOrCreate(['slug' => 'salaty'], ['name' => 'Салаты']);

        $this->makeRecipe('Подходит', $cat->id, 'easy', 25);
        $this->makeRecipe('Не та сложность', $cat->id, 'hard', 25);
        $this->makeRecipe('Не та категория', $other->id, 'easy', 25);
        $this->makeRecipe('Слишком долго', $cat->id, 'easy', 90);

        $res = $this->getJson('/api/recipes?category_id=' . $cat->id . '&difficulty=easy&max_time=30');
        $res->assertOk()->assertJsonPath('total', 1);
        $this->assertSame('Подходит', $res->json('data.0.title'));
    }

    public function test_sorts_by_popularity(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);

        $this->makeRecipe('Популярный', $cat->id, 'easy', 20, views: 100);
        $this->makeRecipe('Непопулярный', $cat->id, 'easy', 20, views: 1);

        $res = $this->getJson('/api/recipes?sort=popular');
        $res->assertOk();
        $this->assertSame('Популярный', $res->json('data.0.title'));
    }

    public function test_sorts_by_time(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);

        $this->makeRecipe('Долгий', $cat->id, 'easy', 90);
        $this->makeRecipe('Быстрый', $cat->id, 'easy', 10);

        $res = $this->getJson('/api/recipes?sort=time');
        $res->assertOk();
        $this->assertSame('Быстрый', $res->json('data.0.title'));
    }

    public function test_inactive_recipes_are_excluded_from_catalog(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);
        $recipe = $this->makeRecipe('Скрытый', $cat->id, 'easy', 20);
        $recipe->update(['is_active' => false]);

        $this->getJson('/api/recipes')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_invalid_difficulty_is_ignored(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);
        $this->makeRecipe('Нормальный', $cat->id, 'easy', 20);

        // Неизвестная сложность не должна отсекать всё подряд:
        // фильтр с незнакомым значением просто не применяется.
        $this->getJson('/api/recipes?difficulty=bogus')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Нормальный');
    }

    public function test_invalid_difficulty_does_not_break_valid_ones(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);
        $this->makeRecipe('Лёгкий', $cat->id, 'easy', 20);
        $this->makeRecipe('Средний', $cat->id, 'medium', 50);

        // Знакомые значения работают, незнакомое — просто игнорируется.
        $this->getJson('/api/recipes?difficulty=easy,bogus')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Лёгкий');
    }

    public function test_filters_by_category_slug(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);
        $other = Category::firstOrCreate(['slug' => 'salaty'], ['name' => 'Салаты']);

        $this->makeRecipe('Борщ', $cat->id, 'easy', 60);
        $this->makeRecipe('Цезарь', $other->id, 'easy', 20);

        $res = $this->getJson('/api/recipes?category=supy');
        $res->assertOk()->assertJsonPath('total', 1);
        $this->assertSame('Борщ', $res->json('data.0.title'));
    }

    public function test_per_page_is_respected(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);

        foreach (range(1, 5) as $i) {
            $this->makeRecipe("Рецепт $i", $cat->id, 'easy', 20);
        }

        $res = $this->getJson('/api/recipes?per_page=2');
        $res->assertOk()
            ->assertJsonPath('total', 5)
            ->assertJsonPath('per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_per_page_is_capped(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);
        $this->makeRecipe('Рецепт', $cat->id, 'easy', 20);

        // Клиент не может выпросить сразу всю базу.
        $this->getJson('/api/recipes?per_page=100000')
            ->assertOk()
            ->assertJsonPath('per_page', 48);

        // Ноль и мусор приводят к значению по умолчанию.
        $this->getJson('/api/recipes?per_page=0')
            ->assertOk()
            ->assertJsonPath('per_page', 12);
    }
}
