<?php

namespace App\Filament\Pages;

use App\Models\ArticleStock;
use App\Models\Compagnie;
use App\Models\FactureFournisseur;
use App\Models\Fournisseur;
use App\Models\MouvementStockFournisseur;
use App\Models\PaiementFournisseur;
use App\Models\ReceptionFournisseur;
use App\Services\AuditInterne\Acces;
use App\Services\AuditInterne\CycleFournisseurs as CycleFournisseursService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class CycleFournisseurs extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Fournisseurs et stock';

    protected static string|UnitEnum|null $navigationGroup = 'Contrôle interne et audit';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Cycle fournisseurs';

    protected static ?string $slug = 'cycle-fournisseurs';

    protected string $view = 'filament.pages.cycle-fournisseurs';

    public string $compagnieId = '';

    public static function canAccess(): bool
    {
        return Acces::visible(auth()->user());
    }

    public function getTitle(): string
    {
        return 'Cycle fournisseurs';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Suivez les tiers, les réceptions, les factures et les paiements avec validation séparée.';
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

        if ($this->compagnieId !== '') {
            abort_unless(Compagnie::query()->whereKey((int) $this->compagnieId)->exists(), 404);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('creerFournisseur')
                ->label('Nouveau fournisseur')
                ->icon('heroicon-o-building-office-2')
                ->modalHeading('Ajouter un fournisseur')
                ->schema([
                    TextInput::make('nom')->label('Nom / raison sociale')->required()->maxLength(150),
                    TextInput::make('identifiant_fiscal')->label('Identifiant fiscal')->maxLength(80),
                    Grid::make(2)->schema([
                        TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
                        TextInput::make('email')->label('Courriel')->email()->maxLength(150),
                    ]),
                    TextInput::make('adresse')->label('Adresse')->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->verifierCompagnie();
                    $this->service()->creerFournisseur(auth()->user(), $this->cid(), $data);
                    Notification::make()->title('Fournisseur enregistré')->success()->send();
                })
                ->visible(fn (): bool => $this->cid() > 0),
            Action::make('creerArticleStock')
                ->label('Nouvel article')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->modalHeading('Ajouter un article au stock')
                ->schema([
                    TextInput::make('reference')->label('Référence')->maxLength(50),
                    TextInput::make('nom')->label('Désignation')->required()->maxLength(150),
                    TextInput::make('unite')->label('Unité de mesure')->required()->maxLength(30)
                        ->placeholder('pièce, kg, litre...'),
                ])
                ->action(function (array $data): void {
                    $this->verifierCompagnie();
                    $this->service()->creerArticleStock(auth()->user(), $this->cid(), $data);
                    Notification::make()->title('Article ajouté au stock')->success()->send();
                })
                ->visible(fn (): bool => $this->cid() > 0),
            Action::make('creerReception')
                ->label('Nouvelle réception')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->modalHeading('Enregistrer une réception')
                ->modalWidth('4xl')
                ->schema([
                    Select::make('fournisseur_id')
                        ->label('Fournisseur')
                        ->options(fn (): array => $this->optionsFournisseurs())
                        ->searchable()
                        ->required(),
                    DatePicker::make('recu_le')->label('Date de réception')->default(today())->required(),
                    Textarea::make('description')->label('Observations')->rows(2)->maxLength(5000)->columnSpanFull(),
                    Repeater::make('lignes')
                        ->label('Articles reçus')
                        ->schema([
                            Select::make('article_id')
                                ->label('Article')
                                ->options(fn (): array => $this->optionsArticles())
                                ->searchable()
                                ->required(),
                            TextInput::make('quantite')
                                ->label('Quantité')
                                ->numeric()
                                ->minValue(0.001)
                                ->step(0.001)
                                ->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->maxItems(50)
                        ->required()
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $this->verifierCompagnie();
                    $this->service()->creerReception(auth()->user(), $this->cid(), $data);
                    Notification::make()->title('Réception enregistrée, en attente de validation')->success()->send();
                })
                ->visible(fn (): bool => $this->cid() > 0 && $this->optionsFournisseurs() !== [] && $this->optionsArticles() !== []),
            Action::make('creerFacture')
                ->label('Nouvelle facture')
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->modalHeading('Enregistrer une facture fournisseur')
                ->modalWidth('3xl')
                ->schema([
                    Select::make('reception_id')
                        ->label('Réception validée')
                        ->options(fn (): array => $this->optionsReceptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('reference_fournisseur')
                        ->label('Référence sur la facture')
                        ->required()
                        ->maxLength(100),
                    Grid::make(2)->schema([
                        DatePicker::make('date_facture')->label('Date de facture')->default(today())->required(),
                        DatePicker::make('echeance')->label('Échéance de paiement'),
                    ]),
                    TextInput::make('montant')
                        ->label('Montant total (FCFA)')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    FileUpload::make('piece_path')
                        ->label('Pièce justificative')
                        ->disk('local')
                        ->directory('audit/fournisseurs')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(10240)
                        ->helperText('PDF, JPG ou PNG; taille maximale 10 Mo.'),
                ])
                ->action(function (array $data): void {
                    $this->verifierCompagnie();
                    $this->service()->creerFacture(auth()->user(), $this->cid(), $data);
                    Notification::make()->title('Facture enregistrée, en attente de validation')->success()->send();
                })
                ->visible(fn (): bool => $this->cid() > 0 && $this->optionsReceptions() !== []),
            Action::make('creerPaiement')
                ->label('Saisir un paiement')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->modalHeading('Saisir un paiement fournisseur')
                ->schema([
                    Select::make('facture_id')
                        ->label('Facture validée')
                        ->options(fn (): array => $this->optionsFactures())
                        ->searchable()
                        ->required(),
                    TextInput::make('montant')
                        ->label('Montant du paiement (FCFA)')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    DatePicker::make('paye_le')->label('Date du paiement')->default(today())->required(),
                    Select::make('mode')
                        ->label('Mode de paiement')
                        ->options([
                            'especes' => 'Espèces',
                            'cheque' => 'Chèque',
                            'virement' => 'Virement',
                            'mobile_money' => 'Mobile money',
                            'autre' => 'Autre',
                        ])
                        ->required(),
                    TextInput::make('reference')->label('Référence de transaction')->maxLength(100),
                ])
                ->action(function (array $data): void {
                    $this->verifierCompagnie();
                    $this->service()->creerPaiement(auth()->user(), $this->cid(), $data);
                    Notification::make()->title('Paiement enregistré, en attente de validation')->success()->send();
                })
                ->visible(fn (): bool => $this->cid() > 0 && $this->optionsFactures() !== []),
        ];
    }

    public function validerReceptionAction(): Action
    {
        return Action::make('validerReception')
            ->label('Valider la réception et entrer en stock')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Valider cette réception ?')
            ->modalDescription('La validation enregistrera les quantités reçues dans le stock.')
            ->action(function (array $arguments): void {
                $this->verifierCompagnie();
                $this->service()->validerReception(auth()->user(), $this->cid(), (int) ($arguments['id'] ?? 0));
                Notification::make()->title('Réception validée et stock mis à jour')->success()->send();
            });
    }

    public function validerFactureAction(): Action
    {
        return Action::make('validerFacture')
            ->label('Valider la facture')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Valider cette facture ?')
            ->action(function (array $arguments): void {
                $this->verifierCompagnie();
                $this->service()->validerFacture(auth()->user(), $this->cid(), (int) ($arguments['id'] ?? 0));
                Notification::make()->title('Facture validée')->success()->send();
            });
    }

    public function validerPaiementAction(): Action
    {
        return Action::make('validerPaiement')
            ->label('Valider le paiement')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Valider ce paiement ?')
            ->action(function (array $arguments): void {
                $this->verifierCompagnie();
                $this->service()->validerPaiement(auth()->user(), $this->cid(), (int) ($arguments['id'] ?? 0));
                Notification::make()->title('Paiement validé')->success()->send();
            });
    }

    protected function getViewData(): array
    {
        $compagnieId = $this->cid();
        $toutesCompagnies = auth()->user()->isSuperAdmin() && $this->compagnieId === '';
        $parCompagnie = $toutesCompagnies
            ? Compagnie::query()->orderBy('nom')->get(['id', 'nom'])->map(fn (Compagnie $compagnie): array => [
                'id' => (int) $compagnie->id,
                'nom' => $compagnie->nom,
                'fournisseurs' => Fournisseur::query()->where('compagnie_id', $compagnie->id)->count(),
                'articles' => ArticleStock::query()->where('compagnie_id', $compagnie->id)->count(),
                'receptions' => ReceptionFournisseur::query()->where('compagnie_id', $compagnie->id)->count(),
                'factures' => FactureFournisseur::query()->where('compagnie_id', $compagnie->id)->count(),
                'paiementsEnAttente' => PaiementFournisseur::query()->where('compagnie_id', $compagnie->id)->where('statut', 'en_attente')->count(),
            ])
            : collect();

        return [
            'd' => [
                'compagnies' => auth()->user()->isSuperAdmin()
                    ? collect(['' => 'Toutes les compagnies'])->union(Compagnie::query()->orderBy('nom')->pluck('nom', 'id'))
                    : collect(),
                'toutesCompagnies' => $toutesCompagnies,
                'parCompagnie' => $parCompagnie,
                'nomCompagnie' => Compagnie::query()->whereKey($compagnieId)->value('nom') ?? '—',
                'fournisseurs' => $compagnieId > 0
                    ? Fournisseur::query()->where('compagnie_id', $compagnieId)->orderBy('nom')->limit(100)->get()
                    : collect(),
                'articles' => $compagnieId > 0
                    ? ArticleStock::query()->where('compagnie_id', $compagnieId)->orderBy('nom')->limit(100)->get()
                    : collect(),
                'receptions' => $compagnieId > 0
                    ? ReceptionFournisseur::query()->where('compagnie_id', $compagnieId)
                        ->with(['fournisseur', 'createur', 'lignes.article'])
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get()
                    : collect(),
                'mouvements' => $compagnieId > 0
                    ? MouvementStockFournisseur::query()->where('compagnie_id', $compagnieId)
                        ->with(['article', 'reception'])
                        ->orderByDesc('cree_le')
                        ->orderByDesc('id')
                        ->limit(50)
                        ->get()
                    : collect(),
                'factures' => $compagnieId > 0
                    ? FactureFournisseur::query()->where('compagnie_id', $compagnieId)
                        ->with(['fournisseur', 'reception', 'createur', 'paiements.createur'])
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get()
                    : collect(),
                'paiementsEnAttente' => $compagnieId > 0
                    ? PaiementFournisseur::query()->where('compagnie_id', $compagnieId)
                        ->where('statut', 'en_attente')
                        ->count()
                    : 0,
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

    protected function service(): CycleFournisseursService
    {
        return app(CycleFournisseursService::class);
    }

    protected function optionsFournisseurs(): array
    {
        return $this->cid() > 0
            ? Fournisseur::query()->where('compagnie_id', $this->cid())->where('actif', true)->orderBy('nom')->pluck('nom', 'id')->all()
            : [];
    }

    protected function optionsArticles(): array
    {
        return $this->cid() > 0
            ? ArticleStock::query()->where('compagnie_id', $this->cid())
                ->orderBy('nom')
                ->get()
                ->mapWithKeys(fn (ArticleStock $article): array => [
                    $article->id => $article->nom.' · '.$article->unite.' · stock '.$article->quantite_stock,
                ])
                ->all()
            : [];
    }

    protected function optionsReceptions(): array
    {
        return $this->cid() > 0
            ? ReceptionFournisseur::query()->where('compagnie_id', $this->cid())
                ->where('statut', 'validee')
                ->with('fournisseur')
                ->orderByDesc('id')
                ->get()
                ->mapWithKeys(fn (ReceptionFournisseur $reception): array => [
                    $reception->id => $reception->numero.' · '.$reception->fournisseur->nom,
                ])
                ->all()
            : [];
    }

    protected function optionsFactures(): array
    {
        return $this->cid() > 0
            ? FactureFournisseur::query()->where('compagnie_id', $this->cid())
                ->whereIn('statut', ['validee', 'partiellement_reglee'])
                ->with('fournisseur')
                ->orderByDesc('id')
                ->get()
                ->mapWithKeys(fn (FactureFournisseur $facture): array => [
                    $facture->id => $facture->numero.' · '.$facture->fournisseur->nom.' · '.number_format($facture->montant, 0, ',', ' ').' FCFA',
                ])
                ->all()
            : [];
    }
}
