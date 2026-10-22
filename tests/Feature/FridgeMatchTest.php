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
            $recipe->ingredientsRelation()->syncWithoutDetaching([$ing->id]);
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
        $res->assertJsonPath('recipes.0.match_percentage', 100);
        $res->assertJsonPath('recipes.0.missing_ingredients', []);
    }

    public function test_partial_match_lists_missing_and_sorts_by_percent(): void
    {
        $this->makeRecipe('Плов', ['Рис', 'Морковь', 'Лук', 'Мясо']);
        $this->makeRecipe('Салат', ['Рис', 'Огурец']);
        $res = $this->postJson('/api/fridge/match', [
            'ingredients' => ['рис', 'морковь'],
            'min_match' => 0,
        ]);
        $res->assertOk()->assertJsonPath('recipes.0.title', 'Плов'); // 50% > 33%? Плов 2/4=50, Салат 1/2=50 — по времени одинаково
        $first = $res->json('recipes.0');
        $this->assertSame(50, $first['match_percentage']);
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

    /**
     * В базе один и тот же продукт записан по-разному: «Лук», «Лук репчатый»,
     * «Красный лук». Пользователь набирает «лук» — и должен находить все эти
     * варианты, а не только точное совпадение.
     */
    public function test_finds_variants_of_the_same_product(): void
    {
        $this->makeRecipe('Пирог с луком', ['Лук репчатый', 'Мука']);
        $this->makeRecipe('Салат с луком', ['Красный лук', 'Помидоры']);

        $res = $this->postJson('/api/fridge/match', [
            'ingredients' => ['лук'],
            'min_match' => 0,
        ]);

        $res->assertOk()->assertJsonPath('count', 2);

        $titles = array_column($res->json('recipes'), 'title');
        $this->assertContains('Пирог с луком', $titles);
        $this->assertContains('Салат с луком', $titles);

        // В обоих рецептах лук засчитан как найденный, а не как недостающий.
        foreach ($res->json('recipes') as $recipe) {
            $matched = $recipe['matched_ingredients'];
            $missing = array_column($recipe['missing_ingredients'], 'name');

            $hasOnion = fn (string $name) => mb_stripos($name, 'лук') !== false;

            $this->assertNotEmpty(
                array_filter($matched, $hasOnion),
                'Лук должен засчитываться как найденный'
            );
            $this->assertEmpty(
                array_filter($missing, $hasOnion),
                'Лук не должен попадать в недостающие'
            );
        }
    }

    /**
     * Похожие, но разные продукты не должны считаться одним и тем же:
     * «масло» и «маслина» — разные вещи, а уточняющие слова
     * («Кокосовое молоко») меняют продукт.
     */
    public function test_different_products_are_not_confused(): void
    {
        $this->makeRecipe('Маслянина', ['Маслина', 'Соль']);
        $this->makeRecipe('Кокосовое молоко', ['Кокосовое молоко', 'Сахар']);

        $res = $this->postJson('/api/fridge/match', ['ingredients' => ['масло', 'молоко']]);

        $res->assertOk();
        $this->assertSame(0, $res->json('count'), 'Разные продукты не должны совпадать');
    }
}
