<?php

namespace App\Filament\Resources\CongesEmployes;

use App\Models\CongeEmploye;
use App\Models\Employe;
use App\Services\Personnel\AccesPersonnel;
use App\Services\Personnel\SoldeConges;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CongeEmployeResource extends Resource
{
    protected static ?string $model = CongeEmploye::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static ?string $navigationLabel = 'Congés et absences';

    protected static ?string $modelLabel = 'demande';

    protected static ?string $pluralModelLabel = 'congés et absences';

    protected static string|UnitEnum|null $navigationGroup = 'Personnel';

    protected static ?int $navigationSort = 3;

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
        return $record->statut === 'demande' && static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visiblePour(auth()->user())->with('employe');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')->label('Employé')->options(fn () => Employe::optionsPour(auth()->user()))
                ->searchable()->required()->live()->columnSpanFull(),
            Select::make('type')->label('Nature')->options(CongeEmploye::TYPES)->required()->default('annuel'),
            DatePicker::make('date_debut')->label('Premier jour')->required()->live()->minDate(today()->subDays(30)),
            DatePicker::make('date_fin')->label('Dernier jour')->required()->afterOrEqual('date_debut')
                ->rules([
                    fn (Get $get, ?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                        if (! $get('employe_id') || ! $get('date_debut')) {
                            return;
                        }

                        $existe = CongeEmploye::query()->where('employe_id', $get('employe_id'))
                            ->whereIn('statut', ['demande', 'approuve'])
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->whereDate('date_debut', '<=', $value)->whereDate('date_fin', '>=', $get('date_debut'))->exists();

                        if ($existe) {
                            $fail('Cet employé a déjà un congé ou une demande sur ces dates.');
                        }
                    },
                ]),
            Textarea::make('motif')->label('Motif / précisions')->rows(2)->columnSpanFull(),
            FileUpload::make('justificatif')->label('Justificatif (certificat médical, acte…)')->directory('personnel/conges')
                ->acceptedFileTypes(['application/pdf', 'image/*'])->openable()->downloadable()->columnSpanFull(),
        ])->columns(3);
    }

    protected static function executer(Closure $faire, string $titre): void
    {
        try {
            $faire();
            Notification::make()->title($titre)->success()->send();
        } catch (AuthorizationException|\DomainException $e) {
            Notification::make()->title('Action refusée')->body($e->getMessage())->danger()->send();
        }
    }

    public static function table(Table $table): Table
    {
        $gerant = AccesPersonnel::gerant(auth()->user());

        return $table
            ->defaultSort('date_debut', 'desc')
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('employe_id')->label('Employé')->weight('bold')
                            ->formatStateUsing(fn ($state, CongeEmploye $r) => $r->employe?->nomComplet())
                            ->description(fn (CongeEmploye $r) => $r->employe?->matricule.' · '.$r->employe?->poste)
                            ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('employe', fn ($e) => $e->where('nom', 'like', "%$search%")->orWhere('prenoms', 'like', "%$search%"))),
                        TextColumn::make('statut')->label('État')->badge()->formatStateUsing(fn ($s) => CongeEmploye::STATUTS[$s] ?? $s)
                            ->color(fn ($s) => match ($s) {
                                'approuve' => 'success', 'demande' => 'warning', 'refuse' => 'danger', default => 'gray'
                            }),
                    ])->from('sm'),
                    Split::make([
                        TextColumn::make('type')->label('Nature')->badge()->color('gray')->formatStateUsing(fn ($s) => CongeEmploye::TYPES[$s] ?? $s),
                        TextColumn::make('jours')->label('Durée')->suffix(' jour(s)'),
                        TextColumn::make('solde')->label('Solde annuel')->visible($gerant)
                            ->getStateUsing(fn (CongeEmploye $r) => $r->employe ? SoldeConges::pour($r->employe)['solde'].' j' : '—'),
                    ])->from('sm'),
                    TextColumn::make('date_debut')->label('Période')->date('d/m/Y')->sortable()
                        ->description(fn (CongeEmploye $r) => 'au '.$r->date_fin->format('d/m/Y')),
                ])->space(3),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(CongeEmploye::STATUTS)->default('demande'),
                SelectFilter::make('type')->label('Nature')->options(CongeEmploye::TYPES),
            ])
            ->recordActions([
                Action::make('approuver')->label('Approuver')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (CongeEmploye $r) => $gerant && $r->statut === 'demande')
                    ->requiresConfirmation()
                    ->modalDescription(fn (CongeEmploye $r) => 'Les '.$r->jours.' jour(s) seront marqués dans le pointage de l\'employé.'
                        .($r->type === 'annuel' && $r->employe && SoldeConges::pour($r->employe)['solde'] < $r->jours ? ' Attention : le solde de congés annuels est insuffisant.' : ''))
                    ->action(fn (CongeEmploye $record) => static::executer(fn () => $record->approuver(auth()->user()), 'Congé approuvé')),
                Action::make('refuser')->label('Refuser')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (CongeEmploye $r) => $gerant && $r->statut === 'demande')
                    ->schema([Textarea::make('motif')->label('Motif du refus')->required()->rows(2)])
                    ->action(fn (CongeEmploye $record, array $data) => static::executer(fn () => $record->refuser(auth()->user(), $data['motif']), 'Demande refusée')),
                Action::make('annuler')->label('Annuler le congé')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (CongeEmploye $r) => $gerant && $r->statut === 'approuve' && $r->date_fin->gte(today()))
                    ->requiresConfirmation()->modalDescription('Les jours seront retirés du pointage de l\'employé.')
                    ->action(fn (CongeEmploye $record) => static::executer(fn () => $record->annuler(auth()->user()), 'Congé annulé')),
                EditAction::make(),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCongesEmployes::route('/')];
    }
}
