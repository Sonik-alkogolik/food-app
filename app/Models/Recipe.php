<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
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
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cook_time_minutes' => 'integer',
        'servings' => 'integer',
        'calories_per_serving' => 'integer',
        'views_count' => 'integer',
        'favorites_count' => 'integer',
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

    /** Scope: только активные рецепты */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
