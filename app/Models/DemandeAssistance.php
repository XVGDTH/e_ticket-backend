<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DemandeAssistance extends Model
{
    protected $table = 'demandes_assistance';

    public const THEMES = [
        'paiement' => 'Paiement',
        'billet' => 'Mon billet',
        'report' => 'Report ou annulation',
        'voyage' => 'Voyage ou gare',
        'autre' => 'Autre question',
    ];

    public const STATUTS = [
        'nouvelle' => 'Nouvelle',
        'en_cours' => 'En cours',
        'resolue' => 'Résolue',
    ];

    protected $fillable = [
        'user_id', 'billet_id', 'compagnie_id', 'gare_id', 'theme', 'objet', 'statut',
        'rappel_statut', 'assigne_a', 'note', 'derniere_activite_le', 'resolue_le',
    ];

    protected $casts = [
        'derniere_activite_le' => 'datetime',
        'resolue_le' => 'datetime',
        'note' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billet(): BelongsTo
    {
        return $this->belongsTo(Billet::class);
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class, 'compagnie_id');
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class, 'gare_id');
    }

    public function assigneA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessageAssistance::class, 'demande_id')->orderBy('created_at')->orderBy('id');
    }

    /**
     * Qui voit quoi : le super admin et le service client voient tout ; l'admin de compagnie, les demandes
     * liées à sa compagnie (et sa ville) ; l'agent, celles liées aux billets de sa gare.
     * Une demande sans billet n'est liée à aucune gare ni compagnie : seuls les deux premiers la voient.
     */
    // public function scopeVisiblePour(Builder $query, User $user): Builder
    // {
    //     if ($user->isSuperAdmin() || $user->isServiceClient()) {
    //         return $query;
    //     }

    //     if ($user->isAdminCompagnie()) {
    //         $query->where('compagnie_id', $user->compagnieId() ?? 0);

    //         if ($villeId = $user->villeId()) {
    //             $query->whereHas('gare', fn ($g) => $g->where('ville_id', $villeId));
    //         }

    //         return $query;
    //     }

    //     if ($user->isAgentGare()) {
    //         return $query->where('gare_id', $user->gareId() ?? 0);
    //     }

    //     return $query->whereRaw('1 = 0');
    // }

    /** Seuls le service client et l'agent de la gare concernée répondent. */
    // public function peutRepondre(User $user): bool
    // {
    //     if ($user->isServiceClient()) {
    //         return true;
    //     }

    //     return $user->isAgentGare() && $this->gare_id !== null && $this->gare_id === $user->gareId();
    // }

    /**
     * Qui voit quoi : le super admin et le service client national voient tout ; l'admin de compagnie, les demandes
     * de sa compagnie (et de sa ville) ; l'agent et le service client de gare, celles des billets de leur gare.
     * Une demande sans billet n'est liée à aucune gare ni compagnie : seuls les deux premiers la voient.
     */
    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->isServiceClientGlobal()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            $query->where('compagnie_id', $user->compagnieId() ?? 0);

            if ($villeId = $user->villeId()) {
                $query->whereHas('gare', fn ($g) => $g->where('ville_id', $villeId));
            }

            return $query;
        }

        if ($user->isAgentGare() || $user->isServiceClient()) {
            return $query->where('gare_id', $user->gareId() ?? 0);
        }

        return $query->whereRaw('1 = 0');
    }

    /** Répondent : le service client national, et l'agent ou le service client de la gare concernée. */
    public function peutRepondre(User $user): bool
    {
        if ($user->isServiceClientGlobal()) {
            return true;
        }

        return ($user->isAgentGare() || $user->isServiceClient())
            && $this->gare_id !== null
            && $this->gare_id === $user->gareId();
    }

    public function ajouterMessage(User $auteur, string $corps, string $type): MessageAssistance
    {
        return DB::transaction(function () use ($auteur, $corps, $type) {
            $message = $this->messages()->create([
                'auteur_id' => $auteur->id,
                'auteur_type' => $type,
                'corps' => $corps,
            ]);

            $maj = ['derniere_activite_le' => now()];

            if ($type === 'staff' && $this->statut === 'nouvelle') {
                $maj['statut'] = 'en_cours';
                $maj['assigne_a'] = $this->assigne_a ?? $auteur->id;
            }

            // Le client écrit sur une demande résolue : elle se rouvre.
            if ($type === 'client' && $this->statut === 'resolue') {
                $maj['statut'] = 'nouvelle';
                $maj['resolue_le'] = null;
            }

            $this->forceFill($maj)->save();

            return $message;
        });
    }

    public function notifierClient(string $titre, string $corps, string $cle): void
    {
        NotificationClient::create([
            'user_id' => $this->user_id,
            'type' => 'assistance',
            'titre' => $titre,
            'corps' => $corps,
            'billet_id' => $this->billet_id,
            'billet_reference' => $this->billet?->reference,
            'cle' => $cle,
        ]);
    }

    /** La conversation, prête pour l'affichage dans le panel. */
    public function fil(): array
    {
        $client = trim(($this->user?->prenom ?? '').' '.($this->user?->nom ?? '')) ?: 'Client';

        return $this->messages()->with('auteur:id,prenom,nom')->get()->map(fn (MessageAssistance $m) => [
            'type' => $m->auteur_type,
            'auteur' => $m->auteur_type === 'client'
                ? $client
                : (trim(($m->auteur?->prenom ?? '').' '.($m->auteur?->nom ?? '')) ?: 'Service client'),
            'corps' => $m->corps,
            'date' => $m->created_at->format('d/m/Y H:i'),
        ])->all();
    }
}
