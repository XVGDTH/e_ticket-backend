<?php

namespace App\Console\Commands;

use App\Enums\BilletStatut;
use App\Models\AvisGare;
use App\Models\Billet;
use App\Models\NotificationClient;
use App\Services\Billetterie;
use Illuminate\Console\Command;

class EnvoyerRappelsDepart extends Command
{
    protected $signature = 'billets:rappels';

    protected $description = 'Crée les rappels de départ et les invitations à noter la gare.';

    // public function handle(): int
    // {
    //     $this->info('Rappels créés : ' . $this->rappels());
    //     $this->info('Invitations à noter créées : ' . $this->invitationsAvis());

    //     return self::SUCCESS;
    // }
    public function handle(): int
    {
        $this->info('Rappels créés : '.$this->rappels());
        $this->info('Invitations à noter créées : '.$this->invitationsAvis());
        $this->info('Messages de validité créés : '.$this->notificationsValidite());

        return self::SUCCESS;
    }

    /** Un rappel quelques heures avant le départ des billets valides. */
    private function rappels(): int
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

        return $crees;
    }

    /** Une fois le départ passé, les voyageurs embarqués sont invités à noter la gare. */
    private function invitationsAvis(): int
    {
        $billets = Billet::query()
            ->where('statut', BilletStatut::Utilise->value)
            ->whereBetween('date_voyage', [today()->subDays(2)->toDateString(), today()->toDateString()])
            ->get();

        $dejaInvites = NotificationClient::withTrashed()
            ->where('type', 'avis')
            ->whereIn('billet_id', $billets->pluck('id'))
            ->pluck('billet_id');

        $dejaNotes = AvisGare::whereIn('billet_id', $billets->pluck('id'))->pluck('billet_id');

        $crees = 0;

        foreach ($billets as $billet) {
            if ($dejaInvites->contains($billet->id) || $dejaNotes->contains($billet->id)) {
                continue;
            }

            $heure = Billetterie::normaliserHeure((string) $billet->heure);
            $depart = $billet->date_voyage->copy()->setTimeFromTimeString($heure);

            if ($depart->gt(now()->subMinutes(30))) {
                continue; // trop tôt : le voyage n'est pas encore terminé
            }

            NotificationClient::pourBillet(
                $billet,
                'avis',
                'Comment s\'est passé votre voyage ?',
                'Donnez votre avis sur la gare :'
            );

            $crees++;
        }

        return $crees;
    }

    /** Voyage manqué : un message par jour jusqu'à la fin de la validité, puis un message d'expiration. */
    private function notificationsValidite(): int
    {
        $billets = Billet::query()
            ->with('route.compagnie')
            ->where('statut', BilletStatut::Valide->value)
            ->whereBetween('date_voyage', [today()->subDays(120)->toDateString(), today()->toDateString()])
            ->get();

        $deja = NotificationClient::withTrashed()
            ->whereIn('type', ['validite', 'expiration'])
            ->whereIn('billet_id', $billets->pluck('id'))
            ->get(['billet_id', 'cle'])
            ->map(fn ($n) => $n->billet_id.'|'.$n->cle);

        $crees = 0;

        foreach ($billets as $billet) {
            $fin = $billet->valide_jusqu_au;
            $depart = $billet->date_voyage->copy()
                ->setTimeFromTimeString(Billetterie::normaliserHeure((string) $billet->heure));

            // On laisse une heure à la gare pour embarquer avant de parler de voyage manqué.
            if (! $fin || $depart->gt(now()->subHour())) {
                continue;
            }

            $compagnie = $billet->route?->compagnie?->nom ?? '';

            if (today()->lte($fin)) {
                $cle = 'validite:'.today()->toDateString();

                if ($deja->contains($billet->id.'|'.$cle)) {
                    continue;
                }

                $reste = (int) today()->diffInDays($fin);

                NotificationClient::create([
                    'user_id' => $billet->user_id,
                    'type' => 'validite',
                    'titre' => $reste === 0 ? 'Dernier jour pour voyager' : 'Voyage manqué ? Reportez votre billet',
                    'corps' => 'Votre billet '.$compagnie.' (réf. '.$billet->reference.') reste valable '
                        .($reste === 0
                            ? 'aujourd\'hui seulement'
                            : 'jusqu\'au '.$fin->format('d/m/Y').' ('.$reste.' jour(s))')
                        .'. Reportez votre voyage depuis l\'application avant son expiration.',
                    'billet_id' => $billet->id,
                    'billet_reference' => $billet->reference,
                    'cle' => $cle,
                ]);
            } else {
                if ($deja->contains($billet->id.'|expiration')) {
                    continue;
                }

                NotificationClient::create([
                    'user_id' => $billet->user_id,
                    'type' => 'expiration',
                    'titre' => 'Billet expiré',
                    'corps' => 'Votre billet '.$compagnie.' (réf. '.$billet->reference.') a expiré le '
                        .$fin->format('d/m/Y').' sans avoir été utilisé.',
                    'billet_id' => $billet->id,
                    'billet_reference' => $billet->reference,
                    'cle' => 'expiration',
                ]);
            }

            $crees++;
        }

        return $crees;
    }
}
