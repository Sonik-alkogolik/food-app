<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'step_number',
        'title',
        'description',
        'image_url',
        'duration_minutes',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'duration_minutes' => 'integer',
    ];

    /** Шаг принадлежит рецепту */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
