<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Базовые продукты: соль, перец, масло, сахар, мука, вода.
     *
     * Флаг отвечает на вопрос «обязательно ли это указывать в холодильнике».
     * Соль и масло есть в любой кухне, и заставлять пользователя отмечать
     * их радикально усложняет подбор: рецепт из двух продуктов требовал
     * трёх. При подборе такие продукты не считаются недостающими —
     * если пользователь явно не попросил иначе.
     */
    public function up(): void
    {
        if (Schema::hasColumn('ingredients', 'is_pantry_staple')) {
            return;
        }

        Schema::table('ingredients', function (Blueprint $table) {
            $table->boolean('is_pantry_staple')->default(false)->after('slug');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ingredients', 'is_pantry_staple')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->dropColumn('is_pantry_staple');
            });
        }
    }
};
