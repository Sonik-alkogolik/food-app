<?php

namespace App\Services;

use App\Support\Slug;
use Illuminate\Support\Str;

class IngredientParser
{
    /**
     * Разбирает текстовое поле "Мука - 200г\nЯйца - 2 шт" на пары [name, quantity].
     * Возвращает массив ['name' => ..., 'quantity' => ..., 'slug' => ...].
     */
    public function parse(string $text): array
    {
        $result = [];
        foreach (preg_split('/[\r\n;]+/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // "Мука - 200г" / "Мука – 200 г" / "Мука — 200г"
            if (preg_match('/^(.*?)[-–—:]\s*(.+)$/u', $line, $m)) {
                $name = trim($m[1]);
                $quantity = trim($m[2]);
            } else {
                $name = $line;
                $quantity = null;
            }
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $result[] = [
                'name' => $name,
                'quantity' => $quantity ?: null,
                'slug' => Slug::make($name),
            ];
        }
        return $result;
    }
}
