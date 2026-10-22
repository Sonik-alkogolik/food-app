<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Избранное пользователя.
 *
 * Все маршруты требуют токен: избранное — личное, и без авторизации
 * его негде хранить. Счётчик favorites_count в самом рецепте нужен,
 * чтобы показывать «♥ 12» в каталоге без лишнего запроса на каждую карточку.
 */
class FavoriteController extends Controller
{
    /**
     * GET /api/favorites — избранные рецепты пользователя.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $recipes = $user->favoriteRecipes()
            ->active()
            ->with('category:id,name,slug')
            ->orderByDesc('favorites.created_at')
            ->paginate(12);

        $recipes->getCollection()->transform(fn (Recipe $recipe) => $this->recipePayload($recipe, true));

        return response()->json($recipes);
    }

    /**
     * POST /api/favorites — добавить рецепт в избранное.
     *
     * Повторный вызов не создаёт дубль: операция идемпотентна.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipe_id' => ['required', 'integer', 'exists:recipes,id'],
        ], [
            'recipe_id.exists' => 'Рецепт не найден.',
        ]);

        $recipe = Recipe::query()->active()->find($data['recipe_id']);

        if (!$recipe) {
            return response()->json(['message' => 'Рецепт не найден.'], 404);
        }

        $favorite = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'recipe_id' => $recipe->id,
        ]);

        // Счётчик двигаем только при реальном добавлении, иначе повторный
        // запрос с тем же recipe_id завышал бы его.
        if ($favorite->wasRecentlyCreated) {
            $recipe->increment('favorites_count');
        }

        return response()->json([
            'message' => 'Рецепт добавлен в избранное.',
            'data' => [
                'is_favorite' => true,
                'favorites_count' => (int) $recipe->favorites_count,
            ],
        ], 201);
    }

    /**
     * DELETE /api/favorites/{recipeId} — убрать рецепт из избранного.
     */
    public function destroy(Request $request, int $recipeId): JsonResponse
    {
        $recipe = Recipe::find($recipeId);

        if (!$recipe) {
            return response()->json(['message' => 'Рецепт не найден.'], 404);
        }

        $deleted = Favorite::where('user_id', $request->user()->id)
            ->where('recipe_id', $recipeId)
            ->delete();

        if ($deleted) {
            // Счётчик не уходит в минус, даже если он разошёлся с таблицей
            // favorites (например, после ручной правки базы).
            if ($recipe->favorites_count > 0) {
                $recipe->decrement('favorites_count');
            }
        }

        return response()->json([
            'message' => 'Рецепт убран из избранного.',
            'data' => [
                'is_favorite' => false,
                'favorites_count' => (int) $recipe->favorites_count,
            ],
        ]);
    }

    /** Ответ каталога/списка избранного: те же поля, что у карточки рецепта. */
    private function recipePayload(Recipe $recipe, bool $isFavorite): array
    {
        return array_merge($recipe->toArray(), [
            'is_favorite' => $isFavorite,
        ]);
    }
}
