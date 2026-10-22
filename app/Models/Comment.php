<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Комментарий пользователя к рецепту.
 */
class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipe_id',
        'body',
    ];

    /** Рецепт, к которому написан комментарий. */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** Автор комментария. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Может ли пользователь удалить этот комментарий.
     *
     * Удалять может автор или администратор — иначе любой прохожий
     * стирал бы чужие мнения.
     */
    public function canBeDeletedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->id === $this->user_id || (bool) $user->is_admin;
    }
}
