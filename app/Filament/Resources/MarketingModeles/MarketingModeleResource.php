<?php

namespace App\Filament\Resources\MarketingModeles;

use App\Models\MarketingModele;
use App\Services\Marketing\WhatsAppCloud;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class MarketingModeleResource extends Resource
{
    protected static ?string $model = MarketingModele::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationLabel = 'Modèles de message';

    protected static ?string $modelLabel = 'modèle';

    protected static ?string $pluralModelLabel = 'modèles de message';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && $record->statut === 'brouillon';
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && in_array($record->statut, ['brouillon', 'refuse'], true);
    }

    public static function nomMeta(string $nom): string
    {
        return 'promo_'.(Str::slug(Str::limit($nom, 30, ''), '_') ?: 'modele').'_'.Str::lower(Str::random(4));
    }

    public static function securiser(array $data, ?MarketingModele $modele = null): array
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $erreur = MarketingModele::verifier((string) ($data['corps'] ?? ''), $data['pied'] ?? null, (array) ($data['exemples'] ?? []));

        if ($erreur) {
            throw ValidationException::withMessages(['data.corps' => $erreur]);
        }

        $data['corps'] = trim($data['corps']);
        $data['pied'] = filled($data['pied'] ?? null) ? trim($data['pied']) : null;
        $data['exemples'] = collect($data['exemples'] ?? [])->only(MarketingModele::personnalisees($data['corps']))->all();

        if (! $modele) {
            $data['nom_meta'] = static::nomMeta($data['nom']);
            $data['cree_par'] = auth()->id();
            $data['statut'] = 'brouillon';
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Le modèle')->columns(2)->schema([
                TextInput::make('nom')->label('Nom du modèle (pour vous)')->required()->maxLength(80),
                Select::make('langue')->label('Langue')->options(['fr' => 'Français', 'en' => 'Anglais'])->default('fr')->required(),
                Textarea::make('corps')
                    ->label('Message')
                    ->required()
                    ->rows(7)
                    ->maxLength(1500)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $actuels = (array) $get('exemples');

                        $set('exemples', collect(MarketingModele::personnalisees((string) $state))
                            ->mapWithKeys(fn ($nom) => [$nom => $actuels[$nom] ?? ''])->all());
                    })
                    ->helperText('Variables automatiques : {prenom} {nom} {compagnie} {gare} {billets}. Vous pouvez en inventer d\'autres, par exemple {offre} : elles se rempliront dans chaque campagne. Le message ne doit ni commencer ni finir par une variable. Mise en gras : *texte*.')
                    ->columnSpanFull(),
                KeyValue::make('exemples')
                    ->label('Exemple pour chaque variable personnalisée')
                    ->keyLabel('Variable')->valueLabel('Exemple de valeur')
                    ->addable(false)->deletable(false)->editableKeys(false)
                    ->live(onBlur: true)
                    ->visible(fn (Get $get) => ! empty($get('exemples')))
                    ->helperText('Meta demande un exemple réaliste pour valider le modèle.')
                    ->columnSpanFull(),
                TextInput::make('pied')->label('Pied de message (facultatif)')->maxLength(60)->live(onBlur: true)->helperText('60 caractères au maximum.'),
                Toggle::make('bouton_stop')->label('Ajouter le bouton « Ne plus recevoir »')->default(true)->live()->inline(false)
                    ->helperText('Recommandé : le client se désabonne en un geste.'),
            ]),
            Section::make('Aperçu')->schema([
                ViewField::make('apercu')
                    ->hiddenLabel()
                    ->view('filament.marketing.apercu')
                    ->viewData(fn (Get $get) => [
                        'corps' => MarketingModele::rendre((string) $get('corps'), array_merge(MarketingModele::AUTO, array_filter((array) $get('exemples')))),
                        'pied' => $get('pied'),
                        'stop' => (bool) $get('bouton_stop'),
                    ])
                    ->dehydrated(false),
            ]),
        ]);
    }

    protected static function executer(\Closure $faire): void
    {
        try {
            $faire();
        } catch (\Throwable $e) {
            Notification::make()->title('Action impossible')->body($e->getMessage())->danger()->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('nom')->label('Modèle')->weight('bold')->searchable()->description(fn (MarketingModele $r) => $r->nom_meta),
                TextColumn::make('corps')->label('Message')->limit(70)->wrap(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MarketingModele::STATUTS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'approuve' => 'success', 'en_attente' => 'warning', 'refuse', 'pause' => 'danger', default => 'gray',
                    })
                    ->description(fn (MarketingModele $r) => $r->motif_refus),
                TextColumn::make('soumis_le')->label('Soumis le')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('soumettre')
                        ->label('Soumettre à Meta')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->visible(fn (MarketingModele $r) => $r->statut === 'brouillon')
                        ->requiresConfirmation()
                        ->modalDescription('Meta examine le modèle, en général sous 24 heures. Il ne pourra plus être modifié.')
                        ->action(fn (MarketingModele $record) => static::executer(function () use ($record) {
                            $r = WhatsAppCloud::soumettreModele($record);

                            if (! $r['ok']) {
                                throw new \RuntimeException($r['erreur']);
                            }

                            $record->forceFill(['statut' => $r['statut'], 'meta_id' => $r['id'], 'soumis_le' => now(), 'motif_refus' => null])->save();

                            Notification::make()
                                ->title('Modèle soumis à Meta')
                                ->body('Vous serez prévenu de la décision par le webhook ; vous pouvez aussi « Actualiser le statut ».')
                                ->success()->send();
                        })),
                    Action::make('actualiser')
                        ->label('Actualiser le statut')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->visible(fn (MarketingModele $r) => filled($r->meta_id) && in_array($r->statut, ['en_attente', 'pause'], true))
                        ->action(fn (MarketingModele $record) => static::executer(function () use ($record) {
                            $r = WhatsAppCloud::lireModele((string) $record->meta_id);

                            if (! $r['ok']) {
                                throw new \RuntimeException($r['erreur']);
                            }

                            $record->forceFill(['statut' => $r['statut'], 'motif_refus' => $r['motif']])->save();

                            Notification::make()->title('Statut : '.(MarketingModele::STATUTS[$r['statut']] ?? $r['statut']))->success()->send();
                        })),
                    Action::make('dupliquer')
                        ->label('Dupliquer')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->action(function (MarketingModele $record) {
                            $copie = $record->replicate(['nom_meta', 'statut', 'meta_id', 'motif_refus', 'soumis_le']);
                            $copie->nom = $record->nom.' (copie)';
                            $copie->nom_meta = static::nomMeta($record->nom);
                            $copie->statut = 'brouillon';
                            $copie->cree_par = auth()->id();
                            $copie->save();

                            Notification::make()->title('Copie créée en brouillon')->success()->send();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketingModeles::route('/'),
            'create' => Pages\CreateMarketingModele::route('/create'),
            'edit' => Pages\EditMarketingModele::route('/{record}/edit'),
        ];
    }
}
