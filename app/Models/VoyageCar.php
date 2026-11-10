<?php

namespace App\Models;

use App\Enums\BilletStatut;
use App\Services\Flotte\Rentabilite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class VoyageCar extends Model
{
    protected $table = 'voyages_cars';

    protected $guarded = [];

    protected $casts = [
        'date_voyage' => 'date',
        'cloture_le' => 'datetime',
        'km_parcourus' => 'integer',
        'places_guichet' => 'integer',
        'recette_guichet' => 'integer',
        'recette_colis' => 'integer',
    ];

    public const STATUTS = [
        'programme' => 'Programmé',
        'parti' => 'Parti',
        'termine' => 'Terminé',
        'annule' => 'Annulé',
    ];

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RouteTransport::class, 'route_transport_id');
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    /** Identifie le départ (route, date, heure) : clé commune avec les billets. */
    public function cle(): string
    {
        return $this->route_transport_id.'|'.$this->date_voyage->toDateString().'|'.$this->heure;
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

    /** Signale un départ où les réservations de l'application dépassent les places des cars affectés. */
    public function alerteCapacite(): ?string
    {
        $places = (int) static::query()
            ->where('route_transport_id', $this->route_transport_id)
            ->whereDate('date_voyage', $this->date_voyage)
            ->where('heure', $this->heure)
            ->where('statut', '!=', 'annule')
            ->with('vehicule')->get()
            ->sum(fn ($v) => (int) $v->vehicule?->places);

        $vendus = (int) Billet::query()
            ->where('route_transport_id', $this->route_transport_id)
            ->whereDate('date_voyage', $this->date_voyage)
            ->whereIn('statut', BilletStatut::comptabilises())
            ->get(['heure', 'travelers_count'])
            ->filter(fn ($b) => Rentabilite::heure((string) $b->heure) === $this->heure)
            ->sum('travelers_count');

        return $vendus > $places
            ? $vendus.' voyageur(s) ont réservé par l\'application pour '.$places.' place(s) dans le ou les cars affectés : ajoutez un car ou prenez-en un plus grand.'
            : null;
    }

    /**
     * Clôture le voyage : kilomètres, ventes du guichet, colis et dépenses de la route.
     *
     * @throws AuthorizationException pas d'accès à ce voyage
     * @throws \DomainException voyage non clôturable ou données manquantes
     */
    public function cloturer(User $acteur, array $d): self
    {
        return DB::transaction(function () use ($acteur, $d) {
            $v = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! static::query()->visiblePour($acteur)->whereKey($v->id)->exists()) {
                throw new AuthorizationException('Vous n\'avez pas accès à ce voyage.');
            }

            if (! in_array($v->statut, ['programme', 'parti'], true)) {
                throw new \DomainException('Ce voyage est déjà « '.(self::STATUTS[$v->statut] ?? $v->statut).' ».');
            }

            if ($v->date_voyage->gt(today())) {
                throw new \DomainException('Ce voyage n\'a pas encore eu lieu.');
            }

            $km = (int) ($d['km_parcourus'] ?? 0);

            if ($km < 1) {
                throw new \DomainException('Indiquez les kilomètres parcourus.');
            }

            $v->forceFill([
                'statut' => 'termine',
                'km_parcourus' => $km,
                'places_guichet' => (int) ($d['places_guichet'] ?? 0),
                'recette_guichet' => (int) ($d['recette_guichet'] ?? 0),
                'recette_colis' => (int) ($d['recette_colis'] ?? 0),
                'observations' => filled($d['observations'] ?? null) ? trim($d['observations']) : $v->observations,
                'cloture_le' => now(),
            ])->save();

            $ajouter = function (string $categorie, int $montant, array $extra = []) use ($v, $acteur) {
                if ($montant <= 0) {
                    return;
                }

                DepenseVehicule::create(array_merge([
                    'compagnie_id' => $v->compagnie_id,
                    'vehicule_id' => $v->vehicule_id,
                    'voyage_id' => $v->id,
                    'date_depense' => $v->date_voyage->toDateString(),
                    'categorie' => $categorie,
                    'montant' => $montant,
                    'source' => 'cloture',
                    'cree_par' => $acteur->id,
                ], $extra));
            };

            $litres = (float) ($d['carburant_litres'] ?? 0);

            $ajouter('carburant', (int) ($d['carburant_montant'] ?? 0), ['litres' => $litres > 0 ? $litres : null]);
            $ajouter('peage', (int) ($d['peages'] ?? 0));
            $ajouter('chauffeur', (int) ($d['frais_chauffeur'] ?? 0));
            $ajouter('autre', (int) ($d['autres_frais'] ?? 0), ['note' => 'Frais divers du voyage']);

            Vehicule::whereKey($v->vehicule_id)->increment('km_actuel', $km);

            return $v;
        });
    }

    public function annuler(User $acteur): self
    {
        return DB::transaction(function () use ($acteur) {
            $v = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! static::query()->visiblePour($acteur)->whereKey($v->id)->exists()) {
                throw new AuthorizationException('Vous n\'avez pas accès à ce voyage.');
            }

            if (! in_array($v->statut, ['programme', 'parti'], true)) {
                throw new \DomainException('Seul un voyage non clôturé peut être annulé.');
            }

            $v->forceFill(['statut' => 'annule'])->save();

            return $v;
        });
    }
}
