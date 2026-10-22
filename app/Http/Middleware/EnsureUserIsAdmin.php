<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ только для администраторов.
 *
 * Ставится после auth:sanctum: к этому моменту пользователь уже
 * аутентифицирован, и мы проверяем именно его права.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Требуется авторизация.'], 401);
        }

        if (!$user->is_admin) {
            return response()->json(['message' => 'Доступ разрешён только администраторам.'], 403);
        }

        return $next($request);
    }
}
