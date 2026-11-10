<?php

namespace App\Filament\Resources\DemandesAssistance;

use App\Models\DemandeAssistance;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DemandeAssistanceResource extends Resource
{
    protected static ?string $model = DemandeAssistance::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Demandes d\'assistance';

    protected static ?string $modelLabel = 'demande d\'assistance';

    protected static ?string $pluralModelLabel = 'demandes d\'assistance';

    protected static string|UnitEnum|null $navigationGroup = 'Assistance';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return $u && ($u->isSuperAdmin() || $u->isServiceClient() || $u->isAdminCompagnie() || $u->isAgentGare());
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
        return parent::getEloquentQuery()
            ->visiblePour(auth()->user())
            ->with(['user', 'billet', 'compagnie', 'gare']);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('statut', 'nouvelle')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    private static function couleurStatut(?string $statut): string
    {
        return match ($statut) {
            'nouvelle' => 'danger',
            'en_cours' => 'warning',
            default => 'success',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('derniere_activite_le', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('id')->label('N°')->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => DemandeAssistance::STATUTS[$state] ?? $state)
                    ->color(fn ($state) => self::couleurStatut($state)),
                TextColumn::make('rappel_statut')
                    ->label('Rappel')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'demande' => 'À rappeler',
                        'fait' => 'Rappelé',
                        default => '—',
                    })
                    ->color(fn ($state) => match ($state) {
                        'demande' => 'danger',
                        'fait' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('theme')
                    ->label('Thème')
                    ->badge()->color('info')
                    ->formatStateUsing(fn ($state) => DemandeAssistance::THEMES[$state] ?? $state),
                TextColumn::make('objet')
                    ->label('Objet')
                    ->limit(48)
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (DemandeAssistance $r) => trim(($r->user?->prenom ?? '').' '.($r->user?->nom ?? ''))
                        .' · '.($r->user?->telephone ?? '')),
                TextColumn::make('billet.reference')->label('Billet')->placeholder('Sans billet')->searchable()->fontFamily('mono'),
                TextColumn::make('compagnie.nom')->label('Compagnie')->placeholder('—'),
                TextColumn::make('gare.quartier')->label('Gare')->placeholder('—'),
                TextColumn::make('derniere_activite_le')->label('Dernière activité')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(DemandeAssistance::STATUTS),
                SelectFilter::make('theme')->label('Thème')->options(DemandeAssistance::THEMES),
                Filter::make('rappels')
                    ->label('Rappels à faire')
                    ->toggle()
                    ->query(fn (Builder $q) => $q->where('rappel_statut', 'demande')),
                Filter::make('sans_billet')
                    ->label('Sans billet')
                    ->toggle()
                    ->query(fn (Builder $q) => $q->whereNull('billet_id')),
            ])
            ->recordActions([
                ViewAction::make()->label('Ouvrir')->icon('heroicon-o-chat-bubble-left-right'),
            ])
            ->striped();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Demande')
                ->schema([
                    TextEntry::make('objet')->label('Objet')->weight('bold'),
                    TextEntry::make('statut')
                        ->label('Statut')
                        ->badge()
                        ->formatStateUsing(fn ($state) => DemandeAssistance::STATUTS[$state] ?? $state)
                        ->color(fn ($state) => self::couleurStatut($state)),
                    TextEntry::make('theme')
                        ->label('Thème')
                        ->formatStateUsing(fn ($state) => DemandeAssistance::THEMES[$state] ?? $state),
                    TextEntry::make('client')
                        ->label('Client')
                        ->state(fn (DemandeAssistance $r) => trim(($r->user?->prenom ?? '').' '.($r->user?->nom ?? ''))),
                    TextEntry::make('user.telephone')->label('Téléphone')->icon('heroicon-o-phone')->copyable(),
                    TextEntry::make('rappel_statut')
                        ->label('Rappel')
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'demande' => 'Le client demande à être rappelé',
                            'fait' => 'Rappel effectué',
                            default => 'Non demandé',
                        }),
                    TextEntry::make('billet.reference')->label('Billet')->placeholder('Sans billet')->copyable(),
                    TextEntry::make('compagnie.nom')->label('Compagnie')->placeholder('—'),
                    TextEntry::make('gare.quartier')->label('Gare')->placeholder('—'),
                    TextEntry::make('created_at')->label('Reçue le')->dateTime('d/m/Y H:i'),
                    TextEntry::make('assigneA.prenom')->label('Prise en charge par')->placeholder('Personne pour l\'instant'),
                    TextEntry::make('note')
                        ->label('Satisfaction')
                        ->formatStateUsing(fn ($state) => $state ? str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state) : '—'),
                ])
                ->columns(3),

            Section::make('Conversation')
                ->schema([
                    ViewEntry::make('fil')
                        ->hiddenLabel()
                        ->state(fn (DemandeAssistance $r) => $r->fil())
                        ->view('filament.assistance.fil')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemandesAssistance::route('/'),
            'view' => Pages\ViewDemandeAssistance::route('/{record}'),
        ];
    }
}
