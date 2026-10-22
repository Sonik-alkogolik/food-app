<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Комментарий к рецепту.
 *
 * Написать комментарий может и гость: тогда user_id пустой, а автора
 * зовут по имени из guest_name.
 */
class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_name',
        'recipe_id',
        'body',
    ];

    /** Рецепт, к которому написан комментарий. */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** Автор комментария. У гостя его нет. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Написал ли комментарий гость. */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Как подписать автора.
     *
     * У зарегистрированного пользователя есть имя в профиле, у гостя —
     * в самом комментарии. Если имени нет (старые записи, например),
     * показываем «Аноним».
     */
    public function authorName(): string
    {
        return $this->user?->name
            ?: ($this->guest_name ?: 'Аноним');
    }
}
