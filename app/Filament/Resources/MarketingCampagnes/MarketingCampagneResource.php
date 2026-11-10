<?php

namespace App\Filament\Resources\MarketingCampagnes;

use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\MarketingCampagne;
use App\Models\MarketingModele;
use App\Services\Marketing\Audience;
use App\Services\Marketing\Telephone;
use App\Services\Marketing\WhatsAppCloud;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class MarketingCampagneResource extends Resource
{
    protected static ?string $model = MarketingCampagne::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Campagnes';

    protected static ?string $modelLabel = 'campagne';

    protected static ?string $pluralModelLabel = 'campagnes';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 4;

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
        return static::canViewAny() && in_array($record->statut, ['brouillon', 'annulee', 'erreur'], true);
    }

    public static function securiser(array $data): array
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $data['canal'] = in_array($data['canal'] ?? 'whatsapp', ['whatsapp', 'app'], true) ? $data['canal'] : 'whatsapp';

        if ($data['canal'] === 'app' && blank($data['titre'] ?? null)) {
            throw ValidationException::withMessages(['data.titre' => 'Donnez un titre à la notification.']);
        }

        if ($data['canal'] !== 'app') {
            $data['titre'] = null;
        }

        $modele = MarketingModele::findOrFail($data['modele_id']);
        $noms = MarketingModele::personnalisees($modele->corps);
        $variables = (array) ($data['variables'] ?? []);

        foreach ($noms as $nom) {
            if (blank($variables[$nom] ?? null)) {
                throw ValidationException::withMessages(['data.variables' => 'Renseignez la variable {'.$nom.'}.']);
            }
        }

        $data['variables'] = collect($variables)->only($noms)->map(fn ($v) => trim((string) $v))->all();
        $data['segments'] = array_values(array_intersect((array) ($data['segments'] ?? []), array_keys(Audience::SEGMENTS)));

        if ($data['segments'] === []) {
            throw ValidationException::withMessages(['data.segments' => 'Choisissez au moins un type de client.']);
        }

        if (! empty($data['gare_id'])) {
            abort_unless(Gare::whereKey($data['gare_id'])->where('compagnie_id', $data['compagnie_id'])->exists(), 403);
        } else {
            $data['gare_id'] = null;
        }

        $data['planifiee_le'] = $data['planifiee_le'] ?? null;
        $data['cree_par'] = $data['cree_par'] ?? auth()->id();

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('La campagne')->columns(2)->schema([
                TextInput::make('nom')->label('Nom de la campagne (pour vous)')->required()->maxLength(100),
                Radio::make('canal')
                    ->label('Où envoyer le message ?')
                    ->options([
                        'whatsapp' => 'WhatsApp (payant, modèle approuvé par Meta)',
                        'app' => 'Notification dans l\'application (gratuit)',
                    ])
                    ->default('whatsapp')
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (Set $set) {
                        $set('modele_id', null);
                        $set('variables', []);
                    }),
                Select::make('modele_id')
                    ->label('Modèle de message')
                    ->required()
                    ->live()
                    ->options(fn (Get $get) => MarketingModele::query()
                        ->when($get('canal') !== 'app', fn ($q) => $q->where('statut', 'approuve'))
                        ->orderBy('nom')->pluck('nom', 'id')->all())
                    ->afterStateUpdated(function ($state, Set $set) {
                        $noms = $state ? MarketingModele::personnalisees((string) MarketingModele::whereKey($state)->value('corps')) : [];
                        $set('variables', array_fill_keys($noms, ''));
                    })
                    ->helperText('Pour WhatsApp, seuls les modèles approuvés par Meta apparaissent. Pour l\'application, tous les modèles conviennent.'),
                TextInput::make('titre')
                    ->label('Titre de la notification')
                    ->maxLength(100)
                    ->visible(fn (Get $get) => $get('canal') === 'app')
                    ->required(fn (Get $get) => $get('canal') === 'app'),
            ]),

            Section::make('À qui l\'envoyer ?')->columns(2)->schema([
                Select::make('compagnie_id')
                    ->label('Compagnie')
                    ->required()
                    ->live()
                    ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                    ->afterStateUpdated(fn (Set $set) => $set('gare_id', null)),
                Select::make('gare_id')
                    ->label('Une seule gare (facultatif)')
                    ->placeholder('Toutes les gares de la compagnie')
                    ->live()
                    ->searchable()
                    ->options(fn (Get $get) => Gare::where('compagnie_id', (int) $get('compagnie_id'))
                        ->with('ville')->orderBy('quartier')->get()
                        ->mapWithKeys(fn (Gare $g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')])->all()),
                CheckboxList::make('segments')
                    ->label('Types de clients')
                    ->options(Audience::SEGMENTS)
                    ->default(['fidele', 'regulier'])
                    ->required()
                    ->live()
                    ->columns(2)
                    ->columnSpanFull(),
                ViewField::make('audience')
                    ->hiddenLabel()
                    ->view('filament.marketing.audience')
                    ->viewData(fn (Get $get) => [
                        'a' => Audience::compter((int) $get('compagnie_id'), $get('gare_id') ? (int) $get('gare_id') : null, (array) $get('segments'), (string) ($get('canal') ?: 'whatsapp')),
                        'cout' => $get('canal') === 'app' ? 0 : (int) config('marketing.cout_message_fcfa', 0),
                        'pause' => (int) config('marketing.pause_jours', 7),
                    ])
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]),

            Section::make('Le message')->schema([
                KeyValue::make('variables')
                    ->label('Variables à remplir')
                    ->keyLabel('Variable')->valueLabel('Valeur dans le message')
                    ->addable(false)->deletable(false)->editableKeys(false)
                    ->live(onBlur: true)
                    ->visible(fn (Get $get) => ! empty($get('variables'))),
                ViewField::make('apercu')
                    ->hiddenLabel()
                    ->view('filament.marketing.apercu')
                    ->viewData(fn (Get $get) => MarketingModele::apercu((int) $get('modele_id'), (array) $get('variables')))
                    ->dehydrated(false),
            ]),

            Section::make('Quand ?')->columns(2)->schema([
                Radio::make('quand')
                    ->hiddenLabel()
                    ->options(['maintenant' => 'Dès que je lance la campagne', 'plus_tard' => 'À une date et une heure précises'])
                    ->default('maintenant')
                    ->live()
                    ->dehydrated(false),
                DateTimePicker::make('planifiee_le')
                    ->label('Date et heure')
                    ->minDate(now())
                    ->visible(fn (Get $get) => $get('quand') === 'plus_tard')
                    ->required(fn (Get $get) => $get('quand') === 'plus_tard'),
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

    public static function actionTester(): Action
    {
        return Action::make('tester')
            ->label('Envoyer un test')
            ->icon('heroicon-o-beaker')
            ->color('gray')
            ->visible(fn (MarketingCampagne $r) => $r->canal !== 'app' && in_array($r->statut, ['brouillon', 'erreur'], true))
            ->modalHeading('Envoyer le message à un numéro de test')
            ->schema([
                TextInput::make('telephone')->label('Numéro WhatsApp')->tel()->required()->default(fn () => auth()->user()->telephone),
            ])
            ->action(fn (MarketingCampagne $record, array $data) => static::executer(function () use ($record, $data) {
                $numero = Telephone::whatsapp($data['telephone']);

                if (! $numero) {
                    throw new \RuntimeException('Numéro invalide.');
                }

                $moi = auth()->user();
                $gare = $record->gare_id ? (Gare::whereKey($record->gare_id)->value('quartier') ?: 'votre gare') : 'votre gare';

                $r = WhatsAppCloud::envoyerModele($numero, $record->modele, $record->valeursPour($moi, $gare));

                if (! $r['ok']) {
                    throw new \RuntimeException($r['erreur']);
                }

                Notification::make()->title('Message de test envoyé')->success()->send();
            }));
    }

    public static function actionLancer(): Action
    {
        return Action::make('lancer')
            ->label('Lancer la campagne')
            ->icon('heroicon-o-rocket-launch')
            ->color('success')
            ->visible(fn (MarketingCampagne $r) => in_array($r->statut, ['brouillon', 'erreur'], true))
            ->requiresConfirmation()
            ->modalHeading('Lancer cette campagne ?')
            ->modalDescription(function (MarketingCampagne $r) {
                $a = Audience::compter((int) $r->compagnie_id, $r->gare_id ? (int) $r->gare_id : null, (array) $r->segments, (string) ($r->canal ?: 'whatsapp'));

                return $a['cibles'].' client(s) recevront le message.'
                    .($r->canal === 'app' ? '' : ' Ceux qui ont déjà reçu une campagne depuis moins de '.config('marketing.pause_jours', 7).' jours sont exclus.');
            })
            ->action(fn (MarketingCampagne $record) => static::executer(function () use ($record) {
                $a = Audience::compter((int) $record->compagnie_id, $record->gare_id ? (int) $record->gare_id : null, (array) $record->segments, (string) ($record->canal ?: 'whatsapp'));

                if ($a['cibles'] === 0) {
                    throw new \RuntimeException('Aucun client à contacter : vérifiez les types de clients choisis et les consentements.');
                }

                if ($record->canal !== 'app') {
                    if (! WhatsAppCloud::configure()) {
                        throw new \RuntimeException(WhatsAppCloud::NON_CONFIGURE);
                    }

                    if ($record->modele?->statut !== 'approuve') {
                        throw new \RuntimeException('Le modèle de message n\'est pas encore approuvé par Meta.');
                    }
                }

                $record->forceFill(['statut' => 'planifiee', 'erreur' => null])->save();

                Notification::make()
                    ->title($record->planifiee_le && $record->planifiee_le->isFuture()
                        ? 'Campagne planifiée pour le '.$record->planifiee_le->format('d/m/Y à H:i')
                        : 'Campagne lancée : l\'envoi commence dans la minute')
                    ->success()->send();
            }));
    }

    public static function actionAnnuler(): Action
    {
        return Action::make('annuler')
            ->label('Annuler')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (MarketingCampagne $r) => in_array($r->statut, ['planifiee', 'en_cours'], true))
            ->requiresConfirmation()
            ->modalDescription('Les messages déjà envoyés ne peuvent pas être rappelés ; les autres ne partiront pas.')
            ->action(function (MarketingCampagne $record) {
                $record->destinataires()->where('statut', 'en_attente')->update(['statut' => 'ignore', 'erreur' => 'Campagne annulée']);
                $record->forceFill(['statut' => 'annulee'])->save();

                Notification::make()->title('Campagne annulée')->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('nom')->label('Campagne')->weight('bold')->searchable()->description(fn (MarketingCampagne $r) => $r->modele?->nom),
                TextColumn::make('canal')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'app' ? 'Application' : 'WhatsApp')
                    ->color(fn ($state) => $state === 'app' ? 'info' : 'success'),
                TextColumn::make('cible')
                    ->label('Cible')
                    ->getStateUsing(fn (MarketingCampagne $r) => ($r->compagnie?->nom ?? '—').($r->gare ? ' · '.$r->gare->quartier : ''))
                    ->description(fn (MarketingCampagne $r) => collect((array) $r->segments)->map(fn ($s) => Audience::SEGMENTS[$s] ?? $s)->implode(', ')),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MarketingCampagne::STATUTS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'terminee' => 'success', 'en_cours' => 'info', 'planifiee' => 'warning', 'erreur' => 'danger', default => 'gray',
                    }),
                TextColumn::make('nb_cibles')->label('Visés')->numeric(0, ',', ' ')->alignEnd(),
                TextColumn::make('envoyes')
                    ->label('Lus')
                    ->getStateUsing(function (MarketingCampagne $r) {
                        $s = $r->stats();

                        return $s['envoyes'] > 0 ? $s['lus'].' / '.$s['envoyes'] : '—';
                    })
                    ->alignEnd(),
                TextColumn::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(MarketingCampagne::STATUTS),
                SelectFilter::make('compagnie_id')->label('Compagnie')->relationship('compagnie', 'nom'),
            ])
            ->recordActions([
                ViewAction::make()->label('Ouvrir'),
                ActionGroup::make([
                    static::actionLancer(),
                    static::actionTester(),
                    EditAction::make(),
                    static::actionAnnuler(),
                    DeleteAction::make(),
                ]),
            ])
            ->striped();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campagne')->columns(3)->schema([
                TextEntry::make('nom')->label('Nom')->weight('bold'),
                TextEntry::make('statut')
                    ->label('Statut')->badge()
                    ->formatStateUsing(fn ($state) => MarketingCampagne::STATUTS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'terminee' => 'success', 'en_cours' => 'info', 'planifiee' => 'warning', 'erreur' => 'danger', default => 'gray',
                    }),
                TextEntry::make('canal')
                    ->label('Canal')
                    ->formatStateUsing(fn ($state) => $state === 'app' ? 'Notification dans l\'application' : 'WhatsApp'),
                TextEntry::make('modele.nom')->label('Modèle'),
                TextEntry::make('compagnie.nom')->label('Compagnie'),
                TextEntry::make('gare.quartier')->label('Gare')->placeholder('Toutes les gares'),
                TextEntry::make('planifiee_le')->label('Planifiée le')->dateTime('d/m/Y H:i')->placeholder('Dès le lancement'),
                TextEntry::make('erreur')->label('Erreur')->color('danger')->placeholder('—')->columnSpanFull()
                    ->visible(fn (MarketingCampagne $r) => filled($r->erreur)),
            ]),
            Section::make('Résultats')->schema([
                ViewEntry::make('entonnoir')
                    ->hiddenLabel()
                    ->state(fn (MarketingCampagne $r) => ['stats' => $r->stats(), 'raisons' => $r->raisonsEchec()])
                    ->view('filament.marketing.entonnoir')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketingCampagnes::route('/'),
            'create' => Pages\CreateMarketingCampagne::route('/create'),
            'view' => Pages\ViewMarketingCampagne::route('/{record}'),
            'edit' => Pages\EditMarketingCampagne::route('/{record}/edit'),
        ];
    }
}
