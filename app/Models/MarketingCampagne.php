<?php

namespace App\Models;

use App\Services\Marketing\Audience;
use App\Services\Marketing\Telephone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MarketingCampagne extends Model
{
    protected $table = 'marketing_campagnes';

    protected $guarded = [];

    protected $casts = [
        'segments' => 'array',
        'variables' => 'array',
        'planifiee_le' => 'datetime',
        'lancee_le' => 'datetime',
        'terminee_le' => 'datetime',
    ];

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'planifiee' => 'Planifiée',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
        'erreur' => 'Erreur',
    ];

    public const CANAUX = [
        'whatsapp' => 'WhatsApp',
        'app' => 'Notification dans l\'application',
    ];

    public function modele(): BelongsTo
    {
        return $this->belongsTo(MarketingModele::class, 'modele_id');
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class, 'gare_id');
    }

    public function destinataires(): HasMany
    {
        return $this->hasMany(MarketingDestinataire::class, 'campagne_id');
    }

    public function estApplication(): bool
    {
        return $this->canal === 'app';
    }

    /** Fige la liste des clients à contacter au moment du lancement. */
    public function preparerDestinataires(): void
    {
        $this->destinataires()->delete();
        $total = 0;
        $app = $this->estApplication();

        Audience::requete((int) $this->compagnie_id, $this->gare_id ? (int) $this->gare_id : null, (array) $this->segments, true, $this->canal ?: 'whatsapp')
            ->select('users.id', 'users.telephone')
            ->orderBy('users.id')
            ->chunkById(500, function ($clients) use (&$total, $app) {
                $lignes = [];

                foreach ($clients as $c) {
                    $numero = Telephone::whatsapp($c->telephone);
                    $joignable = $app || $numero;

                    $lignes[] = [
                        'campagne_id' => $this->id,
                        'user_id' => $c->id,
                        'telephone' => $numero ?? '',
                        'statut' => $joignable ? 'en_attente' : 'ignore',
                        'erreur' => $joignable ? null : 'Numéro invalide',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $total++;
                }

                DB::table('marketing_destinataires')->insert($lignes);
            }, 'users.id', 'id');

        $this->forceFill(['nb_cibles' => $total])->save();
    }

    /** Les valeurs des variables pour un client : automatiques, puis celles saisies dans la campagne. */
    public function valeursPour(User $client, string $gare): array
    {
        $billets = PreferenceClient::where('user_id', $client->id)
            ->where('compagnie_id', $this->compagnie_id)->where('gare_id', 0)->value('nb_billets');

        return array_merge($this->variables ?? [], [
            'prenom' => trim((string) $client->prenom) ?: 'cher client',
            'nom' => trim((string) $client->nom) ?: 'cher client',
            'compagnie' => $this->compagnie?->nom ?? '',
            'gare' => $gare,
            'billets' => (string) ($billets ?: 1),
        ]);
    }

    public function stats(): array
    {
        $t = $this->destinataires()->selectRaw('statut, COUNT(*) as n')->groupBy('statut')->pluck('n', 'statut');
        $n = fn (string $s) => (int) ($t[$s] ?? 0);

        $lus = $n('lu');

        // Dans l'application, « lu » vient de la boîte de notifications du client.
        if ($this->estApplication()) {
            $lus = NotificationClient::where('cle', 'offre:'.$this->id)->whereNotNull('lu_a')->count();
        }

        return [
            'cibles' => (int) $t->sum(),
            'attente' => $n('en_attente'),
            'envoyes' => $n('envoye') + $n('remis') + $n('lu'),
            'remis' => $n('remis') + $n('lu'),
            'lus' => $lus,
            'echecs' => $n('echec'),
            'ignores' => $n('ignore'),
        ];
    }

    public function raisonsEchec(): array
    {
        return $this->destinataires()->where('statut', 'echec')
            ->selectRaw('erreur_code, erreur, COUNT(*) as n')
            ->groupBy('erreur_code', 'erreur')->orderByDesc('n')->limit(8)->get()
            ->map(fn ($r) => ['code' => $r->erreur_code, 'message' => $r->erreur, 'n' => (int) $r->n])->all();
    }
}
