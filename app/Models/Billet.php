<?php

namespace App\Models;

use App\Enums\BilletStatut;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'taxe', 'operateur_paiement', 'voyageurs', 'date_paiement',
    ];

    protected $casts = [
        'voyageurs' => 'array',
        'date_voyage' => 'date',
        'date_paiement' => 'datetime',
        'statut' => BilletStatut::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Billet $billet) {
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

        static::created(function (Billet $billet) {
            BilletEvenement::create([
                'billet_id' => $billet->id,
                'statut_avant' => null,
                'statut_apres' => BilletStatut::Valide->value,
                'user_id' => $billet->user_id,
                'source' => 'achat',
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
            $user->isAgentGare() => $query->where($query->getModel()->getTable() . '.gare_id', $user->gareId() ?? 0),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Le SEUL chemin pour changer le statut d'un billet.
     *
     * @throws AuthorizationException  l'acteur n'a pas le droit (ou ne voit pas ce billet)
     * @throws \DomainException        transition ou règle métier non respectée (message affichable)
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
                BilletStatut::Annule, BilletStatut::Rembourse => $acteur->isSuperAdmin() || $acteur->isAdminCompagnie(),
                default => false,
            };

            if (! $autorise) {
                throw new AuthorizationException('Votre rôle ne permet pas cette opération.');
            }

            if (! $billet->statut->peutDevenir($nouveau)) {
                throw new \DomainException("Ce billet est déjà « {$billet->statut->getLabel()} » : opération impossible.");
            }

            if ($nouveau === BilletStatut::Utilise && ! $billet->date_voyage->isToday()) {
                throw new \DomainException('Ce billet est valable le ' . $billet->date_voyage->format('d/m/Y') . ', pas aujourd\'hui.');
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

            return $billet;
        });
    }
}