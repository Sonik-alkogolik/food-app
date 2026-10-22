<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Счётчик комментариев у рецепта.
     *
     * Держим его в самой строке рецепта, а не считаем на лету: список
     * каталога показывает число комментариев у каждого рецепта, и COUNT
     * по каждой строке каталога — лишний запрос на каждую карточку.
     */
    public function up(): void
    {
        if (Schema::hasColumn('recipes', 'comments_count')) {
            return;
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->unsignedInteger('comments_count')->default(0)->after('favorites_count');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('recipes', 'comments_count')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->dropColumn('comments_count');
            });
        }
    }
};
