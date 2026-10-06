<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Support\Collection;
use App\Support\Slug;
use Illuminate\Support\Str;

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

        $recipes = Recipe::query()
            ->where('is_active', true)
            ->with(['category', 'ingredients'])
            ->get();

        return $recipes
            ->map(function (Recipe $recipe) use ($fridgeSlugs) {
                // $recipe->ingredients — атрибут (string), relation — это getRelationValue('ingredients')
                $needed = $recipe->getRelation('ingredients');
                $total = $needed->count();

                if ($total === 0) {
                    return null;
                }

                $matched = $needed->filter(fn (Ingredient $i) => $fridgeSlugs->contains($i->slug));
                $missing = $needed->reject(fn (Ingredient $i) => $fridgeSlugs->contains($i->slug));
                $percent = (int) round($matched->count() / $total * 100);

                return [
                    'id' => $recipe->id,
                    'title' => $recipe->title,
                    'slug' => $recipe->slug,
                    'category' => $recipe->category?->name,
                    'cook_time_minutes' => $recipe->cook_time_minutes,
                    'instructions' => $recipe->instructions,
                    'image_url' => $recipe->image_url,
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
            // показываем только те, где совпал хотя бы один ингредиент
            ->when($fridgeSlugs->isNotEmpty(), fn ($c) => $c->where('match_percent', '>', 0))
            ->sortBy([
                ['match_percent', 'desc'],
                ['cook_time_minutes', 'asc'],
            ])
            ->take(20)
            ->values();
    }
}
