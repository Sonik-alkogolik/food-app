<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->integer('servings')->default(4)->after('cook_time_minutes');
            $table->string('difficulty')->default('easy')->after('servings'); // easy|medium|hard
            $table->integer('calories_per_serving')->nullable()->after('difficulty');
            $table->string('video_url')->nullable()->after('image_url');
            $table->unsignedInteger('views_count')->default(0)->after('is_active');
            $table->unsignedInteger('favorites_count')->default(0)->after('views_count');
            $table->string('created_by')->nullable()->after('favorites_count');
        });

        // переименование image_url -> main_image_url (SQLite не поддерживает rename без doctrine, делаем через SQL)
        if (Schema::hasColumn('recipes', 'image_url')) {
            DB::statement('ALTER TABLE recipes RENAME COLUMN image_url TO main_image_url');
        }

        // Индексы для ускорения поиска (ШАГ 40)
        Schema::table('recipes', function (Blueprint $table) {
            $table->index('category_id', 'recipes_category_id_idx');
            $table->index('is_active', 'recipes_is_active_idx');
            $table->fullText(['title', 'ingredients'], 'recipes_title_ingredients_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex('recipes_category_id_idx');
            $table->dropIndex('recipes_is_active_idx');
            $table->dropFullText('recipes_title_ingredients_fulltext');
        });

        if (Schema::hasColumn('recipes', 'main_image_url')) {
            DB::statement('ALTER TABLE recipes RENAME COLUMN main_image_url TO image_url');
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'servings', 'difficulty', 'calories_per_serving',
                'video_url', 'views_count', 'favorites_count', 'created_by',
            ]);
        });
    }
};
