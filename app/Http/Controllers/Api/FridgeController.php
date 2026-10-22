<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FridgeMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FridgeController extends Controller
{
    public function __construct(private readonly FridgeMatcher $matcher)
    {
    }

    public function match(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ingredients' => ['required', 'array'],
            'ingredients.*' => ['required', 'string', 'max:100'],
            // Базовые продукты (соль, масло, вода…) по умолчанию не считаются
            // недостающими. Пользователь может попросить учитывать их.
            'include_pantry_staples' => ['sometimes', 'boolean'],
        ]);

        $includeStaples = (bool) ($data['include_pantry_staples'] ?? false);

        $results = $this->matcher->match($data['ingredients'], $includeStaples);

        return response()->json([
            'fridge' => $data['ingredients'],
            'include_pantry_staples' => $includeStaples,
            'count' => $results->count(),
            'recipes' => $results,
        ]);
    }
}
