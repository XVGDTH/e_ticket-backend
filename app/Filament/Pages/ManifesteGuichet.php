<?php

namespace App\Filament\Pages;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\RouteTransport;
use App\Services\Billetterie;
use App\Services\Guichet\AccesGuichet;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Route;
use UnitEnum;

class ManifesteGuichet extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Manifeste de départ';

    protected static ?string $title = 'Manifeste de départ';

    protected static string|UnitEnum|null $navigationGroup = 'Guichet';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'guichet/manifeste';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AccesGuichet::visible(auth()->user());
    }

    public function mount(): void
    {
        $this->form->fill(['date' => today()->toDateString()]);
    }

    private function getRoutes(): array
    {
        $u = auth()->user();

        $q = RouteTransport::query()->with(['gareDepart', 'villeArrivee'])->where('actif', true);

        $q = match (true) {
            $u->isSuperAdmin() => $q,
            $u->isAdminCompagnie() => $q->where('compagnie_id', $u->compagnieId() ?? 0)
                ->when($u->villeId(), fn ($x) => $x->whereIn('gare_depart_id', $u->garesGerables()->select('gares.id'))),
            default => $q->where('gare_depart_id', $u->gareId() ?? 0),
        };

        return $q->get()->mapWithKeys(fn (RouteTransport $r) => [$r->id => ($r->gareDepart?->quartier ?? '?').' → '.($r->villeArrivee?->nom ?? '?')])->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                Select::make('route_transport_id')->label('Trajet')->options(fn () => $this->getRoutes())->required()->searchable()->live()
                    ->afterStateUpdated(fn (Set $set) => $set('heure', null)),
                DatePicker::make('date')->label('Date')->required()->live()->afterStateUpdated(fn (Set $set) => $set('heure', null)),
                Select::make('heure')->label('Heure')->required()->live()
                    ->options(function (Get $get) {
                        if (! $get('route_transport_id') || ! $get('date')) {
                            return [];
                        }

                        $r = RouteTransport::with('horaires')->find($get('route_transport_id'));

                        return $r ? collect(Billetterie::horairesDuJour($r, $get('date')))->mapWithKeys(fn ($h) => [$h => str_replace(':', 'h', $h)])->all() : [];
                    }),
                Text::make(fn (Get $get) => $this->resume($get))->columnSpanFull(),
            ]),
        ])->statePath('data');
    }

    private function resume(Get $get): string
    {
        if (! $get('route_transport_id') || ! $get('date') || ! $get('heure')) {
            return 'Choisissez un trajet, une date et une heure.';
        }

        $b = Billet::query()->visiblesPar(auth()->user())->where('route_transport_id', $get('route_transport_id'))
            ->whereDate('date_voyage', $get('date'))->whereIn('statut', BilletStatut::comptabilises())->get()
            ->filter(fn ($x) => Billetterie::normaliserHeure((string) $x->heure) === $get('heure'));

        $app = (int) $b->where('canal', 'app')->sum('travelers_count');
        $gui = (int) $b->where('canal', 'guichet')->sum('travelers_count');

        return $app + $gui.' voyageur(s) : '.$app.' par l\'application, '.$gui.' au guichet.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('ouvrir')
                ->footer([Actions::make([Action::make('ouvrir')->label('Imprimer le manifeste')->icon('heroicon-o-printer')->submit('ouvrir')])->key('actions')]),
        ]);
    }

    public function ouvrir(): void
    {
        $d = $this->form->getState();

        if (! Route::has('guichet.manifeste')) {
            return;
        }

        $this->js("window.open('".route('guichet.manifeste', ['route' => $d['route_transport_id'], 'date' => $d['date'], 'heure' => $d['heure']])."', '_blank')");
    }
}
