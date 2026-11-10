<?php

namespace App\Filament\Resources\Villes;

use App\Filament\Concerns\ScopedToCompagnie;
use App\Models\Ville;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VilleResource extends Resource
{
    use ScopedToCompagnie;

    protected static ?string $model = Ville::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Villes';

    protected static string|UnitEnum|null $navigationGroup = 'Transport';

    // Les villes sont communes à toutes les compagnies : réservées au super admin.
    protected static function droits(): array
    {
        return [
            'super_admin' => ['voir', 'creer', 'modifier', 'supprimer'],
        ];
    }

    // La table villes n'a pas de compagnie_id : fermé pour les autres rôles.
    protected static function scopeToCompagnie(Builder $query, ?int $compagnieId): Builder
    {
        return $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('nom')->required(),
            Forms\Components\TextInput::make('pays')
                ->required()
                ->default('Burkina Faso')
                ->datalist(['Burkina Faso', "Côte d'Ivoire", 'Ghana', 'Togo', 'Bénin', 'Niger', 'Mali'])
                ->helperText('Tout pays autre que le Burkina Faso rend la ville « étrangère » : ses trajets sont alors internationaux.'),
            Forms\Components\TagsInput::make('aliases')
                ->placeholder('ex: ouaga, waga')
                ->helperText("Variantes d'écriture reconnues pour la recherche."),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('nom')->searchable(),
            Tables\Columns\TextColumn::make('pays')->searchable(),
            Tables\Columns\TextColumn::make('etranger')
                ->label('Type')
                ->badge()
                ->formatStateUsing(fn ($state) => $state ? 'Étranger' : 'National')
                ->color(fn ($state) => $state ? 'warning' : 'gray'),
            Tables\Columns\TextColumn::make('aliases')->badge()->separator(','),
        ])->filters([
            TernaryFilter::make('etranger')
                ->label('Villes')
                ->trueLabel('Étrangères')
                ->falseLabel('Nationales'),
        ])->actions([
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVilles::route('/'),
            'create' => Pages\CreateVille::route('/create'),
            'edit' => Pages\EditVille::route('/{record}/edit'),
        ];
    }
}
