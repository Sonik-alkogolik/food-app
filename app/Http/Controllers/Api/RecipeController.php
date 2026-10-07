<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ШАГ 19: список рецептов с фильтрацией и пагинацией (12 на страницу).
 * Параметры: category_id, difficulty, max_time, sort (new|popular|time), page.
 */
class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Recipe::query()->active()->with('category:id,name,slug');

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($difficulty = $request->string('difficulty')->toString()) {
            $difficulties = explode(',', $difficulty); // поддержка чекбоксов: easy,medium,hard
            $query->whereIn('difficulty', array_intersect($difficulties, ['easy', 'medium', 'hard']));
        }

        if ($maxTime = $request->integer('max_time')) {
            $query->where('cook_time_minutes', '<=', $maxTime);
        }

        match ($request->string('sort')->toString()) {
            'popular' => $query->orderByDesc('views_count'),
            'time'    => $query->orderBy('cook_time_minutes'),
            'title'   => $query->orderBy('title'),
            default   => $query->latest('id'),
        };

        $recipes = $query->paginate(12)->appends($request->query());

        return response()->json($recipes);
    }
}
