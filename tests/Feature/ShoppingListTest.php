<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Список покупок: списки, позиции и массовое добавление ингредиентов
 * рецепта.
 */
class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipe(string $ingredients = "Свёкла — 200г\nСоль — 1/2 tsp\nСоль, перец — по вкусу", int $servings = 4): Recipe
    {
        $cat = Category::firstOrCreate(['slug' => 'supy'], ['name' => 'Супы']);

        return Recipe::create([
            'category_id' => $cat->id,
            'title' => 'Борщ',
            'slug' => \App\Support\Slug::make('Борщ'),
            'ingredients' => $ingredients,
            'instructions' => '1. Сварить',
            'cook_time_minutes' => 60,
            'difficulty' => 'easy',
            'servings' => $servings,
            'is_active' => true,
        ]);
    }

    private function user(string $email = 'ivan@example.com'): User
    {
        return User::create(['name' => 'Иван', 'email' => $email, 'password' => 'secret123']);
    }

    public function test_guest_cannot_use_shopping_list(): void
    {
        $this->getJson('/api/shopping-list')->assertStatus(401);
        $this->postJson('/api/shopping-list', ['title' => 'Покупки'])->assertStatus(401);
    }

    public function test_new_user_gets_a_default_list(): void
    {
        $user = $this->user();

        $res = $this->asUser($user)->getJson('/api/shopping-list');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Мой список')
            ->assertJsonCount(0, 'data.0.items');

        $this->assertDatabaseHas('shopping_lists', ['user_id' => $user->id, 'title' => 'Мой список']);
    }

    public function test_user_can_create_rename_and_delete_list(): void
    {
        $user = $this->user();

        $created = $this->asUser($user)->postJson('/api/shopping-list', ['title' => 'На неделю']);
        $created->assertStatus(201)->assertJsonPath('data.title', 'На неделю');

        $id = $created->json('data.id');

        $this->asUser($user)->patchJson("/api/shopping-list/{$id}", ['title' => 'Выходные'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Выходные');

        $this->asUser($user)->deleteJson("/api/shopping-list/{$id}")->assertStatus(200);

        $this->assertDatabaseMissing('shopping_lists', ['id' => $id]);
    }

    public function test_list_title_is_required(): void
    {
        $this->asUser($this->user())
            ->postJson('/api/shopping-list', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_user_can_add_item_and_mark_it_bought(): void
    {
        $user = $this->user();
        $listId = $this->asUser($user)->getJson('/api/shopping-list')->json('data.0.id');

        $res = $this->asUser($user)->postJson("/api/shopping-list/{$listId}/items", [
            'name' => 'Молоко',
            'amount' => 2,
            'unit' => 'л',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Молоко')
            ->assertJsonPath('data.checked', false)
            ->assertJsonPath('data.amount_label', '2 л');

        $itemId = $res->json('data.id');

        $this->asUser($user)->patchJson("/api/shopping-list/{$listId}/items/{$itemId}", ['checked' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.checked', true);

        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $listId,
            'name' => 'Молоко',
            'checked' => true,
        ]);
    }

    public function test_user_can_delete_item(): void
    {
        $user = $this->user();
        $listId = $this->asUser($user)->getJson('/api/shopping-list')->json('data.0.id');
        $itemId = $this->asUser($user)->postJson("/api/shopping-list/{$listId}/items", ['name' => 'Хлеб'])
            ->json('data.id');

        $this->asUser($user)->deleteJson("/api/shopping-list/{$listId}/items/{$itemId}")->assertStatus(200);

        $this->assertDatabaseMissing('shopping_list_items', ['id' => $itemId]);
    }

    public function test_bulk_add_ingredients_of_recipe(): void
    {
        $user = $this->user();
        $recipe = $this->makeRecipe();
        $listId = $this->asUser($user)->getJson('/api/shopping-list')->json('data.0.id');

        $res = $this->asUser($user)->postJson("/api/shopping-list/{$listId}/items/bulk", [
            'recipe' => $recipe->slug,
        ]);

        $res->assertStatus(201)->assertJsonCount(3, 'data.items');

        $items = collect($res->json('data.items'));
        $this->assertSame('Свёкла', $items->firstWhere('name', 'Свёкла')['name']);
        $this->assertSame(200.0, (float) $items->firstWhere('name', 'Свёкла')['amount']);
        $this->assertSame('г', $items->firstWhere('name', 'Свёкла')['unit']);
        $this->assertSame(0.5, (float) $items->firstWhere('name', 'Соль')['amount']);
        // Позиция без количества остаётся позицией без количества.
        $this->assertNull($items->firstWhere('name', 'Соль, перец — по вкусу')['amount']);

        $this->assertDatabaseCount('shopping_list_items', 3);
    }

    public function test_bulk_add_scales_amounts_by_servings(): void
    {
        $user = $this->user();
        $recipe = $this->makeRecipe(servings: 4);
        $listId = $this->asUser($user)->getJson('/api/shopping-list')->json('data.0.id');

        $res = $this->asUser($user)->postJson("/api/shopping-list/{$listId}/items/bulk", [
            'recipe' => $recipe->slug,
            'servings' => 8,
        ]);

        $res->assertStatus(201);

        $items = collect($res->json('data.items'));
        $this->assertSame(400.0, (float) $items->firstWhere('name', 'Свёкла')['amount']);
        $this->assertSame(1.0, (float) $items->firstWhere('name', 'Соль')['amount']);
    }

    public function test_bulk_add_does_not_duplicate_item_but_adds_amount(): void
    {
        $user = $this->user();
        $recipe = $this->makeRecipe();
        $listId = $this->asUser($user)->getJson('/api/shopping-list')->json('data.0.id');

        $this->asUser($user)->postJson("/api/shopping-list/{$listId}/items/bulk", ['recipe' => $recipe->slug])
            ->assertStatus(201);

        $res = $this->asUser($user)->postJson("/api/shopping-list/{$listId}/items/bulk", ['recipe' => $recipe->slug]);
        $res->assertStatus(201);

        $items = collect($res->json('data.items'));
        $this->assertCount(3, $items, 'Позиции не должны дублироваться.');
        $this->assertSame(400.0, (float) $items->firstWhere('name', 'Свёкла')['amount']);

        $this->assertDatabaseCount('shopping_list_items', 3);
    }

    public function test_bulk_add_reports_unknown_recipe(): void
    {
        $user = $this->user();
        $listId = $this->asUser($user)->getJson('/api/shopping-list')->json('data.0.id');

        $this->asUser($user)->postJson("/api/shopping-list/{$listId}/items/bulk", ['recipe' => 'net-takogo'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Рецепт не найден.');
    }

    public function test_user_cannot_touch_another_users_list(): void
    {
        $owner = $this->user('owner@example.com');
        $other = $this->user('other@example.com');

        $list = ShoppingList::create(['user_id' => $owner->id, 'title' => 'Покупки']);

        $otherLists = $this->asUser($other)->getJson('/api/shopping-list');
        $otherLists->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertNotSame($list->id, $otherLists->json('data.0.id'));

        $this->asUser($other)->patchJson("/api/shopping-list/{$list->id}", ['title' => 'Взлом'])
            ->assertStatus(404);
        $this->asUser($other)->deleteJson("/api/shopping-list/{$list->id}")->assertStatus(404);
        $this->asUser($other)->postJson("/api/shopping-list/{$list->id}/items", ['name' => 'Хлеб'])
            ->assertStatus(404);

        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id, 'title' => 'Покупки']);
    }

    public function test_item_of_another_list_is_not_accessible(): void
    {
        $owner = $this->user('owner@example.com');
        $other = $this->user('other@example.com');

        $list = ShoppingList::create(['user_id' => $owner->id, 'title' => 'Покупки']);
        $item = $list->items()->create(['name' => 'Сахар', 'amount' => 1, 'unit' => 'кг']);

        $ownListId = $this->asUser($other)->getJson('/api/shopping-list')->json('data.0.id');

        $this->asUser($other)
            ->patchJson("/api/shopping-list/{$ownListId}/items/{$item->id}", ['checked' => true])
            ->assertStatus(404);
        $this->asUser($other)
            ->deleteJson("/api/shopping-list/{$ownListId}/items/{$item->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'checked' => false]);
    }

    public function test_deleting_list_removes_its_items(): void
    {
        $user = $this->user();
        $listId = $this->asUser($user)->getJson('/api/shopping-list')->json('data.0.id');
        $this->asUser($user)->postJson("/api/shopping-list/{$listId}/items", ['name' => 'Мёд'])->assertStatus(201);

        $this->asUser($user)->deleteJson("/api/shopping-list/{$listId}")->assertStatus(200);

        $this->assertDatabaseMissing('shopping_list_items', ['shopping_list_id' => $listId]);
    }
}
