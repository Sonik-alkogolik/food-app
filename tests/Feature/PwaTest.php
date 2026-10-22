<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PWA: манифест, service worker и оболочка приложения.
 *
 * Service worker, манифест и иконки — статика в public/, их раздаёт
 * веб-сервер, а не Laravel: в тестах маршрут /sw.js ушёл бы в SPA-fallback.
 * Поэтому проверяем сами файлы, а по HTTP — только то, что отдаёт Laravel.
 */
class PwaTest extends TestCase
{
    private function manifest(): array
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertIsArray($manifest, 'Манифест должен быть корректным JSON.');

        return $manifest;
    }

    public function test_manifest_describes_the_installable_app(): void
    {
        $manifest = $this->manifest();

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);
        $this->assertNotEmpty($manifest['theme_color']);
    }

    public function test_manifest_icons_exist_on_disk(): void
    {
        $manifest = $this->manifest();

        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            $this->assertNotEmpty($icon['sizes']);
        }
    }

    public function test_service_worker_lives_in_the_site_root(): void
    {
        // Service worker в корне сайта — иначе его scope был бы /frontend/
        // и страницы рецептов он бы не контролировал.
        $swPath = public_path('sw.js');
        if (! is_file($swPath)) {
            $this->markTestSkipped('Фронтенд не собран: service worker создаётся при сборке.');
        }

        $worker = file_get_contents($swPath);
        $this->assertStringContainsString('precacheAndRoute', $worker);
        $this->assertStringContainsString('/manifest.webmanifest', $worker);
    }

    public function test_app_shell_links_the_manifest_and_declares_theme_color(): void
    {
        // Оболочка приложения — собранный frontend/index.html, и именно
        // он кладётся в precache: сервер и офлайн отдают один и тот же файл.
        $shell = file_get_contents(base_path('frontend/index.html'));

        $this->assertStringContainsString('rel="manifest"', $shell);
        $this->assertStringContainsString('/manifest.webmanifest', $shell);
        $this->assertStringContainsString('name="theme-color"', $shell);
        $this->assertStringContainsString('name="apple-mobile-web-app-capable"', $shell);

        $this->get('/')->assertOk();
    }
}
