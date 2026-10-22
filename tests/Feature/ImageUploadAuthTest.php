<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Загрузка изображений доступна только администраторам.
 *
 * Регрессия: эндпоинт был открыт всем подряд, и любой посетитель
 * мог заливать файлы на диск.
 */
class ImageUploadAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_requires_authentication(): void
    {
        Storage::fake('public');

        $this->postJson('/api/upload/image', [
            'image' => UploadedFile::fake()->image('dish.jpg'),
        ])->assertStatus(401);
    }

    public function test_upload_is_forbidden_for_regular_user(): void
    {
        Storage::fake('public');

        $user = User::create(['name' => 'Обычный', 'email' => 'user@example.com', 'password' => 'secret123']);

        $this->withToken($user->createToken('api')->plainTextToken)
            ->postJson('/api/upload/image', [
                'image' => UploadedFile::fake()->image('dish.jpg'),
            ])->assertStatus(403);
    }

    public function test_admin_can_upload_image(): void
    {
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Админ',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $res = $this->withToken($admin->createToken('api')->plainTextToken)
            ->postJson('/api/upload/image', [
                'image' => UploadedFile::fake()->image('dish.jpg', 100, 100),
            ]);

        $res->assertCreated()->assertJsonStructure(['message', 'path', 'url']);

        // В ответе путь публичный («/storage/...»), а на диске — относительный.
        $relative = str_replace('/storage/', '', $res->json('path'));
        Storage::disk('public')->assertExists($relative);
    }

    public function test_upload_rejects_non_image(): void
    {
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Админ',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $this->withToken($admin->createToken('api')->plainTextToken)
            ->postJson('/api/upload/image', [
                'image' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
            ])->assertStatus(422)->assertJsonValidationErrors('image');
    }
}
