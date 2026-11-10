<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employe extends Model
{
    protected $table = 'employes';

    protected $guarded = [];

    protected $casts = [
        'date_naissance' => 'date',
        'date_embauche' => 'date',
        'date_sortie' => 'date',
        'conjoint_a_charge' => 'boolean',
        'enfants_a_charge' => 'integer',
    ];

    public const STATUTS = ['actif' => 'En poste', 'suspendu' => 'Suspendu', 'sorti' => 'Sorti'];

    public const SERVICES = [
        'exploitation' => 'Exploitation / départs',
        'guichet' => 'Guichet / billetterie',
        'conduite' => 'Conduite',
        'convoyage' => 'Convoyage / contrôle',
        'mecanique' => 'Mécanique / maintenance',
        'colis' => 'Colis et courrier',
        'securite' => 'Sécurité / gardiennage',
        'entretien' => 'Entretien / propreté',
        'service_client' => 'Service client',
        'comptabilite' => 'Comptabilité / caisse',
        'administration' => 'Administration / direction',
        'autre' => 'Autre',
    ];

    public const SITUATIONS = [
        'celibataire' => 'Célibataire', 'marie' => 'Marié(e)', 'divorce' => 'Divorcé(e)', 'veuf' => 'Veuf / veuve',
    ];

    public const MODES_PAIEMENT = [
        'especes' => 'Espèces', 'orange_money' => 'Orange Money', 'moov_money' => 'Moov Money',
        'virement' => 'Virement bancaire', 'cheque' => 'Chèque',
    ];

    protected static function booted(): void
    {
        static::creating(function (Employe $e) {
            $e->cree_par ??= auth()->id();

            if (blank($e->matricule)) {
                $n = static::where('compagnie_id', $e->compagnie_id)->count() + 1;

                do {
                    $m = sprintf('%s-%04d', config('personnel.matricule_prefixe', 'EMP'), $n++);
                } while (static::where('compagnie_id', $e->compagnie_id)->where('matricule', $m)->exists());

                $e->matricule = $m;
            }
        });
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(ContratEmploye::class)->orderByDesc('date_debut');
    }

    public function contratActif(): HasOne
    {
        return $this->hasOne(ContratEmploye::class)->where('actif', true)->latest('date_debut');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function conges(): HasMany
    {
        return $this->hasMany(CongeEmploye::class);
    }

    public function avances(): HasMany
    {
        return $this->hasMany(AvanceEmploye::class);
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(SanctionEmploye::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentEmploye::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(BulletinPaie::class);
    }

    public function nomComplet(): string
    {
        return trim($this->nom.' '.$this->prenoms);
    }

    /** Charges de famille retenues pour la réduction d'IUTS (conjoint + enfants, plafonnées). */
    public function chargesFamille(): int
    {
        return min(
            (int) config('personnel.iuts.max_charges', 4),
            ($this->conjoint_a_charge ? 1 : 0) + (int) $this->enfants_a_charge
        );
    }

    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            $query->where('compagnie_id', $user->compagnieId() ?? 0);

            if ($user->villeId()) {
                $query->whereIn('gare_id', $user->garesGerables()->select('gares.id'));
            }

            return $query;
        }

        if ($user->isAgentGare()) {
            return $query->where('gare_id', $user->gareId() ?? 0);
        }

        return $query->whereRaw('1 = 0');
    }

    /** @return array<int, string> employés en poste visibles par l'utilisateur, pour les listes déroulantes */
    public static function optionsPour(User $user, bool $aussiSortis = false): array
    {
        return static::query()->visiblePour($user)
            ->when(! $aussiSortis, fn ($q) => $q->where('statut', '!=', 'sorti'))
            ->orderBy('nom')->orderBy('prenoms')->get()
            ->mapWithKeys(fn (Employe $e) => [$e->id => $e->matricule.' · '.$e->nomComplet()])->all();
    }

    /** @return array<int, string> gares où l'utilisateur a des employés, pour les filtres */
    public static function garesOptionsPour(User $user): array
    {
        return Gare::query()
            ->whereIn('id', static::query()->visiblePour($user)->whereNotNull('gare_id')->select('gare_id'))
            ->with('ville')->orderBy('quartier')->get()
            ->mapWithKeys(fn (Gare $g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')])->all();
    }
}
