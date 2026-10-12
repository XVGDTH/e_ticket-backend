<?php

namespace App\Filament\Resources\Compagnies;

use App\Filament\Resources\Compagnies\Pages;
use App\Models\Compagnie;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Concerns\ScopedToCompagnie;


class CompagnieResource extends Resource
{
    
use ScopedToCompagnie;
    protected static ?string $model = Compagnie::class;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $navigationLabel = 'Compagnies';
    protected static ?string $recordTitleAttribute = 'nom';
    protected static string|UnitEnum|null $navigationGroup = 'Transport';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('nom')->required(),
            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('logo_url')->label('URL du logo'),
            Forms\Components\Toggle::make('actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('nom')->searchable(),
            Tables\Columns\TextColumn::make('slug'),
            Tables\Columns\IconColumn::make('actif')->boolean(),
            Tables\Columns\TextColumn::make('created_at')->dateTime('d/m/Y'),
        ])->actions([
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ]);
    }

    // public static function canCreate(): bool
    // {
    //     return auth()->user()->hasRoleForCompagnie('super_admin');
    // }

    // public static function canDelete($record): bool
    // {
    //     return auth()->user()->hasRoleForCompagnie('super_admin');
    // }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompagnies::route('/'),
            'create' => Pages\CreateCompagnie::route('/create'),
            'edit' => Pages\EditCompagnie::route('/{record}/edit'),
        ];
    }

        protected static function droits(): array
    {
        return [
            'super_admin' => ['voir', 'creer', 'modifier', 'supprimer'],
            'admin_compagnie' => ['voir', 'modifier'],
        ];
    }

    protected static function scopeToCompagnie(Builder $query, ?int $compagnieId): Builder
    {
        return $compagnieId ? $query->whereKey($compagnieId) : $query->whereRaw('1 = 0');
    }
        protected static function scopeToVille(Builder $query, int $villeId): Builder
    {
        return $query;
    }
}