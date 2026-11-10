<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketingDestinataire;
use App\Models\MarketingModele;
use App\Models\User;
use App\Services\Marketing\Consentement;
use App\Services\Marketing\WhatsAppCloud;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    private const RANG = ['en_attente' => 0, 'envoye' => 1, 'remis' => 2, 'lu' => 3, 'echec' => 99, 'ignore' => 99];

    /** Vérification demandée par Meta quand on enregistre l'adresse du webhook. */
    public function verifier(Request $request)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $jeton = (string) $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $attendu = (string) config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $attendu !== '' && hash_equals($attendu, $jeton)) {
            return response((string) $request->query('hub_challenge', $request->query('hub.challenge')), 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Refusé', 403);
    }

    public function recevoir(Request $request)
    {
        $secret = (string) config('services.whatsapp.app_secret');
        $attendu = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($attendu, (string) $request->header('X-Hub-Signature-256'))) {
            return response('Signature invalide', 403);
        }

        foreach ((array) $request->json('entry', []) as $entree) {
            foreach ((array) ($entree['changes'] ?? []) as $changement) {
                $valeur = (array) ($changement['value'] ?? []);

                match ($changement['field'] ?? '') {
                    'messages' => $this->traiterMessages($valeur),
                    'message_template_status_update' => $this->traiterModele($valeur),
                    default => null,
                };
            }
        }

        return response('OK', 200);
    }

    private function traiterMessages(array $valeur): void
    {
        foreach ((array) ($valeur['statuses'] ?? []) as $s) {
            $d = MarketingDestinataire::where('wamid', (string) ($s['id'] ?? ''))->first();

            if (! $d) {
                continue;
            }

            $quand = isset($s['timestamp']) ? now()->setTimestamp((int) $s['timestamp']) : now();

            match ($s['status'] ?? '') {
                'delivered' => $this->monter($d, 'remis', ['remis_le' => $quand]),
                'read' => $this->monter($d, 'lu', ['lu_le' => $quand, 'remis_le' => $d->remis_le ?? $quand]),
                'failed' => $this->echouer($d, $s),
                default => null,
            };
        }

        foreach ((array) ($valeur['messages'] ?? []) as $m) {
            $this->traiterEntrant((array) $m);
        }
    }

    private function monter(MarketingDestinataire $d, string $statut, array $champs): void
    {
        if ((self::RANG[$d->statut] ?? -1) >= self::RANG[$statut]) {
            return;
        }

        $d->forceFill(array_merge(['statut' => $statut], $champs))->save();
    }

    private function echouer(MarketingDestinataire $d, array $s): void
    {
        if ($d->statut === 'lu') {
            return;
        }

        $erreur = (array) (($s['errors'][0] ?? []) ?: []);

        $d->forceFill([
            'statut' => 'echec',
            'erreur_code' => (string) ($erreur['code'] ?? ''),
            'erreur' => Str::limit((string) ($erreur['error_data']['details'] ?? $erreur['title'] ?? $erreur['message'] ?? 'Échec de livraison'), 250, ''),
        ])->save();
    }

    /** Un client répond STOP (ou touche « Ne plus recevoir ») : son désabonnement est immédiat. */
    private function traiterEntrant(array $m): void
    {
        $numero = preg_replace('/\D/', '', (string) ($m['from'] ?? ''));

        $texte = match ($m['type'] ?? '') {
            'text' => (string) ($m['text']['body'] ?? ''),
            'button' => (string) ($m['button']['payload'] ?? $m['button']['text'] ?? ''),
            default => '',
        };

        $mot = mb_strtolower(trim($texte));

        if ($numero === '' || $mot === '') {
            return;
        }

        $arret = in_array($mot, array_map('mb_strtolower', (array) config('marketing.mots_stop')), true);
        $reprise = in_array($mot, array_map('mb_strtolower', (array) config('marketing.mots_start')), true);

        if (! $arret && ! $reprise) {
            return;
        }

        $local = str_starts_with($numero, '226') ? substr($numero, 3) : $numero;

        $client = User::query()
            ->where(fn ($q) => $q->where('telephone', $numero)->orWhere('telephone', $local)->orWhere('telephone', '+'.$numero))
            ->first();

        if (! $client) {
            return;
        }

        if ($arret) {
            Consentement::definir($client, false, 'whatsapp_stop');
            WhatsAppCloud::envoyerTexte($numero, 'Vous ne recevrez plus nos offres. Pour les reprendre un jour, répondez START.');
        } else {
            Consentement::definir($client, true, 'whatsapp_start');
            WhatsAppCloud::envoyerTexte($numero, 'Merci ! Vous recevrez à nouveau les offres de vos compagnies. Répondez STOP à tout moment pour arrêter.');
        }
    }

    private function traiterModele(array $v): void
    {
        $modele = MarketingModele::where('meta_id', (string) ($v['message_template_id'] ?? ''))->first();

        if (! $modele) {
            return;
        }

        $statut = WhatsAppCloud::statutLocal((string) ($v['event'] ?? ''));

        $modele->forceFill([
            'statut' => $statut,
            'motif_refus' => $statut === 'refuse' ? Str::limit((string) ($v['reason'] ?? 'Refusé par Meta'), 250, '') : null,
        ])->save();
    }
}
