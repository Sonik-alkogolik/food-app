<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Комментарии от гостей.
     *
     * Раньше комментарий мог написать только владелец аккаунта, и
     * user_id был обязательным. Теперь комментарий может оставить и
     * гость: user_id становится пустым, а автора зовут по имени —
     * guest_name.
     *
     * Имя гостя хранится рядом с текстом, а не в отдельной таблице
     * пользователей: гость не пользователь сайта, и заводить ему
     * аккаунт только ради одного комментария незачем.
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->string('guest_name', 50)->nullable()->after('user_id');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Комментарии гостей не переживут откат: пришлось бы подставлять
        // несуществующего пользователя. Поэтому сначала удаляем гостей,
        // потом уже ужесточаем колонку.
        DB::table('comments')->whereNull('user_id')->delete();

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('guest_name');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
