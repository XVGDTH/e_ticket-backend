<?php

namespace App\Filament\Resources\ComptesComptables;

use App\Models\Compagnie;
use App\Models\CptCompte;
use App\Models\CptLigne;
use App\Services\Modules;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class CompteComptableResource extends Resource
{
    protected static ?string $model = CptCompte::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Plan comptable';

    protected static ?string $modelLabel = 'compte';

    protected static ?string $pluralModelLabel = 'comptes';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?int $navigationSort = 2;

    public static function autorise(): bool
    {
        $u = auth()->user();

        return $u && Modules::visiblePour($u, 'comptabilite')
            && ($u->isSuperAdmin() || ($u->isAdminCompagnie() && ! $u->villeId()));
    }

    public static function canViewAny(): bool
    {
        return static::autorise();
    }

    public static function canCreate(): bool
    {
        return static::autorise();
    }

    public static function canEdit(Model $record): bool
    {
        $u = auth()->user();

        return static::autorise() && ($u->isSuperAdmin() || (int) $record->compagnie_id === (int) $u->compagnieId());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record) && ! CptLigne::where('compte_id', $record->id)->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with('compagnie');
        $u = auth()->user();

        if ($u->isSuperAdmin()) {
            return $q;
        }

        return $q->where(fn ($q) => $q->whereNull('compagnie_id')->orWhere('compagnie_id', $u->compagnieId() ?? 0));
    }

    public static function securiser(array $data, ?CptCompte $compte = null): array
    {
        $u = auth()->user();

        $data['compagnie_id'] = $u->isSuperAdmin()
            ? (filled($data['compagnie_id'] ?? null) ? (int) $data['compagnie_id'] : null)
            : $u->compagnieId();

        abort_unless($u->isSuperAdmin() || $data['compagnie_id'], 403);

        $numero = preg_replace('/\D/', '', (string) ($data['numero'] ?? ''));

        if ($numero === '' || $numero[0] === '0') {
            throw ValidationException::withMessages(['data.numero' => 'Le numéro doit commencer par un chiffre de 1 à 9.']);
        }

        $existe = CptCompte::where('numero', $numero)
            ->when($compte, fn ($q) => $q->whereKeyNot($compte->id))
            ->where(fn ($q) => $q->whereNull('compagnie_id')->orWhere('compagnie_id', $data['compagnie_id']))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages(['data.numero' => 'Ce numéro de compte existe déjà.']);
        }

        $data['numero'] = $numero;
        $data['classe'] = (int) $numero[0];

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $superAdmin = auth()->user()->isSuperAdmin();

        return $schema->components([
            TextInput::make('numero')->label('Numéro du compte')->required()->maxLength(10)->helperText('Chiffres seulement. Ex. : 6242 pour l\'entretien des cars.'),
            TextInput::make('intitule')->label('Intitulé')->required()->maxLength(160),
            Select::make('compagnie_id')
                ->label('Compagnie')
                ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                ->placeholder('Plan de base (toutes les compagnies)')
                ->visible($superAdmin),
            Toggle::make('actif')->label('Compte actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('numero')
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('numero')->label('Compte')->fontFamily('mono')->weight('bold')->searchable()->sortable(),
                TextColumn::make('intitule')->label('Intitulé')->searchable()->wrap(),
                TextColumn::make('classe')->label('Classe')->badge()->color('info'),
                TextColumn::make('portee')
                    ->label('Portée')
                    ->getStateUsing(fn (CptCompte $c) => $c->compagnie_id ? ($c->compagnie?->nom ?? 'Propre') : 'Plan de base')
                    ->badge()
                    ->color(fn ($state) => $state === 'Plan de base' ? 'gray' : 'warning'),
                IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('classe')->label('Classe')->options([
                    1 => '1 · Ressources durables', 2 => '2 · Actif immobilisé', 3 => '3 · Stocks', 4 => '4 · Tiers',
                    5 => '5 · Trésorerie', 6 => '6 · Charges', 7 => '7 · Produits', 8 => '8 · Autres charges et produits',
                ]),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComptesComptables::route('/'),
            'create' => Pages\CreateCompteComptable::route('/create'),
            'edit' => Pages\EditCompteComptable::route('/{record}/edit'),
        ];
    }
}
