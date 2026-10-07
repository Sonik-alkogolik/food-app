<?php

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

Route::fallback(function () {
    return view('spa');
});
