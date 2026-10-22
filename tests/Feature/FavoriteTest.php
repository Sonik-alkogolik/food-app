<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Избранное: добавление, удаление, счётчик и личная видимость.
 */
class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipe(string $title = 'Борщ'): Recipe
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);

        return Recipe::create([
            'category_id' => $cat->id,
            'title' => $title,
            'slug' => \App\Support\Slug::make($title),
            'ingredients' => "Свёкла — 200г",
            'instructions' => '1. Сварить',
            'cook_time_minutes' => 60,
            'difficulty' => 'easy',
            'servings' => 4,
            'is_active' => true,
        ]);
    }

    private function user(): User
    {
        return User::create(['name' => 'Иван', 'email' => 'ivan@example.com', 'password' => 'secret123']);
    }

    public function test_guest_cannot_use_favorites(): void
    {
        $this->getJson('/api/favorites')->assertStatus(401);
        $this->postJson('/api/favorites', ['recipe_id' => 1])->assertStatus(401);
    }

    public function test_user_can_add_recipe_to_favorites(): void
    {
        $recipe = $this->makeRecipe();
        $user = $this->user();

        $res = $this->asUser($user)
            ->postJson('/api/favorites', ['recipe_id' => $recipe->id]);

        $res->assertStatus(201)
            ->assertJsonPath('data.is_favorite', true)
            ->assertJsonPath('data.favorites_count', 1);

        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'recipe_id' => $recipe->id]);
        $this->assertSame(1, $recipe->fresh()->favorites_count);
    }

    public function test_adding_twice_does_not_duplicate_or_double_count(): void
    {
        $recipe = $this->makeRecipe();
        $token = $this->user()->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/favorites', ['recipe_id' => $recipe->id])->assertStatus(201);
        $this->withToken($token)->postJson('/api/favorites', ['recipe_id' => $recipe->id])->assertStatus(201);

        $this->assertDatabaseCount('favorites', 1);
        $this->assertSame(1, $recipe->fresh()->favorites_count);
    }

    public function test_user_can_remove_recipe_from_favorites(): void
    {
        $recipe = $this->makeRecipe();
        $token = $this->user()->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/favorites', ['recipe_id' => $recipe->id])->assertStatus(201);

        $res = $this->withToken($token)->deleteJson('/api/favorites/' . $recipe->id);

        $res->assertStatus(200)
            ->assertJsonPath('data.is_favorite', false)
            ->assertJsonPath('data.favorites_count', 0);

        $this->assertDatabaseCount('favorites', 0);
        $this->assertSame(0, $recipe->fresh()->favorites_count);
    }

    public function test_favorites_list_contains_only_own_recipes(): void
    {
        $recipe = $this->makeRecipe();
        $mine = $this->user();

        $other = User::create(['name' => 'Пётр', 'email' => 'petr@example.com', 'password' => 'secret123']);
        $otherRecipe = $this->makeRecipe('Сырники');

        $this->asUser($mine)
            ->postJson('/api/favorites', ['recipe_id' => $recipe->id])->assertStatus(201);
        $this->asUser($other)
            ->postJson('/api/favorites', ['recipe_id' => $otherRecipe->id])->assertStatus(201);

        $res = $this->asUser($mine)
            ->getJson('/api/favorites')
            ->assertOk();

        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.id', $recipe->id);
        $res->assertJsonPath('data.0.is_favorite', true);
    }

    public function test_is_favorite_is_personal_in_catalog_and_detail(): void
    {
        $recipe = $this->makeRecipe();
        $user = $this->user();
        $stranger = User::create(['name' => 'Пётр', 'email' => 'petr@example.com', 'password' => 'secret123']);

        $this->asUser($user)
            ->postJson('/api/favorites', ['recipe_id' => $recipe->id])->assertStatus(201);

        // У автора отметка есть…
        $this->asUser($user)
            ->getJson('/api/recipes')
            ->assertOk()
            ->assertJsonPath('data.0.is_favorite', true);

        $this->asUser($user)
            ->getJson('/api/recipes/' . $recipe->slug)
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        // …а у постороннего — нет.
        $this->asUser($stranger)
            ->getJson('/api/recipes')
            ->assertOk()
            ->assertJsonPath('data.0.is_favorite', false);

        $this->asUser($stranger)
            ->getJson('/api/recipes/' . $recipe->slug)
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_guest_sees_is_favorite_false(): void
    {
        $recipe = $this->makeRecipe();

        $this->getJson('/api/recipes/' . $recipe->slug)
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_public_route_recognizes_user_by_token(): void
    {
        // Регрессия: каталог и карточка — публичные маршруты, поэтому
        // middleware auth:sanctum на них не выполняется и пользователь
        // не определялся. Отметка «в избранном» всегда была false,
        // даже когда запрос шёл с токеном.
        $recipe = $this->makeRecipe();
        $user = $this->user();

        $this->asUser($user)->postJson('/api/favorites', ['recipe_id' => $recipe->id])->assertStatus(201);

        // Первым запросом в тесте идёт публичный маршрут — так и было бы
        // в реальном приложении, где состояние guard'а не наследуется.
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('api')->plainTextToken)
            ->getJson('/api/recipes')
            ->assertOk()
            ->assertJsonPath('data.0.is_favorite', true);
    }

    public function test_favorite_of_missing_recipe_is_rejected(): void
    {
        $token = $this->user()->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/favorites', ['recipe_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('recipe_id');
    }

    public function test_favorites_count_is_visible_in_catalog(): void
    {
        $recipe = $this->makeRecipe();
        $token = $this->user()->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/favorites', ['recipe_id' => $recipe->id])->assertStatus(201);

        $this->getJson('/api/recipes')
            ->assertOk()
            ->assertJsonPath('data.0.favorites_count', 1);
    }
}
