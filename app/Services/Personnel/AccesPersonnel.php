<?php

namespace App\Services\Personnel;

use App\Models\User;

/** Règles d'accès du module Personnel. */
class AccesPersonnel
{
    /** Peut ouvrir le module (fiches de son périmètre, pointage, demandes de congé). */
    public static function visible(?User $u): bool
    {
        return $u && ($u->isSuperAdmin() || $u->isAdminCompagnie() || $u->isAgentGare());
    }

    /** Gère les ressources humaines : fiches, contrats, congés, avances, sanctions. */
    public static function gerant(?User $u): bool
    {
        return $u && ($u->isSuperAdmin() || $u->isAdminCompagnie());
    }

    /**
     * Consulte la paie : périodes, bulletins (imprimables) et exports.
     * L'admin de compagnie rattaché à une ville ne voit que les bulletins des employés de ses gares.
     */
    public static function paie(?User $u): bool
    {
        return $u && ($u->isSuperAdmin() || $u->isAdminCompagnie());
    }

    /**
     * Gère la paie : ouvrir une période, calculer, valider, payer, rouvrir.
     * Ces actions portent sur toute la compagnie : elles sont réservées au super admin
     * et à l'admin de compagnie sans ville.
     */
    public static function paieGestion(?User $u): bool
    {
        return $u && ($u->isSuperAdmin() || ($u->isAdminCompagnie() && ! $u->villeId()));
    }
}
