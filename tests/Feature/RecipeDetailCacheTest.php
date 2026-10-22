<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Кеш детальной карточки рецепта.
 *
 * Регрессии:
 * — счётчик просмотров попадал в кеш и на 10 минут замирал на значении,
 *   вычисленном при первом обращении;
 * — после правки рецепта карточка до истечения TTL отдавала старые данные.
 */
class RecipeDetailCacheTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipe(string $title = 'Борщ'): Recipe
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);

        return Recipe::create([
            'category_id' => $cat->id,
            'title' => $title,
            'slug' => \App\Support\Slug::make($title),
            'ingredients' => "Свёкла — 200г\nВода — 1 л",
            'instructions' => '1. Сварить: ' . $title,
            'cook_time_minutes' => 60,
            'difficulty' => 'easy',
            'servings' => 4,
            'is_active' => true,
        ]);
    }

    public function test_views_count_increases_on_every_request(): void
    {
        $recipe = $this->makeRecipe();

        $first = $this->getJson('/api/recipes/' . $recipe->slug)->assertOk();
        $this->assertSame(1, $first->json('data.views_count'));

        // Второй запрос попадает в кеш карточки — но счётчик просмотров
        // обязан продолжать расти.
        $second = $this->getJson('/api/recipes/' . $recipe->slug)->assertOk();
        $this->assertSame(2, $second->json('data.views_count'));
    }

    public function test_detail_cache_is_dropped_when_recipe_changes(): void
    {
        $recipe = $this->makeRecipe('Борщ');

        $this->getJson('/api/recipes/' . $recipe->slug)
            ->assertOk()
            ->assertJsonPath('data.title', 'Борщ');

        $recipe->update(['title' => 'Борщ с говядиной', 'slug' => \App\Support\Slug::make('Борщ с говядиной')]);

        $this->getJson('/api/recipes/' . \App\Support\Slug::make('Борщ с говядиной'))
            ->assertOk()
            ->assertJsonPath('data.title', 'Борщ с говядиной');
    }
}
