<?php

namespace App\Filament\Pages;

use App\Models\ChargeStructure;
use App\Models\Compagnie;
use App\Services\ControleGestion\Acces;
use BackedEnum;
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

class ChargesStructure extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Charges de structure';

    protected static string|UnitEnum|null $navigationGroup = 'Contrôle de gestion';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Charges de structure';

    protected static ?string $slug = 'charges-de-structure';

    protected string $view = 'filament.pages.charges-structure';

    /** mois, annee ou toutes. */
    public string $portee = 'mois';

    public string $compagnieId = '';

    public static function canAccess(): bool
    {
        return Acces::visible(auth()->user());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Loyers, salaires d’agence, frais de siège et d’atelier, répartis ensuite sur les départs';
    }

    public function mount(): void
    {
        $this->compagnieId = auth()->user()->isSuperAdmin()
            ? ''
            : (string) (auth()->user()->compagnieId() ?? '');
    }

    public function updatedCompagnieId(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if ($this->compagnieId !== '') {
            abort_unless(Compagnie::query()->whereKey((int) $this->compagnieId)->exists(), 404);
        }
    }

    protected function cid(): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() ? (int) $this->compagnieId : (int) ($u->compagnieId() ?? 0);
    }

    protected function getViewData(): array
    {
        $u = auth()->user();
        $cid = $this->cid();
        $compagnieIds = $u->isSuperAdmin()
            ? ($this->compagnieId === ''
                ? Compagnie::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all()
                : [(int) $this->compagnieId])
            : [(int) ($u->compagnieId() ?? 0)];

        if ($u->isSuperAdmin() && $this->compagnieId !== '') {
            abort_unless(Compagnie::query()->whereKey((int) $this->compagnieId)->exists(), 404);
        }

        $q = ChargeStructure::query()->visiblePour($u)->whereIn('compagnie_id', $compagnieIds)->with(['gare.ville', 'compagnie']);

        match ($this->portee) {
            'mois' => $q->whereDate('periode_debut', '<=', today()->copy()->endOfMonth()->toDateString())
                ->whereDate('periode_fin', '>=', today()->copy()->startOfMonth()->toDateString()),
            'annee' => $q->whereDate('periode_debut', '<=', today()->copy()->endOfYear()->toDateString())
                ->whereDate('periode_fin', '>=', today()->copy()->startOfYear()->toDateString()),
            default => null,
        };

        return ['d' => [
            'compagnies' => $u->isSuperAdmin()
                ? collect(['' => 'Toutes les compagnies'])->union(Compagnie::query()->orderBy('nom')->pluck('nom', 'id'))
                : collect(),
            'toutesCompagnies' => $u->isSuperAdmin() && $this->compagnieId === '',
            'charges' => $compagnieIds !== [] ? $q->orderByDesc('periode_fin')->get()->groupBy('niveau') : collect(),
            'portees' => ['mois' => 'Actives ce mois', 'annee' => 'Cette année', 'toutes' => 'Toutes'],
        ]];
    }

    // ------------------------------------------------------------------ actions

    protected function getHeaderActions(): array
    {
        return [
            Action::make('creer')
                ->label('Ajouter une charge')
                ->icon('heroicon-o-plus-circle')
                ->modalHeading('Nouvelle charge')
                ->modalDescription('Le montant est étalé jour par jour sur la période, puis réparti sur les départs selon la clé choisie.')
                ->modalWidth('3xl')
                ->schema(fn (): array => $this->champs())
                ->action(function (array $data) {
                    ChargeStructure::create($this->normaliser($data) + [
                        'compagnie_id' => $this->cid(),
                        'cree_par' => auth()->id(),
                    ]);

                    Notification::make()->title('Charge enregistrée')->success()->send();
                })
                ->visible(fn () => $this->cid() > 0),
        ];
    }

    public function modifierAction(): Action
    {
        return Action::make('modifier')
            ->modalHeading('Modifier la charge')
            ->modalWidth('3xl')
            ->fillForm(function (array $arguments): array {
                $c = $this->charge((int) ($arguments['id'] ?? 0));

                return $c ? [
                    'niveau' => $c->niveau,
                    'gare_id' => $c->gare_id,
                    'libelle' => $c->libelle,
                    'montant' => $c->montant,
                    'periode_debut' => $c->periode_debut->toDateString(),
                    'periode_fin' => $c->periode_fin->toDateString(),
                    'cle' => $c->cle,
                    'note' => $c->note,
                ] : [];
            })
            ->schema(fn (): array => $this->champs())
            ->action(function (array $data, array $arguments) {
                $c = $this->charge((int) ($arguments['id'] ?? 0));
                abort_unless($c, 404);

                $c->update($this->normaliser($data));

                Notification::make()->title('Charge modifiée')->success()->send();
            });
    }

    public function supprimerAction(): Action
    {
        return Action::make('supprimer')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer cette charge ?')
            ->modalDescription('Elle ne sera plus comptée dans la rentabilité.')
            ->action(function (array $arguments) {
                $this->charge((int) ($arguments['id'] ?? 0))?->delete();

                Notification::make()->title('Charge supprimée')->success()->send();
            });
    }

    protected function charge(int $id): ?ChargeStructure
    {
        abort_unless(Acces::visible(auth()->user()), 403);

        return ChargeStructure::query()->visiblePour(auth()->user())->where('compagnie_id', $this->cid())->find($id);
    }

    protected function champs(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('niveau')->label('Niveau')
                    ->options($this->niveauxAutorises())->default('gare')->required()->live(),
                Select::make('gare_id')->label('Gare ou agence')
                    ->options(fn () => $this->optionsGares())->searchable()
                    ->visible(fn (Get $get) => $get('niveau') === 'gare')
                    ->required(fn (Get $get) => $get('niveau') === 'gare'),
                TextInput::make('libelle')->label('Libellé')->required()->maxLength(150)
                    ->placeholder('Loyer, électricité, salaires de l’agence…'),
                TextInput::make('montant')->label('Montant sur la période')->numeric()->minValue(1)->required()->suffix('FCFA'),
                DatePicker::make('periode_debut')->label('Du')->default(today()->startOfMonth())->required(),
                DatePicker::make('periode_fin')->label('Au')->default(today()->endOfMonth())->required()->afterOrEqual('periode_debut'),
                Select::make('cle')->label('Répartition sur les départs')
                    ->options(config('controle_gestion.cles'))->default('departs')->required(),
                Textarea::make('note')->label('Note')->rows(2)->maxLength(300)->columnSpanFull(),
            ]),
        ];
    }

    /** Un admin limité à une ville ne saisit que des charges de ses gares. */
    protected function niveauxAutorises(): array
    {
        return Acces::finance(auth()->user())
            ? ChargeStructure::NIVEAUX
            : ['gare' => ChargeStructure::NIVEAUX['gare']];
    }

    protected function optionsGares(): array
    {
        return auth()->user()->garesGerables($this->cid())->with('ville')->orderBy('quartier')->get()
            ->mapWithKeys(fn ($g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')])->all();
    }

    /** Contrôle côté serveur : niveau permis, gare réellement gérable par l'utilisateur. */
    protected function normaliser(array $data): array
    {
        $u = auth()->user();

        abort_unless(Acces::visible($u) && $this->cid() > 0, 403);
        abort_unless(array_key_exists($data['niveau'], $this->niveauxAutorises()), 403);

        $gareId = null;

        if ($data['niveau'] === 'gare') {
            $gareId = (int) ($data['gare_id'] ?? 0);
            abort_unless($u->garesGerables($this->cid())->whereKey($gareId)->exists(), 403);
        }

        return [
            'gare_id' => $gareId,
            'niveau' => $data['niveau'],
            'libelle' => trim($data['libelle']),
            'montant' => (int) $data['montant'],
            'periode_debut' => $data['periode_debut'],
            'periode_fin' => $data['periode_fin'],
            'cle' => $data['cle'],
            'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
        ];
    }
}
