<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('step_number');       // порядковый номер шага, начиная с 1
            $table->string('title');                      // "Подготовка ингредиентов"
            $table->text('description');                  // подробное описание действия
            $table->string('image_url')->nullable();      // фотография шага
            $table->unsignedInteger('duration_minutes')->default(5);
            $table->timestamps();

            $table->unique(['recipe_id', 'step_number']);
            $table->index('recipe_id', 'recipe_steps_recipe_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_steps');
    }
};
