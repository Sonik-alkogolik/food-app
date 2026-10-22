<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ограничение частоты запросов к API.
 *
 * Регрессия: лимитов не было вовсе, поэтому /api/search (LIKE-скан по
 * таблицам) и /api/fridge/match можно было завалить насмерть.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_rate_limited(): void
    {
        // Лимит api-search — 60 запросов в минуту.
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/search?q=борщ')->assertOk();
        }

        $this->getJson('/api/search?q=борщ')->assertStatus(429);
    }

    public function test_login_is_rate_limited(): void
    {
        // Лимит api-login — 10 попыток в минуту: перебор пароля упирается
        // в него, а не в бесконечные попытки.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
