<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Смена пароля и восстановление по токену.
 */
class PasswordTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create(['name' => 'Иван', 'email' => 'ivan@example.com', 'password' => 'secret123']);
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = $this->user();

        $this->asUser($user)
            ->postJson('/api/auth/password', [
                'current_password' => 'wrong-password',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('secret123', $user->fresh()->password));
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->user();

        $this->asUser($user)
            ->postJson('/api/auth/password', [
                'current_password' => 'secret123',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertStatus(200);

        $this->assertTrue(Hash::check('newsecret123', $user->fresh()->password));

        // Старый пароль больше не подходит, новый — работает.
        $this->postJson('/api/auth/login', ['email' => 'ivan@example.com', 'password' => 'secret123'])
            ->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'ivan@example.com', 'password' => 'newsecret123'])
            ->assertStatus(200);
    }

    public function test_change_password_revokes_other_tokens_but_keeps_current(): void
    {
        $user = $this->user();
        $current = $user->createToken('api')->plainTextToken;
        $other = $user->createToken('api')->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($current)->postJson('/api/auth/password', [
            'current_password' => 'secret123',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertStatus(200);

        // Токен на другом устройстве больше не работает…
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/auth/me')->assertStatus(401);

        // …а текущий остаётся рабочим.
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/auth/me')->assertStatus(200);
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->postJson('/api/auth/password', [
            'current_password' => 'secret123',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertStatus(401);
    }

    public function test_forgot_password_does_not_reveal_whether_email_exists(): void
    {
        $this->user();

        $known = $this->postJson('/api/auth/forgot-password', ['email' => 'ivan@example.com']);
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertStatus(200);
        $unknown->assertStatus(200);

        // Одинаковый ответ: иначе форма восстановления показывала бы,
        // какие адреса зарегистрированы в системе.
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_forgot_password_creates_token_for_known_email(): void
    {
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => 'ivan@example.com'])->assertStatus(200);

        $this->assertNotNull(
            Password::broker()->tokenExists($user, Password::broker()->createToken($user))
        );
    }

    public function test_user_can_reset_password_with_token(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'ivan@example.com',
            'password' => 'brandnew123',
            'password_confirmation' => 'brandnew123',
        ])->assertStatus(200);

        $this->assertTrue(Hash::check('brandnew123', $user->fresh()->password));

        $this->postJson('/api/auth/login', ['email' => 'ivan@example.com', 'password' => 'brandnew123'])
            ->assertStatus(200);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $this->user();

        $this->postJson('/api/auth/reset-password', [
            'token' => 'wrong-token',
            'email' => 'ivan@example.com',
            'password' => 'brandnew123',
            'password_confirmation' => 'brandnew123',
        ])->assertStatus(422);
    }

    public function test_reset_password_revokes_existing_tokens(): void
    {
        $user = $this->user();
        $token = $user->createToken('api')->plainTextToken;
        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $resetToken,
            'email' => 'ivan@example.com',
            'password' => 'brandnew123',
            'password_confirmation' => 'brandnew123',
        ])->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
