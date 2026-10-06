<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\JsonResponse;

class IngredientController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Ingredient::orderBy('name')->get(['id', 'name', 'slug'])
        );
    }
}
