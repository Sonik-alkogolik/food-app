<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\Slug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Процент совпадения в ответе подбора по холодильнику.
 *
 * По умолчанию показываем рецепты, совпавшие не меньше чем на 70%:
 * это «почти готово» — до кухни не надо идти за одним недостающим
 * продуктом. Порог можно ослабить параметром min_match.
 */
class FridgeMatchPercentageTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipe(string $title, array $ingredientNames, int $cookTime = 10): Recipe
    {
        $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Тест']);
        $recipe = Recipe::create([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => Slug::make($title),
            'ingredients' => implode("\n", $ingredientNames),
            'instructions' => 'Шаг 1',
            'cook_time_minutes' => $cookTime,
            'is_active' => true,
        ]);

        foreach ($ingredientNames as $name) {
            $ingredient = Ingredient::firstOrCreate(
                ['slug' => Slug::make($name)],
                ['name' => $name]
            );
            $recipe->ingredientsRelation()->syncWithoutDetaching([$ingredient->id]);
        }

        return $recipe;
    }

    /**
     * Три рецепта с разным процентом совпадения при холодильнике
     * «яйца, молоко»: 100%, 50% и 33%.
     */
    private function seedRecipes(): void
    {
        $this->makeRecipe('Готово', ['Яйца', 'Молоко']);
        $this->makeRecipe('Почти готово', ['Яйца', 'Молоко', 'Мука', 'Сахар']);
        $this->makeRecipe('Совсем не то', ['Яйца', 'Молоко', 'Мука', 'Сахар', 'Соль', 'Перец']);
    }

    public function test_by_default_only_recipes_above_seventy_percent_are_returned(): void
    {
        $this->seedRecipes();

        $res = $this->postJson('/api/fridge/match', ['ingredients' => ['яйца', 'молоко']]);

        $res->assertOk();
        $res->assertJsonPath('min_match', 70);
        $res->assertJsonPath('count', 1);

        $titles = array_column($res->json('recipes'), 'title');
        $this->assertSame(['Готово'], $titles);
    }

    public function test_threshold_can_be_lowered(): void
    {
        $this->seedRecipes();

        $res = $this->postJson('/api/fridge/match', [
            'ingredients' => ['яйца', 'молоко'],
            'min_match' => 50,
        ]);

        $res->assertOk();
        $res->assertJsonPath('min_match', 50);
        $res->assertJsonPath('count', 2);
    }

    public function test_zero_threshold_returns_everything(): void
    {
        $this->seedRecipes();

        $res = $this->postJson('/api/fridge/match', [
            'ingredients' => ['яйца', 'молоко'],
            'min_match' => 0,
        ]);

        $res->assertOk();
        $res->assertJsonPath('count', 3);
    }

    public function test_results_are_sorted_by_match_percentage_desc(): void
    {
        $this->seedRecipes();

        $res = $this->postJson('/api/fridge/match', [
            'ingredients' => ['яйца', 'молоко'],
            'min_match' => 0,
        ]);

        $percentages = array_column($res->json('recipes'), 'match_percentage');
        $this->assertSame([100, 50, 33], $percentages);
    }

    public function test_response_carries_percentage_missing_list_and_counts(): void
    {
        $this->makeRecipe('Почти готово', ['Яйца', 'Молоко', 'Мука', 'Сахар']);

        $res = $this->postJson('/api/fridge/match', [
            'ingredients' => ['яйца', 'молоко'],
            'min_match' => 50,
        ]);

        $res->assertOk();
        $recipe = $res->json('recipes.0');

        $this->assertIsInt($recipe['match_percentage']);
        $this->assertSame(50, $recipe['match_percentage']);
        $this->assertSame(2, $recipe['matched_count']);
        $this->assertSame(4, $recipe['total_count']);

        $this->assertIsArray($recipe['missing_ingredients']);
        $this->assertSame(['Мука', 'Сахар'], array_column($recipe['missing_ingredients'], 'name'));
        $this->assertSame(['Яйца', 'Молоко'], $recipe['matched_ingredients']);
    }

    public function test_min_match_is_validated(): void
    {
        $this->postJson('/api/fridge/match', [
            'ingredients' => ['яйца'],
            'min_match' => 101,
        ])->assertStatus(422);

        $this->postJson('/api/fridge/match', [
            'ingredients' => ['яйца'],
            'min_match' => -1,
        ])->assertStatus(422);

        $this->postJson('/api/fridge/match', [
            'ingredients' => ['яйца'],
            'min_match' => 'много',
        ])->assertStatus(422);
    }
}
