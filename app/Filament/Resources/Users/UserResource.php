<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\ScopedToCompagnie;
use App\Filament\Resources\Users\Pages;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\User;
use App\Models\Ville;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    use ScopedToCompagnie;

    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Utilisateurs';
    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static function droits(): array
    {
        return [
            'super_admin' => ['voir', 'creer', 'modifier', 'supprimer'],
            'admin_compagnie' => ['voir', 'creer', 'modifier', 'supprimer'],
        ];
    }

    // Un compte super admin n'est jamais modifié ni supprimé depuis cette liste.
    public static function canEdit(Model $record): bool
    {
        return static::peut('modifier') && ! $record->isSuperAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return static::peut('supprimer') && ! $record->isSuperAdmin();
    }

    /**
     * Rôles que l'utilisateur connecté a le droit d'attribuer.
     */
    protected static function rolesAssignables(): array
    {
        if (auth()->user()->isSuperAdmin()) {
            return [
                'admin_compagnie' => 'Administrateur de compagnie',
                'agent_gare' => 'Agent de gare',
            ];
        }

        // Un admin_compagnie ne peut créer que des agents de gare.
        return ['agent_gare' => 'Agent de gare'];
    }

    public static function form(Schema $schema): Schema
    {
        $authUser = auth()->user();
        $isSuperAdmin = $authUser->isSuperAdmin();

        return $schema->components([
            Forms\Components\TextInput::make('prenom')->required(),
            Forms\Components\TextInput::make('nom')->required(),
            Forms\Components\TextInput::make('telephone')
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('password')
                ->password()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                ->helperText('Laisser vide pour ne pas changer le mot de passe (en édition).'),

            Forms\Components\Select::make('role')
                ->label('Rôle')
                ->options(self::rolesAssignables())
                ->required()
                ->live(),

            // Le super admin choisit la compagnie ; l'admin_compagnie est
            // automatiquement restreint à la sienne (champ masqué).
            $isSuperAdmin
                ? Forms\Components\Select::make('compagnie_id')
                    ->label('Compagnie')
                    ->options(Compagnie::pluck('nom', 'id'))
                    ->required(fn (Get $get) => in_array($get('role'), ['admin_compagnie', 'agent_gare']))
                    ->visible(fn (Get $get) => in_array($get('role'), ['admin_compagnie', 'agent_gare']))
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('gare_id', null))
                : Forms\Components\Hidden::make('compagnie_id')
                    ->default($authUser->compagnieId()),

            // L'administrateur de compagnie est rattaché à une ville (choisie par le super admin).
            Forms\Components\Select::make('ville_id')
                ->label('Ville de l\'administrateur')
                ->options(fn () => Ville::orderBy('nom')->pluck('nom', 'id'))
                ->searchable()
                ->required(fn (Get $get) => $get('role') === 'admin_compagnie')
                ->visible(fn (Get $get) => $isSuperAdmin && $get('role') === 'admin_compagnie'),

            // L'agent de gare est rattaché à UNE gare de la compagnie.
            Forms\Components\Select::make('gare_id')
                ->label('Gare')
                // ->options(function (Get $get) use ($authUser, $isSuperAdmin) {
                //     $compagnieId = $isSuperAdmin ? $get('compagnie_id') : $authUser->compagnieId();

                //     return $compagnieId
                //         ? Gare::where('compagnie_id', $compagnieId)->pluck('quartier', 'id')
                //         : [];
                // })
                     ->options(fn (Get $get) => $authUser
                    ->garesGerables($isSuperAdmin ? (int) $get('compagnie_id') : null)
                    ->pluck('quartier', 'id'))
                ->required(fn (Get $get) => $get('role') === 'agent_gare')
                ->visible(fn (Get $get) => $get('role') === 'agent_gare'),

            Forms\Components\Toggle::make('actif')
                ->label('Compte actif')
                ->default(true)
                ->disabled(fn (?User $record) => $record?->id === auth()->id())
                ->helperText('Un compte désactivé ne peut plus se connecter, sans être supprimé.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('prenom'),
            Tables\Columns\TextColumn::make('nom'),
            Tables\Columns\TextColumn::make('telephone')->searchable(),
            Tables\Columns\TextColumn::make('role')
                ->label('Rôle')
                ->getStateUsing(fn (User $record) => $record->profilStaff()['role'])
                ->badge()
                // les nouvelles couleurs de badges

               
                ->color(fn (?string $state): string => match ($state) {
                    'Super administrateur'        => 'danger',
                    'Administrateur de compagnie' => 'primary',
                    default                       => 'gray',
                }),
            Tables\Columns\TextColumn::make('compagnie')

                ->label('Compagnie')
                ->getStateUsing(fn (User $record) => $record->profilStaff()['compagnie'] ?? '—'),
            Tables\Columns\TextColumn::make('affectation')
                ->label('Affectation')
                ->getStateUsing(function (User $record) {
                    $profil = $record->profilStaff();

                    return collect([$profil['gare'], $profil['ville']])->filter()->implode(' · ') ?: '—';
                }),
            Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean()
                ->trueColor('primary')
    ->falseColor('danger'),
        ])->actions([
            // Actions\EditAction::make(),
            Actions\EditAction::make()->color('primary'),
            // Actions\DeleteAction::make(),
            Actions\DeleteAction::make()->color('danger'),
        ])
        ->striped();
    }

    /**
     * Chacun ne voit que les comptes relevant de son périmètre.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $authUser = auth()->user();

        if ($authUser->isSuperAdmin()) {
            // return $query->whereHas('compagnieRoles'); // tous les comptes staff

            // Comptes du personnel seulement : les clients de l'application n'apparaissent pas ici.
            return $query->whereHas('compagnieRoles.role', fn ($r) => $r->whereIn('name', ['super_admin', 'admin_compagnie', 'agent_gare']));
        }

        // admin_compagnie : uniquement les agents de sa propre compagnie.
        // if ($authUser->isAdminCompagnie()) {
        //     return $query->whereHas('compagnieRoles', function ($q) use ($authUser) {
        //         $q->where('compagnie_id', $authUser->compagnieId())
        //             ->whereHas('role', fn ($r) => $r->where('name', 'agent_gare'));
        //     });
        // }
            if ($authUser->isAdminCompagnie()) {
            $villeId = $authUser->villeId();

            return $query->whereHas('compagnieRoles', function ($q) use ($authUser, $villeId) {
                $q->where('compagnie_id', $authUser->compagnieId())
                    ->whereHas('role', fn ($r) => $r->where('name', 'agent_gare'))
                    ->when($villeId, fn ($qq) => $qq->whereHas('gare', fn ($g) => $g->where('ville_id', $villeId)));
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}