<?php

namespace App\Services\Maintenance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DiagnosticSysteme
{
    /**
     * @return array{
     *     resume: array{statut: string, libelle: string},
     *     runtime: array<int, array{label: string, valeur: string, statut: string}>,
     *     controles: array<int, array{label: string, statut: string, detail: string}>
     * }
     */
    public function rapport(): array
    {
        $baseDeDonnees = $this->verifierBaseDeDonnees();
        $controles = [
            $baseDeDonnees,
            $this->verifierCache(),
            $this->verifierStockage(),
            $this->verifierClientSauvegarde(),
            $this->verifierMigrations($baseDeDonnees['statut'] === 'ok'),
            $this->verifierFile($baseDeDonnees['statut'] === 'ok'),
        ];
        $runtime = [
            [
                'label' => 'Application',
                'valeur' => (string) config('app.name'),
                'statut' => 'ok',
            ],
            [
                'label' => 'Environnement',
                'valeur' => (string) config('app.env'),
                'statut' => 'ok',
            ],
            [
                'label' => 'PHP',
                'valeur' => PHP_VERSION,
                'statut' => 'ok',
            ],
            [
                'label' => 'Laravel',
                'valeur' => app()->version(),
                'statut' => 'ok',
            ],
            [
                'label' => 'Mode debug',
                'valeur' => config('app.debug') ? 'Activé' : 'Désactivé',
                'statut' => config('app.debug') ? 'warning' : 'ok',
            ],
            [
                'label' => 'Clé applicative',
                'valeur' => filled(config('app.key')) ? 'Configurée' : 'Absente',
                'statut' => filled(config('app.key')) ? 'ok' : 'error',
            ],
            [
                'label' => 'Configuration',
                'valeur' => app()->configurationIsCached() ? 'En cache' : 'Non mise en cache',
                'statut' => 'ok',
            ],
            [
                'label' => 'Routes',
                'valeur' => app()->routesAreCached() ? 'En cache' : 'Non mises en cache',
                'statut' => 'ok',
            ],
        ];

        $aDesErreurs = collect($controles)->contains(fn (array $controle): bool => $controle['statut'] === 'error')
            || collect($runtime)->contains(fn (array $element): bool => $element['statut'] === 'error');
        $aDesAlertes = collect($controles)->contains(fn (array $controle): bool => $controle['statut'] === 'warning')
            || collect($runtime)->contains(fn (array $element): bool => $element['statut'] === 'warning');

        return [
            'resume' => [
                'statut' => $aDesErreurs ? 'error' : ($aDesAlertes ? 'warning' : 'ok'),
                'libelle' => $aDesErreurs ? 'Action requise' : ($aDesAlertes ? 'À surveiller' : 'Opérationnel'),
            ],
            'runtime' => $runtime,
            'controles' => $controles,
        ];
    }

    /**
     * @return array{label: string, statut: string, detail: string}
     */
    private function verifierBaseDeDonnees(): array
    {
        try {
            DB::connection()->select('select 1');

            return [
                'label' => 'Base de données',
                'statut' => 'ok',
                'detail' => 'La connexion répond correctement.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'label' => 'Base de données',
                'statut' => 'error',
                'detail' => 'Connexion impossible ('.$exception::class.'). Consultez les journaux serveur.',
            ];
        }
    }

    /**
     * @return array{label: string, statut: string, detail: string}
     */
    private function verifierCache(): array
    {
        $cle = 'maintenance-health-check:'.Str::uuid();
        $valeur = Str::random(24);

        try {
            Cache::put($cle, $valeur, now()->addMinute());
            $estFonctionnel = Cache::get($cle) === $valeur;

            return [
                'label' => 'Cache applicatif',
                'statut' => $estFonctionnel ? 'ok' : 'error',
                'detail' => $estFonctionnel
                    ? 'Écriture et lecture confirmées.'
                    : 'Le cache ne restitue pas la valeur de contrôle.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'label' => 'Cache applicatif',
                'statut' => 'error',
                'detail' => 'Vérification impossible ('.$exception::class.'). Consultez les journaux serveur.',
            ];
        } finally {
            try {
                Cache::forget($cle);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @return array{label: string, statut: string, detail: string}
     */
    private function verifierStockage(): array
    {
        $chemin = storage_path();
        $estAccessible = is_dir($chemin) && is_readable($chemin) && is_writable($chemin);
        $espaceLibre = disk_free_space($chemin);

        return [
            'label' => 'Stockage',
            'statut' => $estAccessible && $espaceLibre !== false ? 'ok' : 'error',
            'detail' => ! $estAccessible
                ? 'Le dossier storage doit être accessible en lecture et en écriture.'
                : ($espaceLibre === false
                    ? 'Espace disque indisponible pour le diagnostic.'
                    : 'Espace libre : '.number_format($espaceLibre / 1024 / 1024 / 1024, 2, ',', ' ').' Go.'),
        ];
    }

    /**
     * @return array{label: string, statut: string, detail: string}
     */
    private function verifierClientSauvegarde(): array
    {
        $binaire = (string) config('maintenance.backup_binary', 'mysqldump');
        $disponible = app(OperationsMaintenance::class)->clientSauvegardeDisponible();

        return [
            'label' => 'Outil de sauvegarde',
            'statut' => $disponible ? 'ok' : 'warning',
            'detail' => $disponible
                ? 'Le client '.basename($binaire).' est disponible.'
                : 'Le client '.basename($binaire).' est absent. Les sauvegardes et migrations depuis le panneau sont bloquées.',
        ];
    }

    /**
     * @return array{label: string, statut: string, detail: string}
     */
    private function verifierMigrations(bool $baseDisponible): array
    {
        if (! $baseDisponible) {
            return [
                'label' => 'Migrations',
                'statut' => 'warning',
                'detail' => 'Indisponibles tant que la base de données ne répond pas.',
            ];
        }

        $table = (string) config('database.migrations.table', 'migrations');

        if (! Schema::hasTable($table)) {
            return [
                'label' => 'Migrations',
                'statut' => 'error',
                'detail' => 'La table de suivi des migrations est absente.',
            ];
        }

        $executees = DB::table($table)->pluck('migration')->all();
        $fichiers = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $fichier): string => pathinfo($fichier, PATHINFO_FILENAME));
        $enAttente = $fichiers->diff($executees)->values();

        return [
            'label' => 'Migrations',
            'statut' => $enAttente->isEmpty() ? 'ok' : 'warning',
            'detail' => $enAttente->isEmpty()
                ? 'Toutes les migrations présentes sont appliquées.'
                : $enAttente->count().' migration(s) en attente : '.implode(', ', $enAttente->take(3)->all()),
        ];
    }

    /**
     * @return array{label: string, statut: string, detail: string}
     */
    private function verifierFile(bool $baseDisponible): array
    {
        $connexion = (string) config('queue.default');

        if ($connexion !== 'database') {
            return [
                'label' => 'File de travaux',
                'statut' => 'ok',
                'detail' => 'Connexion configurée : '.$connexion.'. Le nombre de travaux dépend du pilote.',
            ];
        }

        if (! $baseDisponible) {
            return [
                'label' => 'File de travaux',
                'statut' => 'warning',
                'detail' => 'Indisponible tant que la base de données ne répond pas.',
            ];
        }

        $configuration = (array) config('queue.connections.database', []);
        $tableTravaux = (string) ($configuration['table'] ?? 'jobs');
        $tableEchecs = (string) config('queue.failed.table', 'failed_jobs');
        $travaux = Schema::hasTable($tableTravaux) ? DB::table($tableTravaux)->count() : null;
        $echecs = Schema::hasTable($tableEchecs) ? DB::table($tableEchecs)->count() : null;

        if ($travaux === null || $echecs === null) {
            return [
                'label' => 'File de travaux',
                'statut' => 'warning',
                'detail' => 'Une table de suivi de la file est absente.',
            ];
        }

        return [
            'label' => 'File de travaux',
            'statut' => $echecs > 0 ? 'warning' : 'ok',
            'detail' => $travaux.' travail(aux) en attente, '.$echecs.' échec(s).',
        ];
    }
}
