<?php

namespace App\Models;

use App\Enums\BilletStatut;
use App\Exceptions\PlacesInsuffisantes;
use App\Services\Billetterie;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Billet extends Model
{
    use HasFactory;

    /** Vrai uniquement pendant changerStatut() : toute autre modification du statut est annulée. */
    protected static bool $transitionAutorisee = false;

    // Le statut n'est volontairement PAS modifiable en masse (une application ne peut pas l'envoyer).

    protected $fillable = [
        'reference', 'user_id', 'route_transport_id', 'gare_id',
        'date_voyage', 'heure', 'travelers_count', 'montant_transport',
        'taxe', 'operateur_paiement', 'voyageurs', 'date_paiement', 'consentement_le', 'consentement_version',
        'validite_jours', 'presence_le', 'presence_verifiee',
        'canal', 'vendu_par', 'caisse_id', 'remise', 'nom_client', 'telephone_client', 'reference_paiement',
    ];

    protected $casts = [
        'voyageurs' => 'array',
        'date_voyage' => 'date',
        'date_paiement' => 'datetime',
        'statut' => BilletStatut::class,
        'consentement_le' => 'datetime',
        'presence_le' => 'datetime',
        'presence_verifiee' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Billet $billet) {
            $billet->heure = Billetterie::normaliserHeure((string) $billet->heure);

            // Un billet appartient TOUJOURS à la gare de départ de sa route :
            // la gare envoyée par l'application est ignorée.
            $gareDepart = RouteTransport::whereKey($billet->route_transport_id)->value('gare_depart_id');

            if ($gareDepart) {
                $billet->gare_id = $gareDepart;
            }

            if (! $billet->statut) {
                $billet->statut = BilletStatut::Valide;
            }
        });

        // static::created(function (Billet $billet) {
        //     BilletEvenement::create([
        //         'billet_id' => $billet->id,
        //         'statut_avant' => null,
        //          'statut_apres' => BilletStatut::Valide->value,
        //         'statut_apres' => $billet->statut->value,
        //         'user_id' => $billet->user_id,
        //         'source' => 'achat',
        //         'created_at' => now(),
        //     ]);
        // });
        static::created(function (Billet $billet) {
            BilletEvenement::create([
                'billet_id' => $billet->id,
                'statut_avant' => null,
                'statut_apres' => $billet->statut->value,
                'user_id' => $billet->vendu_par ?? $billet->user_id,
                'source' => $billet->canal === 'guichet' ? 'guichet' : 'achat',
                'created_at' => now(),
            ]);
        });

        static::updating(function (Billet $billet) {
            // Une fois émis, un billet ne change plus de route ni de gare.
            foreach (['route_transport_id', 'gare_id'] as $champ) {
                if ($billet->isDirty($champ)) {
                    $billet->setAttribute($champ, $billet->getOriginal($champ));
                }
            }

            // Le statut ne change que par changerStatut().
            if (! static::$transitionAutorisee && $billet->isDirty('statut')) {
                $billet->setAttribute('statut', $billet->getOriginal('statut'));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RouteTransport::class, 'route_transport_id');
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(BilletEvenement::class);
    }

    /** Dernier jour d'utilisation : 12 jours après l'achat, mais jamais avant le jour du voyage. */
    // public function getValideJusquAuAttribute(): ?Carbon
    // {
    //     if (! $this->date_paiement) {
    //         return null; // billet jamais payé
    //     }

    //     $fin = $this->date_paiement->copy()->startOfDay()
    //         ->addDays((int) config('billetterie.validite_jours', 12));

    //     return $this->date_voyage->greaterThan($fin) ? $this->date_voyage->copy() : $fin;
    // }
    /** Dernier jour d'utilisation : durée de validité de la compagnie à l'achat, jamais avant le jour du voyage. */
    public function getValideJusquAuAttribute(): ?Carbon
    {
        if (! $this->date_paiement) {
            return null; // billet jamais payé
        }

        $jours = (int) ($this->validite_jours ?: config('billetterie.validite_jours', 12));
        $fin = $this->date_paiement->copy()->startOfDay()->addDays($jours);

        return $this->date_voyage->greaterThan($fin) ? $this->date_voyage->copy() : $fin;
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReportBillet::class);
    }

    /**
     * Le client reporte son voyage à un autre départ de la même route, dans la durée
     * de validité du billet. Aucun motif n'est demandé. La route et la gare ne changent jamais.
     *
     * @throws AuthorizationException ce billet n'appartient pas à cet utilisateur
     * @throws PlacesInsuffisantes le nouveau départ n'a plus assez de places
     * @throws \DomainException règle non respectée (message affichable)
     */
    public function reporter(User $acteur, Carbon $nouvelleDate, string $nouvelleHeure): self
    {
        return DB::transaction(function () use ($acteur, $nouvelleDate, $nouvelleHeure) {
            // Même verrou que l'achat : deux clients ne se disputent pas la dernière place.
            RouteTransport::whereKey($this->route_transport_id)->lockForUpdate()->first();
            $billet = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $billet->user_id !== (int) $acteur->id) {
                throw new AuthorizationException('Ce billet ne vous appartient pas.');
            }

            if ($billet->statut !== BilletStatut::Valide) {
                throw new \DomainException('Seul un billet valide peut être reporté.');
            }

            $heure = Billetterie::normaliserHeure($nouvelleHeure);
            $date = $nouvelleDate->copy()->startOfDay();
            $fin = $billet->valide_jusqu_au;

            if (! $fin || $date->lt(today()) || $date->gt($fin)) {
                throw new \DomainException(
                    'Choisissez un jour entre aujourd\'hui et le '.($fin ? $fin->format('d/m/Y') : '—').' (fin de validité du billet).'
                );
            }

            if ($date->copy()->setTimeFromTimeString($heure)->lte(now())) {
                throw new \DomainException('Ce départ est déjà passé.');
            }

            $ancienneHeure = Billetterie::normaliserHeure((string) $billet->heure);
            // Départ initial déjà passé : on attend que la gare ait embarqué (ou 6 h après le départ).
            $departInitial = $billet->date_voyage->copy()->setTimeFromTimeString($ancienneHeure);

            if ($departInitial->isPast() && ! $billet->departCloture($departInitial)) {
                throw new \DomainException('Le départ n\'est pas encore clôturé par la gare. Réessayez dans quelques heures ou appelez le service client.');
            }

            if ($date->equalTo($billet->date_voyage) && $heure === $ancienneHeure) {
                throw new \DomainException('Choisissez un autre départ que celui de votre billet.');
            }

            $route = RouteTransport::with('horaires')->findOrFail($billet->route_transport_id);

            if (! in_array($heure, Billetterie::horairesDuJour($route, $date->toDateString()), true)) {
                throw new \DomainException('Aucun départ à cette heure ce jour-là.');
            }

            $restantes = Billetterie::placesApp($route)
            // Billetterie::placesParDepart()
                - (int) Billetterie::vendus($billet->route_transport_id, $date->toDateString())->get($heure, 0);

            if ($billet->travelers_count > $restantes) {
                throw new PlacesInsuffisantes(max(0, $restantes));
            }

            $ancienneDate = $billet->date_voyage->toDateString();

            $billet->date_voyage = $date;
            $billet->heure = $heure;
            $billet->save();

            ReportBillet::create([
                'billet_id' => $billet->id,
                'route_transport_id' => $billet->route_transport_id,
                'ancienne_date' => $ancienneDate,
                'ancienne_heure' => $ancienneHeure,
                'nouvelle_date' => $date->toDateString(),
                'nouvelle_heure' => $heure,
            ]);

            return $billet;
        });
    }

    /**
     * LA règle de visibilité des billets, à utiliser partout :
     * panneau Filament, application mobile des agents, exports.
     */
    public function scopeVisiblesPar(Builder $query, User $user): Builder
    {
        $villeId = $user->villeId();

        return match (true) {
            $user->isSuperAdmin() => $query,
            $user->isAdminCompagnie() => $query->whereHas('gare', function ($gare) use ($user, $villeId) {
                $gare->where('compagnie_id', $user->compagnieId() ?? 0);

                if ($villeId) {
                    $gare->where('ville_id', $villeId);
                }
            }),
            $user->isAgentGare() => $query->where($query->getModel()->getTable().'.gare_id', $user->gareId() ?? 0),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Le SEUL chemin pour changer le statut d'un billet.
     *
     * @throws AuthorizationException l'acteur n'a pas le droit (ou ne voit pas ce billet)
     * @throws \DomainException transition ou règle métier non respectée (message affichable)
     */
    public function changerStatut(BilletStatut $nouveau, User $acteur, ?string $motif = null, string $source = 'panel'): self
    {
        return DB::transaction(function () use ($nouveau, $acteur, $motif, $source) {
            // Verrou : deux validations simultanées ne peuvent pas réussir toutes les deux.
            $billet = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! static::query()->visiblesPar($acteur)->whereKey($billet->getKey())->exists()) {
                throw new AuthorizationException("Vous n'avez pas accès à ce billet.");
            }

            $autorise = match ($nouveau) {
                BilletStatut::Utilise => $acteur->isAgentGare(),
                BilletStatut::Annule => $acteur->isSuperAdmin() || $acteur->isAdminCompagnie()
                    || ($acteur->isAgentGare() && $billet->canal === 'guichet' && (int) $billet->vendu_par === (int) $acteur->id),
                BilletStatut::Rembourse => $acteur->isSuperAdmin() || $acteur->isAdminCompagnie(),
                // Correction d'une erreur d'embarquement : seul l'agent, le jour même.
                BilletStatut::Valide => $acteur->isAgentGare() && $billet->statut === BilletStatut::Utilise,
                default => false,
            };

            if (! $autorise) {
                throw new AuthorizationException('Votre rôle ne permet pas cette opération.');
            }

            // Correction d'un embarquement : possible seulement le jour même.
            if ($nouveau === BilletStatut::Valide && $billet->statut === BilletStatut::Utilise && ! $billet->updated_at->isToday()) {
                throw new \DomainException('L\'embarquement ne peut être corrigé que le jour même.');
            }

            if (! $billet->statut->peutDevenir($nouveau)) {
                throw new \DomainException("Ce billet est déjà « {$billet->statut->getLabel()} » : opération impossible.");
            }

            if ($nouveau === BilletStatut::Utilise) {
                $fin = $billet->valide_jusqu_au;

                if (today()->lt($billet->date_voyage)) {
                    throw new \DomainException('Ce billet est valable à partir du '.$billet->date_voyage->format('d/m/Y').'.');
                }

                if (! $fin || today()->gt($fin)) {
                    throw new \DomainException('Ce billet a expiré'.($fin ? ' le '.$fin->format('d/m/Y') : '').'.');
                }

                // Départ manqué : le client doit d'abord reporter son billet sur un autre départ.
                if (today()->gt($billet->date_voyage)) {
                    throw new \DomainException('Ce billet était pour le '.$billet->date_voyage->format('d/m/Y').' : le client doit le reporter sur un autre départ depuis l\'application.');
                }

                // L'embarquement n'ouvre que dans les minutes qui précèdent le départ.
                $ouverture = $billet->date_voyage->copy()
                    ->setTimeFromTimeString(Billetterie::normaliserHeure((string) $billet->heure))
                    ->subMinutes((int) config('billetterie.embarquement_minutes', 90));

                if (now()->lt($ouverture)) {
                    throw new \DomainException('L\'embarquement de ce départ ouvre à '.$ouverture->format('H:i').'.');
                }
            }

            if (in_array($nouveau, [BilletStatut::Annule, BilletStatut::Rembourse], true) && mb_strlen(trim((string) $motif)) < 5) {
                throw new \DomainException('Un motif d\'au moins 5 caractères est obligatoire.');
            }

            $ancien = $billet->statut;

            static::$transitionAutorisee = true;

            try {
                $billet->statut = $nouveau;
                $billet->save();
            } finally {
                static::$transitionAutorisee = false;
            }

            BilletEvenement::create([
                'billet_id' => $billet->id,
                'statut_avant' => $ancien->value,
                'statut_apres' => $nouveau->value,
                'user_id' => $acteur->id,
                'motif' => $motif ? trim($motif) : null,
                'source' => $source,
                'created_at' => now(),
            ]);

            if (in_array($nouveau, [BilletStatut::Annule, BilletStatut::Rembourse], true)) {
                $annule = $nouveau === BilletStatut::Annule;

                rescue(fn () => NotificationClient::pourBillet(
                    $billet,
                    $annule ? 'annulation' : 'remboursement',
                    $annule ? 'Billet annulé' : 'Billet remboursé',
                    $annule ? 'Votre billet a été annulé :' : 'Votre billet a été remboursé :',
                ));
            }

            return $billet;
        });
    }

    /** Le départ est clôturé si la gare y a embarqué quelqu'un, ou si le délai automatique est écoulé. */
    public function departCloture(Carbon $depart): bool
    {
        if (now()->gte($depart->copy()->addHours((int) config('billetterie.cloture_auto_heures', 6)))) {
            return true;
        }

        return static::query()
            ->where('route_transport_id', $this->route_transport_id)
            ->whereDate('date_voyage', $this->date_voyage)
            ->whereRaw('LEFT(heure, 5) = ?', [Billetterie::normaliserHeure((string) $this->heure)])
            ->where('statut', BilletStatut::Utilise->value)
            ->exists();
    }
}
