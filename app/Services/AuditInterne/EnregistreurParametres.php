<?php

namespace App\Services\AuditInterne;

use App\Models\Compagnie;
use App\Models\JournalParametre;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EnregistreurParametres
{
    /**
     * @param  array<string, array{libelle: string, ancienne: mixed, nouvelle: mixed}>  $valeurs
     */
    public static function enregistrer(
        string $evenement,
        string $entite,
        ?int $entiteId,
        ?int $compagnieId,
        array $valeurs,
        ?int $cibleUtilisateurId = null,
        ?string $compagnieNom = null,
    ): void {
        if ($valeurs === []) {
            return;
        }

        $acteur = Auth::user();
        $acteur = $acteur instanceof User ? $acteur : null;

        if ($compagnieNom === null && $compagnieId !== null) {
            $compagnieNom = Compagnie::query()->whereKey($compagnieId)->value('nom');
        }

        $cible = $cibleUtilisateurId
            ? User::query()->whereKey($cibleUtilisateurId)->first(['prenom', 'nom'])
            : null;

        JournalParametre::query()->create([
            'evenement' => $evenement,
            'entite' => $entite,
            'entite_id' => $entiteId,
            'compagnie_id' => $compagnieId,
            'compagnie_nom' => $compagnieNom,
            'acteur_id' => $acteur?->getKey(),
            'acteur_nom' => $acteur?->getFilamentName() ?? 'Système',
            'cible_utilisateur_id' => $cibleUtilisateurId,
            'cible_utilisateur_nom' => $cible?->getFilamentName(),
            'valeurs' => $valeurs,
            'enregistre_le' => now(),
        ]);
    }
}
