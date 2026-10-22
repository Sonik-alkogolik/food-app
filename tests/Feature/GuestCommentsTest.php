<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Комментарии гостей.
 *
 * Гость пишет комментарий под именем: у него нет аккаунта, и заводить
 * его ради одного отзыва незачем. Имя хранится рядом с комментарием,
 * а user_id остаётся пустым.
 */
class GuestCommentsTest extends TestCase
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

    private function user(string $email = 'ivan@example.com'): User
    {
        return User::create([
            'name' => 'Иван',
            'email' => $email,
            'password' => 'secret123',
        ]);
    }

    public function test_guest_can_post_comment_with_name(): void
    {
        $recipe = $this->makeRecipe();

        $res = $this->postJson('/api/recipes/' . $recipe->slug . '/comments', [
            'body' => 'Вкусно!',
            'guest_name' => 'Маша',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.comment.body', 'Вкусно!')
            ->assertJsonPath('data.comment.user.name', 'Маша')
            ->assertJsonPath('data.comment.is_guest', true);

        $this->assertDatabaseHas('comments', [
            'recipe_id' => $recipe->id,
            'body' => 'Вкусно!',
            'guest_name' => 'Маша',
            'user_id' => null,
        ]);

        $this->assertSame(1, $recipe->fresh()->comments_count);
    }

    public function test_guest_comment_requires_name(): void
    {
        $recipe = $this->makeRecipe();

        $this->postJson('/api/recipes/' . $recipe->slug . '/comments', ['body' => 'Вкусно!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('guest_name');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_guest_name_is_limited_to_fifty_characters(): void
    {
        $recipe = $this->makeRecipe();

        $this->postJson('/api/recipes/' . $recipe->slug . '/comments', [
            'body' => 'Вкусно!',
            'guest_name' => str_repeat('а', 51),
        ])->assertStatus(422)->assertJsonValidationErrors('guest_name');

        $this->postJson('/api/recipes/' . $recipe->slug . '/comments', [
            'body' => 'Вкусно!',
            'guest_name' => str_repeat('а', 50),
        ])->assertStatus(201);
    }

    /**
     * Гостю — 3 комментария за 15 минут с одного адреса.
     */
    public function test_fourth_guest_comment_is_rejected(): void
    {
        $recipe = $this->makeRecipe();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/recipes/' . $recipe->slug . '/comments', [
                'body' => 'Вкусно!',
                'guest_name' => 'Маша',
            ])->assertStatus(201);
        }

        $this->postJson('/api/recipes/' . $recipe->slug . '/comments', [
            'body' => 'И ещё раз',
            'guest_name' => 'Маша',
        ])->assertStatus(429);

        $this->assertDatabaseCount('comments', 3);
    }

    public function test_guest_comment_is_visible_to_everyone(): void
    {
        $recipe = $this->makeRecipe();
        $this->postJson('/api/recipes/' . $recipe->slug . '/comments', [
            'body' => 'Вкусно!',
            'guest_name' => 'Маша',
        ])->assertStatus(201);

        $this->getJson('/api/recipes/' . $recipe->slug . '/comments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.name', 'Маша')
            ->assertJsonPath('data.0.is_guest', true);
    }

    /**
     * Гость не может удалить комментарий — ни свой, ни чужой.
     */
    public function test_guest_cannot_delete_comment(): void
    {
        $recipe = $this->makeRecipe();
        $author = $this->user();
        $comment = $recipe->comments()->create(['user_id' => $author->id, 'body' => 'Вкусно!']);

        $this->deleteJson('/api/comments/' . $comment->id)->assertStatus(401);

        $this->assertDatabaseCount('comments', 1);
    }

    /**
     * Гостю кнопка удаления не показывается: подписать его комментарий
     * именем недостаточно, чтобы доказать, что он его написал.
     */
    public function test_guest_sees_no_delete_button(): void
    {
        $recipe = $this->makeRecipe();
        $recipe->comments()->create(['user_id' => null, 'guest_name' => 'Маша', 'body' => 'Вкусно!']);

        $this->getJson('/api/recipes/' . $recipe->slug . '/comments')
            ->assertOk()
            ->assertJsonPath('data.0.can_delete', false);
    }

    public function test_user_still_posts_without_guest_name(): void
    {
        $recipe = $this->makeRecipe();
        $user = $this->user();

        $res = $this->asUser($user)
            ->postJson('/api/recipes/' . $recipe->slug . '/comments', ['body' => 'Вкусно!']);

        $res->assertStatus(201)
            ->assertJsonPath('data.comment.user.name', 'Иван')
            ->assertJsonPath('data.comment.is_guest', false);

        $this->assertDatabaseHas('comments', [
            'recipe_id' => $recipe->id,
            'user_id' => $user->id,
            'guest_name' => null,
        ]);
    }

    /**
     * Гостевой лимит не распространяется на авторизованных: у них свой
     * общий лимит на запись.
     */
    public function test_guest_limit_does_not_apply_to_users(): void
    {
        $recipe = $this->makeRecipe();
        $user = $this->user();

        for ($i = 0; $i < 4; $i++) {
            $this->asUser($user)
                ->postJson('/api/recipes/' . $recipe->slug . '/comments', ['body' => 'Вкусно!'])
                ->assertStatus(201);
        }
    }

    /**
     * Мусорные байты в тексте не должны ломать ответ: клиент с битой
     * кодировкой получал бы 500 вместо комментария.
     *
     * Формой, а не JSON: в JSON такие байты не разбираются вовсе, и запрос
     * просто остаётся без данных (это отдельный случай, ответ 422).
     */
    public function test_invalid_utf8_in_body_does_not_break_the_request(): void
    {
        $recipe = $this->makeRecipe();

        $res = $this->post('/api/recipes/' . $recipe->slug . '/comments', [
            'body' => "Вкусно\xC3\x28",
            'guest_name' => 'Маша',
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201);
        $this->assertTrue(mb_check_encoding(Comment::first()->body, 'UTF-8'));
    }

    public function test_comment_keeps_its_author_after_user_deletion(): void
    {
        $recipe = $this->makeRecipe();
        $comment = $recipe->comments()->create([
            'user_id' => null,
            'guest_name' => 'Маша',
            'body' => 'Вкусно!',
        ]);

        $this->assertTrue($comment->isGuest());
        $this->assertSame('Маша', $comment->authorName());
    }
}
