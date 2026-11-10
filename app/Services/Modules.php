<?php

namespace App\Services;

use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\User;
use App\Services\AuditInterne\EnregistreurParametres;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class Modules
{
    private static array $cacheCompagnies = [];

    private static array $cacheGares = [];

    public static function catalogue(): array
    {
        return config('modules.catalogue', []);
    }

    public static function oublier(): void
    {
        self::$cacheCompagnies = [];
        self::$cacheGares = [];
    }

    /** @return array<int,string> */
    public static function activesCompagnie(int $compagnieId): array
    {
        return self::$cacheCompagnies[$compagnieId] ??= DB::table('modules_compagnies')
            ->where('compagnie_id', $compagnieId)->where('actif', true)->pluck('module')->all();
    }

    /** @return array<int,string> */
    public static function activesGare(int $gareId): array
    {
        return self::$cacheGares[$gareId] ??= DB::table('modules_gares')
            ->where('gare_id', $gareId)->where('actif', true)->pluck('module')->all();
    }

    /** Gares où ce rôle métier travaille ET où le module est attribué. */
public static function garesAccessibles(User $user, string $module): Collection
{
    return $user->gareIdsPourModule($module)
        ->filter(fn ($g) => in_array($module, self::activesGare((int) $g), true))
        ->values();
}

    /** Le module est-il visible pour cet utilisateur ? À utiliser dans chaque ressource et page de module. */
    public static function visiblePour(?User $user, string $module): bool
    {
        if (! $user || ! isset(self::catalogue()[$module])) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $compagnieId = $user->compagnieId();

        if (! $compagnieId || ! in_array($module, self::activesCompagnie($compagnieId), true)) {
            return false;
        }

        if ($user->isAdminCompagnie()) {
            return true;
        }

        if ($user->isAgentGare()) {
            $gareId = $user->gareId();

            if ($gareId && in_array($module, self::activesGare($gareId), true)) {
                return true;
            }
        }

        // Rôles métiers : le rôle doit donner accès au module, et une de ses gares doit l'avoir reçu.
        // return $user->roleDonneAccesAuModule($module)
        //     && self::garesAccessibles($user, $module)->isNotEmpty();
                // Rôles métiers : le module est déjà actif pour la compagnie (vérifié plus haut),
        // le rôle donne accès au module sur toutes les gares de la compagnie.
        return $user->roleDonneAccesAuModule($module);
    }

    public static function definirCompagnie(int $compagnieId, string $module, bool $actif): void
    {
        DB::transaction(function () use ($compagnieId, $module, $actif): void {
            $compagnie = Compagnie::query()->findOrFail($compagnieId);
            $actuelle = DB::table('modules_compagnies')
                ->where('compagnie_id', $compagnieId)
                ->where('module', $module)
                ->first();

            if ($actuelle && (bool) $actuelle->actif === $actif) {
                return;
            }

            DB::table('modules_compagnies')->upsert(
                [['compagnie_id' => $compagnieId, 'module' => $module, 'actif' => $actif, 'created_at' => now(), 'updated_at' => now()]],
                ['compagnie_id', 'module'],
                ['actif', 'updated_at']
            );

            $id = DB::table('modules_compagnies')
                ->where('compagnie_id', $compagnieId)
                ->where('module', $module)
                ->value('id');

            EnregistreurParametres::enregistrer(
                evenement: $actuelle ? 'modification' : 'creation',
                entite: 'modules_compagnies',
                entiteId: $id ? (int) $id : null,
                compagnieId: $compagnieId,
                valeurs: [
                    'module' => ['libelle' => 'Module', 'ancienne' => $actuelle?->module, 'nouvelle' => $module],
                    'actif' => ['libelle' => 'Activation', 'ancienne' => $actuelle ? (bool) $actuelle->actif : null, 'nouvelle' => $actif],
                ],
                compagnieNom: $compagnie->nom,
            );
        });

        self::oublier();
    }

    public static function definirGare(int $gareId, string $module, bool $actif): void
    {
        DB::transaction(function () use ($gareId, $module, $actif): void {
            $gare = Gare::query()->with('compagnie')->findOrFail($gareId);
            $actuelle = DB::table('modules_gares')
                ->where('gare_id', $gareId)
                ->where('module', $module)
                ->first();

            if ($actuelle && (bool) $actuelle->actif === $actif) {
                return;
            }

            DB::table('modules_gares')->upsert(
                [['gare_id' => $gareId, 'module' => $module, 'actif' => $actif, 'created_at' => now(), 'updated_at' => now()]],
                ['gare_id', 'module'],
                ['actif', 'updated_at']
            );

            $id = DB::table('modules_gares')
                ->where('gare_id', $gareId)
                ->where('module', $module)
                ->value('id');

            EnregistreurParametres::enregistrer(
                evenement: $actuelle ? 'modification' : 'creation',
                entite: 'modules_gares',
                entiteId: $id ? (int) $id : null,
                compagnieId: $gare->compagnie_id ? (int) $gare->compagnie_id : null,
                valeurs: [
                    'module' => ['libelle' => 'Module', 'ancienne' => $actuelle?->module, 'nouvelle' => $module],
                    'actif' => ['libelle' => 'Activation', 'ancienne' => $actuelle ? (bool) $actuelle->actif : null, 'nouvelle' => $actif],
                ],
                compagnieNom: $gare->compagnie?->nom,
            );
        });

        self::oublier();
    }
}
