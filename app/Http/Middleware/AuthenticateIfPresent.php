<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Мягкая авторизация: токен есть — пользователь известен, токена нет —
 * запрос просто гостевой.
 *
 * Нужна публичным маршрутам, которые отдают персональные данные:
 * каталог и карточка рецепта показывают отметку «в избранном», а
 * комментарии — флаг «можно удалить». Обычный auth:sanctum там не годится
 * (он отклонил бы гостя), но и без него $request->user() всегда null:
 * middleware с токеном на маршруте не выполняется, и пользователь не
 * определяется.
 */
class AuthenticateIfPresent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            $sanctum = auth('sanctum');

            // check() возвращает false для испорченного или отозванного
            // токена: такой запрос считаем гостевым, а не падаем с 401 —
            // маршрут-то публичный.
            if ($sanctum->check()) {
                $request->setUserResolver(function ($guard = null) use ($sanctum) {
                    return $sanctum->user();
                });
            }
        }

        return $next($request);
    }
}
