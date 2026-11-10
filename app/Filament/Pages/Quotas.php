<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Compagnies\CompagnieResource;
use App\Filament\Resources\Gares\GareResource;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Services\Billetterie;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class Quotas extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.quotas';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Quotas et places';

    protected static string|UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperAdmin() || $user->isAdminCompagnie() || $user->isAgentGare());
    }

    public function getTitle(): string
    {
        return 'Quotas et places';
    }

    /** Quota d'une gare : super admin et admin de compagnie (dans leur périmètre). */
    protected function peutModifier(): bool
    {
        $user = auth()->user();

        return $user->isSuperAdmin() || $user->isAdminCompagnie();
    }

    /** Places et quota de toute la compagnie : super admin, ou admin non limité à une ville. */
    protected function peutRegler(): bool
    {
        $user = auth()->user();

        return $user->isSuperAdmin() || ($user->isAdminCompagnie() && ! $user->villeId());
    }

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Action::make('compagnie')
    //             ->label(fn () => auth()->user()->isSuperAdmin() ? 'Places et quota des compagnies' : 'Places et quota de ma compagnie')
    //             ->icon('heroicon-o-truck')
    //             ->visible(fn () => $this->peutRegler())
    //             ->url(function () {
    //                 $user = auth()->user();

    //                 return $user->isSuperAdmin()
    //                     ? CompagnieResource::getUrl('index')
    //                     : CompagnieResource::getUrl('edit', ['record' => $user->compagnieId()]);
    //             }),
    //     ];
    // }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reglages')
                ->label('Réglages de la compagnie')
                ->icon('heroicon-o-cog-6-tooth')
                ->visible(fn () => $this->peutRegler())
                ->modalHeading('Réglages de la compagnie')
                ->modalDescription('Places des cars, part vendue par l\'application et durée de validité des billets.')
                ->fillForm(function (): array {
                    $user = auth()->user();

                    return $this->valeursCompagnie(
                        $user->isSuperAdmin()
                            ? Compagnie::orderBy('nom')->first()
                            : Compagnie::find($user->compagnieId())
                    );
                })
                ->schema([
                    Select::make('compagnie_id')
                        ->label('Compagnie')
                        ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, $set) {
                            foreach ($this->valeursCompagnie(Compagnie::find($state)) as $cle => $valeur) {
                                if ($cle !== 'compagnie_id') {
                                    $set($cle, $valeur);
                                }
                            }
                        })
                        ->visible(fn () => auth()->user()->isSuperAdmin()),
                    TextInput::make('places_par_depart')
                        ->label('Places par car')
                        ->numeric()->minValue(1)->maxValue(200)->required(),
                    TextInput::make('quota_app_pourcent')
                        ->label('Quota de l\'application mobile')
                        ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                    TextInput::make('validite_jours')
                        ->label('Durée de validité des billets')
                        ->numeric()->minValue(1)->maxValue(90)->suffix('jours')->required()
                        ->helperText('Compte à partir de l\'achat. Ne concerne que les billets achetés ensuite.'),
                ])
                ->action(function (array $data): void {
                    abort_unless($this->peutRegler(), 403);

                    $user = auth()->user();
                    $id = $user->isSuperAdmin() ? ($data['compagnie_id'] ?? null) : $user->compagnieId();
                    $compagnie = Compagnie::findOrFail($id);

                    $compagnie->forceFill([
                        'places_par_depart' => (int) $data['places_par_depart'],
                        'quota_app_pourcent' => (int) $data['quota_app_pourcent'],
                        'validite_jours' => (int) $data['validite_jours'],
                    ])->save();

                    Notification::make()->title('Réglages enregistrés')->body($compagnie->nom)->success()->send();
                }),
        ];
    }

    protected function valeursCompagnie(?Compagnie $compagnie): array
    {
        return [
            'compagnie_id' => $compagnie?->id,
            'places_par_depart' => $compagnie?->places_par_depart ?? 60,
            'quota_app_pourcent' => $compagnie?->quota_app_pourcent ?? 100,
            'validite_jours' => $compagnie?->validite_jours ?? 12,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => GareResource::getEloquentQuery()->with(['compagnie', 'ville']))
            ->columns([
                TextColumn::make('compagnie.nom')
                    ->label('Compagnie')
                    ->visible(fn () => auth()->user()->isSuperAdmin())
                    ->sortable(),
                TextColumn::make('quartier')->label('Gare')->searchable()->sortable(),
                TextColumn::make('ville.nom')->label('Ville'),
                TextColumn::make('places_car')
                    ->label('Places du car')
                    ->getStateUsing(fn (Gare $gare) => Billetterie::placesBusDe($gare->compagnie)),
                TextColumn::make('quota')
                    ->label('Quota application')
                    ->badge()
                    ->getStateUsing(fn (Gare $gare) => Billetterie::quotaAppDe($gare, $gare->compagnie).' %')
                    ->color(fn (Gare $gare) => $gare->quota_app_pourcent === null ? 'gray' : 'warning')
                    ->description(fn (Gare $gare) => $gare->quota_app_pourcent === null
                        ? 'quota de la compagnie'
                        : 'quota propre à la gare'),
                TextColumn::make('places_app')
                    ->label('Vendues par l\'application')
                    ->weight('bold')
                    ->getStateUsing(fn (Gare $gare) => Billetterie::placesAppDe($gare, $gare->compagnie).' par départ'),

                TextColumn::make('validite')
                    ->label('Validité des billets')
                    ->getStateUsing(fn (Gare $gare) => ($gare->compagnie?->validite_jours ?? 12).' jours'),
            ])
            ->recordActions([
                Action::make('quota')
                    ->label('Quota de la gare')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn () => $this->peutModifier())
                    ->fillForm(fn (Gare $record): array => ['quota_app_pourcent' => $record->quota_app_pourcent])
                    ->schema([
                        TextInput::make('quota_app_pourcent')
                            ->label('Quota de l\'application mobile')
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->helperText('Laisser vide pour suivre le quota de la compagnie. 0 % ferme la gare à l\'application.'),
                    ])
                    ->action(function (Gare $record, array $data): void {
                        abort_unless($this->peutModifier(), 403);

                        $valeur = filled($data['quota_app_pourcent'] ?? null) ? (int) $data['quota_app_pourcent'] : null;
                        $record->forceFill(['quota_app_pourcent' => $valeur])->save();

                        Notification::make()->title('Quota enregistré')->success()->send();
                    }),
            ])
            ->striped();
    }
}
