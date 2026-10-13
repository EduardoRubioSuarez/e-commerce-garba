<?php

namespace App\Support;

final class Money
{
    /** Formatea centavos como $1,234.56 (sin la moneda; las vistas la agregan aparte). */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.'$'.number_format(intdiv($absolute, 100), 0, '.', ',').'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
