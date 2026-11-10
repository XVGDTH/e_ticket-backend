<?php

namespace App\Filament\Resources\Employes\RelationManagers;

use App\Models\SanctionEmploye;
use App\Services\Personnel\AccesPersonnel;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SanctionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sanctions';

    protected static ?string $title = 'Discipline';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AccesPersonnel::gerant(auth()->user());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('Mesure')->options(SanctionEmploye::TYPES)->required()->live(),
            DatePicker::make('date_faits')->label('Date des faits')->required()->default(today())->maxDate(today()),
            DatePicker::make('date_sanction')->label('Date de la mesure')->required()->default(today()),
            TextInput::make('jours_mise_a_pied')->label('Jours de mise à pied')->numeric()->minValue(1)->maxValue(8)
                ->visible(fn (Get $get) => $get('type') === 'mise_a_pied')
                ->helperText('Pensez à marquer ces jours « Mise à pied » dans le pointage : ils seront retenus sur la paie.'),
            Textarea::make('motif')->label('Motif / faits reprochés')->required()->rows(3)->columnSpanFull(),
            Textarea::make('notes')->label('Notes internes')->rows(2)->columnSpanFull(),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_sanction', 'desc')
            ->columns([
                TextColumn::make('date_sanction')->label('Date')->date('d/m/Y'),
                TextColumn::make('type')->label('Mesure')->badge()->color('danger')->formatStateUsing(fn ($s) => SanctionEmploye::TYPES[$s] ?? $s),
                TextColumn::make('motif')->label('Motif')->limit(70)->wrap(),
            ])
            ->headerActions([CreateAction::make()->label('Enregistrer une mesure')])
            ->recordActions([EditAction::make()]);
    }
}
