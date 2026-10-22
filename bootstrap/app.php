<?php

use App\Http\Middleware\AuthenticateIfPresent;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Доступ только для администраторов (после auth:sanctum).
        // 'auth.optional' — мягкая авторизация для публичных маршрутов,
        // которые отдают персональные данные (отметка «в избранном»,
        // права на удаление комментария).
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'auth.optional' => AuthenticateIfPresent::class,
        ]);

        // Приложение — SPA, отдельной страницы «login» в нём нет.
        // По умолчанию Laravel 11+ редиректит гостя на route('login'),
        // и такой маршрут в проекте отсутствует: попытка построить URL
        // бросала «Route [login] not defined» ещё до обработчика ошибок,
        // и неавторизованный запрос отвечал 500 вместо 401.
        // Пустой редирект — ответ формирует обработчик ошибок (JSON 401).
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Любая ошибка внутри /api отдаётся JSON'ом, а не HTML-страницей:
        // SPA ждёт одинаковый формат ответа для всех кодов (401, 404, 422, 500).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
