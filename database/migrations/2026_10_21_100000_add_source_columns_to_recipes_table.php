<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ключ источника для рецептов, загруженных из интернета.
 *
 * Пара (source, source_id) — уникальный ключ блюда в источнике
 * (например, mealdb + 52772). Он нужен сидеру, чтобы повторный запуск
 * обновлял уже загруженный рецепт, а не создавал второй с суффиксом "-2".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('recipes', 'source')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->string('source', 32)->nullable()->after('slug');
                $table->string('source_id', 64)->nullable()->after('source');
            });
        }

        // Уникальный индекс ставим только если пара ещё не дублируется.
        $duplicates = DB::table('recipes')
            ->whereNotNull('source')
            ->whereNotNull('source_id')
            ->select('source', 'source_id')
            ->groupBy('source', 'source_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates === 0 && !Schema::hasIndex('recipes', 'recipes_source_source_id_unique')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->unique(['source', 'source_id'], 'recipes_source_source_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('recipes', 'recipes_source_source_id_unique')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->dropUnique('recipes_source_source_id_unique');
            });
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_id']);
        });
    }
};
