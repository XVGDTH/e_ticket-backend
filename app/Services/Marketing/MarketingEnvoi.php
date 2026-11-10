<?php

namespace App\Services\Marketing;

use App\Models\Gare;
use App\Models\MarketingCampagne;
use App\Models\MarketingModele;
use App\Models\NotificationClient;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

class MarketingEnvoi
{
    /** Problèmes qui bloquent toute la campagne (jeton, modèle, paiement, compte). */
    private const BLOQUANTS = ['190', '131031', '131042', '132000', '132001', '132007', '132012', '132015', '132016'];

    /** Limites de débit : on laisse le message en attente pour la minute suivante. */
    private const A_REESSAYER = ['130429', '80007', '131048', '4'];

    /** Traite les campagnes à envoyer. @return int nombre de messages envoyés ou de notifications créées */
    public static function traiter(): int
    {
        $envoyes = 0;

        $campagnes = MarketingCampagne::query()->with(['modele', 'compagnie'])
            ->whereIn('statut', ['planifiee', 'en_cours'])
            ->where(fn ($q) => $q->whereNull('planifiee_le')->orWhere('planifiee_le', '<=', now()))
            ->orderBy('id')->get();

        foreach ($campagnes as $c) {
            $app = $c->estApplication();

            if (! $app) {
                if (! WhatsAppCloud::configure()) {
                    $c->forceFill(['statut' => 'erreur', 'erreur' => WhatsAppCloud::NON_CONFIGURE])->save();

                    continue;
                }

                if ($c->modele?->statut !== 'approuve') {
                    $c->forceFill(['statut' => 'erreur', 'erreur' => 'Le modèle de message n\'est pas approuvé par Meta.'])->save();

                    continue;
                }
            }

            if ($c->statut === 'planifiee') {
                $c->preparerDestinataires();
                $c->forceFill(['statut' => 'en_cours', 'lancee_le' => now()])->save();
            }

            $gare = $c->gare_id ? (Gare::whereKey($c->gare_id)->value('quartier') ?: 'votre gare') : 'votre gare';

            $lot = $c->destinataires()->where('statut', 'en_attente')
                ->orderBy('id')->limit($app ? 500 : (int) config('marketing.lot', 100))->get();

            foreach ($lot as $d) {
                $client = User::find($d->user_id);
                $consentement = $app ? ($client?->marketing_app) : ($client?->marketing_whatsapp);

                // Le consentement est revérifié au dernier moment : un refus reçu entre-temps est respecté.
                if (! $client || ! $consentement || ! $client->actif) {
                    $d->forceFill(['statut' => 'ignore', 'erreur' => 'Client désabonné ou désactivé'])->save();

                    continue;
                }

                $valeurs = $c->valeursPour($client, $gare);

                if ($app) {
                    try {
                        (new NotificationClient)->forceFill([
                            'user_id' => $client->id,
                            'type' => 'offre',
                            'titre' => $c->titre ?: ($c->compagnie?->nom ?? 'Offre'),
                            'corps' => MarketingModele::rendre($c->modele->corps, $valeurs),
                            'cle' => 'offre:'.$c->id,
                        ])->save();

                        $d->forceFill(['statut' => 'remis', 'envoye_le' => now(), 'remis_le' => now()])->save();
                        $envoyes++;
                    } catch (Throwable $e) {
                        $d->forceFill(['statut' => 'echec', 'erreur' => Str::limit($e->getMessage(), 250, '')])->save();
                    }

                    continue;
                }

                $r = WhatsAppCloud::envoyerModele($d->telephone, $c->modele, $valeurs);

                if ($r['ok']) {
                    $d->forceFill(['statut' => 'envoye', 'wamid' => $r['wamid'], 'envoye_le' => now()])->save();
                    $envoyes++;

                    continue;
                }

                $code = (string) $r['code'];

                if (in_array($code, self::A_REESSAYER, true)) {
                    continue 2;
                }

                $d->forceFill([
                    'statut' => 'echec',
                    'erreur_code' => $code,
                    'erreur' => Str::limit((string) $r['erreur'], 250, ''),
                ])->save();

                if (in_array($code, self::BLOQUANTS, true)) {
                    $c->forceFill(['statut' => 'erreur', 'erreur' => Str::limit((string) $r['erreur'], 250, '')])->save();

                    continue 2;
                }
            }

            if (! $c->destinataires()->where('statut', 'en_attente')->exists()) {
                $c->forceFill(['statut' => 'terminee', 'terminee_le' => now()])->save();
            }
        }

        return $envoyes;
    }
}
