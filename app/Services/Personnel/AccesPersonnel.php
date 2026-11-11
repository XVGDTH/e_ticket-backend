<?php

namespace App\Services\Personnel;

use App\Models\User;
use App\Services\Modules;

/** Règles d'accès du module Personnel. */
class AccesPersonnel
{
    /** Rôle « ressources humaines » : module actif pour la compagnie et rôle donnant accès au personnel. */
    private static function rh(?User $u): bool
    {
        return $u && $u->roleDonneAccesAuModule('personnel') && Modules::visiblePour($u, 'personnel');
    }

    /** Rôle RH avec droit d'écriture (l'auditeur, en lecture seule, n'en a pas). */
    private static function rhEcriture(?User $u): bool
    {
        return static::rh($u) && in_array('modifier', $u->droitsMetierPourModule('personnel'), true);
    }

    /** Peut ouvrir le module (fiches de son périmètre, pointage, demandes de congé). */
    public static function visible(?User $u): bool
    {
        return $u && ($u->isSuperAdmin() || $u->isAdminCompagnie() || $u->isAgentGare() || static::rh($u));
    }

    /** Gère les ressources humaines : fiches, contrats, congés, avances, sanctions. */
    public static function gerant(?User $u): bool
    {
        return $u && ($u->isSuperAdmin() || $u->isAdminCompagnie() || static::rhEcriture($u));
    }

    /** Consulte la paie : périodes, bulletins (imprimables) et exports. */
    public static function paie(?User $u): bool
    {
        return $u && ($u->isSuperAdmin() || $u->isAdminCompagnie() || static::rh($u));
    }

    /** Gère la paie : ouvrir une période, calculer, valider, payer, rouvrir. */
    public static function paieGestion(?User $u): bool
    {
        return $u && ($u->isSuperAdmin() || ($u->isAdminCompagnie() && ! $u->villeId()) || static::rhEcriture($u));
    }
}