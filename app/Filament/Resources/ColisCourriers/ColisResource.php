<?php

namespace App\Filament\Resources\ColisCourriers;

use App\Filament\Concerns\ConditionneParModule;
use App\Models\Colis;
use App\Models\Compagnie;
use App\Models\Gare;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ColisResource extends Resource
{
    use ConditionneParModule;

    protected static ?string $model = Colis::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Colis et courriers';

    protected static ?string $modelLabel = 'envoi';

    protected static ?string $pluralModelLabel = 'envois';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|UnitEnum|null $navigationGroup = 'Opérations';

    protected static ?int $navigationSort = 10;

    protected static function moduleCle(): string
    {
        return 'colis';
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return $u && static::moduleVisible() && ($u->isSuperAdmin() || $u->isAdminCompagnie() || $u->isAgentGare());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny()
            && $record->statut === 'enregistre'
            && $record->etapeAutorisee('en_transit', auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visiblePour(auth()->user())
            ->with(['origine.ville', 'destination.ville', 'compagnie']);
    }

    // ------------------------------------------------------------------ formulaire

    protected static function libelleGare(Gare $g): string
    {
        return $g->quartier.($g->ville ? ' — '.$g->ville->nom : '');
    }

    protected static function garesOrigine(Get $get): array
    {
        $u = auth()->user();

        $q = match (true) {
            $u->isSuperAdmin() => Gare::query()->when((int) $get('compagnie_id'), fn ($q, $c) => $q->where('compagnie_id', $c)),
            $u->isAdminCompagnie() => $u->garesGerables(),
            default => Gare::query()->whereKey($u->gareId() ?? 0),
        };

        return $q->with('ville')->orderBy('quartier')->get()
            ->mapWithKeys(fn (Gare $g) => [$g->id => static::libelleGare($g)])->all();
    }

    protected static function garesDestination(Get $get): array
    {
        $u = auth()->user();
        $origine = (int) $get('gare_origine_id');
        $compagnie = $u->isSuperAdmin() ? (int) $get('compagnie_id') : (int) $u->compagnieId();

        if (! $compagnie && $origine) {
            $compagnie = (int) Gare::whereKey($origine)->value('compagnie_id');
        }

        return Gare::where('compagnie_id', $compagnie)->where('id', '!=', $origine)
            ->with('ville')->orderBy('quartier')->get()
            ->mapWithKeys(fn (Gare $g) => [$g->id => static::libelleGare($g)])->all();
    }

    /** Impose côté serveur la compagnie, la gare de départ et les valeurs automatiques. */
    public static function securiser(array $data, ?Colis $colis = null): array
    {
        $u = auth()->user();

        if ($colis) {
            $data['gare_origine_id'] = $colis->gare_origine_id; // le départ ne change pas
        } elseif ($u->isAgentGare()) {
            $data['gare_origine_id'] = $u->gareId();
            abort_unless($data['gare_origine_id'], 403);
        } elseif ($u->isAdminCompagnie()) {
            abort_unless($u->garesGerables()->whereKey($data['gare_origine_id'] ?? 0)->exists(), 403);
        }

        $origine = Gare::findOrFail($data['gare_origine_id']);

        if (! $u->isSuperAdmin()) {
            abort_unless((int) $origine->compagnie_id === (int) $u->compagnieId(), 403);
        }

        $destination = Gare::findOrFail($data['gare_destination_id']);

        abort_unless((int) $destination->compagnie_id === (int) $origine->compagnie_id, 403);
        abort_if($destination->id === $origine->id, 403);

        $data['compagnie_id'] = $origine->compagnie_id;

        if (($data['type'] ?? 'colis') === 'courrier') {
            $data['nature'] = null;
        }

        if (! $colis) {
            $data['reference'] = Colis::genererReference($data['type'] ?? 'colis');
            $data['code_retrait'] = Colis::genererCode();
            $data['cree_par'] = $u->id;
            $data['paye_le'] = ($data['paiement'] ?? 'expediteur') === 'expediteur' ? now() : null;
        } else {
            $data['paye_le'] = ($data['paiement'] ?? 'expediteur') === 'expediteur' ? ($colis->paye_le ?? now()) : null;
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $u = auth()->user();
        $superAdmin = $u->isSuperAdmin();

        return $schema->components([
            Section::make('Que souhaitez-vous envoyer ?')->schema([
                ToggleButtons::make('type')
                    ->hiddenLabel()
                    ->options(['colis' => 'Colis', 'courrier' => 'Courrier'])
                    ->icons(['colis' => 'heroicon-o-archive-box', 'courrier' => 'heroicon-o-envelope'])
                    ->colors(['colis' => 'warning', 'courrier' => 'info'])
                    ->inline()
                    ->default('colis')
                    ->required()
                    ->live(),
            ]),

            Section::make('Trajet')->columns(3)->schema([
                $superAdmin
                    ? Select::make('compagnie_id')
                        ->label('Compagnie')
                        ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($set) {
                            $set('gare_origine_id', null);
                            $set('gare_destination_id', null);
                        })
                        ->dehydrated(false)
                    : Hidden::make('compagnie_id')->dehydrated(false),

                Select::make('gare_origine_id')
                    ->label('Gare de départ')
                    ->options(fn (Get $get) => static::garesOrigine($get))
                    ->default(fn () => auth()->user()->isAgentGare() ? auth()->user()->gareId() : null)
                    ->disabled(fn (string $operation) => $operation === 'edit' || auth()->user()->isAgentGare())
                    ->dehydrated()
                    ->searchable()
                    ->required()
                    ->live(),

                Select::make('gare_destination_id')
                    ->label('Gare d\'arrivée')
                    ->options(fn (Get $get) => static::garesDestination($get))
                    ->searchable()
                    ->required()
                    ->different('gare_origine_id'),
            ]),

            Section::make('Expéditeur')->columns(2)->schema([
                TextInput::make('expediteur_nom')->label('Nom complet')->required()->maxLength(100),
                TextInput::make('expediteur_telephone')->label('Téléphone')->tel()->required()->maxLength(30),
            ]),

            Section::make('Destinataire')->columns(2)->schema([
                TextInput::make('destinataire_nom')->label('Nom complet')->required()->maxLength(100),
                TextInput::make('destinataire_telephone')->label('Téléphone')->tel()->required()->maxLength(30),
            ]),

            Section::make('Contenu')->columns(3)->schema([
                Select::make('nature')
                    ->label('Nature')
                    ->options(Colis::NATURES)
                    ->visible(fn (Get $get) => $get('type') !== 'courrier'),
                TextInput::make('nombre_pieces')->label('Nombre de pièces')->numeric()->minValue(1)->maxValue(500)->default(1)->required(),
                TextInput::make('poids_kg')->label('Poids')->numeric()->step('any')->minValue(0)->suffix('kg'),
                TextInput::make('valeur_declaree')->label('Valeur déclarée')->numeric()->minValue(0)->suffix('FCFA'),
                Textarea::make('description')->label('Description')->rows(2)->maxLength(500)->columnSpanFull(),
            ]),

            Section::make('Paiement')->columns(2)->schema([
                TextInput::make('montant')->label('Frais d\'envoi')->numeric()->minValue(0)->default(0)->required()->suffix('FCFA'),
                ToggleButtons::make('paiement')
                    ->label('Qui paie ?')
                    ->options(['expediteur' => 'Payé au départ', 'destinataire' => 'À payer à l\'arrivée'])
                    ->colors(['expediteur' => 'success', 'destinataire' => 'warning'])
                    ->inline()
                    ->default('expediteur')
                    ->required(),
            ]),
        ]);
    }

    // ------------------------------------------------------------------ actions

    protected static function executer(Colis $record, string $vers, ?string $note, array $extra, string $titre): void
    {
        try {
            $record->avancer($vers, auth()->user(), $note, $extra);
            $record->refresh();

            Notification::make()->title($titre)->success()->send();
        } catch (AuthorizationException|\DomainException $e) {
            Notification::make()->title('Action refusée')->body($e->getMessage())->danger()->send();
        }
    }

    public static function actionExpedier(): Action
    {
        return Action::make('expedier')
            ->label('Expédier')
            ->icon('heroicon-o-truck')
            ->color('warning')
            ->visible(fn (Colis $record) => $record->etapeAutorisee('en_transit', auth()->user()))
            ->modalHeading('Expédier cet envoi')
            ->schema([
                TextInput::make('note')->label('Car ou départ (facultatif)')->placeholder('Ex. : car de 14h30')->maxLength(150),
            ])
            ->action(fn (Colis $record, array $data) => static::executer($record, 'en_transit', $data['note'] ?? null, [], 'Envoi expédié'));
    }

    public static function actionArriver(): Action
    {
        return Action::make('arriver')
            ->label('Marquer arrivé')
            ->icon('heroicon-o-map-pin')
            ->color('primary')
            ->visible(fn (Colis $record) => $record->etapeAutorisee('arrive', auth()->user()))
            ->requiresConfirmation()
            ->modalHeading('L\'envoi est arrivé à la gare ?')
            ->action(fn (Colis $record) => static::executer($record, 'arrive', null, [], 'Envoi arrivé, prêt à être retiré'));
    }

    public static function actionRemettre(): Action
    {
        return Action::make('remettre')
            ->label('Remettre')
            ->icon('heroicon-o-hand-raised')
            ->color('success')
            ->visible(fn (Colis $record) => $record->etapeAutorisee('livre', auth()->user()))
            ->modalHeading('Remettre l\'envoi')
            ->modalDescription(fn (Colis $record) => 'Destinataire attendu : '.$record->destinataire_nom.'.'
                .($record->paiement === 'destinataire' && ! $record->paye_le
                    ? ' À encaisser auprès du destinataire : '.number_format($record->montant, 0, ',', ' ').' FCFA.'
                    : ''))
            ->fillForm(fn (Colis $record) => ['remis_a' => $record->destinataire_nom])
            ->schema([
                TextInput::make('remis_a')->label('Remis à (nom de la personne)')->required()->maxLength(100),
                TextInput::make('code')
                    ->label('Code de retrait présenté')
                    ->maxLength(6)
                    ->helperText('Le code figure sur le reçu de l\'expéditeur. Sans code, vérifiez une pièce d\'identité.'),
                Toggle::make('piece_verifiee')->label('J\'ai vérifié une pièce d\'identité'),
                Toggle::make('encaisse')->label('J\'ai encaissé le montant dû à l\'arrivée (si un montant est à payer)'),
            ])
            ->action(function (Colis $record, array $data, Action $action) {
                $code = trim((string) ($data['code'] ?? ''));
                $codeBon = $code !== '' && hash_equals((string) $record->code_retrait, $code);

                if ($code !== '' && ! $codeBon) {
                    Notification::make()->title('Code incorrect')->body('Le code présenté ne correspond pas à cet envoi.')->danger()->send();
                    $action->halt();
                }

                if (! $codeBon && empty($data['piece_verifiee'])) {
                    Notification::make()->title('Vérification nécessaire')->body('Saisissez le bon code, ou vérifiez une pièce d\'identité.')->warning()->send();
                    $action->halt();
                }

                if ($record->paiement === 'destinataire' && ! $record->paye_le && empty($data['encaisse'])) {
                    Notification::make()->title('Montant à encaisser')->body('Confirmez l\'encaissement avant de remettre l\'envoi.')->warning()->send();
                    $action->halt();
                }

                static::executer(
                    $record,
                    'livre',
                    'Remis à '.trim($data['remis_a']).($codeBon ? ' (code vérifié)' : ' (pièce d\'identité vérifiée)'),
                    ['remis_a' => trim($data['remis_a']), 'code_verifie' => $codeBon],
                    'Envoi remis'
                );
            });
    }

    public static function actionIncident(): Action
    {
        return Action::make('incident')
            ->label('Signaler un incident')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->visible(fn (Colis $record) => $record->etapeAutorisee('incident', auth()->user()))
            ->modalHeading('Signaler un incident')
            ->schema([
                Textarea::make('note')->label('Que s\'est-il passé ?')->required()->minLength(5)->maxLength(255)->rows(3),
            ])
            ->action(fn (Colis $record, array $data) => static::executer($record, 'incident', $data['note'], [], 'Incident enregistré'));
    }

    public static function actionRecu(): Action
    {
        return Action::make('recu')
            ->label('Reçu PDF')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->action(function (Colis $record) {
                $u = auth()->user();
                $record->loadMissing(['compagnie', 'origine.ville', 'destination.ville', 'createur']);

                // Le code de retrait n'est imprimé que pour la gare de départ et l'administration.
                $avecCode = $u->isSuperAdmin() || $u->isAdminCompagnie() || (int) $u->gareId() === (int) $record->gare_origine_id;

                $pdf = Pdf::loadView('pdf.colis-recu', ['c' => $record, 'avecCode' => $avecCode])->setPaper('a5', 'portrait');

                return response()->streamDownload(fn () => print ($pdf->output()), $record->reference.'.pdf', ['Content-Type' => 'application/pdf']);
            });
    }

    // ------------------------------------------------------------------ table

    public static function table(Table $table): Table
    {
        $couleur = fn ($statut) => match ($statut) {
            'enregistre' => 'info',
            'en_transit' => 'warning',
            'arrive' => 'primary',
            'livre' => 'success',
            default => 'danger',
        };

        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('reference')
                    ->label('Envoi')
                    ->fontFamily('mono')->weight('bold')->searchable()->copyable()
                    ->description(fn (Colis $r) => ($r->type === 'courrier' ? 'Courrier' : 'Colis').' · '.$r->nombre_pieces.' pièce(s)'),
                TextColumn::make('expediteur_nom')
                    ->label('Expéditeur')->searchable()
                    ->description(fn (Colis $r) => $r->expediteur_telephone),
                TextColumn::make('destinataire_nom')
                    ->label('Destinataire')->searchable()
                    ->description(fn (Colis $r) => $r->destinataire_telephone),
                TextColumn::make('trajet')
                    ->label('Trajet')
                    ->getStateUsing(fn (Colis $r) => ($r->origine?->quartier ?? '?').' → '.($r->destination?->quartier ?? '?'))
                    ->description(fn (Colis $r) => $r->compagnie?->nom),
                TextColumn::make('statut')
                    ->label('Étape')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Colis::STATUTS[$state] ?? $state)
                    ->color($couleur),
                TextColumn::make('montant')
                    ->label('Frais')
                    ->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()
                    ->description(fn (Colis $r) => $r->paiement === 'destinataire'
                        ? ($r->paye_le ? 'Payé à l\'arrivée' : 'À payer à l\'arrivée')
                        : 'Payé au départ'),
                TextColumn::make('created_at')->label('Enregistré le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Étape')->options(Colis::STATUTS),
                SelectFilter::make('type')->label('Type')->options(['colis' => 'Colis', 'courrier' => 'Courrier']),
            ])
            ->recordActions([
                ViewAction::make()->label('Ouvrir'),
                ActionGroup::make([
                    static::actionExpedier(),
                    static::actionArriver(),
                    static::actionRemettre(),
                    static::actionIncident(),
                    EditAction::make(),
                    static::actionRecu(),
                ]),
            ])
            ->striped();
    }

    // ------------------------------------------------------------------ fiche

    public static function infolist(Schema $schema): Schema
    {
        $u = fn () => auth()->user();

        return $schema->components([
            Section::make('Envoi')->columns(3)->schema([
                TextEntry::make('reference')->label('Référence')->weight('bold')->copyable()->fontFamily('mono'),
                TextEntry::make('statut')
                    ->label('Étape')->badge()
                    ->formatStateUsing(fn ($state) => Colis::STATUTS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'enregistre' => 'info', 'en_transit' => 'warning', 'arrive' => 'primary', 'livre' => 'success', default => 'danger',
                    }),
                TextEntry::make('type')->label('Type')->formatStateUsing(fn ($s) => $s === 'courrier' ? 'Courrier' : 'Colis'),
                TextEntry::make('origine.quartier')->label('Gare de départ'),
                TextEntry::make('destination.quartier')->label('Gare d\'arrivée'),
                TextEntry::make('compagnie.nom')->label('Compagnie'),
            ]),
            // Section::make('Les personnes')->columns(2)->schema([
            //     TextEntry::make('expediteur_nom')->label('Expéditeur')->helperText(fn (Colis $r) => $r->expediteur_telephone),
            //     TextEntry::make('destinataire_nom')->label('Destinataire')->helperText(fn (Colis $r) => $r->destinataire_telephone),
            // ]),
            Section::make('Les personnes')->columns(2)->schema([
                TextEntry::make('expediteur')
                    ->label('Expéditeur')
                    ->state(fn (Colis $r) => $r->expediteur_nom.' · '.$r->expediteur_telephone),
                TextEntry::make('destinataire')
                    ->label('Destinataire')
                    ->state(fn (Colis $r) => $r->destinataire_nom.' · '.$r->destinataire_telephone),
            ]),
            Section::make('Contenu et paiement')->columns(3)->schema([
                TextEntry::make('nature')->label('Nature')->formatStateUsing(fn ($s) => Colis::NATURES[$s] ?? '—'),
                TextEntry::make('nombre_pieces')->label('Pièces'),
                TextEntry::make('poids_kg')->label('Poids')->suffix(' kg')->placeholder('—'),
                TextEntry::make('valeur_declaree')->label('Valeur déclarée')->numeric(0, ',', ' ')->suffix(' FCFA')->placeholder('—'),
                TextEntry::make('montant')->label('Frais d\'envoi')->numeric(0, ',', ' ')->suffix(' FCFA'),
                TextEntry::make('paiement')->label('Paiement')->formatStateUsing(fn (Colis $r) => $r->paiement === 'destinataire'
                    ? ($r->paye_le ? 'Payé à l\'arrivée' : 'À payer à l\'arrivée')
                    : 'Payé au départ'),
                TextEntry::make('description')->label('Description')->columnSpanFull()->placeholder('—'),
                TextEntry::make('code_retrait')
                    ->label('Code de retrait (à donner à l\'expéditeur)')
                    ->fontFamily('mono')->weight('bold')
                    ->visible(fn (Colis $r) => $u()->isSuperAdmin() || $u()->isAdminCompagnie() || (int) $u()->gareId() === (int) $r->gare_origine_id)
                    ->columnSpanFull(),
            ]),
            Section::make('Suivi')->schema([
                ViewEntry::make('suivi')
                    ->hiddenLabel()
                    ->state(fn (Colis $r) => $r->suivi())
                    ->view('filament.colis.suivi')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListColis::route('/'),
            'create' => Pages\CreateColis::route('/create'),
            'view' => Pages\ViewColis::route('/{record}'),
            'edit' => Pages\EditColis::route('/{record}/edit'),
        ];
    }
}
