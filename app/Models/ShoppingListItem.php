<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Позиция списка покупок: что купить, сколько и в какой единице.
 */
class ShoppingListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shopping_list_id',
        'recipe_id',
        'name',
        'amount',
        'unit',
        'checked',
        'position',
    ];

    protected $casts = [
        'amount' => 'float',
        'checked' => 'boolean',
        'position' => 'integer',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'shopping_list_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** «200 г» для показа; без количества — только название. */
    public function amountLabel(): string
    {
        if ($this->amount === null) {
            return (string) $this->name;
        }

        $amount = rtrim(rtrim(number_format($this->amount, 2, ',', ''), '0'), ',');
        $amount = $amount === '' ? '0' : $amount;

        return trim($amount . ' ' . (string) $this->unit);
    }
}
