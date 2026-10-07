<?php

use App\Http\Controllers\Api\CategoryController;
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
*/

Route::get('/categories', [CategoryController::class, 'index']);                 // список категорий с фото и счётчиком
Route::get('/recipes', [RecipeController::class, 'index']);                       // каталог с фильтрацией и пагинацией
Route::get('/recipes/{slug}', [RecipeDetailController::class, 'show'])            // детальная карточка по slug или id
    ->where('slug', '[A-Za-z0-9\-_\d]+');
Route::get('/recipes/{id}/steps', [RecipeDetailController::class, 'steps'])       // шаги приготовления рецепта
    ->whereNumber('id');
Route::get('/search', SearchController::class);                                   // поиск по названию и ингредиентам
Route::get('/random', RandomRecipeController::class);                                      // случайный рецепт
Route::post('/upload/image', [ImageUploadController::class, 'upload']);           // загрузка изображения (админ)

// «Холодильник» — подбор рецептов по имеющимся продуктам
Route::get('/ingredients', [IngredientController::class, 'index']);
Route::post('/fridge/match', [FridgeController::class, 'match']);
