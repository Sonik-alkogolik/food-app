<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Помощник для контроллеров, которые отдают список рецептов.
 *
 * Отметка «в избранном» зависит от того, кто спрашивает, поэтому её
 * нельзя положить в общий кеш карточки: кеш общий для всех, а избранное
 * — личное. Считаем её отдельно, одним запросом на страницу списка.
 */
trait ResolvesFavorites
{
    /**
     * Id рецептов, отмеченных текущим пользователем.
     *
     * @param  Collection<int, int>|array<int, int>  $recipeIds
     * @return array<int, int>
     */
    protected function favoriteIds(Request $request, Collection|array $recipeIds): array
    {
        $ids = $recipeIds instanceof Collection ? $recipeIds->all() : $recipeIds;

        if (!$request->user() || $ids === []) {
            return [];
        }

        return Favorite::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('recipe_id', $ids)
            ->pluck('recipe_id')
            ->all();
    }
}
