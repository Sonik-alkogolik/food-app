<?php

namespace App\Support;

/**
 * Разбор строки ингредиента на название, количество и единицу измерения.
 *
 * Формат у рецептов разный: «Мука — 200 г», «Мука - 200г», «200 г муки»,
 * «1/2 стакана молока», «Соль, перец — по вкусу». Из этого нужен
 * список покупок: «200 г муки» и «Мука — 200 г» должны превратиться
 * в одну позицию «Мука, 200, г».
 */
class IngredientParser
{
    /** «Ингредиент — 200 г»: количество после разделителя. */
    private const PATTERN_SEPARATOR = '/^(.*?)\s*[—–-]\s*(\d+\/\d+|\d+(?:[.,]\d+)?(?:\s+\d+\/\d+)?)\s*(.*)$/u';

    /** «200 г муки»: количество в начале строки. */
    private const PATTERN_LEADING = '/^(\d+\/\d+|\d+(?:[.,]\d+)?(?:\s+\d+\/\d+)?)\s*(.*)$/u';

    /**
     * @return array{name: string, amount: float|null, unit: string|null}
     */
    public static function parse(string $line): array
    {
        $text = trim($line);

        if ($text === '') {
            return ['name' => '', 'amount' => null, 'unit' => null];
        }

        if (preg_match(self::PATTERN_SEPARATOR, $text, $matches) === 1) {
            $amount = self::toNumber($matches[2]);

            if ($amount !== null) {
                $rest = trim($matches[3]);
                $name = trim(preg_replace('/\s*[—–-]\s*$/u', '', trim($matches[1])));

                return [
                    'name' => $name !== '' ? $name : ($rest !== '' ? $rest : $text),
                    'amount' => $amount,
                    'unit' => self::firstToken($rest),
                ];
            }
        }

        if (preg_match(self::PATTERN_LEADING, $text, $matches) === 1) {
            $amount = self::toNumber($matches[1]);

            if ($amount !== null) {
                $rest = trim($matches[2]);
                $unit = self::firstToken($rest);
                $name = $unit !== null ? trim(substr($rest, strlen($unit))) : $rest;

                return [
                    'name' => $name !== '' ? $name : $text,
                    'amount' => $amount,
                    'unit' => $unit,
                ];
            }
        }

        // Количества нет («по вкусу», «несколько веточек») — позиция без него.
        return ['name' => $text, 'amount' => null, 'unit' => null];
    }

    /**
     * Разбирает список строк ингредиентов.
     *
     * @param  array<int, string>  $lines
     * @return array<int, array{name: string, amount: float|null, unit: string|null}>
     */
    public static function parseMany(array $lines): array
    {
        $result = [];

        foreach ($lines as $line) {
            $parsed = self::parse((string) $line);

            if ($parsed['name'] === '') {
                continue;
            }

            $result[] = $parsed;
        }

        return $result;
    }

    /**
     * «1/2», «1 1/2», «1,5» → число. null, если разобрать нельзя.
     */
    public static function toNumber(string $raw): ?float
    {
        $text = trim(str_replace(',', '.', $raw));

        if ($text === '') {
            return null;
        }

        $total = 0.0;

        foreach (preg_split('/\s+/u', $text) ?: [] as $part) {
            if ($part === '') {
                continue;
            }

            if (str_contains($part, '/')) {
                $parts = explode('/', $part, 2);
                $num = $parts[0];
                $den = $parts[1] ?? '0';

                if (! is_numeric($num) || ! is_numeric($den) || (float) $den === 0.0) {
                    return null;
                }

                $total += (float) $num / (float) $den;
            } else {
                if (! is_numeric($part)) {
                    return null;
                }

                $total += (float) $part;
            }
        }

        return round($total, 2);
    }

    /** Первое слово строки: «200 г муки» → «г». */
    private static function firstToken(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $parts = preg_split('/\s+/u', trim($text)) ?: [];

        return $parts[0] ?? null;
    }
}
