<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FridgeController;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\RecipeDetailController;
use App\Http\Controllers\Api\RandomRecipeController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (ШАГ 21)
|--------------------------------------------------------------------------
|
| У каждой группы маршрутов свой лимит частоты запросов (см.
| AppServiceProvider::registerRateLimiters) — без них тяжёлые к БД
| запросы поиска и подбора по холодильнику легко кладут приложение.
|
*/

// Аутентификация (Sanctum, токен в заголовке Authorization: Bearer <token>)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:api-register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:api-reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:api-reset');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/password', [AuthController::class, 'changePassword'])
        ->middleware(['auth:sanctum', 'throttle:api-write']);
});

Route::get('/categories', [CategoryController::class, 'index'])                 // список категорий с фото и счётчиком
    ->middleware('throttle:api-read');
Route::get('/recipes', [RecipeController::class, 'index'])                       // каталог с фильтрацией и пагинацией
    ->middleware(['auth.optional', 'throttle:api-read']);
Route::get('/recipes/{slug}', [RecipeDetailController::class, 'show'])            // детальная карточка по slug или id
    ->where('slug', '[A-Za-z0-9\-_\d]+')
    ->middleware(['auth.optional', 'throttle:api-detail']);
Route::get('/recipes/{id}/steps', [RecipeDetailController::class, 'steps'])       // шаги приготовления рецепта
    ->whereNumber('id')
    ->middleware('throttle:api-read');
Route::get('/search', SearchController::class)                                   // поиск по названию и ингредиентам
    ->middleware(['auth.optional', 'throttle:api-search']);
Route::get('/random', RandomRecipeController::class)                             // случайный рецепт
    ->middleware(['auth.optional', 'throttle:api-read']);
Route::post('/upload/image', [ImageUploadController::class, 'upload'])           // загрузка изображения (админ)
    ->middleware(['auth:sanctum', 'admin', 'throttle:api-write']);

// «Холодильник» — подбор рецептов по имеющимся продуктам
Route::get('/ingredients', [IngredientController::class, 'index'])
    ->middleware('throttle:api-read');
Route::post('/fridge/match', [FridgeController::class, 'match'])
    ->middleware('throttle:api-search');

/*
| Комментарии читать можно и без авторизации — их видит любой, кто открыл
| рецепт. А вот писать и удалять уже только авторизованным.
*/
Route::get('/recipes/{slug}/comments', [CommentController::class, 'index'])
    ->where('slug', '[A-Za-z0-9\-_\d]+')
    ->middleware(['auth.optional', 'throttle:api-read']);

/*
| Комментарий может оставить и гость — под своим именем. Гостей держит
| отдельный лимит: 3 комментария за 15 минут с одного адреса, иначе
| через анонимные комментарии можно было бы завалить рецепт. Авторизованным
| этот лимит не мешает: у них общий лимит на запись (throttle:api-write).
*/
Route::post('/recipes/{slug}/comments', [CommentController::class, 'store'])
    ->where('slug', '[A-Za-z0-9\-_\d]+')
    ->middleware(['auth.optional', 'throttle:api-write', 'throttle:guest-comment']);

/*
| Личное: избранное и удаление комментариев. Всё требует токена —
| избранное принадлежит конкретному пользователю, а удалить комментарий
| может только его автор или администратор.
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])
        ->middleware('throttle:api-read');
    Route::post('/favorites', [FavoriteController::class, 'store'])
        ->middleware('throttle:api-write');
    Route::delete('/favorites/{recipeId}', [FavoriteController::class, 'destroy'])
        ->whereNumber('recipeId')
        ->middleware('throttle:api-write');

    Route::delete('/comments/{id}', [CommentController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('throttle:api-write');
});

/*
| Несуществующий путь внутри /api — это ошибка API, а не страница сайта.
| Свой catch-all на все методы нужен потому, что Route::fallback()
| регистрируется только на GET: для остальных методов Laravel отвечал бы
| 405, а клиенту нужен единый JSON-ответ с кодом 404.
*/
Route::any('{any?}', function () {
    return response()->json(['message' => 'Метод не найден.'], 404);
})->where('any', '.*');
