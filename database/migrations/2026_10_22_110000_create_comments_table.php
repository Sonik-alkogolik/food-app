<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Комментарии к рецептам.
     *
     * Текст комментария — обычный TEXT: в отличие от рецепта, у него нет
     * ограничения по длине из-за верстки, но есть разумный предел,
     * который проверяется валидатором в контроллере.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['recipe_id', 'created_at'], 'comments_recipe_id_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
