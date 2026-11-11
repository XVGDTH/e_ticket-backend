<?php

namespace App\Services\ControleGestion;

use App\Models\User;
use App\Services\Modules;

class Acces
{
    /** Module actif et rôle de gestion : super admin, admin de compagnie ou rôle métier du module. */
    public static function visible(?User $u): bool
    {
        return $u
            && Modules::visiblePour($u, 'controle_gestion')
            && ($u->isSuperAdmin() || $u->isAdminCompagnie() || $u->roleDonneAccesAuModule('controle_gestion'));
    }

    /** Règle les paramètres et les charges de siège : super admin, admin sans ville, ou rôle métier avec droit d'écriture. */
    public static function finance(?User $u): bool
    {
        return $u
            && Modules::visiblePour($u, 'controle_gestion')
            && ($u->isSuperAdmin()
                || ($u->isAdminCompagnie() && ! $u->villeId())
                || in_array('modifier', $u->droitsMetierPourModule('controle_gestion'), true));
    }
}