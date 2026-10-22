<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Создаёт администратора — пользователя с доступом к закрытым
 * эндпоинтам (загрузка изображений).
 *
 * Логин и пароль берутся из переменных окружения ADMIN_EMAIL и
 * ADMIN_PASSWORD. Если они не заданы, сидер ничего не делает: пароль
 * по умолчанию был бы одинаковым на всех установках.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            $this->command?->warn(
                'AdminUserSeeder: пропущен — задайте ADMIN_EMAIL и ADMIN_PASSWORD в .env, '
                . 'чтобы создать администратора.'
            );
            return;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            // Уже существующего администратора обновляем, а не пересоздаём:
            // вместе с ним не потеряются его токены.
            $user->update([
                'is_admin' => true,
                'password' => Hash::make($password),
            ]);
        } else {
            User::create([
                'name' => 'Администратор',
                'email' => $email,
                'password' => $password,
                'is_admin' => true,
            ]);
        }

        $this->command?->info('AdminUserSeeder: администратор ' . $email . ' готов.');
    }
}
