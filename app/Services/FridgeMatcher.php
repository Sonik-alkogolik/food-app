<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Support\Collection;
use App\Support\Slug;

class FridgeMatcher
{
    /**
     * Уточняющие слова: меняют продукт, поэтому «молоко» и «Кокосовое
     * молоко» — разные вещи, а «масло» и «Масло сливочное» — тоже.
     */
    private const QUALIFIERS = [
        'kokosovyy', 'kokosovoe', 'kokosovaya', 'soevoe', 'soyevoe',
        'rastitelnoe', 'rastitelnyy', 'slivochnoe', 'slivochnyy', 'slivochnaya',
        'olivkovoe', 'podsolnechnoe', 'nezhirnoe', 'bezhlirnoe',
        'mangovyy', 'apelsinovyy', 'myasnoy', 'rybnoy', 'ovsyanoe',
    ];

    /**
     * Подбирает рецепты по списку ингредиентов пользователя.
     *
     * @param  array<int,string> $fridgeNames
     * @param  bool  $includePantryStaples  Считать ли базовые продукты
     *                                       (соль, перец, масло, сахар, мука,
     *                                       вода) недостающими. По умолчанию
     *                                       нет: они есть в любой кухне.
     * @return Collection<int,array>
     */
    public function match(array $fridgeNames, bool $includePantryStaples = false): Collection
    {
        $fridgeSlugs = collect($fridgeNames)
            ->map(fn ($n) => Slug::make((string) $n))
            ->filter()
            ->unique()
            ->values();

        if ($fridgeSlugs->isEmpty()) {
            return collect();
        }

        // Продукты пользователя + их варианты из рецептов: набрав «лук»,
        // человек ожидает увидеть и «Лук репчатый», а не «лук» отдельно
        // от «Лук репчатый».
        $matchSlugs = $fridgeSlugs
            ->merge($this->variantSlugs($fridgeSlugs))
            ->unique()
            ->values();

        // Хеш для O(1)-проверки «есть ли этот ингредиент в холодильнике».
        $fridgeSet = array_fill_keys($matchSlugs->all(), true);

        // Отбираем рецепты прямо в БД: берём только те, где есть хотя бы один
        // совпадающий ингредиент — иначе тянем в память все рецепты целиком.
        $recipes = Recipe::query()
            ->where('is_active', true)
            ->with(['category:id,name,slug', 'ingredientsRelation'])
            ->whereHas('ingredientsRelation', fn ($q) => $q->whereIn('ingredients.slug', $matchSlugs))
            ->get();

        return $recipes
            ->map(function (Recipe $recipe) use ($fridgeSet, $includePantryStaples) {
                // Реляция называется ingredientsRelation, т.к. атрибут recipes.ingredients
                // (TEXT) уже занят человекочитаемым списком.
                $all = $recipe->ingredientsRelation;

                // Базовые продукты (соль, масло, вода…) по умолчанию выпадают
                // из расчёта: они есть в любой кухне, и требовать их отметить
                // значит занижать совпадение у всех рецептов подряд.
                $staples = $all->filter(fn (Ingredient $i) => (bool) $i->is_pantry_staple);
                $needed = $includePantryStaples
                    ? $all
                    : $all->reject(fn (Ingredient $i) => (bool) $i->is_pantry_staple);

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
                    // Базовые продукты, которые не учитывались в расчёте:
                    // интерфейс показывает их отдельно.
                    'ignored_pantry_staples' => $includePantryStaples
                        ? []
                        : $staples->pluck('name')->values(),
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

    /**
     * Ищет варианты продуктов: «лук» -> «Лук репчатый», «Красный лук»,
     * «масло» -> «Масло сливочное», «Оливковое масло».
     *
     * В базе названия записаны по-разному, а пользователь набирает продукт
     * одним словом. Без этого поиска «лук» в холодильнике не находился
     * в рецептах, где он назван «Лук репчатый».
     *
     * @param  \Illuminate\Support\Collection<int,string> $fridgeSlugs
     * @return \Illuminate\Support\Collection<int,string>
     */
    private function variantSlugs(Collection $fridgeSlugs): Collection
    {
        $slugs = Ingredient::query()
            ->pluck('slug')
            ->filter()
            ->unique();

        $found = collect();

        foreach ($fridgeSlugs as $fridgeSlug) {
            $fridgeStem = $this->stem($fridgeSlug);

            foreach ($slugs as $slug) {
                if ($slug === $fridgeSlug) {
                    continue;
                }

                if ($this->isVariantOf($slug, $fridgeStem)) {
                    $found->push($slug);
                }
            }
        }

        return $found->unique()->values();
    }

    /**
     * Считает ли название продукта вариантом слова из холодильника.
     *
     * Название может состоять из нескольких слов: «Масло подсолнечное,
     * лук, соль». Совпадение ищем по основе слова, но уточняющие слова
     * («кокосовое», «сливочное») меняют продукт, поэтому такое название
     * считаем другим продуктом.
     */
    private function isVariantOf(string $slug, string $fridgeStem): bool
    {
        $words = explode('-', $slug);

        foreach ($words as $word) {
            if (in_array($word, self::QUALIFIERS, true)) {
                // Уточняющее слово есть — это другой продукт.
                return false;
            }
        }

        foreach ($words as $word) {
            if ($this->stem($word) === $fridgeStem) {
                return true;
            }
        }

        return false;
    }

    /**
     * Грубая основа русского слова: отрезаем типичные окончания.
     *
     * Основа считается по транслитерации (slug), поэтому сравнение
     * одинаково работает и для кириллицы, и для латиницы.
     */
    private function stem(string $word): string
    {
        static $endings = [
            'ami', 'yami', 'ov', 'ev', 'iy', 'yy', 'ie', 'aya', 'ye', 'oy', 'em', 'om',
            'akh', 'yakh', 'y', 'i', 'a', 'o', 'ya', 'e', 'yu', 'u',
        ];

        foreach ($endings as $ending) {
            if (mb_strlen($word) <= mb_strlen($ending)) {
                continue;
            }

            $rest = mb_substr($word, 0, mb_strlen($word) - mb_strlen($ending));

            if (mb_strlen($rest) >= 3 && str_ends_with($word, $ending)) {
                return $rest;
            }
        }

        return $word;
    }
}
