<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\FridgeController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/recipes', [RecipeController::class, 'index']);

Route::get('/ingredients', [IngredientController::class, 'index']);
Route::post('/fridge/match', [FridgeController::class, 'match']);
