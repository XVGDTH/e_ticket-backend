<?php

namespace App\Filament\Resources\RouteTransports;

use App\Filament\Concerns\ScopedToCompagnie;
use App\Filament\Resources\RouteTransports\Pages;
use App\Models\Gare;
use App\Models\RouteTransport;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RouteTransportResource extends Resource
{
    use ScopedToCompagnie;

    protected static ?string $model = RouteTransport::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationLabel = 'Routes';
    protected static string|UnitEnum|null $navigationGroup = 'Transport';

    protected static function droits(): array
    {
        return [
            'super_admin' => ['voir', 'creer', 'modifier', 'supprimer'],
            'admin_compagnie' => ['voir', 'creer', 'modifier'],
            'agent_gare' => ['voir'],
        ];
    }

    protected static function scopeToGare(Builder $query, ?int $gareId): Builder
    {
        return $gareId ? $query->where('gare_depart_id', $gareId) : $query->whereRaw('1 = 0');
    }

    protected static function scopeToVille(Builder $query, int $villeId): Builder
    {
        return $query->whereHas('gareDepart', fn ($g) => $g->where('ville_id', $villeId));
    }

    public static function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();

        $compagnieField = $isSuperAdmin
            ? Forms\Components\Select::make('compagnie_id')
                ->label('Compagnie')
                ->relationship('compagnie', 'nom')
                ->required()
                ->live()
            : Forms\Components\Hidden::make('compagnie_id')->default($user->compagnieId());

        return $schema->components([
            $compagnieField,
            Forms\Components\Select::make('gare_depart_id')
                ->label('Gare de départ')
                ->relationship(
                    'gareDepart',
                    'quartier',
                    fn (Builder $query) => $isSuperAdmin
                        ? $query
                        : $query->where('compagnie_id', $user->compagnieId())
                            ->when($user->villeId(), fn ($q, $villeId) => $q->where('ville_id', $villeId))
                )
                ->required()
                ->disabled(fn (?RouteTransport $record) => $record?->billets()->exists())
                ->helperText(fn (?RouteTransport $record) => $record?->billets()->exists()
                    ? 'Des billets existent sur cette route : la gare de départ ne peut plus être modifiée.'
                    : null)
                ->searchable()
                ->rules([
                    fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get, $user, $isSuperAdmin) {
                        $compagnieId = $isSuperAdmin ? $get('compagnie_id') : $user->compagnieId();

                        $ok = Gare::whereKey($value)
                            ->where('compagnie_id', $compagnieId)
                            ->when(! $isSuperAdmin && $user->villeId(), fn ($q) => $q->where('ville_id', $user->villeId()))
                            ->exists();

                        if (! $ok) {
                            $fail("Cette gare n'appartient pas à la compagnie (ou à la ville) de la route.");
                        }
                    },
                ]),
            Forms\Components\Select::make('ville_arrivee_id')
                ->label('Ville d\'arrivée')
                ->relationship('villeArrivee', 'nom')
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('prix')->numeric()->required()->suffix('FCFA'),
            Forms\Components\TextInput::make('duree_max')->placeholder('ex: 5h30'),
            Forms\Components\Toggle::make('actif')->default(true),
            Forms\Components\Repeater::make('horaires')
                ->relationship()
                ->schema([
                    Forms\Components\TimePicker::make('heure')->required()->seconds(false),
                ])
                ->columns(1)
                ->defaultItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('compagnie.nom')
                ->label('Compagnie')
                ->visible(fn () => auth()->user()->isSuperAdmin()),
            Tables\Columns\TextColumn::make('gareDepart.quartier')->label('Gare de départ'),
            Tables\Columns\TextColumn::make('villeArrivee.nom')->label('Destination'),
            Tables\Columns\TextColumn::make('prix')->money('XOF'),
            Tables\Columns\IconColumn::make('actif')->boolean(),
        ])->actions([
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRouteTransports::route('/'),
            'create' => Pages\CreateRouteTransport::route('/create'),
            'edit' => Pages\EditRouteTransport::route('/{record}/edit'),
        ];
    }
}