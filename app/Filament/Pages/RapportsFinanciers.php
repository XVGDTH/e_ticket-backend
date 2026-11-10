<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Gares\GareResource;
use App\Models\Compagnie;
use App\Services\Finance\ExportFinancier;
use App\Services\Finance\RapportFinancier;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use UnitEnum;

class RapportsFinanciers extends Page
{
    protected string $view = 'filament.pages.rapports-financiers';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Rapports financiers';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public string $periode = '30j';

    public ?string $debut = null;

    public ?string $fin = null;

    public string $compagnieId = '';

    public string $gareId = '';

    public string $operateur = '';

    public string $statut = 'encaisses';

    public static function canAccess(): bool
    {
        return RapportFinancier::autorise(auth()->user());
    }

    public function getTitle(): string
    {
        return 'Rapports financiers';
    }

    public function updatedCompagnieId(): void
    {
        $this->gareId = '';
    }

    protected function service(): RapportFinancier
    {
        return RapportFinancier::pour(auth()->user());
    }

    protected function filtres(): array
    {
        $periode = array_key_exists($this->periode, RapportFinancier::PERIODES) ? $this->periode : '30j';
        [$debut, $fin] = RapportFinancier::bornes($periode, $this->debut, $this->fin);
        $superAdmin = auth()->user()->isSuperAdmin();

        return [
            'debut' => $debut,
            'fin' => $fin,
            'compagnie_id' => $superAdmin && $this->compagnieId !== '' ? (int) $this->compagnieId : null,
            'gare_id' => $this->gareId !== '' ? (int) $this->gareId : null,
            'operateur' => $this->operateur !== '' ? $this->operateur : null,
            'statut' => in_array($this->statut, ['encaisses', 'tous', 'rembourse'], true) ? $this->statut : 'encaisses',
        ];
    }

    protected function options(RapportFinancier $r): array
    {
        $user = auth()->user();
        $superAdmin = $user->isSuperAdmin();

        return [
            'compagnies' => $superAdmin ? Compagnie::orderBy('nom')->pluck('nom', 'id')->all() : [],
            'gares' => GareResource::getEloquentQuery()->with('compagnie')
                ->when($superAdmin && $this->compagnieId !== '', fn ($q) => $q->where('compagnie_id', (int) $this->compagnieId))
                ->orderBy('quartier')
                ->get()
                ->mapWithKeys(fn ($g) => [$g->id => $g->quartier.($superAdmin ? ' · '.($g->compagnie?->nom ?? '') : '')])
                ->all(),
            'operateurs' => $r->base()->whereNotNull('b.operateur_paiement')->distinct()
                ->orderBy('b.operateur_paiement')->pluck('b.operateur_paiement')->all(),
        ];
    }

    public function donnees(): array
    {
        $r = $this->service();
        $f = $this->filtres();
        $s = $r->synthese($f);

        $debutHeat = now()->startOfWeek()->subWeeks(11)->startOfDay();
        $heat = $r->groupe([...$f, 'debut' => $debutHeat, 'fin' => now()->endOfDay(), 'statut' => 'encaisses'], 'jour')
            ->pluck('total', 'libelle')
            ->all();

        return [
            'f' => $f,
            't' => $s['totaux'],
            'p' => $r->totaux($r->periodePrecedente($f)),
            'remb' => $s['remboursements'],
            'granularite' => $s['granularite'],
            'serie' => $s['par_periode'],
            'compagnies' => $s['par_compagnie'],
            'gares' => $s['par_gare'],
            'operateurs' => $s['par_operateur'],
            'heat' => $heat,
            'perimetre' => $r->perimetre($f),
            'options' => $this->options($r),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('pdf')
                    ->label('Rapport PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(fn () => $this->exporter('pdf')),
                Action::make('xlsx')
                    ->label('Excel complet (.xlsx)')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn () => $this->exporter('xlsx')),
                Action::make('csv')
                    ->label('CSV (synthèse)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => $this->exporter('csv')),
            ])
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->button(),
        ];
    }

    public function exporter(string $format)
    {
        abort_unless(RapportFinancier::autorise(auth()->user()), 403);

        $r = $this->service();
        $f = $this->filtres();
        $s = $r->synthese($f);
        $t = $s['totaux'];
        $remb = $s['remboursements'];
        $g = $s['granularite'];

        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');

        $kpis = [
            ['Total encaissé', $fmt($t->total).' FCFA'],
            ['Prix des billets', $fmt($t->ca).' FCFA'],
            ['Frais collectés', $fmt($t->frais).' FCFA'],
            ['Billets', $fmt($t->billets)],
            ['Voyageurs', $fmt($t->voyageurs)],
            ['Panier moyen', $fmt($t->billets > 0 ? $t->total / $t->billets : 0).' FCFA'],
            ['Clients actifs', $fmt($t->clients)],
        ];

        if ((int) $remb->billets > 0) {
            $kpis[] = ['Remboursés (hors totaux)', $fmt($remb->billets).' billets · '.$fmt($remb->montant).' FCFA'];
        }

        $sections = [
            RapportFinancier::section('Par compagnie', 'Compagnie', $s['par_compagnie']),
            RapportFinancier::section('Par gare', 'Gare', $s['par_gare']),
            RapportFinancier::section('Par ville de départ', 'Ville', $s['par_ville']),
            RapportFinancier::section('Par opérateur', 'Opérateur de paiement', $s['par_operateur']),
            RapportFinancier::section('Par '.$g, 'Période', $s['par_periode'], $g),
        ];

        if ($format === 'xlsx') {
            $sections[] = [
                'titre' => 'Transactions',
                'entetes' => RapportFinancier::ENTETES_TRANSACTIONS,
                'lignes' => $r->lignesTransactions($f),
            ];
        }

        $meta = [
            'Période' => $f['debut']->format('d/m/Y').' au '.$f['fin']->format('d/m/Y'),
            'Périmètre' => $r->perimetre($f),
            'Billets pris en compte' => match ($f['statut']) {
                'tous' => 'Tous les paiements',
                'rembourse' => 'Remboursés uniquement',
                default => 'Billets encaissés',
            },
            'Généré le' => now()->format('d/m/Y H:i'),
            'Généré par' => auth()->user()->getFilamentName(),
        ];

        return ExportFinancier::exporter(
            $format,
            'rapport-financier-'.now()->format('Ymd-His'),
            'Rapport financier des ventes',
            $meta,
            $kpis,
            $sections,
        );
    }
}
