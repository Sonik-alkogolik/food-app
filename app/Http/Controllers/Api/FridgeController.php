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
        ]);

        $results = $this->matcher->match($data['ingredients']);

        return response()->json([
            'fridge' => $data['ingredients'],
            'count' => $results->count(),
            'recipes' => $results,
        ]);
    }
}
