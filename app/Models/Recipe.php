<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'source',
        'source_id',
        'description',
        'ingredients',
        'instructions',
        'cook_time_minutes',
        'servings',
        'difficulty',
        'calories_per_serving',
        'main_image_url',
        'video_url',
        'is_active',
        'views_count',
        'favorites_count',
        'comments_count',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cook_time_minutes' => 'integer',
        'servings' => 'integer',
        'calories_per_serving' => 'integer',
        'views_count' => 'integer',
        'favorites_count' => 'integer',
        'comments_count' => 'integer',
    ];

    /** Рецепт принадлежит категории */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Один рецепт имеет много шагов приготовления (отсортированных по step_number) */
    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('step_number');
    }

    /**
     * Структурированные ингредиенты (pivot ingredient_recipe).
     * Отношение названо ingredientsRelation, т.к. атрибут recipes.ingredients (TEXT) занят.
     */
    public function ingredientsRelation(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_recipe')->withPivot('quantity');
    }

    /** Отметки «в избранном» у этого рецепта. */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** Пользователи, у которых рецепт в избранном. */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /** Комментарии к рецепту. */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** Фотоотчёты: как блюдо выглядит в реальности. */
    public function results(): HasMany
    {
        return $this->hasMany(RecipeResult::class);
    }

    /** Scope: только активные рецепты */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Ключ кеша детальной карточки рецепта.
     *
     * Карточка ищется и по slug, и по id — ключ должен совпадать
     * с тем, что использует RecipeDetailController.
     */
    public static function detailCacheKey(int|string $idOrSlug): string
    {
        return ctype_digit((string) $idOrSlug)
            ? 'api.recipe.detail.id-' . $idOrSlug
            : 'api.recipe.detail.slug-' . $idOrSlug;
    }

    /**
     * Сбрасываем кеш детальной карточки при изменении рецепта.
     *
     * Иначе правка рецепта (из админки или сидером) оставалась бы незамеченной
     * до истечения 10-минутного TTL кеша.
     */
    protected static function booted(): void
    {
        $forget = function (Recipe $recipe): void {
            Cache::forget(self::detailCacheKey($recipe->id));
            Cache::forget(self::detailCacheKey($recipe->slug));
        };

        static::saved($forget);
        static::deleted($forget);
    }
}
