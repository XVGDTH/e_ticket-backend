<?php

namespace App\Filament\Resources\VentesGuichet;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\RouteTransport;
use App\Models\User;
use App\Services\Billetterie;
use App\Services\Guichet\AccesGuichet;
use App\Services\Guichet\VenteGuichet;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use UnitEnum;

class VenteResource extends Resource
{
    protected static ?string $model = Billet::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Ventes du guichet';

    protected static ?string $modelLabel = 'vente';

    protected static ?string $pluralModelLabel = 'ventes du guichet';

    protected static string|UnitEnum|null $navigationGroup = 'Guichet';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'ventes-guichet';

    public static function canViewAny(): bool
    {
        return AccesGuichet::visible(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('canal', 'guichet')->visiblesPar(auth()->user())->with(['route.villeArrivee', 'gare']);
    }

    private static function agents(): array
    {
        return User::query()->whereIn('id', static::getEloquentQuery()->reorder()->whereNotNull('vendu_par')->select('vendu_par'))
            ->orderBy('nom')->get()->mapWithKeys(fn ($u) => [$u->id => trim($u->prenom.' '.$u->nom)])->all();
    }

    public static function table(Table $table): Table
    {
        $gerant = AccesGuichet::gerant(auth()->user());

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Stack::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('reference')->label('Référence')->searchable()->fontFamily('mono')->weight('bold')->size('lg')
                                ->description(fn (Billet $r) => $r->created_at->format('d/m/Y à H:i')),
                            TextColumn::make('statut')->label('État')->badge(),
                        ])->space(1),
                        TextColumn::make('montant_transport')->label('Montant')->numeric(0, ',', ' ')->suffix(' FCFA')->weight('bold')->size('lg')
                            ->description(fn (Billet $r) => $r->remise ? 'Remise : '.number_format($r->remise, 0, ',', ' ').' FCFA' : null),
                    ])->from('sm'),
                    TextColumn::make('trajet')->label('Trajet')
                        ->getStateUsing(fn (Billet $r) => ($r->gare?->quartier ?? '?').' → '.($r->route?->villeArrivee?->nom ?? '?'))
                        ->description(fn (Billet $r) => $r->date_voyage->format('d/m/Y').' à '.str_replace(':', 'h', Billetterie::normaliserHeure((string) $r->heure))),
                    Split::make([
                        TextColumn::make('voyageurs')->label('Voyageurs')
                            ->formatStateUsing(fn ($state, Billet $r) => collect($r->voyageurs)->pluck('nom')->implode(', '))->limit(40)->wrap()
                            ->description(fn (Billet $r) => $r->travelers_count.' place(s)'.($r->telephone_client ? ' · '.$r->telephone_client : '')),
                        TextColumn::make('operateur_paiement')->label('Paiement')->badge()->color('gray'),
                        TextColumn::make('vendu_par')->label('Vendu par')->formatStateUsing(function ($state) {
                            static $cache = [];
                            $cache[$state] ??= ($u = User::find($state)) ? trim($u->prenom.' '.$u->nom) : '—';

                            return $cache[$state];
                        })->toggleable(),
                    ])->from('sm'),
                ])->space(3),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(collect(BilletStatut::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all()),
                SelectFilter::make('operateur_paiement')->label('Paiement')->options(array_combine(config('guichet.modes'), config('guichet.modes'))),
                SelectFilter::make('vendu_par')->label('Agent')->options(fn () => static::agents())->visible($gerant),
                Filter::make('vente')->label('Date de vente')
                    ->schema([DatePicker::make('du')->label('Du')->default(today()), DatePicker::make('au')->label('Au')])->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('billets.created_at', '>=', $d))
                        ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('billets.created_at', '<=', $d)))
                    ->indicateUsing(fn (array $data) => ($data['du'] ?? null) ? 'Ventes depuis le '.Carbon::parse($data['du'])->format('d/m/Y') : null),
            ])
            ->recordActions([
                Action::make('imprimer')->label(fn (Billet $r) => $r->impressions > 0 ? 'Réimprimer' : 'Imprimer')->icon('heroicon-o-printer')->color('gray')
                    ->visible(fn () => Route::has('guichet.billet'))
                    ->url(fn (Billet $r) => route('guichet.billet', $r->id))->openUrlInNewTab(),

                Action::make('reporter')->label('Changer de départ')->icon('heroicon-o-calendar-days')->color('info')
                    ->visible(fn (Billet $r) => $r->statut === BilletStatut::Valide)
                    ->fillForm(fn (Billet $record) => ['route_id' => $record->route_transport_id])
                    ->schema([
                        Hidden::make('route_id'),
                        DatePicker::make('date')->label('Nouvelle date')->required()->live()->minDate(today()),
                        Select::make('heure')->label('Nouvelle heure')->required()
                            ->options(fn (Get $get) => ($get('date') && $get('route_id'))
                                ? VenteGuichet::departs(RouteTransport::with('horaires')->findOrFail($get('route_id')), $get('date')) : [])
                            ->helperText('Le billet reste valable jusqu\'à la date indiquée sur le billet ; le contrôle est fait à l\'enregistrement.'),
                    ])
                    ->action(function (Billet $record, array $data) {
                        try {
                            VenteGuichet::reporter($record, auth()->user(), Carbon::parse($data['date']), $data['heure']);
                            Notification::make()->title('Départ modifié')->success()->send();
                        } catch (\DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('annuler')->label('Annuler / rembourser')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Billet $r) => $r->statut === BilletStatut::Valide)
                    ->modalDescription('Dans votre caisse en cours, c\'est une annulation ; sinon un remboursement (réservé aux gérants).')
                    ->schema([
                        Textarea::make('motif')->label('Motif (obligatoire)')->required()->minLength(5)->maxLength(255),
                        Toggle::make('restituer')->label('Restituer l\'argent au client depuis ma caisse')->default(true),
                    ])
                    ->action(function (Billet $record, array $data) {
                        try {
                            VenteGuichet::annuler($record, auth()->user(), $data['motif'], (bool) $data['restituer']);
                            Notification::make()->title('Billet annulé')->success()->send();
                        } catch (\DomainException|AuthorizationException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVentes::route('/')];
    }
}
