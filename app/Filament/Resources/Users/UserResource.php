<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\ScopedToCompagnie;
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
use App\Services\Modules;
use Illuminate\Support\Arr;


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
    // protected static function rolesAssignables(): array
    // {
    //     if (auth()->user()->isSuperAdmin()) {
    //         return [
    //             'admin_compagnie' => 'Administrateur de compagnie',
    //             'agent_gare' => 'Agent de gare',
    //             'service_client' => 'Service client',
    //         ];
    //     }

    //     // Un admin de compagnie crée des agents et des services client pour ses gares.
    //     return [
    //         'agent_gare' => 'Agent de gare',
    //         'service_client' => 'Service client',
    //     ];
    // }
    /** Rôles proposables : les rôles métiers seulement si leur module est activé pour la compagnie. */
// protected static function rolesAssignables(?int $compagnieId = null): array
public static function rolesAssignables(?int $compagnieId = null): array
{
    $actifs = $compagnieId ? Modules::activesCompagnie($compagnieId) : [];
    $roles = array_diff_key(static::rolesGerables(), config('modules.roles', []));

    foreach (config('modules.roles', []) as $nom => $def) {
        $ouvert = collect($def['modules'])->contains(
            fn ($m) => collect($actifs)->contains(fn ($a) => $a === $m || str_starts_with($a, $m.'.'))
        );
        if ($ouvert) {
            $roles[$nom] = $def['label'];
        }
    }

    return $roles;
}



    public static function form(Schema $schema): Schema
    {
        $authUser = auth()->user();
        $isSuperAdmin = $authUser->isSuperAdmin();
        $roleCompagnie = fn (Get $get) => array_diff((array) $get('roles'), ['service_client']) !== [];
$roleGare = fn (Get $get) => array_diff((array) $get('roles'), ['admin_compagnie']) !== [];

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

            // Forms\Components\Select::make('role')
            //     ->label('Rôle')
            //     ->options(self::rolesAssignables())
            //     ->required()
            //     ->live()
            //     ->afterStateUpdated(fn (Set $set) => $set('gare_id
            $isSuperAdmin
                ? Forms\Components\Select::make('compagnie_id')
                    ->label('Compagnie')
                    ->options(Compagnie::pluck('nom', 'id'))
                    ->required($roleCompagnie)
                    ->helperText('Choisissez d\'abord la compagnie pour voir ses rôles métiers. Laissez vide pour un service client national.')
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                        $set('gare_ids', []);
                        // On retire les rôles qui n'existent pas pour la nouvelle compagnie.
                        $valides = array_keys(self::rolesAssignables($state ? (int) $state : null));
                        $set('roles', array_values(array_intersect((array) $get('roles'), $valides)));
                    })
                : Forms\Components\Hidden::make('compagnie_id')->default($authUser->compagnieId()),

            Forms\Components\Select::make('roles')
                ->label('Rôles')
                ->multiple()
                ->options(fn (Get $get) => self::rolesAssignables(
                    $isSuperAdmin ? ((int) $get('compagnie_id') ?: null) : $authUser->compagnieId()
                ))
                ->required()
                ->live()
                ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                    foreach ((array) $value as $nom) {
                        $conflit = array_intersect((array) $value, config("modules.roles.$nom.incompatibles", []));
                        if ($conflit) {
                            $fail("« {$nom} » ne peut pas être cumulé avec : ".implode(', ', $conflit).' (séparation des tâches).');
                        }
                    }
                }]),

            // Le super admin choisit la compagnie ; l'admin de compagnie est restreint à la sienne.


            // L'administrateur de compagnie est rattaché à une ville (choisie par le super admin).
            Forms\Components\Select::make('ville_id')
                ->label('Ville de l\'administrateur')
                ->options(fn () => Ville::orderBy('nom')->get()->mapWithKeys(fn (Ville $v) => [
                    $v->id => $v->etranger ? "{$v->nom} — {$v->pays} (étranger)" : $v->nom,
                ]))
                ->searchable()
                // ->required(fn (Get $get) => $get('role') === 'admin_compagnie')
                // ->visible(fn (Get $get) => $isSuperAdmin && $get('role') === 'admin_compagnie'),
                ->required(fn (Get $get) => in_array('admin_compagnie', (array) $get('roles'), true))
->visible(fn (Get $get) => $isSuperAdmin && in_array('admin_compagnie', (array) $get('roles'), true)),

            // L'agent de gare et le service client de gare sont rattachés à UNE gare de la compagnie.
            // Forms\Components\Select::make('gare_id')
            //     ->label('Gare')
            //     ->options(fn (Get $get) => $authUser
            //         ->garesGerables($isSuperAdmin ? (int) $get('compagnie_id') : null)
            //         ->with('ville')
            //         ->get()
            //         ->mapWithKeys(fn (Gare $g) => [
            //             $g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : ''),
            //         ]))
            //     ->required(fn (Get $get) => $get('role') === 'agent_gare'
            //         || ($get('role') === 'service_client' && (! $isSuperAdmin || filled($get('compagnie_id')))))
            //     ->visible(fn (Get $get) => in_array($get('role'), ['agent_gare', 'service_client'], true)),


Forms\Components\Select::make('gare_ids')
    ->label('Gares')
    ->multiple()
    ->options(fn (Get $get) => $authUser
        ->garesGerables($isSuperAdmin ? (int) $get('compagnie_id') : null)
        ->with('ville')->get()
        ->mapWithKeys(fn (Gare $g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')]))
   ->required(fn (Get $get) => in_array('agent_gare', (array) $get('roles'), true)
    || (in_array('service_client', (array) $get('roles'), true) && ($isSuperAdmin ? filled($get('compagnie_id')) : true))
    || $authUser->villeId() !== null)
    ->rules([fn (Get $get) => function ($a, $v, \Closure $fail) use ($get) {
        if (in_array('agent_gare', (array) $get('roles'), true) && count((array) $v) !== 1) {
            $fail('Un agent de gare est rattaché à une seule gare.');
        }
    }])
    ->helperText($authUser->villeId() ? null : 'Vide = toutes les gares de la compagnie.')
    ->visible($roleGare),
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
            // Tables\Columns\TextColumn::make('role')
            //     ->label('Rôle')
            //     ->getStateUsing(fn (User $record) => $record->profilStaff()['role'])
            //     ->badge()
            //     // les nouvelles couleurs de badges

            //     ->color(fn (?string $state): string => match ($state) {
            //         'Super administrateur' => 'danger',
            //         'Administrateur de compagnie' => 'primary',
            //         'Service client' => 'warning',
            //         default => 'gray',
            //     }),
            Tables\Columns\TextColumn::make('roles_staff')->label('Rôles')
    ->getStateUsing(fn (User $r) => $r->libellesRoles())
    ->badge()
    ->color(fn (string $state) => match ($state) {
        'Super administrateur' => 'danger',
        'Administrateur de compagnie' => 'primary',
        'Service client' => 'warning',
        default => 'gray',
    }),
Tables\Columns\TextColumn::make('gares_staff')->label('Gares')
    ->getStateUsing(fn (User $r) => $r->libellesGares() ?: ['—'])->badge(),
            Tables\Columns\TextColumn::make('compagnie')

                ->label('Compagnie')
               ->getStateUsing(fn (User $record) => $record->compagnieActuelle()?->nom ?? '—'),
            // Tables\Columns\TextColumn::make('affectation')
            //     ->label('Affectation')
            //     ->getStateUsing(function (User $record) {
            //         $profil = $record->profilStaff();

            //         return collect([$profil['gare'], $profil['ville']])->filter()->implode(' · ') ?: '—';
            //     }),
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
    /**
     * Chacun ne voit que les comptes relevant de son périmètre.
     */
    // public static function getEloquentQuery(): Builder
    // {
    //     $query = parent::getEloquentQuery();
    //     $authUser = auth()->user();

    //     if ($authUser->isSuperAdmin()) {
    //         // Comptes du personnel seulement : les clients de l'application n'apparaissent pas ici.
    //         return $query->whereHas(
    //             'compagnieRoles.role',
    //             fn ($r) => $r->whereIn('name', ['super_admin', 'admin_compagnie', 'agent_gare', 'service_client'])
    //         );
    //     }

    //     // Admin de compagnie : les agents et services client de sa compagnie (et de sa ville s'il en a une).
    //     if ($authUser->isAdminCompagnie()) {
    //         $villeId = $authUser->villeId();

    //         return $query->whereHas('compagnieRoles', function ($q) use ($authUser, $villeId) {
    //             $q->where('compagnie_id', $authUser->compagnieId())
    //                 ->whereHas('role', fn ($r) => $r->whereIn('name', ['agent_gare', 'service_client']))
    //                 ->when($villeId, fn ($qq) => $qq->whereHas('gare', fn ($g) => $g->where('ville_id', $villeId)));
    //         });
    //     }

    //     return $query->whereRaw('1 = 0');
    // }
    public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();
    $authUser = auth()->user();
    $noms = array_keys(static::rolesGerables());

    if ($authUser->isSuperAdmin()) {
        return $query
            ->whereHas('compagnieRoles.role', fn ($r) => $r->whereIn('name', array_merge(['super_admin'], $noms)))
            ->with('compagnieRoles.role', 'compagnieRoles.gare.ville');
    }

    if ($authUser->isAdminCompagnie()) {
        $villeId = $authUser->villeId();

        return $query->whereHas('compagnieRoles', function ($q) use ($authUser, $villeId, $noms) {
            $q->where('compagnie_id', $authUser->compagnieId())
                ->whereHas('role', fn ($r) => $r->whereIn('name', $noms))
                ->when($villeId, fn ($qq) => $qq->whereHas('gare', fn ($g) => $g->where('ville_id', $villeId)));
        })->with('compagnieRoles.role', 'compagnieRoles.gare.ville');
    }

    return $query->whereRaw('1 = 0');
}

    //     // admin_compagnie : uniquement les agents de sa propre compagnie.
    //     // if ($authUser->isAdminCompagnie()) {
    //     //     return $query->whereHas('compagnieRoles', function ($q) use ($authUser) {
    //     //         $q->where('compagnie_id', $authUser->compagnieId())
    //     //             ->whereHas('role', fn ($r) => $r->where('name', 'agent_gare'));
    //     //     });
    //     // }
    //     if ($authUser->isAdminCompagnie()) {
    //         $villeId = $authUser->villeId();

    //         return $query->whereHas('compagnieRoles', function ($q) use ($authUser, $villeId) {
    //             $q->where('compagnie_id', $authUser->compagnieId())
    //                 ->whereHas('role', fn ($r) => $r->where('name', 'agent_gare'))
    //                 ->when($villeId, fn ($qq) => $qq->whereHas('gare', fn ($g) => $g->where('ville_id', $villeId)));
    //         });
    //     }

    //     return $query->whereRaw('1 = 0');
    // }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /** Tous les rôles que l'utilisateur connecté a le droit de gérer (même si le module est désactivé). */
public static function rolesGerables(): array
{
    $roles = ['agent_gare' => 'Agent de gare', 'service_client' => 'Service client'];

    if (auth()->user()->isSuperAdmin()) {
        $roles = ['admin_compagnie' => 'Administrateur de compagnie'] + $roles;
    }

    foreach (config('modules.roles', []) as $nom => $def) {
        $roles[$nom] = $def['label'];
    }

    return $roles;
}

public static function verifierAttribution(array $roles, ?int $compagnieId, array $gareIds): void
{
    $authUser = auth()->user();

    abort_unless($roles !== [] && array_diff($roles, array_keys(static::rolesAssignables($compagnieId))) === [], 403);

    // Hors service client national, tout rôle est rattaché à une compagnie.
    abort_if($compagnieId === null && array_diff($roles, ['service_client']) !== [], 403);

    // Séparation des tâches, revérifiée côté serveur.
    foreach ($roles as $nom) {
        abort_if(array_intersect($roles, config("modules.roles.$nom.incompatibles", [])) !== [], 403);
    }

    $gareObligatoire = in_array('agent_gare', $roles, true)
        || (in_array('service_client', $roles, true) && $compagnieId !== null)
        || $authUser->villeId() !== null;

    abort_if($gareObligatoire && $gareIds === [], 403);
    abort_if(in_array('agent_gare', $roles, true) && count($gareIds) !== 1, 403);

    if ($gareIds !== []) {
        $permises = $authUser->garesGerables($compagnieId)->pluck('gares.id')->map(fn ($i) => (int) $i)->all();
        abort_if(array_diff($gareIds, $permises) !== [], 403);
    }
}


}
