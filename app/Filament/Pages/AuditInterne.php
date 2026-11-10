<?php

namespace App\Filament\Pages;

use App\Models\Compagnie;
use App\Models\ControleAudit;
use App\Models\RecommandationAudit;
use App\Models\UserCompagnieRole;
use App\Services\AuditInterne\Acces;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class AuditInterne extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static string|UnitEnum|null $navigationGroup = 'Contrôle interne et audit';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Contrôle interne et audit';

    protected static ?string $slug = 'controle-interne';

    protected string $view = 'filament.pages.audit-interne';

    public string $compagnieId = '';

    public static function canAccess(): bool
    {
        return Acces::visible(auth()->user());
    }

    public function getTitle(): string
    {
        return 'Contrôle interne et audit';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Planifiez les contrôles et assurez le suivi des recommandations de votre compagnie.';
    }

    public function mount(): void
    {
        if (auth()->user()->isSuperAdmin()) {
            $this->compagnieId = '';
        }
    }

    public function updatedCompagnieId(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('creerControle')
                ->label('Planifier un contrôle')
                ->icon('heroicon-o-plus')
                ->modalHeading('Nouveau contrôle')
                ->modalWidth('3xl')
                ->schema(fn (): array => $this->champsControle())
                ->action(function (array $data): void {
                    $this->verifierCompagnie();

                    ControleAudit::create($this->donneesControle($data) + [
                        'compagnie_id' => $this->cid(),
                        'cree_par' => auth()->id(),
                    ]);

                    Notification::make()->title('Contrôle ajouté au plan')->success()->send();
                })
                ->visible(fn (): bool => $this->cid() > 0),
            Action::make('creerRecommandation')
                ->label('Nouvelle recommandation')
                ->icon('heroicon-o-flag')
                ->color('warning')
                ->modalHeading('Nouvelle recommandation')
                ->modalWidth('3xl')
                ->schema(fn (): array => $this->champsRecommandation())
                ->action(function (array $data): void {
                    $this->verifierCompagnie();

                    RecommandationAudit::create($this->donneesRecommandation($data) + [
                        'compagnie_id' => $this->cid(),
                        'statut' => 'ouverte',
                        'cree_par' => auth()->id(),
                    ]);

                    Notification::make()->title('Recommandation enregistrée')->success()->send();
                })
                ->visible(fn (): bool => $this->cid() > 0),
        ];
    }

    public function modifierControleAction(): Action
    {
        return Action::make('modifierControle')
            ->label('Modifier')
            ->modalHeading('Modifier le contrôle')
            ->modalWidth('3xl')
            ->fillForm(function (array $arguments): array {
                $controle = $this->controle((int) ($arguments['id'] ?? 0));

                return [
                    'titre' => $controle->titre,
                    'objectif' => $controle->objectif,
                    'procedure' => $controle->procedure,
                    'frequence' => $controle->frequence,
                    'niveau_risque' => $controle->niveau_risque,
                    'responsable_id' => $controle->responsable_id,
                    'prochaine_echeance' => $controle->prochaine_echeance?->toDateString(),
                ];
            })
            ->schema(fn (): array => $this->champsControle())
            ->action(function (array $data, array $arguments): void {
                $this->controle((int) ($arguments['id'] ?? 0))
                    ->update($this->donneesControle($data));

                Notification::make()->title('Contrôle mis à jour')->success()->send();
            });
    }

    public function modifierRecommandationAction(): Action
    {
        return Action::make('modifierRecommandation')
            ->label('Mettre à jour')
            ->modalHeading('Suivi de la recommandation')
            ->modalWidth('3xl')
            ->fillForm(function (array $arguments): array {
                $recommandation = $this->recommandation((int) ($arguments['id'] ?? 0));

                return [
                    'controle_id' => $recommandation->controle_id,
                    'titre' => $recommandation->titre,
                    'constat' => $recommandation->constat,
                    'action_requise' => $recommandation->action_requise,
                    'priorite' => $recommandation->priorite,
                    'statut' => $recommandation->statut,
                    'responsable_id' => $recommandation->responsable_id,
                    'echeance' => $recommandation->echeance?->toDateString(),
                    'resolution' => $recommandation->resolution,
                ];
            })
            ->schema(fn (): array => $this->champsRecommandation(modification: true))
            ->action(function (array $data, array $arguments): void {
                $recommandation = $this->recommandation((int) ($arguments['id'] ?? 0));
                $statut = $data['statut'];
                $termineeLe = $statut === 'terminee'
                    ? ($recommandation->statut === 'terminee' ? $recommandation->terminee_le : now())
                    : null;

                $recommandation->update($this->donneesRecommandation($data) + [
                    'statut' => $statut,
                    'terminee_le' => $termineeLe,
                ]);

                Notification::make()->title('Suivi de la recommandation mis à jour')->success()->send();
            });
    }

    public function basculerControle(int $id): void
    {
        $controle = $this->controle($id);
        $controle->update(['actif' => ! $controle->actif]);

        Notification::make()
            ->title($controle->actif ? 'Contrôle réactivé' : 'Contrôle suspendu')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        $compagnieId = $this->cid();
        $compagnieIds = $compagnieId > 0
            ? [$compagnieId]
            : (auth()->user()->isSuperAdmin()
                ? Compagnie::query()->pluck('id')->map(fn ($id): int => (int) $id)->all()
                : []);
        $controles = $compagnieIds !== []
            ? ControleAudit::query()
                ->whereIn('compagnie_id', $compagnieIds)
                ->with(['responsable', 'compagnie'])
                ->orderByRaw('prochaine_echeance IS NULL')
                ->orderBy('prochaine_echeance')
                ->orderBy('id')
                ->get()
            : collect();
        $recommandations = $compagnieIds !== []
            ? RecommandationAudit::query()
                ->whereIn('compagnie_id', $compagnieIds)
                ->with(['responsable', 'controle', 'compagnie'])
                ->orderByRaw('echeance IS NULL')
                ->orderBy('echeance')
                ->orderByDesc('id')
                ->get()
            : collect();
        $compagnies = auth()->user()->isSuperAdmin()
            ? collect(['' => 'Toutes les compagnies'])->union(Compagnie::query()->orderBy('nom')->pluck('nom', 'id'))
            : collect();

        return [
            'd' => [
                'compagnies' => $compagnies,
                'nomCompagnie' => $compagnieId > 0
                    ? (Compagnie::query()->whereKey($compagnieId)->value('nom') ?? '—')
                    : ($compagnieIds !== [] ? 'Toutes les compagnies' : '—'),
                'peutModifier' => $compagnieId > 0,
                'controles' => $controles,
                'recommandations' => $recommandations,
                'statistiques' => [
                    'controles_actifs' => $controles->where('actif', true)->count(),
                    'controles_en_retard' => $controles->filter(
                        fn (ControleAudit $controle): bool => $controle->actif && $controle->prochaine_echeance?->lt(today())
                    )->count(),
                    'recommandations_ouvertes' => $recommandations->whereIn('statut', ['ouverte', 'en_cours'])->count(),
                    'recommandations_en_retard' => $recommandations->filter(
                        fn (RecommandationAudit $recommandation): bool => in_array($recommandation->statut, ['ouverte', 'en_cours'], true)
                            && $recommandation->echeance?->lt(today())
                    )->count(),
                ],
            ],
        ];
    }

    protected function cid(): int
    {
        $utilisateur = auth()->user();

        return $utilisateur->isSuperAdmin()
            ? (int) $this->compagnieId
            : (int) ($utilisateur->compagnieId() ?? 0);
    }

    protected function verifierCompagnie(): void
    {
        abort_unless(Acces::visible(auth()->user()), 403);
        abort_unless($this->cid() > 0 && Compagnie::query()->whereKey($this->cid())->exists(), 404);
    }

    protected function controle(int $id): ControleAudit
    {
        $this->verifierCompagnie();

        return ControleAudit::query()
            ->where('compagnie_id', $this->cid())
            ->findOrFail($id);
    }

    protected function recommandation(int $id): RecommandationAudit
    {
        $this->verifierCompagnie();

        return RecommandationAudit::query()
            ->where('compagnie_id', $this->cid())
            ->findOrFail($id);
    }

    protected function champsControle(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('titre')->label('Intitulé du contrôle')->required()->maxLength(150)->columnSpanFull(),
                Textarea::make('objectif')->label('Objectif')->required()->rows(3)->maxLength(5000)->columnSpanFull(),
                Textarea::make('procedure')->label('Procédure à suivre')->rows(3)->maxLength(10000)->columnSpanFull(),
                Select::make('frequence')
                    ->label('Fréquence')
                    ->options($this->frequences())
                    ->rules(['in:'.implode(',', array_keys($this->frequences()))])
                    ->required(),
                Select::make('niveau_risque')
                    ->label('Niveau de risque')
                    ->options($this->niveauxRisque())
                    ->rules(['in:'.implode(',', array_keys($this->niveauxRisque()))])
                    ->default('moyen')
                    ->required(),
                Select::make('responsable_id')
                    ->label('Responsable')
                    ->options(fn (): array => $this->optionsResponsables())
                    ->searchable(),
                DatePicker::make('prochaine_echeance')->label('Prochaine échéance'),
            ]),
        ];
    }

    protected function champsRecommandation(bool $modification = false): array
    {
        $champs = [
            Select::make('controle_id')
                ->label('Contrôle associé')
                ->options(fn (): array => $this->optionsControles())
                ->searchable(),
            TextInput::make('titre')->label('Intitulé')->required()->maxLength(150),
            Textarea::make('constat')->label('Constat')->required()->rows(3)->maxLength(5000)->columnSpanFull(),
            Textarea::make('action_requise')->label('Action recommandée')->required()->rows(3)->maxLength(5000)->columnSpanFull(),
            Select::make('priorite')
                ->label('Priorité')
                ->options($this->priorites())
                ->rules(['in:'.implode(',', array_keys($this->priorites()))])
                ->default('normale')
                ->required(),
            Select::make('responsable_id')
                ->label('Responsable')
                ->options(fn (): array => $this->optionsResponsables())
                ->searchable(),
            DatePicker::make('echeance')->label('Échéance'),
        ];

        if ($modification) {
            $champs[] = Select::make('statut')
                ->label('Statut')
                ->options($this->statutsRecommandation())
                ->rules(['in:'.implode(',', array_keys($this->statutsRecommandation()))])
                ->required();
            $champs[] = Textarea::make('resolution')
                ->label('Mesure corrective / preuve de résolution')
                ->rows(3)
                ->maxLength(5000)
                ->required(fn (Get $get): bool => $get('statut') === 'terminee')
                ->columnSpanFull();
        }

        return [Grid::make(2)->schema($champs)];
    }

    protected function donneesControle(array $data): array
    {
        return [
            'titre' => trim($data['titre']),
            'objectif' => trim($data['objectif']),
            'procedure' => filled($data['procedure'] ?? null) ? trim($data['procedure']) : null,
            'frequence' => $data['frequence'],
            'niveau_risque' => $data['niveau_risque'],
            'responsable_id' => $this->responsableId($data['responsable_id'] ?? null),
            'prochaine_echeance' => $data['prochaine_echeance'] ?? null,
        ];
    }

    protected function donneesRecommandation(array $data): array
    {
        $controleId = filled($data['controle_id'] ?? null) ? (int) $data['controle_id'] : null;

        if ($controleId !== null) {
            abort_unless(
                ControleAudit::query()->where('compagnie_id', $this->cid())->whereKey($controleId)->exists(),
                404,
            );
        }

        return [
            'controle_id' => $controleId,
            'titre' => trim($data['titre']),
            'constat' => trim($data['constat']),
            'action_requise' => trim($data['action_requise']),
            'priorite' => $data['priorite'],
            'responsable_id' => $this->responsableId($data['responsable_id'] ?? null),
            'echeance' => $data['echeance'] ?? null,
            'resolution' => filled($data['resolution'] ?? null) ? trim($data['resolution']) : null,
        ];
    }

    protected function responsableId(mixed $id): ?int
    {
        if (blank($id)) {
            return null;
        }

        $id = (int) $id;

        abort_unless(
            UserCompagnieRole::query()
                ->where('compagnie_id', $this->cid())
                ->where('user_id', $id)
                ->whereHas('user', fn ($query) => $query->where('actif', true))
                ->exists(),
            422,
        );

        return $id;
    }

    protected function optionsResponsables(): array
    {
        if ($this->cid() < 1) {
            return [];
        }

        return UserCompagnieRole::query()
            ->with('user')
            ->where('compagnie_id', $this->cid())
            ->whereHas('user', fn ($query) => $query->where('actif', true))
            ->get()
            ->filter(fn (UserCompagnieRole $lien): bool => $lien->user !== null)
            ->mapWithKeys(fn (UserCompagnieRole $lien): array => [$lien->user_id => $lien->user->getFilamentName()])
            ->all();
    }

    protected function optionsControles(): array
    {
        if ($this->cid() < 1) {
            return [];
        }

        return ControleAudit::query()
            ->where('compagnie_id', $this->cid())
            ->orderBy('titre')
            ->pluck('titre', 'id')
            ->all();
    }

    protected function frequences(): array
    {
        return [
            'quotidienne' => 'Quotidienne',
            'hebdomadaire' => 'Hebdomadaire',
            'mensuelle' => 'Mensuelle',
            'trimestrielle' => 'Trimestrielle',
            'annuelle' => 'Annuelle',
            'ponctuelle' => 'Ponctuelle',
        ];
    }

    protected function niveauxRisque(): array
    {
        return [
            'faible' => 'Faible',
            'moyen' => 'Moyen',
            'eleve' => 'Élevé',
            'critique' => 'Critique',
        ];
    }

    protected function priorites(): array
    {
        return [
            'basse' => 'Basse',
            'normale' => 'Normale',
            'haute' => 'Haute',
            'critique' => 'Critique',
        ];
    }

    protected function statutsRecommandation(): array
    {
        return [
            'ouverte' => 'Ouverte',
            'en_cours' => 'En cours',
            'terminee' => 'Terminée',
        ];
    }
}
