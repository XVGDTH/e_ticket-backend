<?php

namespace App\Filament\Pages;

use App\Models\RouteTransport;
use App\Services\Guichet\AccesGuichet;
use App\Services\Guichet\VenteGuichet as Vente;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Route;
use UnitEnum;

class VenteGuichet extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Vendre un billet';

    protected static ?string $title = 'Vente au guichet';

    protected static string|UnitEnum|null $navigationGroup = 'Guichet';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'guichet/vente';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AccesGuichet::visible(auth()->user());
    }

    public function mount(): void
    {
        $this->form->fill($this->valeursInitiales());
    }

    private function valeursInitiales(array $garder = []): array
    {
        return array_merge([
            'date_voyage' => today()->toDateString(), 'mode' => 'especes', 'remise' => 0,
            'voyageurs' => [['nom' => '']], 'montant_recu' => null,
        ], $garder);
    }

    public function getSubheading(): ?string
    {
        $c = Vente::caisseOuverte(auth()->user());

        if (! $c) {
            return 'Aucune caisse ouverte : ouvrez votre caisse pour vendre.';
        }

        $net = (int) $c->mouvements()->whereIn('type', ['vente', 'annulation', 'remboursement'])->sum('montant');
        $n = $c->mouvements()->where('type', 'vente')->count();

        return 'Caisse ouverte à '.$c->ouverte_le->format('H:i').' — '.($c->gare?->quartier ?? '').' — '.$n.' vente(s), '
            .number_format($net, 0, ',', ' ').' FCFA';
    }

    // ------------------------------------------------------------------ caisse

    protected function getHeaderActions(): array
    {
        $ouverte = fn () => Vente::caisseOuverte(auth()->user());

        return [
            Action::make('ouvrir')->label('Ouvrir ma caisse')->icon('heroicon-o-lock-open')->color('success')
                ->visible(fn () => ! $ouverte())
                ->schema([
                    Select::make('gare_id')->label('Gare')->options(fn () => Vente::garesPour(auth()->user()))->required()
                        ->default(fn () => auth()->user()->isAgentGare() ? auth()->user()->gareId() : null)
                        ->disabled(fn () => auth()->user()->isAgentGare())->dehydrated(),
                    TextInput::make('fond_initial')->label('Fond de caisse (espèces dans le tiroir)')->numeric()->minValue(0)->default(0)->required()->suffix('FCFA'),
                ])
                ->action(function (array $data) {
                    try {
                        Vente::ouvrir(auth()->user(), (int) $data['gare_id'], (int) $data['fond_initial']);
                        Notification::make()->title('Caisse ouverte')->success()->send();
                    } catch (\DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('mouvement')->label('Entrée / sortie d\'espèces')->icon('heroicon-o-arrows-right-left')->color('gray')
                ->visible(fn () => (bool) $ouverte())
                ->schema([
                    Select::make('sens')->label('Nature')->options(['sortie' => 'Sortie (versement au siège, dépense)', 'entree' => 'Entrée (apport de monnaie)'])->required(),
                    TextInput::make('montant')->label('Montant')->numeric()->minValue(1)->required()->suffix('FCFA'),
                    TextInput::make('motif')->label('Motif')->required()->minLength(3)->maxLength(255),
                ])
                ->action(function (array $data) use ($ouverte) {
                    try {
                        Vente::mouvementManuel($ouverte(), auth()->user(), $data['sens'], (int) $data['montant'], $data['motif']);
                        Notification::make()->title('Mouvement enregistré')->success()->send();
                    } catch (\DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('etat')->label('État de ma caisse')->icon('heroicon-o-printer')->color('gray')
                ->visible(fn () => $ouverte() && Route::has('guichet.caisse'))
                ->url(fn () => route('guichet.caisse', $ouverte()->id))->openUrlInNewTab(),

            Action::make('fermer')->label('Fermer ma caisse')->icon('heroicon-o-lock-closed')->color('danger')
                ->visible(fn () => (bool) $ouverte())
                ->modalHeading('Fermer la caisse')
                ->modalDescription('Comptez les espèces du tiroir (fond de caisse compris) et saisissez le total. Le système compare avec les ventes.')
                ->schema([
                    TextInput::make('compte')->label('Espèces comptées')->numeric()->minValue(0)->required()->suffix('FCFA'),
                    Textarea::make('note')->label('Note (obligatoire en cas d\'écart)')->rows(2)->maxLength(255),
                ])
                ->action(function (array $data) use ($ouverte) {
                    try {
                        $c = Vente::fermer($ouverte(), auth()->user(), (int) $data['compte'], $data['note'] ?? null);
                        $e = (int) $c->ecart;
                        Notification::make()->title('Caisse fermée')
                            ->body($e === 0 ? 'Aucun écart.' : 'Écart de '.number_format($e, 0, ',', ' ').' FCFA ('.($e > 0 ? 'excédent' : 'manquant').').')
                            ->color($e === 0 ? 'success' : 'warning')->send();
                    } catch (\DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    // ------------------------------------------------------------------ formulaire

    private function route(Get $get): ?RouteTransport
    {
        return $get('route_transport_id') ? RouteTransport::with(['compagnie', 'gareDepart', 'horaires', 'villeArrivee'])->find($get('route_transport_id')) : null;
    }

    private function resume(Get $get): string
    {
        $route = $this->route($get);

        if (! $route) {
            return 'Choisissez une destination.';
        }

        $n = collect($get('voyageurs') ?? [])->count();
        $brut = (int) $route->prix * $n;
        $remise = max(0, (int) $get('remise'));
        $net = $brut - $remise;
        $f = fn ($v) => number_format((int) $v, 0, ',', ' ');

        $texte = $n.' × '.$f($route->prix).' = '.$f($brut).' FCFA'.($remise ? ' − remise '.$f($remise) : '').' → À PAYER : '.$f($net).' FCFA';

        if ($get('mode') === 'especes' && (int) $get('montant_recu') > 0) {
            $rendu = (int) $get('montant_recu') - $net;
            $texte .= $rendu >= 0 ? ' — Monnaie à rendre : '.$f($rendu).' FCFA' : ' — Il manque '.$f(-$rendu).' FCFA';
        }

        if ($get('date_voyage') && $get('heure')) {
            $texte .= ' — Places guichet restantes : '.Vente::placesRestantes($route, $get('date_voyage'), $get('heure'));
        }

        return $texte;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'xl' => 2])->schema([
                Section::make('Départ')->icon('heroicon-o-map-pin')->description('Choisissez un départ encore ouvert à la vente.')->columns(3)->columnSpanFull()->schema([
                    Select::make('route_transport_id')->label('Destination')
                        ->options(function () {
                            $c = Vente::caisseOuverte(auth()->user());

                            return $c ? RouteTransport::with('villeArrivee')->where('gare_depart_id', $c->gare_id)->where('actif', true)->get()
                                ->mapWithKeys(fn (RouteTransport $r) => [$r->id => ($r->villeArrivee?->nom ?? '?').' — '.number_format($r->prix, 0, ',', ' ').' FCFA'])->all() : [];
                        })
                        ->required()->searchable()->live()->afterStateUpdated(fn (Set $set) => $set('heure', null)),
                    DatePicker::make('date_voyage')->label('Date du voyage')->required()->live()
                        ->minDate(today())->maxDate(today()->addDays((int) config('guichet.jours_vente_max', 30)))
                        ->afterStateUpdated(fn (Set $set) => $set('heure', null)),
                    Select::make('heure')->label('Heure de départ')->required()->live()
                        ->options(fn (Get $get) => ($r = $this->route($get)) && $get('date_voyage') ? Vente::departs($r, $get('date_voyage')) : [])
                        ->helperText('Seuls les départs encore ouverts à la vente sont proposés.'),
                ]),

                Section::make('Voyageurs')->icon('heroicon-o-users')->description('Ajoutez les passagers à inscrire sur le billet.')->schema([
                    Repeater::make('voyageurs')->label('')->live()->minItems(1)->maxItems((int) config('billetterie.voyageurs_max', 10))
                        ->addActionLabel('Ajouter un voyageur')->defaultItems(1)->columns(1)
                        ->schema([TextInput::make('nom')->label('Nom et prénom')->maxLength(100)->required(fn () => (bool) config('guichet.nom_obligatoire'))]),
                ]),

                Section::make('Paiement')->icon('heroicon-o-banknotes')->description('Vérifiez le montant avant d’encaisser.')->columns(2)->schema([
                    TextInput::make('remise')->label('Remise')->numeric()->minValue(0)->default(0)->suffix('FCFA')->live(onBlur: true),
                    Select::make('mode')->label('Mode de paiement')->options(config('guichet.modes'))->default('especes')->required()->live(),
                    TextInput::make('montant_recu')->label('Montant reçu')->numeric()->minValue(0)->suffix('FCFA')->live(onBlur: true)
                        ->visible(fn (Get $get) => $get('mode') === 'especes'),
                    TextInput::make('reference_paiement')->label('N° de transaction')->maxLength(80)
                        ->visible(fn (Get $get) => in_array($get('mode'), config('guichet.modes_avec_reference', []), true))
                        ->required(fn (Get $get) => in_array($get('mode'), config('guichet.modes_avec_reference', []), true)),
                    Text::make(fn (Get $get) => $this->resume($get))->weight('bold')->size('lg')->columnSpanFull(),
                ]),

                Section::make('Client (facultatif)')->icon('heroicon-o-identification')->columns(2)->collapsed()->columnSpanFull()->schema([
                    TextInput::make('nom_client')->label('Nom du client')->maxLength(120),
                    TextInput::make('telephone_client')->label('Téléphone')->tel()->maxLength(30),
                ]),
            ]),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('vendre')
                ->footer([
                    Actions::make([
                        Action::make('vendre')->label('Encaisser et imprimer le billet')->icon('heroicon-o-printer')->submit('vendre')->size('lg')->keyBindings(['mod+enter']),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function vendre(): void
    {
        $data = $this->form->getState();

        try {
            $billet = Vente::vendre(auth()->user(), $data);
        } catch (\DomainException $e) {
            Notification::make()->title('Vente refusée')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Billet '.$billet->reference.' encaissé')->success()->send();

        // On garde destination, date et heure : les ventes se font souvent à la chaîne sur le même départ.
        $this->form->fill($this->valeursInitiales([
            'route_transport_id' => $data['route_transport_id'], 'date_voyage' => $data['date_voyage'], 'heure' => $data['heure'],
        ]));

        if (Route::has('guichet.billet')) {
            $this->js("window.open('".route('guichet.billet', $billet->id)."', '_blank')");
        } else {
            Notification::make()->title('Impression indisponible')->body('Ajoutez require __DIR__ . \'/guichet.php\'; à routes/web.php.')->warning()->send();
        }
    }
}
