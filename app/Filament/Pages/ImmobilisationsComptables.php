<?php

namespace App\Filament\Pages;

use App\Models\Compagnie;
use App\Models\CptAmortissement;
use App\Models\CptCategorieImmobilisation;
use App\Models\CptImmobilisation;
use App\Services\Compta;
use App\Services\ImmobilisationsComptables as ImmobilisationsWorkflow;
use App\Services\Modules;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ImmobilisationsComptables extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Immobilisations';

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'immobilisations-comptables';

    protected string $view = 'filament.pages.immobilisations-comptables';

    public string $compagnieId = '';

    public static function canAccess(): bool
    {
        $utilisateur = auth()->user();

        return $utilisateur
            && Modules::visiblePour($utilisateur, 'comptabilite')
            && ($utilisateur->isSuperAdmin()
                || ($utilisateur->isAdminCompagnie() && ! $utilisateur->villeId())
                || in_array('modifier', $utilisateur->droitsMetierPourModule('comptabilite'), true));
    }

    public function mount(): void
    {
        $utilisateur = auth()->user();

        $this->compagnieId = $utilisateur->isSuperAdmin()
            ? (string) (Compagnie::query()->orderBy('nom')->value('id') ?? '')
            : (string) $utilisateur->compagnieId();

        if ($this->cid()) {
            Compta::initialiser($this->cid());
        }
    }

    public function updatedCompagnieId(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        Compagnie::query()->findOrFail($this->cid());
        Compta::initialiser($this->cid());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('creerCategorie')
                ->label('Nouvelle catégorie')
                ->icon('heroicon-o-squares-plus')
                ->modalHeading('Paramétrer une catégorie d’immobilisations')
                ->modalWidth('3xl')
                ->schema($this->champsCategorie())
                ->action(function (array $data): void {
                    $categorie = app(ImmobilisationsWorkflow::class)
                        ->creerCategorie(auth()->user(), $this->cid(), $data);

                    Notification::make()
                        ->title('Catégorie créée')
                        ->body($categorie->libelle.' est prête à être utilisée.')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->cid() > 0),
            Action::make('configurerCategorie')
                ->label('Configurer une catégorie')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading('Réviser les paramètres d’une catégorie')
                ->modalWidth('3xl')
                ->schema($this->champsCategorie(modification: true))
                ->action(function (array $data): void {
                    $categorie = app(ImmobilisationsWorkflow::class)->modifierCategorie(
                        auth()->user(),
                        $this->cid(),
                        (int) $data['categorie_id'],
                        $data
                    );

                    Notification::make()
                        ->title('Paramètres de catégorie enregistrés')
                        ->body($categorie->libelle.' · amortissement linéaire · '.$categorie->duree_utilite_mois.' mois')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->cid() > 0),
            Action::make('creerImmobilisation')
                ->label('Enregistrer une immobilisation')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Nouvelle immobilisation')
                ->modalWidth('3xl')
                ->schema([
                    Select::make('categorie_id')
                        ->label('Catégorie active')
                        ->options(fn (): array => $this->optionsCategories(activesSeulement: true))
                        ->searchable()
                        ->required(),
                    TextInput::make('code')->label('Code interne')->required()->maxLength(30),
                    TextInput::make('designation')->label('Désignation')->required()->maxLength(160),
                    Textarea::make('description')->label('Description')->rows(2)->maxLength(5000)->columnSpanFull(),
                    DatePicker::make('date_acquisition')->label('Date d’acquisition')->required(),
                    DatePicker::make('date_mise_en_service')->label('Mise en service')->required(),
                    TextInput::make('cout_acquisition')->label('Coût d’acquisition')->numeric()->minValue(1)->maxValue(1000000000000)->suffix('XOF')->required(),
                    TextInput::make('valeur_residuelle')->label('Valeur résiduelle')->numeric()->minValue(0)->maxValue(1000000000000)->default(0)->suffix('XOF')->required(),
                ])
                ->action(function (array $data): void {
                    $immobilisation = app(ImmobilisationsWorkflow::class)
                        ->creerImmobilisation(auth()->user(), $this->cid(), $data);

                    Notification::make()
                        ->title('Immobilisation enregistrée en brouillon')
                        ->body($immobilisation->code.' · validation par un autre utilisateur requise.')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->cid() > 0 && $this->optionsCategories(activesSeulement: true) !== []),
        ];
    }

    public function validerImmobilisation(int $immobilisationId): void
    {
        try {
            app(ImmobilisationsWorkflow::class)
                ->validerImmobilisation(auth()->user(), $this->cid(), $immobilisationId);

            Notification::make()->title('Immobilisation validée')->success()->send();
        } catch (DomainException $exception) {
            Notification::make()->title('Validation impossible')->body($exception->getMessage())->danger()->send();
        }
    }

    public function proposerEcriture(int $amortissementId): void
    {
        try {
            app(ImmobilisationsWorkflow::class)
                ->proposerEcriture(auth()->user(), $this->cid(), $amortissementId);

            Notification::make()
                ->title('Écriture d’amortissement préparée')
                ->body('Un autre utilisateur devra la vérifier et la comptabiliser.')
                ->success()
                ->send();
        } catch (DomainException $exception) {
            Notification::make()->title('Proposition impossible')->body($exception->getMessage())->danger()->send();
        }
    }

    public function comptabiliserAmortissement(int $amortissementId): void
    {
        try {
            app(ImmobilisationsWorkflow::class)
                ->comptabiliserAmortissement(auth()->user(), $this->cid(), $amortissementId);

            Notification::make()->title('Dotation comptabilisée')->success()->send();
        } catch (DomainException $exception) {
            Notification::make()->title('Comptabilisation impossible')->body($exception->getMessage())->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        $compagnieId = $this->cid();

        return [
            'd' => [
                'compagnies' => auth()->user()->isSuperAdmin()
                    ? Compagnie::query()->orderBy('nom')->pluck('nom', 'id')
                    : collect(),
                'nomCompagnie' => Compagnie::query()->whereKey($compagnieId)->value('nom') ?? '—',
                'categories' => $compagnieId
                    ? CptCategorieImmobilisation::query()
                        ->where('compagnie_id', $compagnieId)
                        ->withCount('immobilisations')
                        ->orderByDesc('actif')
                        ->orderBy('libelle')
                        ->get()
                    : collect(),
                'immobilisations' => $compagnieId
                    ? CptImmobilisation::query()
                        ->where('compagnie_id', $compagnieId)
                        ->with('categorie')
                        ->withCount([
                            'amortissements as periodes_planifiees_count' => fn (Builder $query) => $query->where('statut', 'planifie'),
                        ])
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get()
                    : collect(),
                'amortissements' => $compagnieId
                    ? CptAmortissement::query()
                        ->where('compagnie_id', $compagnieId)
                        ->whereIn('statut', ['planifie', 'brouillon'])
                        ->with(['immobilisation', 'ecriture'])
                        ->orderBy('periode_fin')
                        ->limit(100)
                        ->get()
                    : collect(),
                'immobilisationsActives' => $compagnieId
                    ? CptImmobilisation::query()->where('compagnie_id', $compagnieId)->where('statut', 'active')->count()
                    : 0,
                'enAttenteValidation' => $compagnieId
                    ? CptImmobilisation::query()->where('compagnie_id', $compagnieId)->where('statut', 'brouillon')->count()
                    : 0,
                'dotationsAProposer' => $compagnieId
                    ? CptAmortissement::query()
                        ->where('compagnie_id', $compagnieId)
                        ->where('statut', 'planifie')
                        ->whereDate('periode_fin', '<=', today())
                        ->count()
                    : 0,
                'ecrituresEnAttente' => $compagnieId
                    ? CptAmortissement::query()->where('compagnie_id', $compagnieId)->where('statut', 'brouillon')->count()
                    : 0,
                'devise' => 'XOF',
                'reglesPremierePeriode' => [
                    'prorata_temporis' => 'Prorata temporis',
                    'mois_complet' => 'Mois de mise en service complet',
                    'mois_suivant' => 'À partir du mois suivant',
                ],
            ],
        ];
    }

    private function champsCategorie(bool $modification = false): array
    {
        $champs = [];

        if ($modification) {
            $champs[] = Select::make('categorie_id')
                ->label('Catégorie à configurer')
                ->options(fn (): array => $this->optionsCategories())
                ->searchable()
                ->live()
                ->required()
                ->afterStateUpdated(function (Set $set, $state): void {
                    $categorie = CptCategorieImmobilisation::query()
                        ->where('compagnie_id', $this->cid())
                        ->find($state);

                    if (! $categorie) {
                        return;
                    }

                    foreach ([
                        'code',
                        'libelle',
                        'duree_utilite_mois',
                        'mode_amortissement',
                        'premiere_periode',
                        'compte_actif_id',
                        'compte_amortissement_id',
                        'compte_charge_id',
                        'actif',
                    ] as $champ) {
                        $set($champ, $categorie->{$champ});
                    }
                });
        } else {
            $champs[] = TextInput::make('code')->label('Code')->required()->maxLength(20);
            $champs[] = TextInput::make('libelle')->label('Libellé')->required()->maxLength(120);
        }

        $champs[] = TextInput::make('duree_utilite_mois')
            ->label('Durée d’utilité')
            ->numeric()
            ->minValue(1)
            ->maxValue(600)
            ->suffix('mois')
            ->required();
        $champs[] = Select::make('mode_amortissement')
            ->label('Méthode')
            ->options(['lineaire' => 'Linéaire'])
            ->default('lineaire')
            ->required();
        $champs[] = Select::make('premiere_periode')
            ->label('Première période')
            ->options([
                'prorata_temporis' => 'Prorata temporis à partir de la mise en service',
                'mois_complet' => 'Mois de mise en service complet',
                'mois_suivant' => 'À partir du mois suivant',
            ])
            ->default('prorata_temporis')
            ->required();
        $champs[] = Select::make('compte_actif_id')
            ->label('Compte d’immobilisation (classe 2)')
            ->options(fn (): array => app(ImmobilisationsWorkflow::class)->optionsComptes($this->cid(), 'actif'))
            ->searchable()
            ->required();
        $champs[] = Select::make('compte_amortissement_id')
            ->label('Compte d’amortissements (classe 28)')
            ->options(fn (): array => app(ImmobilisationsWorkflow::class)->optionsComptes($this->cid(), 'amortissement'))
            ->searchable()
            ->required();
        $champs[] = Select::make('compte_charge_id')
            ->label('Compte de dotation (classe 681)')
            ->options(fn (): array => app(ImmobilisationsWorkflow::class)->optionsComptes($this->cid(), 'charge'))
            ->searchable()
            ->required();

        if ($modification) {
            $champs[] = Toggle::make('actif')
                ->label('Catégorie disponible pour de nouvelles immobilisations')
                ->default(false)
                ->required();
        }

        return $champs;
    }

    /**
     * @return array<int,string>
     */
    private function optionsCategories(bool $activesSeulement = false): array
    {
        if (! $this->cid()) {
            return [];
        }

        return CptCategorieImmobilisation::query()
            ->where('compagnie_id', $this->cid())
            ->when($activesSeulement, fn (Builder $query) => $query->where('actif', true))
            ->orderBy('libelle')
            ->get()
            ->mapWithKeys(fn (CptCategorieImmobilisation $categorie) => [
                $categorie->id => $categorie->libelle.($categorie->actif ? '' : ' · à configurer'),
            ])
            ->all();
    }

    private function cid(): int
    {
        $utilisateur = auth()->user();

        return $utilisateur->isSuperAdmin()
            ? (int) $this->compagnieId
            : (int) $utilisateur->compagnieId();
    }
}
