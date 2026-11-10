<?php

namespace App\Services\ControleGestion;

use App\Models\User;
use App\Services\Modules;

class Acces
{
    /** Module actif et rôle de gestion : super admin, ou admin de compagnie (limité à sa ville s'il en a une). */
    public static function visible(?User $u): bool
    {
        return $u
            && Modules::visiblePour($u, 'controle_gestion')
            && ($u->isSuperAdmin() || $u->isAdminCompagnie());
    }

    /** Règle les paramètres et les charges de siège : super admin, ou admin de compagnie non limité à une ville. */
    public static function finance(?User $u): bool
    {
        return $u
            && Modules::visiblePour($u, 'controle_gestion')
            && ($u->isSuperAdmin() || ($u->isAdminCompagnie() && ! $u->villeId()));
    }
}
