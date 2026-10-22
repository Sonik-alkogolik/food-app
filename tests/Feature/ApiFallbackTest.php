<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Несуществующие пути в /api должны отдавать JSON 404, а не HTML SPA.
 *
 * Регрессия: fallback отдавал view('spa') для любого адреса, поэтому
 * клиент получал HTML с кодом 200 вместо понятной ошибки API.
 */
class ApiFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_api_path_returns_json_404(): void
    {
        $res = $this->getJson('/api/there-is-no-such-endpoint');

        $res->assertNotFound()->assertJsonStructure(['message']);
        $this->assertStringContainsString('application/json', (string) $res->headers->get('Content-Type'));
    }

    public function test_unknown_api_path_with_post_returns_json_404(): void
    {
        $this->postJson('/api/there-is-no-such-endpoint', [])
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_unknown_site_path_still_serves_spa(): void
    {
        // Обычные адреса сайта (например, /recipes/борщ) открывают SPA.
        $res = $this->get('/recipes/borch');

        $res->assertOk();
        $this->assertStringContainsString('text/html', (string) $res->headers->get('Content-Type'));
    }
}
