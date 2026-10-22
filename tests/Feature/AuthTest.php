<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Аутентификация по токену Sanctum: регистрация, вход, выход, «кто я».
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_gets_token(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.email', 'ivan@example.com')
            ->assertJsonPath('data.is_admin', false);

        $this->assertNotEmpty($res->json('token'));
        $this->assertDatabaseHas('users', ['email' => 'ivan@example.com', 'is_admin' => false]);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::create(['name' => 'Первый', 'email' => 'dup@example.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/register', [
            'name' => 'Второй',
            'email' => 'dup@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_requires_password_confirmation(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'other123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_user_can_login_with_correct_password(): void
    {
        User::create(['name' => 'Иван', 'email' => 'ivan@example.com', 'password' => 'secret123']);

        $res = $this->postJson('/api/auth/login', [
            'email' => 'ivan@example.com',
            'password' => 'secret123',
        ]);

        $res->assertOk()->assertJsonPath('data.email', 'ivan@example.com');
        $this->assertNotEmpty($res->json('token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create(['name' => 'Иван', 'email' => 'ivan@example.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/login', [
            'email' => 'ivan@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_error_does_not_reveal_whether_email_exists(): void
    {
        User::create(['name' => 'Иван', 'email' => 'ivan@example.com', 'password' => 'secret123']);

        $unknown = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret123',
        ]);

        $wrongPassword = $this->postJson('/api/auth/login', [
            'email' => 'ivan@example.com',
            'password' => 'wrong-password',
        ]);

        // Одинаковый текст ошибки: иначе форма входа позволяет выяснить,
        // какие адреса зарегистрированы.
        $this->assertSame(
            $unknown->json('errors.email.0'),
            $wrongPassword->json('errors.email.0')
        );
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_unauthorized_request_returns_json_even_without_json_header(): void
    {
        // Без заголовка Accept: application/json обработчик ошибок
        // пытался перенаправить на маршрут «login», которого в SPA нет,
        // и отдавал 500 с HTML-страницей.
        $res = $this->get('/api/auth/me');

        $res->assertStatus(401);
        $this->assertStringContainsString('application/json', (string) $res->headers->get('Content-Type'));
    }

    public function test_me_returns_current_user(): void
    {
        $user = User::create(['name' => 'Иван', 'email' => 'ivan@example.com', 'password' => 'secret123']);
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ivan@example.com')
            ->assertJsonPath('data.is_admin', false);
    }

    public function test_password_is_stored_hashed(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated();

        $user = User::where('email', 'ivan@example.com')->first();

        $this->assertNotSame('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::create(['name' => 'Иван', 'email' => 'ivan@example.com', 'password' => 'secret123']);
        $token = $user->createToken('api')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        // Токен отозван, и тот же токен больше не работает.
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Guard внутри одного теста кэширует найденного пользователя,
        // а в бою каждый запрос получает свежее приложение — сбрасываем
        // кэш, чтобы проверить поведение «как в продакшене».
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401);
    }
}
