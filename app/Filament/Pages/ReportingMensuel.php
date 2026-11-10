<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ChoisitCompagnie;
use App\Models\CgCloture;
use App\Models\Compagnie;
use App\Services\ControleGestion\Acces;
use App\Services\ControleGestion\Reporting;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use UnitEnum;

class ReportingMensuel extends Page
{
    use ChoisitCompagnie;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Reporting mensuel';

    protected static string|UnitEnum|null $navigationGroup = 'Contrôle de gestion';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Reporting mensuel';

    protected static ?string $slug = 'reporting-mensuel';

    protected string $view = 'filament.pages.reporting-mensuel';

    public string $annee = '';

    public string $mois = '';

    protected ?array $cache = null;

    public static function canAccess(): bool
    {
        return Acces::finance(auth()->user());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Clôture du mois : chiffres arrêtés, commentaires figés, exports PDF et Excel';
    }

    public function mount(): void
    {
        $this->initCompagnie();

        $d = today()->copy()->subMonthNoOverflow();
        $this->annee = (string) $d->year;
        $this->mois = (string) $d->month;
    }

    protected function periode(): array
    {
        return [(int) $this->annee, max(1, min(12, (int) $this->mois))];
    }

    protected function cloture(): ?CgCloture
    {
        [$annee, $mois] = $this->periode();

        return CgCloture::where('compagnie_id', $this->cid())->where('annee', $annee)->where('mois', $mois)->with('clotureur')->first();
    }

    /** Chiffres arrêtés si le mois est clôturé, sinon calcul en direct (provisoire). */
    protected function rapport(): ?array
    {
        if ($this->cid() <= 0) {
            return null;
        }

        if ($this->cache !== null) {
            return $this->cache;
        }

        [$annee, $mois] = $this->periode();

        return $this->cache = $this->cloture()?->snapshot ?? Reporting::mois($this->cid(), $annee, $mois);
    }

    protected function getViewData(): array
    {
        $rapports = $this->toutesCompagnies()
            ? collect($this->cids())->mapWithKeys(function (int $compagnieId): array {
                [$annee, $mois] = $this->periode();
                $cloture = CgCloture::query()
                    ->where('compagnie_id', $compagnieId)
                    ->where('annee', $annee)
                    ->where('mois', $mois)
                    ->with('clotureur')
                    ->first();

                return [$compagnieId => [
                    'rapport' => $cloture?->snapshot ?? Reporting::mois($compagnieId, $annee, $mois),
                    'cloture' => $cloture,
                ]];
            })
            : collect();
        $r = $this->rapport();
        $cloture = $r ? $this->cloture() : null;

        return [
            'compagnies' => $this->compagniesChoix(),
            'compagnieNoms' => Compagnie::query()->whereIn('id', $this->cids())->pluck('nom', 'id'),
            'rapports' => $rapports,
            'annees' => range(today()->year - 2, today()->year + 1),
            'mois_noms' => $this->nomsMois(),
            'r' => $r,
            'cloture' => $cloture,
            'bloquants' => ($r && ! $cloture) ? Reporting::bloquants($r) : [],
        ];
    }

    // ------------------------------------------------------------------ actions

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cloturer')
                ->label('Clôturer le mois')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Clôturer ce mois ?')
                ->modalDescription('Les chiffres sont arrêtés et les commentaires figés. Seul un super administrateur peut rouvrir un mois clôturé.')
                ->visible(fn () => $this->cid() > 0 && ! $this->cloture())
                ->action(function () {
                    abort_unless(Acces::finance(auth()->user()) && $this->cid() > 0, 403);

                    [$annee, $mois] = $this->periode();

                    try {
                        Reporting::cloturer($this->cid(), $annee, $mois, auth()->user());
                        Notification::make()->title('Mois clôturé')->success()->send();
                    } catch (\DomainException $e) {
                        Notification::make()->title('Clôture impossible')->body($e->getMessage())->danger()->send();
                    }

                    $this->cache = null;
                }),

            Action::make('rouvrir')
                ->label('Rouvrir le mois')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Rouvrir ce mois ?')
                ->modalDescription('Les chiffres arrêtés seront supprimés ; le mois repassera en calcul en direct.')
                ->visible(fn () => auth()->user()->isSuperAdmin() && $this->cid() > 0 && $this->cloture())
                ->action(function () {
                    abort_unless(auth()->user()->isSuperAdmin(), 403);

                    $this->cloture()?->delete();
                    $this->cache = null;

                    Notification::make()->title('Mois rouvert')->success()->send();
                }),

            ActionGroup::make([
                Action::make('excel')->label('Excel (.xlsx)')->icon('heroicon-o-table-cells')->action(fn () => $this->exporterExcel()),
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document')->action(fn () => $this->exporterPdf()),
            ])
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->visible(fn () => $this->cid() > 0),
        ];
    }

    protected function nomFichier(): string
    {
        [$annee, $mois] = $this->periode();

        return 'reporting-'.$annee.'-'.sprintf('%02d', $mois);
    }

    public function exporterExcel()
    {
        abort_unless(static::canAccess() && $this->cid() > 0, 403);

        $r = $this->rapport();
        $base = tempnam(sys_get_temp_dir(), 'rep');
        $chemin = $base.'.xlsx';
        @unlink($base);

        $w = new XlsxWriter;
        $w->openToFile($chemin);
        $w->addRow(Row::fromValues(['Ligne', 'Départs', 'Voyageurs', 'Recettes', 'Coûts', 'Résultat', 'Résultat budget', 'Écart', 'Commentaire']));

        foreach (Reporting::lignesExport($r) as $ligne) {
            $w->addRow(Row::fromValues($ligne));
        }

        $w->close();

        return response()->download($chemin, $this->nomFichier().'.xlsx')->deleteFileAfterSend(true);
    }

    public function exporterPdf()
    {
        abort_unless(static::canAccess() && $this->cid() > 0, 403);

        $r = $this->rapport();
        $nom = $this->nomsMois()[$this->periode()[1]];

        $contenu = Pdf::loadView('pdf.reporting-mensuel', [
            'r' => $r,
            'lignes' => Reporting::lignesExport($r),
            'titre' => 'Reporting mensuel — '.ucfirst($nom).' '.$this->periode()[0],
            'compagnie' => Compagnie::whereKey($this->cid())->value('nom'),
            'cloture' => $this->cloture(),
        ])->setPaper('a4', 'landscape')->output();

        return response()->streamDownload(fn () => print ($contenu), $this->nomFichier().'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
