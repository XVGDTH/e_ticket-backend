<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Colis extends Model
{
    protected $table = 'colis';

    public const STATUTS = [
        'enregistre' => 'Enregistré',
        'en_transit' => 'En transit',
        'arrive' => 'Arrivé, à retirer',
        'livre' => 'Livré',
        'incident' => 'Incident',
    ];

    public const TRANSITIONS = [
        'enregistre' => ['en_transit', 'incident'],
        'en_transit' => ['arrive', 'incident'],
        'arrive' => ['livre', 'incident'],
        'livre' => [],
        'incident' => [],
    ];

    public const NATURES = [
        'documents' => 'Documents',
        'vetements' => 'Vêtements',
        'alimentaire' => 'Alimentaire',
        'electronique' => 'Électronique',
        'pieces' => 'Pièces détachées',
        'autre' => 'Autre',
    ];

    // Le statut ne change que par avancer() : il n'est pas modifiable en masse.
    protected $fillable = [
        'reference', 'type', 'compagnie_id', 'gare_origine_id', 'gare_destination_id',
        'expediteur_nom', 'expediteur_telephone', 'destinataire_nom', 'destinataire_telephone',
        'nature', 'description', 'nombre_pieces', 'poids_kg', 'valeur_declaree',
        'montant', 'paiement', 'paye_le', 'code_retrait', 'cree_par',
    ];

    protected $casts = [
        'paye_le' => 'datetime',
        'remis_le' => 'datetime',
        'code_verifie' => 'boolean',
        'poids_kg' => 'float',
    ];

    protected static function booted(): void
    {
        static::created(function (Colis $colis) {
            ColisEvenement::create([
                'colis_id' => $colis->id,
                'statut' => 'enregistre',
                'user_id' => auth()->id(),
                'gare_id' => $colis->gare_origine_id,
                'note' => 'Envoi enregistré',
                'created_at' => now(),
            ]);
        });
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function origine(): BelongsTo
    {
        return $this->belongsTo(Gare::class, 'gare_origine_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Gare::class, 'gare_destination_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(ColisEvenement::class)->orderBy('created_at')->orderBy('id');
    }

    public static function genererReference(string $type): string
    {
        do {
            $reference = ($type === 'courrier' ? 'CRR' : 'COL').'-'.random_int(100000, 999999);
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public static function genererCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Le super admin voit tout ; l'admin, sa compagnie (et sa ville) ; l'agent, les envois de sa gare. */
    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            $query->where('compagnie_id', $user->compagnieId() ?? 0);

            if ($user->villeId()) {
                $ids = $user->garesGerables()->select('gares.id');
                $query->where(fn ($q) => $q->whereIn('gare_origine_id', $ids)->orWhereIn('gare_destination_id', $ids));
            }

            return $query;
        }

        if ($user->isAgentGare()) {
            $gareId = $user->gareId() ?? 0;

            return $query->where(fn ($q) => $q->where('gare_origine_id', $gareId)->orWhere('gare_destination_id', $gareId));
        }

        return $query->whereRaw('1 = 0');
    }

    /** Cette étape est-elle permise à cet utilisateur, vu l'état du colis et sa gare ? */
    public function etapeAutorisee(string $vers, User $acteur): bool
    {
        if (! in_array($vers, self::TRANSITIONS[$this->statut] ?? [], true)) {
            return false;
        }

        if ($acteur->isSuperAdmin() || $acteur->isAdminCompagnie()) {
            return true;
        }

        if (! $acteur->isAgentGare()) {
            return false;
        }

        $gare = (int) $acteur->gareId();

        return match ($vers) {
            'en_transit' => $gare === (int) $this->gare_origine_id,
            'arrive', 'livre' => $gare === (int) $this->gare_destination_id,
            'incident' => in_array($gare, [(int) $this->gare_origine_id, (int) $this->gare_destination_id], true),
            default => false,
        };
    }

    /**
     * Le seul chemin pour changer l'étape d'un envoi.
     *
     * @throws AuthorizationException pas d'accès ou étape réservée à l'autre gare
     * @throws \DomainException étape impossible (message affichable)
     */
    public function avancer(string $vers, User $acteur, ?string $note = null, array $extra = []): self
    {
        return DB::transaction(function () use ($vers, $acteur, $note, $extra) {
            $colis = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! static::query()->visiblePour($acteur)->whereKey($colis->id)->exists()) {
                throw new AuthorizationException('Vous n\'avez pas accès à cet envoi.');
            }

            if (! in_array($vers, self::TRANSITIONS[$colis->statut] ?? [], true)) {
                throw new \DomainException('Cet envoi est déjà « '.(self::STATUTS[$colis->statut] ?? $colis->statut).' » : étape impossible.');
            }

            if (! $colis->etapeAutorisee($vers, $acteur)) {
                throw new AuthorizationException('Cette étape revient à l\'autre gare.');
            }

            if ($vers === 'incident' && mb_strlen(trim((string) $note)) < 5) {
                throw new \DomainException('Décrivez l\'incident (5 caractères au moins).');
            }

            $maj = ['statut' => $vers];

            if ($vers === 'livre') {
                $maj['remis_a'] = $extra['remis_a'] ?? null;
                $maj['remis_le'] = now();
                $maj['code_verifie'] = (bool) ($extra['code_verifie'] ?? false);

                if ($colis->paiement === 'destinataire' && ! $colis->paye_le) {
                    $maj['paye_le'] = now();
                }
            }

            $colis->forceFill($maj)->save();

            ColisEvenement::create([
                'colis_id' => $colis->id,
                'statut' => $vers,
                'user_id' => $acteur->id,
                'gare_id' => $acteur->gareId() ?? ($vers === 'en_transit' ? $colis->gare_origine_id : $colis->gare_destination_id),
                'note' => $note ? trim($note) : null,
                'created_at' => now(),
            ]);

            return $colis;
        });
    }

    /** Le suivi, prêt pour l'affichage. */
    public function suivi(): array
    {
        return $this->evenements()->with(['user:id,prenom,nom', 'gare:id,quartier'])->get()
            ->map(fn (ColisEvenement $e) => [
                'statut' => $e->statut,
                'titre' => self::STATUTS[$e->statut] ?? $e->statut,
                'date' => $e->created_at->format('d/m/Y à H:i'),
                'detail' => collect([
                    $e->gare?->quartier ? 'Gare '.$e->gare->quartier : null,
                    $e->user ? 'par '.trim($e->user->prenom.' '.$e->user->nom) : null,
                    $e->note,
                ])->filter()->implode(' · '),
            ])->all();
    }
}
