<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сообщения валидации на русском.
 *
 * Регрессия: приложение выставляет APP_LOCALE=ru, но каталога lang/ru в
 * проекте не было — Laravel брал английский fallback, и в форме входа
 * пользователь видел «The email field must be a valid email address».
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_in_russian(): void
    {
        $this->app->setLocale('ru');

        $res = $this->postJson('/api/auth/register', [
            'name' => 'A',
            'email' => 'не-почта',
            'password' => 'short',
            'password_confirmation' => 'other',
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'password']);

        $messages = $res->json('errors');

        foreach ($messages as $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $this->assertMatchesRegularExpression(
                    '/[а-яё]/ui',
                    $message,
                    "Ожидалось русское сообщение об ошибке, получено: {$message}"
                );
            }
        }
    }

    public function test_password_reset_notification_is_translated(): void
    {
        $this->app->setLocale('ru');

        // Стандартное уведомление Laravel ищет строки по ключу, равному
        // английскому тексту, — переводы лежат именно под такими ключами.
        $this->assertSame('Сброс пароля', trans('Reset Password Notification'));
        $this->assertSame('Сбросить пароль', trans('Reset Password'));
    }
}
