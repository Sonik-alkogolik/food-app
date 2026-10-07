<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ШАГ 21: поиск рецептов по названию и ингредиентам (GET /api/search?q=...).
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $q = trim($request->string('q')->toString());

        if ($q === '') {
            return response()->json(['data' => [], 'query' => $q]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        $recipes = Recipe::query()
            ->active()
            ->with('category:id,name,slug')
            ->where(function ($query) use ($like) {
                $query->where('title', 'LIKE', $like)
                    ->orWhere('ingredients', 'LIKE', $like)
                    ->orWhere('description', 'LIKE', $like);
            })
            ->orderByDesc('views_count')
            ->paginate(12)
            ->appends(['q' => $q]);

        return response()->json($recipes->setCollection(
            $recipes->getCollection()->map(fn (Recipe $recipe) => array_merge($recipe->toArray(), [
                'highlight' => $this->highlight($recipe, $q),
            ]))
        ));
    }

    /** Подсветка найденных слов для фронтенда (ШАГ 36) */
    private function highlight(Recipe $recipe, string $q): array
    {
        $pattern = '/(' . preg_quote($q, '/') . ')/iu';

        return [
            'title' => preg_replace($pattern, '<mark>$1</mark>', e($recipe->title)),
            'ingredients' => preg_replace($pattern, '<mark>$1</mark>', e($recipe->ingredients ?? '')),
        ];
    }
}
