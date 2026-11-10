<?php

namespace App\Filament\Pages;

use App\Models\Compagnie;
use App\Models\CptCompte;
use App\Models\CptEcriture;
use App\Models\CptExercice;
use App\Services\Compta;
use App\Services\Finance\ExportFinancier;
use App\Services\Modules;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use UnitEnum;

class EtatsComptables extends Page
{
    protected string $view = 'filament.pages.etats-comptables';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'États financiers';

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?int $navigationSort = 3;

    public string $compagnieId = '';

    public string $exerciceId = '';

    public string $onglet = 'resultat';

    public string $compteId = '';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && Modules::visiblePour($u, 'comptabilite')
            && ($u->isSuperAdmin() || ($u->isAdminCompagnie() && ! $u->villeId()));
    }

    public function getTitle(): string
    {
        return 'États financiers';
    }

    public function mount(): void
    {
        $u = auth()->user();

        $this->compagnieId = $u->isSuperAdmin()
            ? (string) (Compagnie::orderBy('nom')->value('id') ?? '')
            : (string) $u->compagnieId();

        $this->preparer();
    }

    public function updatedCompagnieId(): void
    {
        $this->preparer();
    }

    protected function cid(): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() ? (int) $this->compagnieId : (int) $u->compagnieId();
    }

    protected function preparer(): void
    {
        $cid = $this->cid();

        if ($cid) {
            Compta::initialiser($cid);
            $this->exerciceId = (string) (Compta::exerciceCourant($cid)?->id ?? '');
        }

        $this->compteId = '';
    }

    public function donnees(): array
    {
        $u = auth()->user();
        $cid = $this->cid();

        $exercice = CptExercice::where('compagnie_id', $cid)->find((int) $this->exerciceId);
        $soldes = $exercice ? Compta::soldes($cid, $exercice->id) : collect();
        $cr = Compta::compteDeResultat($soldes);

        $d = [
            'compagnies' => $u->isSuperAdmin() ? Compagnie::orderBy('nom')->pluck('nom', 'id')->all() : [],
            'nomCompagnie' => Compagnie::whereKey($cid)->value('nom') ?? '—',
            'exercices' => Compta::exercices($cid)->mapWithKeys(fn ($e) => [$e->id => $e->libelle.' ('.$e->date_debut->format('d/m/Y').' – '.$e->date_fin->format('d/m/Y').')'])->all(),
            'exercice' => $exercice,
            'brouillons' => $exercice ? CptEcriture::where('compagnie_id', $cid)->where('exercice_id', $exercice->id)->where('statut', 'brouillon')->count() : 0,
            'resultat' => $cr,
            'bilan' => null,
            'balance' => [],
            'analytique' => [],
            'grandLivre' => [],
            'comptes' => [],
        ];

        if (! $exercice) {
            return $d;
        }

        match ($this->onglet) {
            'bilan' => $d['bilan'] = Compta::bilan($soldes),
            'balance' => $d['balance'] = Compta::balance($soldes),
            'analytique' => $d['analytique'] = Compta::analytique($cid, $exercice->id),
            'grand_livre' => (function () use (&$d, $cid, $exercice, $soldes) {
                $numeros = $soldes->pluck('numero')->all();
                $d['comptes'] = CptCompte::whereIn('numero', $numeros)
                    ->where(fn ($q) => $q->whereNull('compagnie_id')->orWhere('compagnie_id', $cid))
                    ->orderBy('numero')->get()
                    ->mapWithKeys(fn ($c) => [$c->id => $c->numero.' · '.$c->intitule])->all();

                if ($this->compteId !== '' && array_key_exists((int) $this->compteId, $d['comptes'])) {
                    $d['grandLivre'] = Compta::grandLivre($cid, $exercice->id, (int) $this->compteId);
                }
            })(),
            default => null,
        };

        return $d;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document-text')->action(fn () => $this->exporter('pdf')),
                Action::make('xlsx')->label('Excel (.xlsx)')->icon('heroicon-o-table-cells')->action(fn () => $this->exporter('xlsx')),
                Action::make('csv')->label('CSV')->icon('heroicon-o-arrow-down-tray')->action(fn () => $this->exporter('csv')),
            ])->label('Exporter')->icon('heroicon-o-arrow-down-tray')->color('primary')->button(),
        ];
    }

    public function exporter(string $format)
    {
        abort_unless(static::canAccess(), 403);

        $d = $this->donnees();
        abort_unless($d['exercice'], 404);

        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' FCFA';
        $cr = $d['resultat'];

        $sections = match ($this->onglet) {
            'bilan' => [
                [
                    'titre' => 'Actif',
                    'entetes' => ['Poste', 'Brut', 'Amortissements', 'Net'],
                    'lignes' => collect($d['bilan']['actif'])->map(fn ($l) => [$l['libelle'], $l['brut'] ?? '', $l['amort'] ?? '', $l['net']])->all(),
                ],
                [
                    'titre' => 'Passif',
                    'entetes' => ['Poste', 'Montant'],
                    'lignes' => collect($d['bilan']['passif'])->map(fn ($l) => [$l['libelle'], $l['net']])->all(),
                ],
            ],
            'balance' => [[
                'titre' => 'Balance générale',
                'entetes' => ['Compte', 'Intitulé', 'Débit', 'Crédit', 'Solde débiteur', 'Solde créditeur'],
                'lignes' => $d['balance'],
            ]],
            'grand_livre' => [[
                'titre' => 'Grand livre : '.($d['comptes'][(int) $this->compteId] ?? ''),
                'entetes' => ['Date', 'Écriture', 'Libellé', 'Débit', 'Crédit', 'Solde cumulé'],
                'lignes' => $d['grandLivre'],
            ]],
            'analytique' => [[
                'titre' => 'Résultat par section analytique',
                'entetes' => ['Section', 'Produits', 'Charges', 'Résultat'],
                'lignes' => collect($d['analytique'])->map(fn ($l) => [$l['code'].' · '.$l['libelle'], $l['produits'], $l['charges'], $l['resultat']])->all(),
            ]],
            default => [[
                'titre' => 'Compte de résultat',
                'entetes' => ['Poste', 'Montant (FCFA)'],
                'lignes' => collect($cr['lignes'])->map(fn ($l) => [$l['libelle'], $l['montant']])->all(),
            ]],
        };

        return ExportFinancier::exporter(
            $format,
            'etats-comptables-'.$this->onglet.'-'.now()->format('Ymd-His'),
            'États comptables : '.$d['nomCompagnie'],
            [
                'Compagnie' => $d['nomCompagnie'],
                'Exercice' => $d['exercice']->libelle.' ('.$d['exercice']->date_debut->format('d/m/Y').' au '.$d['exercice']->date_fin->format('d/m/Y').')',
                'Base' => 'Écritures comptabilisées uniquement',
                'Généré le' => now()->format('d/m/Y H:i'),
                'Généré par' => auth()->user()->getFilamentName(),
            ],
            [
                ['Résultat net', $fmt($cr['resultat'])],
                ['Chiffre d\'affaires', $fmt($cr['chiffre_affaires'])],
                ['Valeur ajoutée', $fmt($cr['valeur_ajoutee'])],
                ['Excédent brut d\'exploitation', $fmt($cr['ebe'])],
            ],
            $sections,
        );
    }
}
