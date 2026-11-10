<?php

namespace App\Filament\Pages;

use App\Models\Billet;
use App\Models\User;
use App\Services\Guichet\AccesGuichet;
use App\Services\Guichet\RapportGuichet as Rapport;
use App\Services\Guichet\VenteGuichet;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use UnitEnum;

class RapportGuichet extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Rapport du guichet';

    protected static ?string $title = 'Rapport du guichet';

    protected static string|UnitEnum|null $navigationGroup = 'Guichet';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'guichet/rapport';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AccesGuichet::visible(auth()->user());
    }

    public function mount(): void
    {
        $this->form->fill(['du' => today()->startOfMonth()->toDateString(), 'au' => today()->toDateString()]);
    }

    private function periode(): array
    {
        $d = $this->data;

        return [
            Carbon::parse($d['du'] ?? today()->startOfMonth()),
            Carbon::parse($d['au'] ?? today()),
            ($d['gare_id'] ?? null) ? (int) $d['gare_id'] : null,
            ($d['agent_id'] ?? null) ? (int) $d['agent_id'] : null,
        ];
    }

    public function form(Schema $schema): Schema
    {
        $u = auth()->user();

        return $schema->components([
            Section::make('Période')->columns(4)->schema([
                DatePicker::make('du')->label('Du')->required()->live(),
                DatePicker::make('au')->label('Au')->required()->live()->afterOrEqual('du'),
                Select::make('gare_id')->label('Gare')->options(fn () => VenteGuichet::garesPour($u))->placeholder('Toutes')->live()
                    ->visible(fn () => ! $u->isAgentGare()),
                Select::make('agent_id')->label('Agent')->placeholder('Tous')->live()->visible(fn () => AccesGuichet::gerant($u))
                    ->options(fn () => User::query()->whereIn('id', Billet::query()->visiblesPar($u)->where('canal', 'guichet')->whereNotNull('vendu_par')->select('vendu_par'))
                        ->orderBy('nom')->get()->mapWithKeys(fn ($x) => [$x->id => trim($x->prenom.' '.$x->nom)])->all()),
            ]),
            Html::make(fn () => view('guichet.rapport', ['r' => Rapport::calculer(auth()->user(), ...$this->periode())])->render()),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')->label('Exporter en CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(function () {
                    $r = Rapport::calculer(auth()->user(), ...$this->periode());

                    return response()->streamDownload(function () use ($r) {
                        $f = fopen('php://output', 'w');
                        fwrite($f, "\xEF\xBB\xBF");
                        fputcsv($f, ['Date de vente', 'Référence', 'Trajet', 'Départ', 'Voyageurs', 'Montant', 'Remise', 'Paiement', 'État'], ';');

                        foreach ($r['billets'] as $b) {
                            fputcsv($f, [
                                $b->created_at->format('d/m/Y H:i'), $b->reference,
                                ($b->gare?->quartier ?? '?').' → '.($b->route?->villeArrivee?->nom ?? '?'),
                                $b->date_voyage->format('d/m/Y').' '.substr((string) $b->heure, 0, 5), $b->travelers_count,
                                $b->montant_transport, $b->remise, $b->operateur_paiement, $b->statut->getLabel(),
                            ], ';');
                        }

                        fclose($f);
                    }, 'rapport-guichet-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
