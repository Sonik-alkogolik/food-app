<?php

namespace App\Support;

/**
 * Чистка текста, пришедшего от клиента.
 *
 * Браузер шлёт валидный UTF-8, но клиент может прислать и мусорные байты:
 * Windows-1251 вместо UTF-8, обрезанный многобайтовый символ. Такая строка
 * ломает json_encode — и вместо ответа об ошибке приложение падало бы с 500.
 *
 * Поэтому текстовые поля чистим до проверки и сохранения.
 */
class Utf8
{
    /**
     * Заменить невалидные байты, оставив строку в UTF-8.
     *
     * Валидная строка возвращается как есть — ничего не меняется.
     */
    public static function clean(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
