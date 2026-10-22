<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesFavorites;
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
    use ResolvesFavorites;

    public function index(Request $request): Response
    {
        $query = Recipe::query()->active()->with('category:id,name,slug');

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        } elseif ($categorySlug = $request->string('category')->toString()) {
            // Категорию можно указать и slug'ом — так удобнее строить
            // ссылки вида /api/recipes?category=supy.
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if ($difficulty = $request->string('difficulty')->toString()) {
            $difficulties = explode(',', $difficulty); // поддержка чекбоксов: easy,medium,hard
            $difficulties = array_values(array_intersect($difficulties, ['easy', 'medium', 'hard']));

            // Пустой результат означает, что пришли только неизвестные
            // значения («difficulty=bogus»). Такой фильтр игнорируем —
            // иначе каталог молча отсекался бы целиком и отдавал 0 рецептов.
            if ($difficulties !== []) {
                $query->whereIn('difficulty', $difficulties);
            }
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

        // Размер страницы ограничен: без предела клиент мог бы запросить
        // сразу всю базу рецептов.
        $perPage = min(max($request->integer('per_page') ?: 12, 1), 48);

        $recipes = $query->paginate($perPage)->appends($request->query());

        // Отметка «в избранном» — личная, поэтому считается отдельно
        // и не кладётся в общий кеш.
        $favoriteIds = $this->favoriteIds($request, $recipes->getCollection()->pluck('id'));
        $recipes->getCollection()->transform(
            fn (Recipe $recipe) => $recipe->setAttribute('is_favorite', in_array($recipe->id, $favoriteIds, true))
        );

        return response()->json($recipes);
    }
}
