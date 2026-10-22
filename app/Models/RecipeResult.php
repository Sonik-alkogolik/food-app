<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Фотоотчёт: как блюдо выглядит в реальности.
 *
 * Оставить может и гость — тогда user_id пустой, а автора зовут по имени
 * из guest_name. Файл лежит в storage/app/public/results, путь к нему
 * в image_path.
 */
class RecipeResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'user_id',
        'guest_name',
        'comment',
        'image_path',
    ];

    /** Рецепт, к которому относится фотоотчёт. */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** Автор фотоотчёта. У гостя его нет. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Оставил ли фотоотчёт гость. */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Как подписать автора.
     *
     * У зарегистрированного пользователя есть имя в профиле, у гостя —
     * в самом отчёте. Если имени нет (старые записи), показываем «Аноним».
     */
    public function authorName(): string
    {
        return $this->user?->name
            ?: ($this->guest_name ?: 'Аноним');
    }

    /**
     * Публичная ссылка на фотографию.
     *
     * Файлы лежат на диске public (storage/app/public), поэтому
     * отдаются через /storage/... — как и остальные загруженные картинки.
     */
    public function imageUrl(): string
    {
        return '/storage/'.ltrim($this->image_path, '/');
    }
}
