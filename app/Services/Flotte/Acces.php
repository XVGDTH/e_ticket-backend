<?php

namespace App\Services\Flotte;

use App\Models\User;
use App\Services\Modules;

class Acces
{
    /** Le module est actif pour la compagnie (et la gare de l'agent) et l'utilisateur a un rôle de gestion. */
    public static function visible(?User $u): bool
    {
        return $u
            && Modules::visiblePour($u, 'flotte')
            && ($u->isSuperAdmin() || $u->isAdminCompagnie() || $u->isAgentGare() || $u->roleDonneAccesAuModule('flotte'));
    }

    /** Peut créer et modifier cars et chauffeurs. */
    public static function gerant(?User $u): bool
    {
        return $u && static::visible($u)
            && ($u->isSuperAdmin()
                || $u->isAdminCompagnie()
                || in_array('modifier', $u->droitsMetierPourModule('flotte'), true));
    }

    /** Voit les prix d'achat, les salaires et la rentabilité. */
    public static function finance(?User $u): bool
    {
        return $u
            && Modules::visiblePour($u, 'flotte')
            && ($u->isSuperAdmin()
                || ($u->isAdminCompagnie() && ! $u->villeId())
                || in_array('modifier', $u->droitsMetierPourModule('flotte'), true));
    }
}