<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Фотоотчёты: как блюдо выглядит в реальности.
     *
     * Отчёт может оставить и гость — тогда user_id пустой, а автора зовут
     * по имени (guest_name). Файл лежит в storage/app/public/results,
     * путь к нему — в image_path.
     */
    public function up(): void
    {
        Schema::create('recipe_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 50)->nullable();
            $table->text('comment')->nullable();
            $table->string('image_path');
            $table->timestamps();

            $table->index(['recipe_id', 'created_at'], 'recipe_results_recipe_id_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_results');
    }
};
