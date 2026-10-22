<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\Slug;
use Database\Seeders\IngredientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Базовые продукты: соль, перец, масло, сахар, мука, вода.
 *
 * Их не нужно отмечать в холодильнике — они есть в любой кухне. Поэтому
 * при подборе рецептов они не считаются недостающими, если пользователь
 * явно не попросил их учитывать.
 */
class PantryStaplesTest extends TestCase
{
    use RefreshDatabase;

    private function makeIngredient(string $name, bool $staple = false): Ingredient
    {
        return Ingredient::create([
            'name' => $name,
            'slug' => Slug::make($name),
            'is_pantry_staple' => $staple,
        ]);
    }

    private function makeRecipe(string $title, array $ingredientNames): Recipe
    {
        $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Тест']);
        $recipe = Recipe::create([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => Slug::make($title),
            'ingredients' => implode("\n", $ingredientNames),
            'instructions' => 'Шаг 1',
            'cook_time_minutes' => 10,
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

    public function test_seeder_marks_base_products(): void
    {
        foreach (['Соль', 'Перец чёрный молотый', 'Масло сливочное', 'Сахар', 'Мука', 'Вода'] as $name) {
            $this->makeIngredient($name);
        }

        $this->seed(IngredientSeeder::class);

        foreach (['Соль', 'Перец чёрный молотый', 'Масло сливочное', 'Сахар', 'Мука', 'Вода'] as $name) {
            $this->assertTrue(
                Ingredient::where('name', $name)->first()->is_pantry_staple,
                "«{$name}» должен быть помечен как базовый продукт"
            );
        }

        $this->assertGreaterThanOrEqual(
            5,
            Ingredient::where('is_pantry_staple', true)->count(),
            'Сидер должен пометить не меньше пяти базовых продуктов'
        );
    }

    /**
     * Овощи подходят под «перец» по слову, но базовым продуктом не
     * являются: перец болгарский нужно купить. «Маслины» — тоже не масло,
     * а оливки, и перечисление вроде «Соль, перец, масло» — это сразу
     * несколько продуктов, а не базовый.
     */
    public function test_seeder_does_not_mark_vegetables_and_special_ingredients(): void
    {
        $notStaples = [
            'Перец болгарский',
            'Сахарный горошек',
            'Масло молотого ореха',
            'Яйца',
            'Маслины',
            'Соль, перец, масло',
        ];

        foreach ($notStaples as $name) {
            $this->makeIngredient($name);
        }

        $this->seed(IngredientSeeder::class);

        foreach ($notStaples as $name) {
            $this->assertFalse(
                Ingredient::where('name', $name)->first()->is_pantry_staple,
                "«{$name}» не должен считаться базовым продуктом"
            );
        }
    }

    public function test_matcher_ignores_base_products_by_default(): void
    {
        $this->makeRecipe('Блины', ['Мука', 'Соль', 'Яйца']);
        Ingredient::where('name', 'Мука')->update(['is_pantry_staple' => true]);
        Ingredient::where('name', 'Соль')->update(['is_pantry_staple' => true]);

        $res = $this->postJson('/api/fridge/match', ['ingredients' => ['яйца']]);

        $res->assertOk()->assertJsonPath('count', 1);
        $res->assertJsonPath('recipes.0.match_percentage', 100);
        $res->assertJsonPath('recipes.0.can_cook', true);
        $res->assertJsonPath('recipes.0.missing_ingredients', []);

        $ignored = $res->json('recipes.0.ignored_pantry_staples');
        $this->assertContains('Мука', $ignored);
        $this->assertContains('Соль', $ignored);
    }

    public function test_matcher_counts_base_products_when_asked(): void
    {
        $this->makeRecipe('Блины', ['Мука', 'Соль', 'Яйца']);
        Ingredient::where('name', 'Мука')->update(['is_pantry_staple' => true]);
        Ingredient::where('name', 'Соль')->update(['is_pantry_staple' => true]);

        $res = $this->postJson('/api/fridge/match', [
            'ingredients' => ['яйца'],
            'include_pantry_staples' => true,
            'min_match' => 0,
        ]);

        $res->assertOk()->assertJsonPath('include_pantry_staples', true);
        $res->assertJsonPath('recipes.0.match_percentage', 33);
        $res->assertJsonPath('recipes.0.can_cook', false);

        $missing = array_column($res->json('recipes.0.missing_ingredients'), 'name');
        $this->assertContains('Мука', $missing);
        $this->assertContains('Соль', $missing);
        $this->assertSame([], $res->json('recipes.0.ignored_pantry_staples'));
    }

    public function test_ingredients_endpoint_marks_base_products(): void
    {
        $this->makeIngredient('Соль', true);
        $this->makeIngredient('Яйца', false);

        $res = $this->getJson('/api/ingredients');

        $res->assertOk();
        $res->assertJsonFragment(['name' => 'Соль', 'is_pantry_staple' => true]);
        $res->assertJsonFragment(['name' => 'Яйца', 'is_pantry_staple' => false]);
    }
}
