<?php

namespace App\Filament\Resources\Caisses;

use App\Models\Caisse;
use App\Services\Guichet\AccesGuichet;
use App\Services\Guichet\VenteGuichet;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use UnitEnum;

class CaisseResource extends Resource
{
    protected static ?string $model = Caisse::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Caisses';

    protected static ?string $modelLabel = 'caisse';

    protected static ?string $pluralModelLabel = 'caisses';

    protected static string|UnitEnum|null $navigationGroup = 'Guichet';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return AccesGuichet::visible(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false; // une caisse s'ouvre depuis « Vendre un billet »
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
        return parent::getEloquentQuery()->visiblePour(auth()->user())->with(['user', 'gare.ville']);
    }

    public static function table(Table $table): Table
    {
        $gerant = AccesGuichet::gerant(auth()->user());
        $fcfa = fn (TextColumn $c) => $c->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd();

        return $table
            ->defaultSort('ouverte_le', 'desc')
            ->columns([
                TextColumn::make('ouverte_le')->label('Ouverte le')->dateTime('d/m/Y H:i')->sortable()
                    ->description(fn (Caisse $r) => $r->fermee_le ? 'fermée à '.$r->fermee_le->format('H:i') : 'en cours'),
                TextColumn::make('user_id')->label('Agent')->formatStateUsing(fn ($state, Caisse $r) => $r->libelleAgent())
                    ->description(fn (Caisse $r) => $r->gare?->quartier.($r->gare?->ville ? ' — '.$r->gare->ville->nom : '')),
                $fcfa(TextColumn::make('fond_initial')->label('Fond')),
                $fcfa(TextColumn::make('especes_attendues')->label('Attendu')->placeholder('—')),
                $fcfa(TextColumn::make('especes_comptees')->label('Compté')->placeholder('—')),
                $fcfa(TextColumn::make('ecart')->label('Écart')->placeholder('—')
                    ->color(fn ($state) => $state === null ? null : ($state == 0 ? 'success' : 'danger'))),
                TextColumn::make('statut')->label('État')->badge()->formatStateUsing(fn ($s) => Caisse::STATUTS[$s] ?? $s)
                    ->color(fn ($s) => match ($s) {
                        'ouverte' => 'warning', 'fermee' => 'info', default => 'success'
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(Caisse::STATUTS),
            ])
            ->recordUrl(fn (Caisse $r) => static::getUrl('view', ['record' => $r]))
            ->recordActions([
                Action::make('valider')->label('Valider')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (Caisse $r) => $gerant && $r->statut === 'fermee')
                    ->requiresConfirmation()->modalDescription('Confirme que le comptage et l\'écart de cette caisse ont été vérifiés.')
                    ->action(function (Caisse $record) {
                        try {
                            VenteGuichet::valider($record, auth()->user());
                            Notification::make()->title('Caisse validée')->success()->send();
                        } catch (\DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('imprimer')->label('État')->icon('heroicon-o-printer')->color('gray')
                    ->visible(fn () => Route::has('guichet.caisse'))
                    ->url(fn (Caisse $r) => route('guichet.caisse', $r->id))->openUrlInNewTab(),
            ])
            ->striped();
    }

    public static function getRelations(): array
    {
        return [RelationManagers\MouvementsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaisses::route('/'),
            'view' => Pages\ViewCaisse::route('/{record}'),
        ];
    }
}
