<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Notification d'un voyageur. Quand il la supprime dans l'application, la ligne
 * reçoit seulement une date de suppression (deleted_at) : elle reste en base.
 */
class NotificationClient extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'notifications_client';

    // protected $fillable = ['user_id', 'type', 'titre', 'corps', 'billet_id', 'billet_reference', 'lu_a'];
    protected $fillable = ['user_id', 'type', 'titre', 'corps', 'billet_id', 'billet_reference', 'cle', 'lu_a'];

    protected $casts = ['lu_a' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billet(): BelongsTo
    {
        return $this->belongsTo(Billet::class);
    }

    /** Crée la notification d'un billet : « Compagnie — Départ → Arrivée, le 14/10 à 08h30. » */
    public static function pourBillet(Billet $billet, string $type, string $titre, string $debut = ''): self
    {
        $billet->loadMissing(['route.compagnie', 'route.villeArrivee', 'gare']);

        $depart = Ville::whereKey($billet->gare?->ville_id)->value('nom') ?? '';
        $arrivee = $billet->route?->villeArrivee?->nom ?? '';
        $trajet = ($billet->route?->compagnie?->nom ?? '').' — '.$depart.' → '.$arrivee
            .', le '.$billet->date_voyage->format('d/m')
            .' à '.str_replace(':', 'h', (string) $billet->heure).'.';

        return static::create([
            'user_id' => $billet->user_id,
            'type' => $type,
            'titre' => $titre,
            'corps' => trim($debut.' '.$trajet),
            'billet_id' => $billet->id,
            'billet_reference' => $billet->reference,
        ]);
    }

    // nouvelle methode
    public function handle(): int
    {
        $heures = (int) config('billetterie.rappel_heures', 3);

        $billets = Billet::query()
            ->with('route.compagnie')
            ->where('statut', BilletStatut::Valide->value)
            ->whereBetween('date_voyage', [today()->toDateString(), today()->addDay()->toDateString()])
            ->get();

        // withTrashed : un rappel supprimé par le voyageur ne doit pas revenir.
        $deja = NotificationClient::withTrashed()
            ->where('type', 'rappel')
            ->whereIn('billet_id', $billets->pluck('id'))
            ->get(['billet_id', 'cle'])
            ->map(fn ($n) => $n->billet_id.'|'.$n->cle);

        $crees = 0;

        foreach ($billets as $billet) {
            $heure = Billetterie::normaliserHeure((string) $billet->heure);
            // Un billet reporté reçoit un nouveau rappel pour son nouveau départ.
            $cle = 'rappel:'.$billet->date_voyage->toDateString().':'.$heure;

            if ($deja->contains($billet->id.'|'.$cle)) {
                continue;
            }

            $depart = $billet->date_voyage->copy()->setTimeFromTimeString($heure);

            if ($depart->isPast() || $depart->gt(now()->addHours($heures))) {
                continue;
            }

            NotificationClient::create([
                'user_id' => $billet->user_id,
                'type' => 'rappel',
                'titre' => 'Rappel de départ',
                'corps' => 'N\'oubliez pas votre trajet '.($billet->route?->compagnie?->nom ?? '')
                    .' du '.$billet->date_voyage->format('d/m')
                    .' à '.str_replace(':', 'h', $heure).' — soyez à la gare 30 min avant.',
                'billet_id' => $billet->id,
                'billet_reference' => $billet->reference,
                'cle' => $cle,
            ]);

            $crees++;
        }

        $this->info("Rappels créés : {$crees}.");

        return self::SUCCESS;
    }
}
