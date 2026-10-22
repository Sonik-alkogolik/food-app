<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Выполнить запрос от имени пользователя.
     *
     * Sanctum хранит найденного пользователя прямо в объекте guard'а, а в
     * тесте подряд идут несколько запросов с разными токенами. Без сброса
     * кеша все они выполнялись бы от первого пользователя, и проверки
     * личных данных (избранное, права на удаление) проходили бы неверно.
     * В продакшене такой проблемы нет: каждый запрос получает свежее
     * приложение.
     */
    protected function asUser(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('api')->plainTextToken);
    }
}
