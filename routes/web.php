<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — SPA «Что приготовить?» (Vue 3)
|--------------------------------------------------------------------------
| Fallback отдаёт resources/views/spa.blade.php, который подключает
| собранный бандл фронтенда (public/frontend/assets) или Vite dev-сервер.
| SEO meta/OG/Schema.org для страниц рецептов управляется на клиенте
| (@unhead/vue) и отдаётся SearchController-страницами при необходимости.
*/

// Страница восстановления пароля. Имя маршрута 'password.reset' обязательно:
// именно на него ссылается стандартное уведомление Laravel о сбросе пароля,
// которое мы отправляем пользователю.
Route::get('/reset-password', function () {
    return view('spa');
})->name('password.reset');

Route::fallback(function (Request $request) {
    // Несуществующий путь в /api — это ошибка API, а не страница сайта.
    // Без этой проверки клиент получал бы HTML SPA вместо JSON 404.
    if ($request->is('api/*')) {
        return response()->json([
            'message' => 'Метод не найден.',
        ], 404);
    }

    return view('spa');
});
