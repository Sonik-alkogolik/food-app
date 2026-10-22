<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Комментарии: чтение, запись, права на удаление.
 */
class CommentTest extends TestCase
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

    private function user(string $email = 'ivan@example.com', bool $isAdmin = false): User
    {
        return User::create([
            'name' => 'Иван',
            'email' => $email,
            'password' => 'secret123',
            'is_admin' => $isAdmin,
        ]);
    }

    public function test_guest_can_read_comments(): void
    {
        $recipe = $this->makeRecipe();
        $user = $this->user();
        $recipe->comments()->create(['user_id' => $user->id, 'body' => 'Вкусно!']);

        $this->getJson('/api/recipes/' . $recipe->slug . '/comments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Вкусно!')
            ->assertJsonPath('data.0.user.name', 'Иван');
    }

    public function test_guest_cannot_post_comment(): void
    {
        $recipe = $this->makeRecipe();

        $this->postJson('/api/recipes/' . $recipe->slug . '/comments', ['body' => 'Вкусно!'])
            ->assertStatus(401);
    }

    public function test_user_can_post_comment(): void
    {
        $recipe = $this->makeRecipe();
        $user = $this->user();

        $res = $this->asUser($user)
            ->postJson('/api/recipes/' . $recipe->slug . '/comments', ['body' => 'Вкусно!']);

        $res->assertStatus(201)
            ->assertJsonPath('data.comment.body', 'Вкусно!')
            ->assertJsonPath('data.comment.user.name', 'Иван');

        $this->assertDatabaseHas('comments', ['recipe_id' => $recipe->id, 'body' => 'Вкусно!']);
        $this->assertSame(1, $recipe->fresh()->comments_count);
    }

    public function test_comment_requires_text(): void
    {
        $recipe = $this->makeRecipe();
        $user = $this->user();

        $this->asUser($user)
            ->postJson('/api/recipes/' . $recipe->slug . '/comments', ['body' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    public function test_comment_is_rejected_for_missing_recipe(): void
    {
        $user = $this->user();

        $this->asUser($user)
            ->postJson('/api/recipes/nosuchrecipe/comments', ['body' => 'Вкусно!'])
            ->assertStatus(404);
    }

    public function test_author_can_delete_own_comment(): void
    {
        $recipe = $this->makeRecipe();
        $user = $this->user();
        $comment = $recipe->comments()->create(['user_id' => $user->id, 'body' => 'Вкусно!']);

        $this->asUser($user)
            ->deleteJson('/api/comments/' . $comment->id)
            ->assertStatus(200);

        $this->assertDatabaseCount('comments', 0);
        $this->assertSame(0, $recipe->fresh()->comments_count);
    }

    public function test_stranger_cannot_delete_comment(): void
    {
        $recipe = $this->makeRecipe();
        $author = $this->user();
        $stranger = $this->user('petr@example.com');
        $comment = $recipe->comments()->create(['user_id' => $author->id, 'body' => 'Вкусно!']);

        $this->asUser($stranger)
            ->deleteJson('/api/comments/' . $comment->id)
            ->assertStatus(403);

        $this->assertDatabaseCount('comments', 1);
    }

    public function test_admin_can_delete_any_comment(): void
    {
        $recipe = $this->makeRecipe();
        $author = $this->user();
        $admin = $this->user('admin@example.com', true);
        $comment = $recipe->comments()->create(['user_id' => $author->id, 'body' => 'Вкусно!']);

        $this->asUser($admin)
            ->deleteJson('/api/comments/' . $comment->id)
            ->assertStatus(200);

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_can_delete_flag_is_personal(): void
    {
        $recipe = $this->makeRecipe();
        $author = $this->user();
        $stranger = $this->user('petr@example.com');
        $recipe->comments()->create(['user_id' => $author->id, 'body' => 'Вкусно!']);

        $this->asUser($author)
            ->getJson('/api/recipes/' . $recipe->slug . '/comments')
            ->assertOk()
            ->assertJsonPath('data.0.can_delete', true);

        $this->asUser($stranger)
            ->getJson('/api/recipes/' . $recipe->slug . '/comments')
            ->assertOk()
            ->assertJsonPath('data.0.can_delete', false);
    }

    public function test_comments_count_is_visible_in_detail(): void
    {
        $recipe = $this->makeRecipe();
        $user = $this->user();

        $this->asUser($user)
            ->postJson('/api/recipes/' . $recipe->slug . '/comments', ['body' => 'Вкусно!'])
            ->assertStatus(201);

        $this->getJson('/api/recipes/' . $recipe->slug)
            ->assertOk()
            ->assertJsonPath('data.comments_count', 1);
    }
}
