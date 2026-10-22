<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Избранный рецепт пользователя.
 *
 * Строка в таблице favorites — факт «пользователь отметил рецепт».
 * Сами рецепты лежат в recipes, счётчик — в recipes.favorites_count.
 */
class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipe_id',
    ];

    /** Рецепт, который добавили в избранное. */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** Пользователь, которому принадлежит избранное. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
