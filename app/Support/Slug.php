<?php

namespace App\Support;

class Slug
{
    private const TRANSLIT = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
        'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];

    public static function make(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $out = '';
        foreach (mb_str_split($text) as $ch) {
            if (isset(self::TRANSLIT[$ch])) {
                $out .= self::TRANSLIT[$ch];
            } elseif (preg_match('/[a-z0-9]/', $ch)) {
                $out .= $ch;
            } else {
                $out .= '-';
            }
        }
        return trim(preg_replace('/-+/', '-', $out), '-');
    }
}
