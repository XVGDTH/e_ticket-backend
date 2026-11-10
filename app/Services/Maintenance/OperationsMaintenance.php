<?php

namespace App\Services\Maintenance;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OperationsMaintenance
{
    public function clientSauvegardeDisponible(): bool
    {
        $binaire = (string) config('maintenance.backup_binary', 'mysqldump');

        if (blank($binaire)) {
            return false;
        }

        $candidats = str_contains($binaire, DIRECTORY_SEPARATOR) || str_contains($binaire, '/')
            ? [$binaire]
            : collect(explode(PATH_SEPARATOR, (string) getenv('PATH')))
                ->filter()
                ->flatMap(fn (string $dossier): array => [$dossier.DIRECTORY_SEPARATOR.$binaire])
                ->all();
        $executables = $candidats;

        if (DIRECTORY_SEPARATOR === '\\') {
            $extensions = explode(';', (string) (getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD'));

            foreach ($candidats as $candidat) {
                foreach ($extensions as $extension) {
                    $executables[] = $candidat.$extension;
                }
            }
        }

        foreach (array_unique($executables) as $candidat) {
            if (is_file($candidat) && (DIRECTORY_SEPARATOR === '\\' || is_executable($candidat))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{uuid: string, queue: string, failed_at: string, exception: string}>
     */
    public function travauxEchoues(): array
    {
        try {
            $table = (string) config('queue.failed.table', 'failed_jobs');

            if (! Schema::hasTable($table)) {
                return [];
            }

            return DB::table($table)
                ->select(['uuid', 'queue', 'failed_at', 'exception'])
                ->orderByDesc('failed_at')
                ->orderByDesc('id')
                ->limit(25)
                ->get()
                ->map(fn (object $travail): array => [
                    'uuid' => (string) $travail->uuid,
                    'queue' => (string) $travail->queue,
                    'failed_at' => (string) $travail->failed_at,
                    'exception' => $this->masquerSecrets(Str::limit(strtok((string) $travail->exception, "\r\n") ?: '', 220)),
                ])
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function relancerTravail(string $uuid): array
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            return ['ok' => false, 'message' => 'Identifiant de travail invalide.'];
        }

        try {
            $table = (string) config('queue.failed.table', 'failed_jobs');

            if (! Schema::hasTable($table) || ! DB::table($table)->where('uuid', $uuid)->exists()) {
                return ['ok' => false, 'message' => 'Ce travail échoué n’existe plus.'];
            }
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'message' => 'La file de travaux est inaccessible. Consultez le diagnostic et les journaux serveur.'];
        }

        return $this->executerCommande('queue:retry', [$uuid], 'Relance d’un travail échoué');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function purgerCachesTechniques(): array
    {
        foreach ([
            'config:clear',
            'clear-compiled',
            'event:clear',
            'route:clear',
            'view:clear',
            'filament:clear-cached-components',
            'icons:clear',
        ] as $commande) {
            $resultat = $this->executerCommande($commande, [], 'Nettoyage du cache '.$commande);

            if (! $resultat['ok']) {
                return [
                    'ok' => false,
                    'message' => 'Le nettoyage s’est interrompu à '.$commande.'. '.$resultat['message'],
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Caches techniques vidés. Le cache applicatif partagé n’a pas été modifié.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function migrationsEnAttente(): array
    {
        try {
            $table = (string) config('database.migrations.table', 'migrations');

            if (! Schema::hasTable($table)) {
                return [];
            }

            $executees = DB::table($table)->pluck('migration')->all();

            return collect(glob(database_path('migrations/*.php')) ?: [])
                ->map(fn (string $fichier): string => pathinfo($fichier, PATHINFO_FILENAME))
                ->diff($executees)
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return array{ok: bool, message: string, filename?: string}
     */
    public function sauvegarderBase(): array
    {
        $connexion = config('database.default');
        $configuration = (array) config("database.connections.{$connexion}", []);

        if (! in_array($configuration['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            return ['ok' => false, 'message' => 'La sauvegarde depuis l’interface est actuellement disponible uniquement pour MySQL.'];
        }

        if (filled($configuration['url'] ?? null)) {
            $url = parse_url((string) $configuration['url']);

            if ($url === false || blank($url['path'] ?? null)) {
                return ['ok' => false, 'message' => 'L’URL de connexion MySQL ne peut pas être interprétée pour la sauvegarde.'];
            }

            $configuration = array_replace($configuration, [
                'host' => $url['host'] ?? '127.0.0.1',
                'port' => $url['port'] ?? 3306,
                'username' => isset($url['user']) ? rawurldecode($url['user']) : '',
                'password' => isset($url['pass']) ? rawurldecode($url['pass']) : '',
                'database' => ltrim(rawurldecode($url['path']), '/'),
            ]);
        }

        if (! function_exists('proc_open') || ! function_exists('gzopen')) {
            return ['ok' => false, 'message' => 'Le serveur PHP ne dispose pas des extensions requises pour créer une sauvegarde compressée.'];
        }

        if (! $this->clientSauvegardeDisponible()) {
            return ['ok' => false, 'message' => 'Le client mysqldump est absent. Configurez DB_DUMP_BINARY sur le serveur avant les sauvegardes et migrations.'];
        }

        $disque = (string) config('maintenance.backup_disk', 'local');
        $racine = Storage::disk($disque)->path((string) config('maintenance.backup_directory'));

        if (! is_dir($racine) && ! mkdir($racine, 0700, true) && ! is_dir($racine)) {
            throw new RuntimeException('Impossible de créer le répertoire privé des sauvegardes.');
        }

        chmod($racine, 0700);
        $verrou = fopen($racine.DIRECTORY_SEPARATOR.'.backup.lock', 'c');

        if ($verrou === false || ! flock($verrou, LOCK_EX | LOCK_NB)) {
            if (is_resource($verrou)) {
                fclose($verrou);
            }

            return ['ok' => false, 'message' => 'Une sauvegarde est déjà en cours.'];
        }
        chmod($racine.DIRECTORY_SEPARATOR.'.backup.lock', 0600);

        $fichierSql = tempnam($racine, 'backup-');
        $fichierErreur = tempnam($racine, 'backup-error-');
        $fichierFinal = null;
        $sauvegardeReussie = false;

        try {
            if ($fichierSql === false || $fichierErreur === false) {
                throw new RuntimeException('Impossible de préparer les fichiers temporaires privés.');
            }

            chmod($fichierSql, 0600);
            chmod($fichierErreur, 0600);

            $commande = $this->commandeDump($configuration);
            $environnement = array_merge(getenv(), [
                'MYSQL_PWD' => (string) ($configuration['password'] ?? ''),
            ]);
            $descripteurs = [
                0 => ['pipe', 'r'],
                1 => ['file', $fichierSql, 'w'],
                2 => ['file', $fichierErreur, 'w'],
            ];
            $processus = proc_open($commande, $descripteurs, $tuyaux, base_path(), $environnement);

            if (! is_resource($processus)) {
                throw new RuntimeException('Le client mysqldump ne peut pas être lancé.');
            }

            fclose($tuyaux[0]);
            $codeRetour = proc_close($processus);

            if ($codeRetour !== 0 || ! is_file($fichierSql) || filesize($fichierSql) === 0) {
                Log::warning('La sauvegarde de la base a échoué.', [
                    'code_retour' => $codeRetour,
                    'user_id' => auth()->id(),
                ]);

                return ['ok' => false, 'message' => 'La sauvegarde MySQL a échoué. Vérifiez le client configuré et les journaux serveur.'];
            }

            $nom = 'backup_'.now()->format('Ymd_His').'_'.Str::lower(Str::random(8)).'.sql.gz';
            $fichierFinal = $racine.DIRECTORY_SEPARATOR.$nom;
            $this->compresser($fichierSql, $fichierFinal);
            chmod($fichierFinal, 0600);
            $this->purgerAnciennesSauvegardes($racine);
            $sauvegardeReussie = true;

            return [
                'ok' => true,
                'message' => 'La sauvegarde compressée a été créée sur le disque privé.',
                'filename' => $nom,
            ];
        } finally {
            foreach ([$fichierSql, $fichierErreur] as $fichier) {
                if (is_string($fichier) && is_file($fichier)) {
                    unlink($fichier);
                }
            }
            if (! $sauvegardeReussie && is_string($fichierFinal) && is_file($fichierFinal)) {
                unlink($fichierFinal);
            }

            flock($verrou, LOCK_UN);
            fclose($verrou);
        }
    }

    /**
     * @return array<int, array{filename: string, date: string, size: int}>
     */
    public function sauvegardes(): array
    {
        $disque = (string) config('maintenance.backup_disk', 'local');
        $directory = (string) config('maintenance.backup_directory');
        $stockage = Storage::disk($disque);

        if (! $stockage->directoryExists($directory)) {
            return [];
        }

        $this->purgerAnciennesSauvegardes($stockage->path($directory));

        return collect($stockage->files($directory))
            ->filter(fn (string $chemin): bool => preg_match('/^'.preg_quote($directory, '/').'\/backup_\d{8}_\d{6}_[a-z0-9]{8}\.sql\.gz$/', $chemin) === 1)
            ->map(fn (string $chemin): array => [
                'filename' => basename($chemin),
                'date' => date('d/m/Y H:i', $stockage->lastModified($chemin)),
                'size' => $stockage->size($chemin),
            ])
            ->sortByDesc('filename')
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{filename: string, message: string}>
     */
    public function journauxRecents(): array
    {
        $fichiers = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($fichiers, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $lignes = [];

        foreach (array_slice($fichiers, 0, 3) as $fichier) {
            $taille = filesize($fichier);
            $flux = fopen($fichier, 'rb');

            if ($flux === false) {
                continue;
            }

            if ($taille > 65536) {
                fseek($flux, -65536, SEEK_END);
                fgets($flux);
            }

            $contenu = stream_get_contents($flux);
            fclose($flux);

            if (! is_string($contenu)) {
                continue;
            }

            foreach (array_slice(preg_split('/\r\n|\r|\n/', $contenu) ?: [], -100) as $ligne) {
                if (trim($ligne) !== '') {
                    $lignes[] = [
                        'filename' => basename($fichier),
                        'message' => $this->masquerSecrets(Str::limit($ligne, 500)),
                    ];
                }
            }
        }

        return array_slice($lignes, -100);
    }

    /**
     * @param  array<int|string, string|int|bool>  $arguments
     * @return array{ok: bool, message: string}
     */
    public function executerCommande(string $commande, array $arguments, string $libelle): array
    {
        if (! $this->commandeAutorisee($commande, $arguments)) {
            Log::warning('Une commande non autorisée a été refusée depuis la maintenance.', [
                'action' => $commande,
                'user_id' => auth()->id(),
            ]);

            return ['ok' => false, 'message' => 'Cette commande n’est pas autorisée depuis le centre de maintenance.'];
        }

        try {
            $codeRetour = Artisan::call($commande, $arguments);

            if ($codeRetour !== 0) {
                Log::warning('Une commande de maintenance a échoué.', [
                    'action' => $commande,
                    'code_retour' => $codeRetour,
                    'user_id' => auth()->id(),
                ]);

                return ['ok' => false, 'message' => $libelle.' a échoué. Consultez les journaux serveur.'];
            }

            Log::notice('Commande de maintenance exécutée.', [
                'action' => $commande,
                'user_id' => auth()->id(),
            ]);

            return ['ok' => true, 'message' => $libelle.' exécuté.'];
        } catch (Throwable $exception) {
            Log::error('Une commande de maintenance a levé une exception.', [
                'action' => $commande,
                'exception' => $exception::class,
                'user_id' => auth()->id(),
            ]);

            return ['ok' => false, 'message' => $libelle.' a échoué. Consultez les journaux serveur.'];
        }
    }

    /**
     * @param  array<int|string, string|int|bool>  $arguments
     */
    private function commandeAutorisee(string $commande, array $arguments): bool
    {
        return match ($commande) {
            'config:clear', 'clear-compiled', 'event:clear', 'route:clear', 'view:clear', 'filament:clear-cached-components', 'icons:clear' => $arguments === [],
            'queue:restart', 'up' => $arguments === ($commande === 'up' ? ['--no-interaction' => true] : []),
            'queue:retry' => array_is_list($arguments)
                && count($arguments) === 1
                && is_string($arguments[0])
                && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $arguments[0]) === 1,
            'migrate' => $arguments === [
                '--force' => true,
                '--isolated' => true,
                '--no-interaction' => true,
            ],
            'down' => count($arguments) === 3
                && is_string($arguments['--secret'] ?? null)
                && preg_match('/^[A-Za-z0-9]{48}$/', $arguments['--secret']) === 1
                && ($arguments['--retry'] ?? null) === 60
                && ($arguments['--no-interaction'] ?? null) === true,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<int, string>
     */
    private function commandeDump(array $configuration): array
    {
        $binaire = (string) config('maintenance.backup_binary', 'mysqldump');

        if (blank($binaire)) {
            throw new RuntimeException('Aucun client mysqldump n’est configuré.');
        }

        $commande = [
            $binaire,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--routines',
            '--events',
            '--triggers',
            '--host='.(string) ($configuration['host'] ?? '127.0.0.1'),
            '--port='.(string) ($configuration['port'] ?? 3306),
            '--user='.(string) ($configuration['username'] ?? ''),
            '--default-character-set=utf8mb4',
            (string) ($configuration['database'] ?? ''),
        ];

        if (filled($configuration['unix_socket'] ?? null)) {
            $commande[] = '--socket='.(string) $configuration['unix_socket'];
        }

        return $commande;
    }

    private function compresser(string $source, string $destination): void
    {
        $entree = fopen($source, 'rb');
        $sortie = gzopen($destination, 'wb6');

        if ($entree === false || $sortie === false) {
            if (is_resource($entree)) {
                fclose($entree);
            }

            throw new RuntimeException('Impossible de compresser la sauvegarde.');
        }

        try {
            while (! feof($entree)) {
                $bloc = fread($entree, 1024 * 1024);

                if ($bloc === false || ($bloc !== '' && gzwrite($sortie, $bloc) === false)) {
                    throw new RuntimeException('Échec pendant la compression de la sauvegarde.');
                }
            }
        } finally {
            fclose($entree);
            gzclose($sortie);
        }
    }

    private function purgerAnciennesSauvegardes(string $repertoire): void
    {
        $jours = max(1, (int) config('maintenance.backup_retention_days', 30));
        $limite = now()->subDays($jours)->getTimestamp();

        foreach (glob($repertoire.DIRECTORY_SEPARATOR.'backup_*.sql.gz') ?: [] as $fichier) {
            if (preg_match('/^backup_\d{8}_\d{6}_[a-z0-9]{8}\.sql\.gz$/', basename($fichier)) === 1
                && filemtime($fichier) < $limite) {
                unlink($fichier);
            }
        }
    }

    private function masquerSecrets(string $texte): string
    {
        $texte = preg_replace('/(password|passwd|token|secret|api[_-]?key|authorization)(\s*[=:]\s*["\']?)[^\s,"\'}]+/i', '$1$2[masqué]', $texte) ?? $texte;

        return preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [masqué]', $texte) ?? $texte;
    }
}
