<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Support\Collection;
use App\Support\Slug;

class FridgeMatcher
{
    /**
     * Подбирает рецепты по списку ингредиентов пользователя.
     *
     * @param  array<int,string> $fridgeNames
     * @return Collection<int,array>
     */
    public function match(array $fridgeNames): Collection
    {
        $fridgeSlugs = collect($fridgeNames)
            ->map(fn ($n) => Slug::make((string) $n))
            ->filter()
            ->unique()
            ->values();

        if ($fridgeSlugs->isEmpty()) {
            return collect();
        }

        // Хеш для O(1)-проверки «есть ли этот ингредиент в холодильнике».
        $fridgeSet = array_fill_keys($fridgeSlugs->all(), true);

        // Отбираем рецепты прямо в БД: берём только те, где есть хотя бы один
        // совпадающий ингредиент — иначе тянем в память все рецепты целиком.
        $recipes = Recipe::query()
            ->where('is_active', true)
            ->with(['category:id,name,slug', 'ingredientsRelation'])
            ->whereHas('ingredientsRelation', fn ($q) => $q->whereIn('ingredients.slug', $fridgeSlugs))
            ->get();

        return $recipes
            ->map(function (Recipe $recipe) use ($fridgeSet) {
                // Реляция называется ingredientsRelation, т.к. атрибут recipes.ingredients
                // (TEXT) уже занят человекочитаемым списком.
                $needed = $recipe->ingredientsRelation;
                $total = $needed->count();

                if ($total === 0) {
                    return null;
                }

                $matched = $needed->filter(fn (Ingredient $i) => isset($fridgeSet[$i->slug]));
                $missing = $needed->reject(fn (Ingredient $i) => isset($fridgeSet[$i->slug]));
                $percent = (int) round($matched->count() / $total * 100);

                return [
                    'id' => $recipe->id,
                    'title' => $recipe->title,
                    'slug' => $recipe->slug,
                    'category' => $recipe->category?->name,
                    'cook_time_minutes' => $recipe->cook_time_minutes,
                    'instructions' => $recipe->instructions,
                    'main_image_url' => $recipe->main_image_url,
                    'match_percent' => $percent,
                    'can_cook' => $missing->isEmpty(),
                    'matched_ingredients' => $matched->pluck('name')->values(),
                    'missing_ingredients' => $missing->map(fn (Ingredient $i) => [
                        'name' => $i->name,
                        'quantity' => $i->pivot->quantity,
                    ])->values(),
                    'all_ingredients' => $needed->map(fn (Ingredient $i) => [
                        'name' => $i->name,
                        'quantity' => $i->pivot->quantity,
                    ])->values(),
                ];
            })
            ->filter()
            ->sortBy([
                ['match_percent', 'desc'],
                ['cook_time_minutes', 'asc'],
            ])
            ->take(20)
            ->values();
    }
}
