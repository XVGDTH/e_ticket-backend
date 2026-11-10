<?php

namespace App\Filament\Resources\Presences;

use App\Models\Employe;
use App\Models\Presence;
use App\Services\Personnel\AccesPersonnel;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use UnitEnum;

class PresenceResource extends Resource
{
    protected static ?string $model = Presence::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Présences';

    protected static ?string $modelLabel = 'pointage';

    protected static ?string $pluralModelLabel = 'pointages';

    protected static string|UnitEnum|null $navigationGroup = 'Personnel';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return AccesPersonnel::visible(auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return AccesPersonnel::gerant(auth()->user());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visiblePour(auth()->user())->with(['employe', 'gare.ville']);
    }

    public static function form(Schema $schema): Schema
    {
        $agent = auth()->user()->isAgentGare();

        return $schema->components([
            Select::make('employe_id')->label('Employé')->options(fn () => Employe::optionsPour(auth()->user()))
                ->searchable()->required()->columnSpanFull(),
            DatePicker::make('date')->label('Date')->default(today())->required()
                ->minDate($agent ? today()->subDays(7) : null)->maxDate($agent ? today() : null)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('employe_id', $get('employe_id')))
                ->validationMessages(['unique' => 'Cet employé est déjà pointé pour ce jour : modifiez sa ligne.']),
            Select::make('statut')->label('Situation')->options(Presence::STATUTS)->default('present')->required(),
            TimePicker::make('heure_arrivee')->label('Arrivée')->seconds(false),
            TimePicker::make('heure_depart')->label('Départ')->seconds(false),
            TextInput::make('heures_sup_minutes')->label('Heures supplémentaires')->numeric()->minValue(0)->maxValue(600)->default(0)->suffix('minutes')
                ->visible(fn () => AccesPersonnel::gerant(auth()->user()))
                ->helperText('Autorisées par la direction uniquement.'),
            TextInput::make('note')->label('Note')->maxLength(255)->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('employe_id')->label('Employé')->weight('bold')
                            ->formatStateUsing(fn ($state, Presence $r) => $r->employe?->nomComplet())
                            ->description(fn (Presence $r) => $r->employe?->matricule.' · '.$r->employe?->poste)
                            ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('employe', fn ($e) => $e->where('nom', 'like', "%$search%")->orWhere('prenoms', 'like', "%$search%")->orWhere('matricule', 'like', "%$search%"))),
                        TextColumn::make('statut')->label('Situation')->badge()
                            ->formatStateUsing(fn ($state) => Presence::STATUTS[$state] ?? $state)
                            ->color(fn ($state) => Presence::COULEURS[$state] ?? 'gray'),
                    ])->from('sm'),
                    Split::make([
                        TextColumn::make('date')->label('Date')->date('D d/m/Y')->sortable(),
                        TextColumn::make('gare_id')->label('Gare')->formatStateUsing(fn ($state, Presence $r) => $r->gare?->quartier ?? 'Siège')
                            ->description(fn (Presence $r) => $r->gare?->ville?->nom),
                    ])->from('sm'),
                    Split::make([
                        TextColumn::make('heure_arrivee')->label('Arrivée')->time('H\hi')->placeholder('—')
                            ->description(fn (Presence $r) => $r->minutes_retard > 0 ? $r->minutes_retard.' min de retard' : null),
                        TextColumn::make('heure_depart')->label('Départ')->time('H\hi')->placeholder('—'),
                        TextColumn::make('heures_sup_minutes')->label('H. sup.')->formatStateUsing(fn ($s) => $s ? floor($s / 60).'h'.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT) : '—')
                            ->visible(fn () => AccesPersonnel::gerant(auth()->user())),
                    ])->from('sm'),
                    TextColumn::make('note')->label('Note')->limit(60)->placeholder('—'),
                ])->space(3),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->filters([
                Filter::make('periode')->label('Période')
                    ->schema([
                        DatePicker::make('du')->label('Du')->default(today()),
                        DatePicker::make('au')->label('Au'),
                    ])->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))
                        ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d)))
                    ->indicateUsing(fn (array $data) => ($data['du'] ?? null) ? 'Depuis le '.Carbon::parse($data['du'])->format('d/m/Y') : null),
                SelectFilter::make('statut')->label('Situation')->options(Presence::STATUTS),
                SelectFilter::make('gare_id')->label('Gare')->options(fn () => Employe::garesOptionsPour(auth()->user())),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('marquer')->label('Changer la situation')->icon('heroicon-o-pencil-square')
                        ->schema([Select::make('statut')->label('Nouvelle situation')->options(Presence::STATUTS)->required()])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn (Presence $p) => $p->forceFill(['statut' => $data['statut']])->save());
                            Notification::make()->title($records->count().' pointage(s) modifié(s)')->success()->send();
                        })->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePresences::route('/')];
    }
}
