<?php

namespace App\Services\Guichet;

use App\Models\User;
use App\Services\Modules;

class AccesGuichet
{
    /**
     * Peut utiliser le module. Si « guichet » n'est pas déclaré dans config/modules.php,
     * le module n'est pas restreint ; une fois déclaré, il suit l'activation par compagnie et par gare.
     * Le rôle « caisse » accède aux caisses de toute sa compagnie.
     */
    public static function visible(?User $u): bool
    {
        if (! $u) {
            return false;
        }

        if ($u->roleDonneAccesAuModule('caisse') && Modules::visiblePour($u, 'caisse')) {
            return true;
        }

        if (! ($u->isSuperAdmin() || $u->isAdminCompagnie() || $u->isAgentGare())) {
            return false;
        }

        if (! isset(Modules::catalogue()['guichet'])) {
            return true;
        }

        return Modules::visiblePour($u, 'guichet');
    }

    /** Gère : valide les caisses, approuve les remboursements, remises sans limite, rapports de toute la zone. */
    public static function gerant(?User $u): bool
    {
        return $u && static::visible($u) && ($u->isSuperAdmin() || $u->isAdminCompagnie());
    }
}