<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Позиции списка покупок.
     *
     * Количество и единица измерения хранятся отдельно от названия:
     * «Мука» + 200 + «г» — так список можно и распечатать, и посчитать.
     * recipe_id помогает понять, из какого рецепта взялась позиция
     * (при удалении рецепта позиция остаётся, а ссылка обнуляется).
     */
    public function up(): void
    {
        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->boolean('checked')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['shopping_list_id', 'position'], 'shopping_list_items_list_id_position_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
    }
};
