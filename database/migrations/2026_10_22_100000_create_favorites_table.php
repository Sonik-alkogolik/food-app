<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Избранное пользователей.
     *
     * Связь «пользователь — рецепт» с уникальным индексом: один и тот же
     * рецепт нельзя добавить в избранное дважды, а сам факт избранного
     * однозначно определяется парой (user_id, recipe_id).
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'recipe_id']);
            $table->index('recipe_id', 'favorites_recipe_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
