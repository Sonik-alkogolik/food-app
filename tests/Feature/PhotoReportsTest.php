<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Фотоотчёты: как блюдо выглядит в реальности.
 *
 * Загрузить фотографию может и гость — под своим именем. Файл проверяется
 * на стороне Laravel: только настоящие jpeg/png/webp размером до 5 МБ.
 * Переименованный файл с чужим содержимым не проходит: MIME-тип
 * определяется по содержимому, а не по имени, присланному клиентом.
 */
class PhotoReportsTest extends TestCase
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

    private function user(string $email = 'ivan@example.com', bool $admin = false): User
    {
        return User::create([
            'name' => 'Иван',
            'email' => $email,
            'password' => 'secret123',
            'is_admin' => $admin,
        ]);
    }

    public function test_guest_can_post_photo_report_with_name(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        $res = $this->post('/api/recipes/' . $recipe->slug . '/results', [
            'image' => UploadedFile::fake()->image('dish.jpg', 200, 150),
            'comment' => 'Вот так выглядит!',
            'guest_name' => 'Маша',
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201)
            ->assertJsonPath('data.result.comment', 'Вот так выглядит!')
            ->assertJsonPath('data.result.user.name', 'Маша')
            ->assertJsonPath('data.result.is_guest', true);

        $this->assertDatabaseHas('recipe_results', [
            'recipe_id' => $recipe->id,
            'user_id' => null,
            'guest_name' => 'Маша',
            'comment' => 'Вот так выглядит!',
        ]);

        // Файл лежит на публичном диске под уникальным именем.
        $result = RecipeResult::first();
        $this->assertStringStartsWith('results/', $result->image_path);
        $this->assertMatchesRegularExpression('/^results\/[0-9a-f-]{36}\.jpg$/', $result->image_path);
        Storage::disk('public')->assertExists($result->image_path);
    }

    public function test_guest_name_is_required(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        $this->postJson('/api/recipes/' . $recipe->slug . '/results', [
            'image' => UploadedFile::fake()->image('dish.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors('guest_name');

        $this->assertDatabaseCount('recipe_results', 0);
    }

    public function test_user_posts_without_guest_name(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();
        $user = $this->user();

        $res = $this->asUser($user)
            ->post('/api/recipes/' . $recipe->slug . '/results', [
                'image' => UploadedFile::fake()->image('dish.jpg'),
            ], ['Accept' => 'application/json']);

        $res->assertStatus(201)
            ->assertJsonPath('data.result.user.name', 'Иван')
            ->assertJsonPath('data.result.is_guest', false);

        $this->assertDatabaseHas('recipe_results', [
            'recipe_id' => $recipe->id,
            'user_id' => $user->id,
            'guest_name' => null,
        ]);
    }

    /**
     * Загружать можно только настоящие изображения: тип файла проверяется
     * по содержимому, а не по расширению и не по типу, присланному клиентом.
     *
     * Здесь файлы настоящие (не подделки Laravel): иначе подделка сама
     * сообщала бы серверу нужный MIME-тип и проверка ничего не значила бы.
     */
    public function test_disguised_file_is_rejected(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        // Код, названный картинкой: клиент говорит «image/jpeg»,
        // а внутри — текст.
        $this->post('/api/recipes/' . $recipe->slug . '/results', [
            'image' => $this->realFile('dish.jpg', 'image/jpeg', "<?php echo 'привет';"),
            'guest_name' => 'Маша',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('image');

        // Настоящий текстовый файл.
        $this->post('/api/recipes/' . $recipe->slug . '/results', [
            'image' => $this->realFile('notes.txt', 'text/plain', 'просто текст'),
            'guest_name' => 'Маша',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('recipe_results', 0);
    }

    /**
     * Настоящий файл на диске: сервер определит его тип по содержимому.
     */
    private function realFile(string $name, string $clientMime, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'photo-report');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $clientMime, null, true);
    }

    public function test_file_larger_than_five_megabytes_is_rejected(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        $this->post('/api/recipes/' . $recipe->slug . '/results', [
            'image' => UploadedFile::fake()->image('big.jpg', 200, 150)->size(6000),
            'guest_name' => 'Маша',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('recipe_results', 0);
    }

    /**
     * Мусорные байты в имени не должны ломать ответ: иначе клиент
     * с битой кодировкой получал бы 500 вместо отчёта.
     */
    public function test_invalid_utf8_in_name_does_not_break_the_request(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        $res = $this->post('/api/recipes/' . $recipe->slug . '/results', [
            'image' => UploadedFile::fake()->image('dish.jpg'),
            'guest_name' => "Маша\xC3\x28",
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201);

        $name = RecipeResult::first()->guest_name;
        $this->assertTrue(mb_check_encoding($name, 'UTF-8'));
    }

    public function test_recipe_is_required(): void
    {
        Storage::fake('public');

        $this->post('/api/recipes/net-takogo-recepta/results', [
            'image' => UploadedFile::fake()->image('dish.jpg'),
            'guest_name' => 'Маша',
        ], ['Accept' => 'application/json'])->assertStatus(404);
    }

    /**
     * Список фотоотчётов виден всем, в том числе гостю.
     */
    public function test_results_are_listed_for_everyone(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        $this->post('/api/recipes/' . $recipe->slug . '/results', [
            'image' => UploadedFile::fake()->image('dish.jpg'),
            'comment' => 'Вот так выглядит!',
            'guest_name' => 'Маша',
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->getJson('/api/recipes/' . $recipe->slug . '/results')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.name', 'Маша')
            ->assertJsonPath('data.0.is_guest', true)
            ->assertJsonPath('data.0.can_delete', false);
    }

    /**
     * Гостю — 3 фотоотчёта за 15 минут с одного адреса: файл весит до 5 МБ
     * и остаётся на диске навсегда.
     */
    public function test_fourth_guest_photo_report_is_rejected(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/api/recipes/' . $recipe->slug . '/results', [
                'image' => UploadedFile::fake()->image('dish' . $i . '.jpg'),
                'guest_name' => 'Маша',
            ], ['Accept' => 'application/json'])->assertStatus(201);
        }

        $this->post('/api/recipes/' . $recipe->slug . '/results', [
            'image' => UploadedFile::fake()->image('dish4.jpg'),
            'guest_name' => 'Маша',
        ], ['Accept' => 'application/json'])->assertStatus(429);

        $this->assertDatabaseCount('recipe_results', 3);
    }

    public function test_guest_limit_does_not_apply_to_users(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();
        $user = $this->user();

        for ($i = 0; $i < 4; $i++) {
            $this->asUser($user)
                ->post('/api/recipes/' . $recipe->slug . '/results', [
                    'image' => UploadedFile::fake()->image('dish' . $i . '.jpg'),
                ], ['Accept' => 'application/json'])
                ->assertStatus(201);
        }
    }

    /**
     * Удалить фотоотчёт может автор или администратор.
     */
    public function test_only_author_or_admin_can_delete(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();
        $author = $this->user('author@example.com');
        $other = $this->user('other@example.com');
        $admin = $this->user('admin@example.com', true);

        $result = $recipe->results()->create([
            'user_id' => $author->id,
            'image_path' => 'results/x.jpg',
        ]);

        // Гость.
        $this->deleteJson('/api/recipes/' . $recipe->slug . '/results/' . $result->id)
            ->assertStatus(401);

        // Чужой пользователь.
        $this->asUser($other)
            ->deleteJson('/api/recipes/' . $recipe->slug . '/results/' . $result->id)
            ->assertStatus(403);

        $this->assertDatabaseCount('recipe_results', 1);

        // Автор.
        $this->asUser($author)
            ->deleteJson('/api/recipes/' . $recipe->slug . '/results/' . $result->id)
            ->assertOk();

        $this->assertDatabaseCount('recipe_results', 0);
    }

    public function test_admin_can_delete_any_photo_report(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();
        $author = $this->user('author@example.com');
        $admin = $this->user('admin@example.com', true);

        $result = $recipe->results()->create([
            'user_id' => $author->id,
            'image_path' => 'results/x.jpg',
        ]);

        $this->asUser($admin)
            ->deleteJson('/api/recipes/' . $recipe->slug . '/results/' . $result->id)
            ->assertOk();

        $this->assertDatabaseCount('recipe_results', 0);
    }
}
