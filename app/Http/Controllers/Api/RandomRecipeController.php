<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Symfony\Component\HttpFoundation\Response;

/**
 * ШАГ 26 (поддержка страницы /random): случайный активный рецепт
 * вместе с категорией и шагами приготовления.
 */
class RandomRecipeController extends Controller
{
    public function __invoke(): Response
    {
        $recipe = Recipe::query()
            ->active()
            ->with('category:id,name,slug')
            ->inRandomOrder()
            ->first();

        if (!$recipe) {
            return response()->json(['message' => 'Рецепты не найдены'], 404);
        }

        $recipe->load('steps');

        return response()->json([
            'data' => array_merge($recipe->toArray(), [
                'ingredients_list' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $recipe->ingredients)))),
            ]),
        ]);
    }
}
