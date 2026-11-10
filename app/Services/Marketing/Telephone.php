<?php

namespace App\Services\Marketing;

class Telephone
{
    /** Numéro au format international sans « + » (ex. 22670123456), ou null s'il est inutilisable. */
    public static function whatsapp(?string $numero): ?string
    {
        $c = preg_replace('/\D/', '', (string) $numero);

        if ($c === '') {
            return null;
        }

        if (str_starts_with($c, '00')) {
            $c = substr($c, 2);
        }

        if (strlen($c) === 8) {
            $c = config('marketing.indicatif', '226').$c; // numéro local burkinabè
        }

        return strlen($c) >= 10 && strlen($c) <= 15 ? $c : null;
    }
}
