<?php

namespace App\Filament\Pages;

use App\Models\Compagnie;
use App\Models\FraisRegle;
use App\Models\Gare;
use App\Services\Finance\RapportFinancier;
use App\Services\Frais;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class GrilleFrais extends Page
{
    protected string $view = 'filament.pages.grille-frais';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Grille des frais';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    public string $compagnieId = '';

    public string $gareId = '';

    /** @var array<string,string> frais saisis par tranche ; vide = suivre la grille du dessus */
    public array $saisies = [];

    public static function canAccess(): bool
    {
        return RapportFinancier::autorise(auth()->user());
    }

    public function getTitle(): string
    {
        return 'Grille des frais';
    }

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin()) {
            $this->compagnieId = (string) $user->compagnieId();

            // Un admin limité à une ville travaille toujours sur une de ses gares.
            if ($user->villeId()) {
                $this->gareId = (string) ($user->garesGerables()->orderBy('quartier')->value('gares.id') ?? '');
            }
        }

        $this->charger();
    }

    public function updatedCompagnieId(): void
    {
        $this->gareId = '';
        $this->charger();
    }

    public function updatedGareId(): void
    {
        $this->charger();
    }

    /** @return array{compagnie_id: ?int, gare_id: ?int} */
    protected function portee(): array
    {
        $user = auth()->user();
        $superAdmin = $user->isSuperAdmin();

        $compagnieId = $superAdmin
            ? ($this->compagnieId !== '' ? (int) $this->compagnieId : null)
            : (int) $user->compagnieId();
        $gareId = $this->gareId !== '' ? (int) $this->gareId : null;

        abort_if(! $superAdmin && ! $compagnieId, 403);

        if ($gareId) {
            $gare = Gare::findOrFail($gareId);

            abort_unless(
                $superAdmin
                    ? (! $compagnieId || (int) $gare->compagnie_id === $compagnieId)
                    : $user->garesGerables()->whereKey($gareId)->exists(),
                403
            );

            $compagnieId = (int) $gare->compagnie_id;
        }

        abort_if(! $superAdmin && $user->villeId() && ! $gareId, 403);

        return ['compagnie_id' => $compagnieId, 'gare_id' => $gareId];
    }

    protected function estBase(array $portee): bool
    {
        return $portee['compagnie_id'] === null && $portee['gare_id'] === null;
    }

    protected function regle(string $trajet, int $min, ?int $max, ?int $compagnieId, ?int $gareId): ?FraisRegle
    {
        return FraisRegle::query()
            ->when($compagnieId, fn ($q) => $q->where('compagnie_id', $compagnieId), fn ($q) => $q->whereNull('compagnie_id'))
            ->when($gareId, fn ($q) => $q->where('gare_id', $gareId), fn ($q) => $q->whereNull('gare_id'))
            ->whereNull('route_transport_id')
            ->where('trajet', $trajet)
            ->where('prix_min', $min)
            ->when($max !== null, fn ($q) => $q->where('prix_max', $max), fn ($q) => $q->whereNull('prix_max'))
            ->where('mode', 'fixe')
            ->first();
    }

    /** Frais que cette portée suit quand elle n'a pas de valeur à elle : la compagnie pour une gare, sinon la base. */
    protected function heritage(FraisRegle $base, array $portee): int
    {
        if ($portee['gare_id'] && $portee['compagnie_id']) {
            $compagnie = $this->regle($base->trajet, $base->prix_min, $base->prix_max, $portee['compagnie_id'], null);

            if ($compagnie) {
                return (int) $compagnie->valeur;
            }
        }

        return (int) $base->valeur;
    }

    public function charger(): void
    {
        $portee = $this->portee();
        $this->saisies = [];

        foreach (Frais::paliers() as $base) {
            $propre = $this->estBase($portee)
                ? $base
                : $this->regle($base->trajet, $base->prix_min, $base->prix_max, $portee['compagnie_id'], $portee['gare_id']);

            $this->saisies[Frais::cle($base)] = $propre ? (string) (int) $propre->valeur : '';
        }
    }

    public function enregistrer(): void
    {
        $portee = $this->portee();
        $user = auth()->user();
        $paliers = Frais::paliers();

        foreach ($paliers as $base) {
            $saisie = trim((string) ($this->saisies[Frais::cle($base)] ?? ''));

            if ($saisie !== '' && (! ctype_digit($saisie) || (int) $saisie > 100000)) {
                Notification::make()->title('Montant invalide')->body('Indiquez un nombre entier en FCFA, sans espace ni virgule.')->danger()->send();

                return;
            }
        }

        $changements = 0;

        foreach ($paliers as $base) {
            $saisie = trim((string) ($this->saisies[Frais::cle($base)] ?? ''));

            if ($this->estBase($portee)) {
                abort_unless($user->isSuperAdmin(), 403);

                if ($saisie !== '' && (int) $base->valeur !== (int) $saisie) {
                    $base->update(['valeur' => (int) $saisie]);
                    $changements++;
                }

                continue;
            }

            $propre = $this->regle($base->trajet, $base->prix_min, $base->prix_max, $portee['compagnie_id'], $portee['gare_id']);

            if ($saisie === '') {
                if ($propre) {
                    $propre->delete();
                    $changements++;
                }

                continue;
            }

            $valeur = (int) $saisie;

            if ($propre) {
                if ((int) $propre->valeur !== $valeur) {
                    $propre->update(['valeur' => $valeur, 'actif' => true]);
                    $changements++;
                }
            } elseif ($valeur !== $this->heritage($base, $portee)) {
                FraisRegle::create([
                    'compagnie_id' => $portee['compagnie_id'],
                    'gare_id' => $portee['gare_id'],
                    'route_transport_id' => null,
                    'trajet' => $base->trajet,
                    'prix_min' => $base->prix_min,
                    'prix_max' => $base->prix_max,
                    'mode' => 'fixe',
                    'valeur' => $valeur,
                    'actif' => true,
                    'note' => 'Grille des frais',
                ]);
                $changements++;
            }
        }

        Frais::oublier();
        $this->charger();

        Notification::make()
            ->title($changements > 0 ? 'Grille enregistrée' : 'Aucun changement')
            ->body($changements > 0 ? $changements.' tranche(s) modifiée(s). Les billets déjà vendus gardent leurs frais.' : null)
            ->success()
            ->send();
    }

    public function retablir(string $cle): void
    {
        $portee = $this->portee();

        abort_if($this->estBase($portee), 403);

        $base = Frais::paliers()->first(fn (FraisRegle $r) => Frais::cle($r) === $cle);

        abort_unless($base, 404);

        $this->regle($base->trajet, $base->prix_min, $base->prix_max, $portee['compagnie_id'], $portee['gare_id'])?->delete();

        Frais::oublier();
        $this->charger();

        Notification::make()->title('Tranche rétablie')->success()->send();
    }

    public function restaurerBase(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        foreach (Frais::GRILLE_BASE as $palier) {
            $regle = $this->regle($palier['trajet'], $palier['prix_min'], $palier['prix_max'], null, null);

            if ($regle) {
                $regle->update(['valeur' => $palier['valeur'], 'actif' => true]);
            } else {
                FraisRegle::create([
                    'trajet' => $palier['trajet'],
                    'prix_min' => $palier['prix_min'],
                    'prix_max' => $palier['prix_max'],
                    'mode' => 'fixe',
                    'valeur' => $palier['valeur'],
                    'actif' => true,
                    'note' => 'Grille de base',
                ]);
            }
        }

        Frais::oublier();
        $this->charger();

        Notification::make()->title('Grille de base restaurée')->success()->send();
    }

    public function donnees(): array
    {
        $user = auth()->user();
        $superAdmin = $user->isSuperAdmin();
        $portee = $this->portee();

        $gares = collect();

        if ($superAdmin) {
            if ($portee['compagnie_id']) {
                $gares = Gare::where('compagnie_id', $portee['compagnie_id'])->with('ville')->orderBy('quartier')->get();
            }
        } else {
            $gares = $user->garesGerables()->with('ville')->orderBy('quartier')->get();
        }

        $nomCompagnie = $portee['compagnie_id'] ? Compagnie::whereKey($portee['compagnie_id'])->value('nom') : null;
        $nomGare = $portee['gare_id'] ? Gare::whereKey($portee['gare_id'])->value('quartier') : null;

        $niveau = $portee['gare_id'] ? 'gare' : ($portee['compagnie_id'] ? 'compagnie' : 'base');

        $paliers = Frais::paliers()->map(function (FraisRegle $base) use ($portee, $niveau) {
            $propre = $niveau === 'base'
                ? null
                : $this->regle($base->trajet, $base->prix_min, $base->prix_max, $portee['compagnie_id'], $portee['gare_id']);
            $heritee = $niveau === 'base' ? (int) $base->valeur : $this->heritage($base, $portee);

            $source = match (true) {
                $niveau === 'base' => 'base',
                $propre !== null => 'propre',
                $niveau === 'gare' && $heritee !== (int) $base->valeur => 'compagnie',
                default => 'base',
            };

            return [
                'cle' => Frais::cle($base),
                'trajet' => $base->trajet,
                'libelle' => Frais::libelleTranche($base),
                'effectif' => $propre ? (int) $propre->valeur : $heritee,
                'heritee' => $heritee,
                'source' => $source,
            ];
        })->values()->all();

        return [
            'superAdmin' => $superAdmin,
            'compagnies' => $superAdmin ? Compagnie::orderBy('nom')->pluck('nom', 'id')->all() : [],
            'gares' => $gares->mapWithKeys(fn (Gare $g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')])->all(),
            'niveau' => $niveau,
            'peutToutelaCompagnie' => $superAdmin || ! $user->villeId(),
            'titre' => match ($niveau) {
                'base' => 'Grille de base, toutes les compagnies',
                'compagnie' => $nomCompagnie.' · toutes les gares',
                default => $nomCompagnie.' · '.$nomGare,
            },
            'paliers' => $paliers,
        ];
    }
}
