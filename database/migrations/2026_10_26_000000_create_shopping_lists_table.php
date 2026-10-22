<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Списки покупок.
     *
     * Список принадлежит пользователю: у каждого свой, и чужие не видно.
     * Позиции лежат отдельной таблицей (shopping_list_items), чтобы у
     * каждой позиции можно было поставить галочку «куплено».
     */
    public function up(): void
    {
        Schema::create('shopping_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'shopping_lists_user_id_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_lists');
    }
};
