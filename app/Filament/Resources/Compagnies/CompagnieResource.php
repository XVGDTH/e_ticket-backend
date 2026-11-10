<?php

namespace App\Filament\Resources\Compagnies;

use App\Filament\Concerns\ScopedToCompagnie;
use App\Models\Compagnie;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

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
        $superAdmin = auth()->user()->isSuperAdmin();

        return $schema->components([
            Forms\Components\TextInput::make('nom')->required(),
            Forms\Components\TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->alphaDash()
                ->disabled(! $superAdmin)
                ->helperText('Identifiant utilisé par l\'application mobile : il ne se change pas.'),
            Forms\Components\FileUpload::make('logo_url')
                ->label('Logo')
                ->image()
                ->disk('public')
                ->directory('logos')
                ->visibility('public')
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                ->maxSize(2048)
                ->getUploadedFileNameForStorageUsing(
                    fn (TemporaryUploadedFile $file, Get $get): string => (filled($get('slug')) ? $get('slug') : 'logo')
                        .'-'.time().'.'.$file->getClientOriginalExtension()
                )
                ->helperText('PNG, JPG, WebP ou SVG, 2 Mo maximum. Il s\'affiche dans les tableaux de bord de la compagnie.'),
            Forms\Components\Toggle::make('actif')
                ->default(true)
                ->disabled(! $superAdmin),

            // nouvelles formes
            // Forms\Components\TextInput::make('places_par_depart')
            //     ->label('Places par car')
            //     ->numeric()->minValue(1)->maxValue(200)->required()->default(60)
            //     ->disabled(fn () => ! static::peutRegler())
            //     ->helperText('Nombre réel de places de vos cars, par départ.'),
            // Forms\Components\TextInput::make('quota_app_pourcent'),
            // Forms\Components\TextInput::make('validite_jours')
            //     ->label('Durée de validité des billets')
            //     ->numeric()->minValue(1)->maxValue(90)->suffix('jours')->required()->default(12)
            //     ->disabled(fn () => ! static::peutRegler())
            //     ->helperText('Un billet non utilisé reste valable ce nombre de jours après l\'achat (au moins jusqu\'au jour du voyage). Le changement ne concerne que les billets achetés ensuite.')
            //     ->label('Quota de l\'application mobile')
            //     ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required()->default(100)
            //     ->disabled(fn () => ! static::peutRegler())
            //     ->helperText('Part des places de chaque départ vendue via l\'application. Ex. : 60 places × 20 % = 12 places. Une gare peut avoir son propre quota dans la page « Quotas et places ».'),

            Forms\Components\TextInput::make('places_par_depart')
                ->label('Places par car')
                ->numeric()->minValue(1)->maxValue(200)->required()->default(60)
                ->disabled(fn () => ! static::peutRegler())
                ->helperText('Nombre réel de places de vos cars, par départ.'),
            Forms\Components\TextInput::make('quota_app_pourcent')
                ->label('Quota de l\'application mobile')
                ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required()->default(100)
                ->disabled(fn () => ! static::peutRegler())
                ->helperText('Part des places de chaque départ vendue via l\'application. Ex. : 60 places × 20 % = 12 places. Une gare peut avoir son propre quota dans la page « Quotas et places ».'),
            Forms\Components\TextInput::make('validite_jours')
                ->label('Durée de validité des billets')
                ->numeric()->minValue(1)->maxValue(90)->suffix('jours')->required()->default(12)
                ->disabled(fn () => ! static::peutRegler())
                ->helperText('Un billet non utilisé reste valable ce nombre de jours après l\'achat (au moins jusqu\'au jour du voyage). Le changement ne concerne que les billets achetés ensuite.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('nom')->searchable(),
            Tables\Columns\TextColumn::make('slug'),
            Tables\Columns\TextColumn::make('places_par_depart')->label('Places par car'),
            Tables\Columns\TextColumn::make('quota_app_pourcent')->label('Quota app')->suffix(' %'),
            Tables\Columns\TextColumn::make('validite_jours')->label('Validité')->suffix(' jours'),
            Tables\Columns\IconColumn::make('actif')->boolean(),
            Tables\Columns\TextColumn::make('created_at')->dateTime('d/m/Y'),
        ])->actions([
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ]);
    }

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

    /** Les réglages de la compagnie : super admin, ou admin de compagnie non limité à une ville. */
    protected static function peutRegler(): bool
    {
        $user = auth()->user();

        return $user->isSuperAdmin() || ($user->isAdminCompagnie() && ! $user->villeId());
    }
}
