<?php

namespace App\Filament\Resources\Gares;

use App\Filament\Resources\Gares\Pages\CreateGare;
use App\Filament\Resources\Gares\Pages\EditGare;
use App\Filament\Resources\Gares\Pages\ListGares;
use App\Filament\Resources\Gares\Schemas\GareForm;
use App\Filament\Resources\Gares\Tables\GaresTable;
use App\Models\Gare;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Concerns\ScopedToCompagnie;


class GareResource extends Resource
{
  use ScopedToCompagnie;
    protected static ?string $model = Gare::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'quartier';
    protected static ?string $navigationLabel = 'Gares';
protected static string|UnitEnum|null $navigationGroup = 'Transport';

    public static function form(Schema $schema): Schema
    {
        return GareForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GaresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGares::route('/'),
            'create' => CreateGare::route('/create'),
            'edit' => EditGare::route('/{record}/edit'),
        ];
    }

    //     public static function getEloquentQuery(): Builder
    // {
    //     $query = parent::getEloquentQuery();
    //     $user = auth()->user();

    //     if (! $user->hasRoleForCompagnie('super_admin')) {
    //         $query->where('compagnie_id', $user->compagnieActuelle()?->id);
    //     }

    //     return $query;
    // }

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
        return $gareId ? $query->whereKey($gareId) : $query->whereRaw('1 = 0');
    }

        protected static function scopeToVille(Builder $query, int $villeId): Builder
    {
        return $query->where('ville_id', $villeId);
    }
}
