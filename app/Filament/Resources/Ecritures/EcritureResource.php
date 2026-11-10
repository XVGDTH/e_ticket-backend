<?php

namespace App\Filament\Resources\Ecritures;

use App\Filament\Pages\SaisieEcriture;
use App\Models\CptEcriture;
use App\Models\CptJournal;
use App\Models\Gare;
use App\Services\Compta;
use App\Services\Modules;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class EcritureResource extends Resource
{
    protected static ?string $model = CptEcriture::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Écritures comptables';

    protected static ?string $modelLabel = 'écriture';

    protected static ?string $pluralModelLabel = 'écritures';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?int $navigationSort = 1;

public static function autorise(): bool
{
    $u = auth()->user();

    return $u && Modules::visiblePour($u, 'comptabilite')
        && ($u->isSuperAdmin()
            || ($u->isAdminCompagnie() && ! $u->villeId())
            || $u->roleDonneAccesAuModule('comptabilite'));
}

/** Lecture seule pour l'auditeur : seuls les rôles avec le droit « modifier » écrivent. */
public static function peutEcrire(): bool
{
    $u = auth()->user();

    return static::autorise()
        && ($u->isSuperAdmin()
            || ($u->isAdminCompagnie() && ! $u->villeId())
            || in_array('modifier', $u->droitsMetierPourModule('comptabilite'), true));
}

    public static function canViewAny(): bool
    {
        return static::autorise();
    }

    public static function canCreate(): bool
    {
        return false; // la création passe par la page de saisie
    }

    /** Modifiable tant qu'elle est manuelle, en brouillon et qu'aucune ligne n'a été validée. */
    public static function canEdit(Model $record): bool
    {
        $validees = $record->lignes_validees_count ?? $record->lignes()->whereNotNull('valide_le')->count();

        return static::peutEcrire()
            && $record->statut === 'brouillon'
            && ($record->origine ?? 'manuelle') === 'manuelle'
            && (int) $validees === 0;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visiblePour(auth()->user())
            ->with(['journal'])
            ->withSum('lignes', 'debit')
            ->withSum('lignes', 'credit')
            ->withCount(['lignes', 'lignes as lignes_validees_count' => fn ($q) => $q->whereNotNull('valide_le')]);
    }

    /** Compagnie concernée : celle de la saisie pour le super admin, la sienne sinon. */
    public static function cid(mixed $valeur): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() ? (int) $valeur : (int) $u->compagnieId();
    }

    /** Vérifie l'écriture avant enregistrement : équilibre, montants, comptes, sections et imputations. */
    public static function verifier(array $data, int $compagnieId): void
    {
        $lignes = collect($data['lignes'] ?? [])->values();
        $debit = fn ($l) => (int) ($l['debit'] ?? 0);
        $credit = fn ($l) => (int) ($l['credit'] ?? 0);
        $fmt = fn (int $n) => number_format($n, 0, ',', ' ').' FCFA';
        $erreur = null;

        if ($lignes->count() < 2) {
            $erreur = 'Ajoutez au moins deux lignes.';
        } elseif ($lignes->contains(fn ($l) => $debit($l) > 0 && $credit($l) > 0)) {
            $erreur = 'Une ligne ne peut pas avoir un débit et un crédit à la fois.';
        } elseif ($lignes->contains(fn ($l) => $debit($l) <= 0 && $credit($l) <= 0)) {
            $erreur = 'Chaque ligne doit avoir un montant, au débit ou au crédit.';
        } elseif ($lignes->sum($debit) !== $lignes->sum($credit)) {
            $erreur = 'Écriture déséquilibrée : débit '.$fmt((int) $lignes->sum($debit)).', crédit '.$fmt((int) $lignes->sum($credit)).'.';
        }

        $sections = Compta::sectionsIds($compagnieId);

        if (! $erreur) {
            $comptes = Compta::comptesIds($compagnieId);

            foreach ($lignes as $l) {
                if (! in_array((int) ($l['compte_id'] ?? 0), $comptes, true)) {
                    $erreur = 'Un des comptes choisis n\'est pas autorisé.';
                    break;
                }

                if (filled($l['section_id'] ?? null) && ! in_array((int) $l['section_id'], $sections, true)) {
                    $erreur = 'Une des sections analytiques choisies n\'est pas autorisée.';
                    break;
                }
            }
        }

        if (! $erreur) {
            $imputations = collect($data['imputations'] ?? [])->filter(
                fn ($i) => filled($i['rang'] ?? null) || filled($i['section_id'] ?? null) || (int) ($i['montant'] ?? 0) > 0
            );

            foreach ($imputations as $i) {
                if (! filled($i['rang'] ?? null) || ! isset($lignes[(int) $i['rang'] - 1])) {
                    $erreur = 'Chaque imputation analytique doit viser une ligne de l\'écriture.';
                    break;
                }

                if (! in_array((int) ($i['section_id'] ?? 0), $sections, true)) {
                    $erreur = 'Chaque imputation analytique doit avoir une section valide.';
                    break;
                }

                if ((int) ($i['montant'] ?? 0) <= 0) {
                    $erreur = 'Chaque imputation analytique doit avoir un montant positif.';
                    break;
                }
            }

            if (! $erreur) {
                foreach ($imputations->groupBy('rang') as $rang => $groupe) {
                    $ligne = $lignes[(int) $rang - 1];
                    $base = max($debit($ligne), $credit($ligne));
                    $somme = (int) $groupe->sum(fn ($i) => (int) $i['montant']);

                    if ($somme !== $base) {
                        $erreur = 'Ligne '.$rang.' : les imputations totalisent '.$fmt($somme).' pour un montant de '.$fmt($base).'. Elles doivent être égales.';
                        break;
                    }
                }
            }
        }

        if ($erreur) {
            throw ValidationException::withMessages(['data.lignes' => $erreur]);
        }
    }

    /** Impose côté serveur la compagnie, l'exercice et l'auteur. */
    public static function securiser(array $data, ?CptEcriture $ecriture = null): array
    {
        $u = auth()->user();
        $cid = static::cid($data['compagnie_id'] ?? null);

       abort_unless($cid && static::peutEcrire(), 403);

        if ($ecriture) {
            abort_unless((int) $ecriture->compagnie_id === $cid, 403);
        }

        $data['compagnie_id'] = $cid;
        Compta::initialiser($cid);

        abort_unless(CptJournal::whereKey($data['journal_id'] ?? 0)->where('compagnie_id', $cid)->exists(), 403);

        $exercice = Compta::exerciceOuvert($cid, Carbon::parse($data['date_ecriture']));

        if (! $exercice) {
            throw ValidationException::withMessages(['data.date_ecriture' => 'Aucun exercice ouvert ne couvre cette date.']);
        }

        $data['exercice_id'] = $exercice->id;

        if (! empty($data['gare_id'])) {
            abort_unless(Gare::whereKey($data['gare_id'])->where('compagnie_id', $cid)->exists(), 403);
        }

        if (! $ecriture) {
            $data['cree_par'] = $u->id;
            $data['origine'] = 'manuelle';
        }

        return $data;
    }

    // ------------------------------------------------------------------ actions

    protected static function executer(\Closure $faire, string $titre): void
    {
        try {
            $faire();
            Notification::make()->title($titre)->success()->send();
        } catch (AuthorizationException|\DomainException $e) {
            Notification::make()->title('Action refusée')->body($e->getMessage())->danger()->send();
        }
    }

    public static function actionContrepasser(): Action
    {
        return Action::make('contrepasser')
            ->label('Contre-passer')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (CptEcriture $record) => static::peutEcrire() && $record->statut === 'comptabilise' && $record->origine !== 'contrepassation' && ! CptEcriture::where('contrepasse_de', $record->id)->exists())
            ->requiresConfirmation()
            ->modalDescription('Une écriture inverse sera passée à la date du jour. L\'écriture d\'origine reste visible dans les livres.')
            ->action(fn (CptEcriture $record) => static::executer(fn () => $record->contrepasser(auth()->user()), 'Écriture contre-passée'));
    }

    // ------------------------------------------------------------------ liste

    public static function statutLibelle(CptEcriture $r): string
    {
        if ($r->statut === 'comptabilise') {
            return 'Comptabilisée';
        }

        return (int) ($r->lignes_validees_count ?? 0) > 0 ? 'En validation' : 'À valider';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_ecriture', 'desc')
            ->columns([
                TextColumn::make('journal.code')->label('Code journal')->badge()->color('info'),
                TextColumn::make('date_ecriture')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('numero')->label('N° pièce')->fontFamily('mono')->weight('bold')->searchable()->copyable(),
                TextColumn::make('reference')->label('Réf. pièce')->searchable()->placeholder('—'),
                TextColumn::make('libelle')->label('Libellé écriture')->limit(40)->searchable(),
                TextColumn::make('numero_facture')->label('N° facture')->fontFamily('mono')->searchable()->copyable()->placeholder('—'),
                TextColumn::make('code_kmaa')->label('Code kmaa')->badge()->color('warning')->searchable()->copyable()->placeholder('—'),
                TextColumn::make('lignes_sum_debit')->label('Débit')->numeric(0, ',', ' ')->alignEnd(),
                TextColumn::make('lignes_sum_credit')->label('Crédit')->numeric(0, ',', ' ')->alignEnd(),
                // TextColumn::make('progression')->label('Lignes validées')->badge()
                //     ->state(fn (CptEcriture $r) => ((int) $r->lignes_validees_count).' / '.((int) $r->lignes_count))
                //     ->color(fn (CptEcriture $r) => (int) $r->lignes_validees_count === (int) $r->lignes_count && $r->lignes_count > 0
                //         ? 'success'
                //         : ((int) $r->lignes_validees_count > 0 ? 'info' : 'gray')),
                TextColumn::make('statut')->label('Statut')->badge()
                    ->formatStateUsing(fn ($state, CptEcriture $record) => static::statutLibelle($record))
                    ->color(fn ($state, CptEcriture $record) => match (static::statutLibelle($record)) {
                        'Comptabilisée' => 'success', 'En validation' => 'info', default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(['brouillon' => 'En attente de validation', 'comptabilise' => 'Comptabilisée']),
                SelectFilter::make('journal_id')->label('Journal')->relationship('journal', 'code'),
                SelectFilter::make('origine')->label('Origine')->options(['manuelle' => 'Manuelle', 'automatique' => 'Automatique', 'contrepassation' => 'Contre-passation']),
                TernaryFilter::make('est_facture')->label('Factures'),
            ])
            ->recordActions([
                Action::make('ouvrir')
                    ->label('Ouvrir')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (CptEcriture $record) => SaisieEcriture::getUrl(['ecriture' => $record->id])),
                ActionGroup::make([
                    static::actionContrepasser(),
                    DeleteAction::make(),
                ]),
            ])
            ->recordUrl(fn (CptEcriture $record) => SaisieEcriture::getUrl(['ecriture' => $record->id]))
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEcritures::route('/'),
        ];
    }
}