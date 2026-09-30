<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function cents(string $amount): int
    {
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount)) {
            throw ValidationException::withMessages(['precio' => 'Importe fuera de rango o con más de dos decimales.']);
        }
        $parts = explode('.', $amount);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        if ($cents < 0 || $cents > 999999999999) {
            throw ValidationException::withMessages(['total' => 'Importe fuera de rango.']);
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}