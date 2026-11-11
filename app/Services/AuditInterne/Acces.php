<?php

namespace App\Services\AuditInterne;

use App\Models\User;
use App\Services\Modules;

class Acces
{
    public static function visible(?User $utilisateur): bool
    {
        return $utilisateur
            && Modules::visiblePour($utilisateur, 'audit_interne')
            && ($utilisateur->isSuperAdmin()
                || ($utilisateur->isAdminCompagnie() && ! $utilisateur->villeId())
                || $utilisateur->roleDonneAccesAuModule('audit_interne'));
    }
}