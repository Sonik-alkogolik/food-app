<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Services\IngredientParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FridgeMatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipe(string $title, array $ingredientNames): Recipe
    {
        $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Тест']);
        $recipe = Recipe::create([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'ingredients' => implode("\n", $ingredientNames),
            'instructions' => 'Шаг 1',
            'cook_time_minutes' => 10,
            'is_active' => true,
        ]);
        foreach ($ingredientNames as $name) {
            $ing = Ingredient::firstOrCreate(
                ['slug' => \App\Support\Slug::make($name)],
                ['name' => $name]
            );
            $recipe->ingredients()->syncWithoutDetaching([$ing->id]);
        }
        return $recipe;
    }

    public function test_ingredients_endpoint_returns_list(): void
    {
        $this->makeRecipe('Омлет', ['Яйца', 'Молоко']);
        $res = $this->getJson('/api/ingredients');
        $res->assertOk()->assertJsonCount(2);
        $res->assertJsonFragment(['name' => 'Яйца']);
    }

    public function test_full_match_marks_can_cook(): void
    {
        $this->makeRecipe('Омлет', ['Яйца', 'Молоко']);
        $res = $this->postJson('/api/fridge/match', ['ingredients' => ['яйца', 'МОЛОКО']]);
        $res->assertOk()->assertJsonPath('recipes.0.can_cook', true);
        $res->assertJsonPath('recipes.0.match_percent', 100);
        $res->assertJsonPath('recipes.0.missing_ingredients', []);
    }

    public function test_partial_match_lists_missing_and_sorts_by_percent(): void
    {
        $this->makeRecipe('Плов', ['Рис', 'Морковь', 'Лук', 'Мясо']);
        $this->makeRecipe('Салат', ['Рис', 'Огурец']);
        $res = $this->postJson('/api/fridge/match', ['ingredients' => ['рис', 'морковь']]);
        $res->assertOk()->assertJsonPath('recipes.0.title', 'Плов'); // 50% > 33%? Плов 2/4=50, Салат 1/2=50 — по времени одинаково
        $first = $res->json('recipes.0');
        $this->assertSame(50, $first['match_percent']);
        $this->assertFalse($first['can_cook']);
        $names = array_column($first['missing_ingredients'], 'name');
        $this->assertContains('Лук', $names);
    }

    public function test_no_overlap_returns_empty(): void
    {
        $this->makeRecipe('Омлет', ['Яйца']);
        $res = $this->postJson('/api/fridge/match', ['ingredients' => ['ананас']]);
        $res->assertOk()->assertJsonPath('count', 0)->assertJsonPath('recipes', []);
    }

    public function test_validation_requires_array(): void
    {
        $this->postJson('/api/fridge/match', [])->assertStatus(422);
        $this->postJson('/api/fridge/match', ['ingredients' => 'not-array'])->assertStatus(422);
    }

    public function test_parser_splits_lines_with_quantities(): void
    {
        $parsed = (new IngredientParser())->parse("Мука - 200г\nЯйца – 2 шт\nСоль");
        $this->assertCount(3, $parsed);
        $this->assertSame('Мука', $parsed[0]['name']);
        $this->assertSame('200г', $parsed[0]['quantity']);
        $this->assertSame('yaytsa', $parsed[1]['slug']);
        $this->assertNull($parsed[2]['quantity']);
    }

    public function test_inactive_recipes_excluded(): void
    {
        $r = $this->makeRecipe('Скрытый', ['Яйца']);
        $r->update(['is_active' => false]);
        $res = $this->postJson('/api/fridge/match', ['ingredients' => ['яйца']]);
        $res->assertOk()->assertJsonPath('count', 0);
    }
}
