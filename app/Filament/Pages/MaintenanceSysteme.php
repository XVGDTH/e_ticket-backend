<?php

namespace App\Filament\Pages;

use App\Services\Maintenance\DiagnosticSysteme;
use App\Services\Maintenance\OperationsMaintenance;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use UnitEnum;

class MaintenanceSysteme extends Page
{
    protected string $view = 'filament.pages.maintenance-systeme';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationLabel = 'Maintenance système';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'maintenance-systeme';

    public string $maintenanceBypassUrl = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function getTitle(): string
    {
        return 'Maintenance système';
    }

    public function getSubheading(): string
    {
        return 'Surveillez les composants essentiels et lancez uniquement des opérations de maintenance contrôlées.';
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('actualiser')
                ->label('Actualiser le diagnostic')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->actualiser()),
            Action::make('nettoyerCaches')
                ->label('Purger les caches techniques')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Purger les caches techniques ?')
                ->modalDescription('Les caches techniques Laravel et Filament seront supprimés. Le cache applicatif partagé ne sera pas vidé.')
                ->action(fn () => $this->nettoyerCaches()),
            Action::make('redemarrerWorkers')
                ->label('Redémarrer les workers')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Signaler le redémarrage des workers ?')
                ->modalDescription('Les workers de file termineront leur travail en cours puis redémarreront. Cette action ne supprime aucun travail en attente.')
                ->action(fn () => $this->redemarrerWorkers()),
            Action::make('sauvegarderBase')
                ->label('Créer une sauvegarde')
                ->icon('heroicon-o-circle-stack')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Créer une sauvegarde privée ?')
                ->modalDescription('Une copie compressée de la base sera créée dans le stockage local privé. Les sauvegardes de plus de 30 jours seront supprimées.')
                ->disabled(fn (): bool => ! app(OperationsMaintenance::class)->clientSauvegardeDisponible())
                ->action(fn () => $this->sauvegarderBase()),
            Action::make('executerMigrations')
                ->label('Sauvegarder et appliquer les migrations')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Appliquer toutes les migrations en attente ?')
                ->modalDescription('Une sauvegarde locale doit réussir avant l’exécution. Les migrations peuvent modifier le schéma et rendre les données incompatibles avec une ancienne version.')
                ->disabled(fn (): bool => ! app(OperationsMaintenance::class)->clientSauvegardeDisponible())
                ->action(fn () => $this->executerMigrations())
                ->visible(fn (): bool => app(OperationsMaintenance::class)->migrationsEnAttente() !== []),
            Action::make('activerMaintenance')
                ->label('Activer le mode maintenance')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Rendre le site indisponible ?')
                ->modalDescription('Les visiteurs recevront une page de maintenance. Un lien de contournement privé sera fourni pour que le super administrateur puisse revenir dans ce panneau.')
                ->action(fn () => $this->basculerModeMaintenance(true))
                ->visible(fn (): bool => ! app()->isDownForMaintenance()),
            Action::make('desactiverMaintenance')
                ->label('Désactiver le mode maintenance')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Rendre le site à nouveau accessible ?')
                ->action(fn () => $this->basculerModeMaintenance(false))
                ->visible(fn (): bool => app()->isDownForMaintenance()),
        ];
    }

    /**
     * @return array{diagnostic: array, journaux: array, travauxEchoues: array, sauvegardes: array, migrationsEnAttente: array, modeMaintenance: bool}
     */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $operations = app(OperationsMaintenance::class);
        $diagnostic = app(DiagnosticSysteme::class)->rapport();
        $baseDeDonneesDisponible = collect($diagnostic['controles'])
            ->firstWhere('label', 'Base de données')['statut'] === 'ok';

        return [
            'diagnostic' => $diagnostic,
            'journaux' => $operations->journauxRecents(),
            'travauxEchoues' => $baseDeDonneesDisponible ? $operations->travauxEchoues() : [],
            'sauvegardes' => $operations->sauvegardes(),
            'migrationsEnAttente' => $baseDeDonneesDisponible ? $operations->migrationsEnAttente() : [],
            'modeMaintenance' => app()->isDownForMaintenance(),
        ];
    }

    public function nettoyerCaches(): void
    {
        abort_unless(static::canAccess(), 403);

        $resultat = app(OperationsMaintenance::class)->purgerCachesTechniques();
        $notification = Notification::make()
            ->title($resultat['ok'] ? 'Caches techniques purgés' : 'Purge des caches incomplète')
            ->body($resultat['message']);

        ($resultat['ok'] ? $notification->success() : $notification->danger())->send();
    }

    public function actualiser(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function redemarrerWorkers(): void
    {
        $this->executerOperation('queue:restart', 'Signal de redémarrage des workers');
    }

    public function sauvegarderBase(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $resultat = app(OperationsMaintenance::class)->sauvegarderBase();

            Log::notice('Action de sauvegarde demandée depuis la maintenance.', [
                'success' => $resultat['ok'],
                'filename' => $resultat['filename'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $notification = Notification::make()
                ->title($resultat['ok'] ? 'Sauvegarde terminée' : 'Sauvegarde impossible')
                ->body($resultat['message']);

            ($resultat['ok'] ? $notification->success() : $notification->danger())->send();
        } catch (\Throwable $exception) {
            Log::error('La sauvegarde demandée a levé une exception.', [
                'exception' => $exception::class,
                'user_id' => auth()->id(),
            ]);

            Notification::make()
                ->title('Sauvegarde impossible')
                ->body('Une erreur est survenue. Consultez les journaux serveur.')
                ->danger()
                ->send();
        }
    }

    public function relancerTravail(string $uuid): void
    {
        abort_unless(static::canAccess(), 403);

        $resultat = app(OperationsMaintenance::class)->relancerTravail($uuid);
        $notification = Notification::make()
            ->title($resultat['ok'] ? 'Travail relancé' : 'Relance refusée')
            ->body($resultat['message']);

        ($resultat['ok'] ? $notification->success() : $notification->danger())->send();
    }

    public function executerMigrations(): void
    {
        abort_unless(static::canAccess(), 403);

        $operations = app(OperationsMaintenance::class);
        $enAttente = $operations->migrationsEnAttente();

        if ($enAttente === []) {
            Notification::make()->title('Aucune migration en attente')->info()->send();

            return;
        }

        try {
            $sauvegarde = $operations->sauvegarderBase();

            if (! $sauvegarde['ok']) {
                Notification::make()
                    ->title('Migrations bloquées')
                    ->body('La sauvegarde préalable a échoué : '.$sauvegarde['message'])
                    ->danger()
                    ->send();

                return;
            }

            $resultat = $operations->executerCommande(
                'migrate',
                ['--force' => true, '--isolated' => true, '--no-interaction' => true],
                'Application des migrations',
            );
            $migrationsRestantes = $operations->migrationsEnAttente();

            if ($resultat['ok'] && $migrationsRestantes === []) {
                Log::notice('Migrations appliquées depuis la maintenance.', [
                    'user_id' => auth()->id(),
                    'backup' => $sauvegarde['filename'] ?? null,
                    'count' => count($enAttente),
                ]);

                Notification::make()
                    ->title('Migrations appliquées')
                    ->body(count($enAttente).' migration(s) traitée(s). Sauvegarde : '.($sauvegarde['filename'] ?? 'indisponible'))
                    ->success()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Migrations à vérifier')
                ->body($resultat['message'].' Il reste '.count($migrationsRestantes).' migration(s) en attente.')
                ->danger()
                ->send();
        } catch (\Throwable $exception) {
            Log::error('Les migrations demandées ont levé une exception.', [
                'exception' => $exception::class,
                'user_id' => auth()->id(),
            ]);

            Notification::make()
                ->title('Migrations interrompues')
                ->body('Vérifiez les journaux serveur et la sauvegarde créée avant toute nouvelle tentative.')
                ->danger()
                ->send();
        }
    }

    public function basculerModeMaintenance(bool $activer): void
    {
        abort_unless(static::canAccess(), 403);

        if ($activer) {
            $secret = Str::random(48);
            $resultat = app(OperationsMaintenance::class)->executerCommande(
                'down',
                ['--secret' => $secret, '--retry' => 60, '--no-interaction' => true],
                'Activation du mode maintenance',
            );

            if ($resultat['ok']) {
                $this->maintenanceBypassUrl = url('/'.$secret.'/admin/maintenance-systeme');
            }
        } else {
            $resultat = app(OperationsMaintenance::class)->executerCommande(
                'up',
                ['--no-interaction' => true],
                'Désactivation du mode maintenance',
            );
        }

        $notification = Notification::make()
            ->title($resultat['ok'] ? 'Mode maintenance mis à jour' : 'Opération impossible')
            ->body($resultat['message']);

        ($resultat['ok'] ? $notification->success() : $notification->danger())->send();
    }

    private function executerOperation(string $commandeAutorisee, string $libelle): void
    {
        abort_unless(static::canAccess(), 403);

        $resultat = app(OperationsMaintenance::class)->executerCommande($commandeAutorisee, [], $libelle);
        $notification = Notification::make()
            ->title($resultat['ok'] ? $libelle.' exécuté' : 'Opération de maintenance impossible')
            ->body($resultat['message']);

        ($resultat['ok'] ? $notification->success() : $notification->danger())->send();
    }
}
