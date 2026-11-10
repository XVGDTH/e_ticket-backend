<?php

namespace App\Filament\Resources\Employes\RelationManagers;

use App\Models\BulletinPaie;
use App\Services\Personnel\AccesPersonnel;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

class BulletinsPaieRelationManager extends RelationManager
{
    protected static string $relationship = 'bulletins';

    protected static ?string $title = 'Historique des bulletins de paie';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AccesPersonnel::paie(auth()->user());
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->visiblePour(auth()->user())->with('periode'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Stack::make([
                    TextColumn::make('periode_id')->label('Période')->weight('bold')
                        ->formatStateUsing(fn ($state, BulletinPaie $record) => $record->periode?->libelle() ?? 'Période indisponible'),
                    TextColumn::make('net_a_payer')->label('Net à payer')
                        ->numeric(0, ',', ' ')->suffix(' FCFA')->weight('bold')->size('lg'),
                    TextColumn::make('brut')->label('Salaire brut')
                        ->numeric(0, ',', ' ')->suffix(' FCFA')
                        ->description(fn (BulletinPaie $record) => 'CNSS '.number_format((int) $record->cnss_salarie, 0, ',', ' ')
                            .' · IUTS '.number_format((int) $record->iuts, 0, ',', ' ').' FCFA'),
                    TextColumn::make('statut_paiement')->label('Paiement')->badge()
                        ->formatStateUsing(fn ($state) => $state === 'paye' ? 'Payé' : 'À payer')
                        ->color(fn ($state) => $state === 'paye' ? 'success' : 'warning'),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->recordActions([
                Action::make('imprimer')
                    ->label('Afficher le bulletin')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->visible(fn () => Route::has('personnel.bulletin'))
                    ->url(fn (BulletinPaie $record) => route('personnel.bulletin', $record->id))
                    ->openUrlInNewTab(),
            ]);
    }
}
