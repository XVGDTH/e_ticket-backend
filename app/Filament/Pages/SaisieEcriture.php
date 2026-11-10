<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Ecritures\EcritureResource;
use App\Models\Compagnie;
use App\Models\CptCompte;
use App\Models\CptEcriture;
use App\Models\CptImputation;
use App\Models\CptJournal;
use App\Models\CptLigne;
use App\Services\Compta;
use App\Services\Modules;
use App\Services\NumerotationFactures;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use UnitEnum;

class SaisieEcriture extends Page
{
    protected string $view = 'filament.pages.saisie-ecriture';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'saisie-ecriture';

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    #[Url(as: 'ecriture')]
    public ?int $ecritureId = null;

    /** En-tête de la pièce */
    public array $entete = [];

    /** Lignes de la grille */
    public array $lignes = [];

    /** Infos de l'écriture enregistrée */
    public array $meta = [];

    /** Index de la ligne ouverte dans la fenêtre d'imputation */
    public ?int $modal = null;

    /** Lignes d'imputation en cours d'édition dans la fenêtre */
    public array $ventilation = [];

    protected ?array $cacheComptes = null;

    public static function canAccess(): bool
    {
        return EcritureResource::autorise();
    }

        public static function peutEcrire(): bool
    {
        $u = auth()->user();

        return static::autorise()
            && ($u->isSuperAdmin()
                || ($u->isAdminCompagnie() && ! $u->villeId())
                || in_array('modifier', $u->droitsMetierPourModule('comptabilite'), true));
    }
    

    public function getTitle(): string
    {
        return $this->ecritureId ? 'Écriture '.($this->meta['numero'] ?? '') : 'Nouvelle écriture';
    }

    // ------------------------------------------------------------------ chargement

    public function mount(): void
    {
        $this->ecritureId ? $this->charger() : $this->nouvelle();
    }

    public function nouvelle(): void
    {
        $u = auth()->user();

        $this->ecritureId = null;
        $this->meta = [];
        $this->modal = null;
        $this->ventilation = [];
        $this->cacheComptes = null;
        $this->entete = [
            'compagnie_id' => $u->isSuperAdmin()
                ? (string) (Compagnie::orderBy('nom')->value('id') ?? '')
                : (string) $u->compagnieId(),
            'journal_id' => '',
            'date_ecriture' => today()->format('Y-m-d'),
            'reference' => '',
            'libelle' => '',
            'est_facture' => false,
            'tiers' => '',
            'date_echeance' => '',
        ];
        $this->lignes = [$this->ligneVide(), $this->ligneVide()];

        if ($this->cid()) {
            Compta::initialiser($this->cid());
        }
    }

    protected function ligneVide(): array
    {
        return [
            'id' => null, 'compte' => '', 'compte_id' => null, 'intitule' => '',
            'libelle' => '', 'debit' => '', 'credit' => '', 'section_id' => '',
            'imputations' => [],
        ];
    }

    protected function charger(): void
    {
        $e = CptEcriture::query()
            ->visiblePour(auth()->user())
            ->with(['lignes.compte', 'lignes.imputations'])
            ->findOrFail($this->ecritureId);

        Compta::initialiser((int) $e->compagnie_id);
        $this->cacheComptes = null;

        $this->entete = [
            'compagnie_id' => (string) $e->compagnie_id,
            'journal_id' => (string) $e->journal_id,
            'date_ecriture' => $e->date_ecriture->format('Y-m-d'),
            'reference' => (string) $e->reference,
            'libelle' => (string) $e->libelle,
            'est_facture' => (bool) $e->est_facture,
            'tiers' => (string) $e->tiers,
            'date_echeance' => $e->date_echeance?->format('Y-m-d') ?? '',
        ];

        $this->meta = [
            'numero' => $e->numero,
            'statut' => $e->statut,
            'origine' => $e->origine ?? 'manuelle',
            'numero_facture' => $e->numero_facture,
            'code_kmaa' => $e->code_kmaa,
        ];

        $this->lignes = $e->lignes->map(fn ($l) => [
            'id' => $l->id,
            'compte' => (string) $l->compte?->numero,
            'compte_id' => $l->compte_id,
            'intitule' => (string) $l->compte?->intitule,
            'libelle' => (string) $l->libelle,
            'debit' => $l->debit ?: '',
            'credit' => $l->credit ?: '',
            'section_id' => $l->section_id ? (string) $l->section_id : '',
            'imputations' => $l->imputations->map(fn ($im) => [
                'section_id' => (string) $im->section_id,
                'montant' => (int) $im->montant,
            ])->all(),
        ])->values()->all();
    }

    // ------------------------------------------------------------------ état

    protected function cid(): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() ? (int) ($this->entete['compagnie_id'] ?? 0) : (int) $u->compagnieId();
    }

    /** Écriture figée : déjà comptabilisée ou générée automatiquement. */
public function verrou(): bool
{
    return ! EcritureResource::peutEcrire()
        || ($this->meta['statut'] ?? 'brouillon') === 'comptabilise'
        || ($this->meta['origine'] ?? 'manuelle') !== 'manuelle';
}

    public function peutContrepasser(): bool
    {
               return EcritureResource::peutEcrire()
            && $this->ecritureId
            && ($this->meta['statut'] ?? '') === 'comptabilise'
            && ($this->meta['origine'] ?? '') !== 'contrepassation'
            && ! CptEcriture::where('contrepasse_de', $this->ecritureId)->exists();
    }

    protected function comptes(): array
    {
        return $this->cacheComptes ??= CptCompte::query()->where('actif', true)
            ->where(fn ($q) => $q->whereNull('compagnie_id')->orWhere('compagnie_id', $this->cid()))
            ->orderBy('numero')
            ->get(['id', 'numero', 'intitule'])
            ->mapWithKeys(fn ($c) => [(string) $c->numero => ['id' => $c->id, 'intitule' => $c->intitule]])
            ->all();
    }

    protected function getViewData(): array
    {
        $cid = $this->cid();

        return [
            'comptes' => $this->comptes(),
            'journaux' => Compta::optionsJournaux($cid),
            'sections' => Compta::optionsSections($cid),
            'compagnies' => auth()->user()->isSuperAdmin() ? Compagnie::orderBy('nom')->pluck('nom', 'id')->all() : [],
        ];
    }

    protected function erreur(string $titre, ?string $corps = null): void
    {
        Notification::make()->title($titre)->body($corps)->danger()->send();
    }

    protected function succes(string $titre, ?string $corps = null): void
    {
        Notification::make()->title($titre)->body($corps)->success()->send();
    }

    // ------------------------------------------------------------------ réactions de la grille

    // public function updatedEntete($valeur, string $cle): void
    // {
        public function updatedEntete($valeur = null, ?string $cle = null): void
    {
        if ($cle === null) {
            return;
        }
        if ($cle === 'compagnie_id') {
            abort_unless(auth()->user()->isSuperAdmin(), 403);
            $this->entete['journal_id'] = '';
            $this->cacheComptes = null;
            Compta::initialiser($this->cid());

            foreach (array_keys($this->lignes) as $i) {
                $this->resoudreCompte($i);
            }
        }

        if ($cle === 'journal_id' && filled($valeur)) {
            $type = CptJournal::whereKey((int) $valeur)->where('compagnie_id', $this->cid())->value('type');
            $this->entete['est_facture'] = in_array($type, ['ventes', 'achats'], true);
        }
    }

    // public function updatedLignes($valeur, string $cle): void
    // {
    //     [$i, $champ] = array_pad(explode('.', $cle, 2), 2, null);
    //     $i = (int) $i;
        public function updatedLignes($valeur = null, ?string $cle = null): void
    {
        // Tout le tableau remplacé d'un coup (aucune clé précise) : on remet les comptes à jour.
        if ($cle === null) {
            foreach (array_keys($this->lignes) as $i) {
                $this->resoudreCompte($i);
            }

            return;
        }

        [$i, $champ] = array_pad(explode('.', $cle, 2), 2, null);
        $i = (int) $i;

        if (! isset($this->lignes[$i])) {
            return;
        }

        if ($champ === 'compte') {
            $this->resoudreCompte($i);
        }

        if ($champ === 'debit' && (int) $valeur > 0) {
            $this->lignes[$i]['credit'] = '';
        }

        if ($champ === 'credit' && (int) $valeur > 0) {
            $this->lignes[$i]['debit'] = '';
        }

        if ($champ === 'section_id') {
            $this->lignes[$i]['imputations'] = [];

            if (filled($valeur)) {
                $this->ouvrirModal($i);
            }
        }
    }

    protected function resoudreCompte(int $i): void
    {
        $saisie = (string) ($this->lignes[$i]['compte'] ?? '');
        preg_match('/^\s*(\d+)/', $saisie, $m);
        $numero = $m[1] ?? '';
        $compte = $this->comptes()[$numero] ?? null;

        $this->lignes[$i]['compte'] = $numero;
        $this->lignes[$i]['compte_id'] = $compte['id'] ?? null;
        $this->lignes[$i]['intitule'] = $compte['intitule'] ?? ($numero !== '' ? 'Compte inconnu' : '');
    }

    public function ajouterLigne(): void
    {
        if (! $this->verrou()) {
            $this->lignes[] = $this->ligneVide();
        }
    }

    public function retirerLigne(int $i): void
    {
        if ($this->verrou() || count($this->lignes) <= 2) {
            return;
        }

        array_splice($this->lignes, $i, 1);
    }

    // ------------------------------------------------------------------ fenêtre d'imputation analytique

    protected function montantLigne(int $i): int
    {
        return max((int) ($this->lignes[$i]['debit'] ?? 0), (int) ($this->lignes[$i]['credit'] ?? 0));
    }

    public function ouvrirModal(int $i): void
    {
        if (! isset($this->lignes[$i])) {
            return;
        }

        $montant = $this->montantLigne($i);

        if ($montant <= 0 && ! $this->verrou()) {
            $this->lignes[$i]['section_id'] = '';
            $this->erreur('Saisissez d\'abord le montant', 'Le débit ou le crédit de la ligne doit être renseigné avant l\'imputation.');

            return;
        }

        $imputations = $this->lignes[$i]['imputations'] ?? [];

        if (! $imputations && filled($this->lignes[$i]['section_id'] ?? null)) {
            $imputations = [['section_id' => $this->lignes[$i]['section_id'], 'montant' => $montant]];
        }

        $this->ventilation = $imputations ?: [['section_id' => '', 'montant' => $montant]];
        $this->modal = $i;
    }

    public function fermerModal(): void
    {
        $this->modal = null;
        $this->ventilation = [];
    }

    public function ajouterVentilation(): void
    {
        if ($this->modal === null || $this->verrou()) {
            return;
        }

        $reste = $this->montantLigne($this->modal) - (int) collect($this->ventilation)->sum(fn ($v) => (int) ($v['montant'] ?: 0));
        $this->ventilation[] = ['section_id' => '', 'montant' => max($reste, 0)];
    }

    public function retirerVentilation(int $k): void
    {
        if ($this->verrou()) {
            return;
        }

        unset($this->ventilation[$k]);
        $this->ventilation = array_values($this->ventilation);
    }

    public function appliquerVentilation(): void
    {
        $i = $this->modal;

        if ($i === null || $this->verrou()) {
            return;
        }

        $montant = $this->montantLigne($i);
        $lignes = collect($this->ventilation)
            ->filter(fn ($v) => filled($v['section_id'] ?? null) || (int) ($v['montant'] ?? 0) > 0)
            ->values();

        if ($lignes->isEmpty()) {
            $this->lignes[$i]['imputations'] = [];
            $this->lignes[$i]['section_id'] = '';
            $this->fermerModal();

            return;
        }

        if ($lignes->contains(fn ($v) => ! filled($v['section_id'] ?? null) || (int) ($v['montant'] ?? 0) <= 0)) {
            $this->erreur('Imputation incomplète', 'Chaque ligne doit avoir une section et un montant.');

            return;
        }

        $sections = Compta::optionsSections($this->cid());

        if ($lignes->contains(fn ($v) => ! array_key_exists((int) $v['section_id'], $sections))) {
            $this->erreur('Section non autorisée');

            return;
        }

        $somme = (int) $lignes->sum(fn ($v) => (int) $v['montant']);

        if ($somme !== $montant) {
            $this->erreur(
                'Montants imputés incorrects',
                'Total imputé : '.number_format($somme, 0, ',', ' ').' FCFA pour une ligne de '.number_format($montant, 0, ',', ' ').' FCFA.'
            );

            return;
        }

        $this->lignes[$i]['imputations'] = $lignes->map(fn ($v) => [
            'section_id' => (string) $v['section_id'],
            'montant' => (int) $v['montant'],
        ])->all();
        $this->lignes[$i]['section_id'] = (string) $lignes->first()['section_id'];
        $this->fermerModal();
    }

    // ------------------------------------------------------------------ enregistrement

    /** Enregistre et comptabilise directement. $nouvelle = true : repart sur une écriture vierge. */
    public function enregistrer(bool $nouvelle = false): void
    {
        // abort_unless(static::canAccess(), 403);
         abort_unless(EcritureResource::peutEcrire(), 403);

        if ($this->verrou()) {
            $this->erreur('Écriture figée', 'Elle est déjà comptabilisée : utilisez « Contre-passer » pour la corriger.');

            return;
        }

        $cid = $this->cid();

        if (! $cid || ! filled($this->entete['journal_id']) || ! filled($this->entete['date_ecriture']) || blank(trim((string) $this->entete['libelle']))) {
            $this->erreur('En-tête incomplet', 'Renseignez le journal, la date et le libellé.');

            return;
        }

        $lignes = collect($this->lignes)
            ->filter(fn ($l) => filled($l['compte']) || (int) ($l['debit'] ?: 0) > 0 || (int) ($l['credit'] ?: 0) > 0)
            ->values();

        $imputations = [];
        foreach ($lignes as $rang => $l) {
            foreach ($l['imputations'] as $im) {
                $imputations[] = ['rang' => $rang + 1, 'section_id' => $im['section_id'], 'montant' => $im['montant']];
            }
        }

        $controle = [
            'lignes' => $lignes->map(fn ($l) => [
                'compte_id' => $l['compte_id'],
                'debit' => (int) ($l['debit'] ?: 0),
                'credit' => (int) ($l['credit'] ?: 0),
                'section_id' => filled($l['section_id']) ? $l['section_id'] : null,
            ])->all(),
            'imputations' => $imputations,
        ];

        try {
            EcritureResource::verifier($controle, $cid);

            $ecriture = DB::transaction(function () use ($lignes, $cid) {
                $existante = $this->ecritureId
                    ? CptEcriture::query()->visiblePour(auth()->user())->lockForUpdate()->findOrFail($this->ecritureId)
                    : null;

                $d = EcritureResource::securiser([
                    'compagnie_id' => $cid,
                    'journal_id' => $this->entete['journal_id'],
                    'date_ecriture' => $this->entete['date_ecriture'],
                    'libelle' => trim($this->entete['libelle']),
                    'reference' => $this->entete['reference'] ?: null,
                    'est_facture' => (bool) $this->entete['est_facture'],
                    'tiers' => $this->entete['tiers'] ?: null,
                    'date_echeance' => $this->entete['date_echeance'] ?: null,
                ], $existante);

                $attributs = Arr::only($d, [
                    'compagnie_id', 'journal_id', 'exercice_id', 'date_ecriture', 'libelle', 'reference',
                    'est_facture', 'tiers', 'date_echeance', 'cree_par', 'origine',
                ]);

                if ($existante) {
                    $existante->update($attributs);
                    $e = $existante;
                    // d'anciens brouillons ont pu avoir des lignes déjà marquées : on repart propre
                    CptLigne::where('ecriture_id', $e->id)->update(['valide_le' => null, 'valide_par' => null]);
                } else {
                    $e = CptEcriture::create($attributs);
                }

                $conserves = [];

                foreach ($lignes as $l) {
                    $valeurs = [
                        'compte_id' => (int) $l['compte_id'],
                        'libelle' => $l['libelle'] ?: null,
                        'debit' => (int) ($l['debit'] ?: 0),
                        'credit' => (int) ($l['credit'] ?: 0),
                        'section_id' => filled($l['section_id']) ? (int) $l['section_id'] : null,
                    ];

                    $modele = $l['id'] ? $e->lignes()->whereKey($l['id'])->first() : null;
                    $modele ? $modele->update($valeurs) : $modele = $e->lignes()->create($valeurs);
                    $conserves[] = $modele->id;

                    CptImputation::where('ligne_id', $modele->id)->delete();

                    foreach ($l['imputations'] as $im) {
                        CptImputation::create([
                            'compagnie_id' => $cid,
                            'ligne_id' => $modele->id,
                            'section_id' => (int) $im['section_id'],
                            'montant' => (int) $im['montant'],
                            'pourcentage' => round((int) $im['montant'] * 100 / max($modele->montant(), 1), 2),
                        ]);
                    }
                }

                $e->lignes()->whereNotIn('id', $conserves)->get()->each->delete();

                // N° de facture et code kmaa AVANT la comptabilisation (ensuite l'écriture est figée)
                if ($e->est_facture) {
                    NumerotationFactures::attribuer($e->refresh());
                }

                $e->comptabiliser(auth()->user());

                return $e->refresh();
            });
        } catch (ValidationException $ex) {
            $this->erreur('Écriture refusée', collect($ex->errors())->flatten()->first());

            return;
        } catch (AuthorizationException|\DomainException $ex) {
            $this->erreur('Écriture refusée', $ex->getMessage());

            return;
        }

        $this->succes(
            'Écriture enregistrée',
            'N° '.$ecriture->numero.($ecriture->numero_facture ? ' · facture '.$ecriture->numero_facture.' · '.$ecriture->code_kmaa : '')
        );

        if ($nouvelle) {
            $this->nouvelle();

            return;
        }

        $this->redirect(EcritureResource::getUrl('index'));
    }

    /** Corrige une écriture comptabilisée par une écriture inverse. */
    public function contrepasser(): void
    {
        abort_unless(EcritureResource::peutEcrire(), 403);
        try {
            $e = CptEcriture::query()->visiblePour(auth()->user())->findOrFail($this->ecritureId);
            $c = $e->contrepasser(auth()->user());
        } catch (AuthorizationException|\DomainException $ex) {
            $this->erreur('Contre-passation impossible', $ex->getMessage());

            return;
        }

        $this->succes('Écriture contre-passée', 'N° '.$c->numero);
        $this->redirect(static::getUrl(['ecriture' => $c->id]));
    }
}