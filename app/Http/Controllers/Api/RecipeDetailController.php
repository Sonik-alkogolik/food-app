<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * ШАГ 19: полная карточка рецепта по slug или id + шаги с фото + похожие.
 * Инкремент views_count при каждом запросе. Кеш деталей — 10 минут (ШАГ 41).
 */
class RecipeDetailController extends Controller
{
    public function show(string $slugOrId): Response
    {
        $isNumeric = ctype_digit($slugOrId);
        $cacheKey = 'api.recipe.detail.' . ($isNumeric ? "id-{$slugOrId}" : "slug-{$slugOrId}");

        try {
            $recipe = Recipe::query()
                ->active()
                ->where($isNumeric ? 'id' : 'slug', $slugOrId)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Рецепт не найден'], 404);
        }

        // Счётчик просмотров — вне кеша, инкремент каждый запрос
        DB::table('recipes')->where('id', $recipe->id)->increment('views_count');

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($recipe) {
            $recipe->load([
                'category:id,name,slug',
                'steps:id,recipe_id,step_number,title,description,image_url,duration_minutes',
            ]);

            $similar = Recipe::query()
                ->active()
                ->where('category_id', $recipe->category_id)
                ->whereKeyNot($recipe->id)
                ->inRandomOrder()
                ->limit(6)
                ->get(['id', 'title', 'slug', 'main_image_url', 'cook_time_minutes', 'difficulty', 'views_count']);

            return array_merge($recipe->toArray(), [
                'views_count' => $recipe->views_count + 1,
                'ingredients_list' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $recipe->ingredients)))),
                'similar' => $similar,
            ]);
        });

        return response()->json(['data' => $payload]);
    }

    /** GET /api/recipes/{id}/steps — шаги приготовления рецепта */
    public function steps(int $id): Response
    {
        $recipe = Recipe::find($id);

        if (!$recipe) {
            return response()->json(['message' => 'Рецепт не найден'], 404);
        }

        return response()->json([
            'data' => $recipe->steps()->get(['id', 'step_number', 'title', 'description', 'image_url', 'duration_minutes']),
        ]);
    }
}
