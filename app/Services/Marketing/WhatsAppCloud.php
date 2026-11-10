<?php

namespace App\Services\Marketing;

use App\Models\MarketingModele;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class WhatsAppCloud
{
    public const NON_CONFIGURE = 'WhatsApp n\'est pas configuré : renseignez WHATSAPP_TOKEN, WHATSAPP_PHONE_ID et WHATSAPP_BUSINESS_ACCOUNT_ID dans le fichier .env.';

    private static function cfg(string $cle, mixed $defaut = null): mixed
    {
        return config('services.whatsapp.'.$cle, $defaut);
    }

    public static function configure(): bool
    {
        return filled(self::cfg('token')) && filled(self::cfg('phone_number_id')) && filled(self::cfg('waba_id'));
    }

    /** L'envoi est réel dès que les identifiants sont renseignés : il n'y a aucune simulation. */
    public static function live(): bool
    {
        return self::configure();
    }

    private static function http(): PendingRequest
    {
        return Http::withToken((string) self::cfg('token'))->acceptJson()->asJson()->timeout(20);
    }

    private static function url(string $chemin): string
    {
        return 'https://graph.facebook.com/'.self::cfg('version', 'v25.0').'/'.ltrim($chemin, '/');
    }

    /** WhatsApp refuse les retours à la ligne, tabulations et espaces multiples dans une variable. */
    private static function nettoyer(string $valeur): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $valeur)) ?: '-';
    }

    private static function echec(string $code, string $message): array
    {
        return ['ok' => false, 'wamid' => null, 'code' => $code, 'erreur' => $message];
    }

    public static function statutLocal(string $statutMeta): string
    {
        return match (strtoupper($statutMeta)) {
            'APPROVED' => 'approuve',
            'REJECTED' => 'refuse',
            'PAUSED', 'DISABLED' => 'pause',
            default => 'en_attente',
        };
    }

    /** @return array{ok:bool, wamid:?string, code:?string, erreur:?string} */
    private static function analyser($reponse): array
    {
        $wamid = $reponse->json('messages.0.id');

        if ($reponse->successful() && $wamid) {
            return ['ok' => true, 'wamid' => $wamid, 'code' => null, 'erreur' => null];
        }

        return self::echec(
            (string) ($reponse->json('error.code') ?? $reponse->status()),
            (string) ($reponse->json('error.error_data.details') ?? $reponse->json('error.message') ?? 'Erreur inconnue')
        );
    }

    /** Envoie un modèle approuvé à un client. Aucune nouvelle tentative : on ne risque pas de doublon. */
    public static function envoyerModele(string $telephone, MarketingModele $modele, array $valeurs): array
    {
        if (! self::configure()) {
            return self::echec('config', self::NON_CONFIGURE);
        }

        $parametres = array_map(
            fn ($nom) => ['type' => 'text', 'text' => self::nettoyer((string) ($valeurs[$nom] ?? ''))],
            MarketingModele::occurrences($modele->corps)
        );

        $composants = [];

        if ($parametres) {
            $composants[] = ['type' => 'body', 'parameters' => $parametres];
        }

        if ($modele->bouton_stop) {
            $composants[] = [
                'type' => 'button', 'sub_type' => 'quick_reply', 'index' => '0',
                'parameters' => [['type' => 'payload', 'payload' => 'STOP']],
            ];
        }

        try {
            return self::analyser(self::http()->post(self::url(self::cfg('phone_number_id').'/messages'), [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $telephone,
                'type' => 'template',
                'template' => ['name' => $modele->nom_meta, 'language' => ['code' => $modele->langue], 'components' => $composants],
            ]));
        } catch (Throwable $e) {
            return self::echec('reseau', $e->getMessage());
        }
    }

    /** Message libre : permis seulement dans les 24 h qui suivent un message du client (ex. confirmation d'un STOP). */
    public static function envoyerTexte(string $telephone, string $texte): array
    {
        if (! self::configure()) {
            return self::echec('config', self::NON_CONFIGURE);
        }

        try {
            return self::analyser(self::http()->post(self::url(self::cfg('phone_number_id').'/messages'), [
                'messaging_product' => 'whatsapp',
                'to' => $telephone,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $texte],
            ]));
        } catch (Throwable $e) {
            return self::echec('reseau', $e->getMessage());
        }
    }

    /** Crée chez Meta le modèle d'authentification des codes de vérification s'il n'existe pas, sinon donne son statut. */
    public static function modeleOtp(): array
    {
        if (! self::configure()) {
            return ['ok' => false, 'cree' => false, 'statut' => null, 'erreur' => self::NON_CONFIGURE];
        }

        $nom = (string) self::cfg('template');
        $waba = self::cfg('waba_id');

        try {
            $existant = self::http()->get(self::url($waba.'/message_templates'), ['name' => $nom, 'fields' => 'name,status,category']);

            if (! $existant->successful()) {
                return ['ok' => false, 'cree' => false, 'statut' => null, 'erreur' => (string) ($existant->json('error.message') ?? 'Erreur '.$existant->status())];
            }

            if (count((array) $existant->json('data', [])) > 0) {
                return ['ok' => true, 'cree' => false, 'statut' => (string) $existant->json('data.0.status'), 'erreur' => null];
            }

            $cree = self::http()->post(self::url($waba.'/message_templates'), [
                'name' => $nom,
                'language' => (string) self::cfg('langue', 'fr'),
                'category' => 'AUTHENTICATION',
                'components' => [
                    ['type' => 'BODY', 'add_security_recommendation' => true],
                    ['type' => 'FOOTER', 'code_expiration_minutes' => 10],
                    ['type' => 'BUTTONS', 'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => 'Copier le code']]],
                ],
            ]);

            if ($cree->successful()) {
                return ['ok' => true, 'cree' => true, 'statut' => (string) $cree->json('status', 'PENDING'), 'erreur' => null];
            }

            return ['ok' => false, 'cree' => false, 'statut' => null, 'erreur' => (string) ($cree->json('error.error_user_msg') ?? $cree->json('error.message') ?? 'Refus de Meta')];
        } catch (Throwable $e) {
            return ['ok' => false, 'cree' => false, 'statut' => null, 'erreur' => $e->getMessage()];
        }
    }

    /** Soumet un modèle marketing à Meta pour examen. @return array{ok:bool, id:?string, statut:?string, erreur:?string} */
    public static function soumettreModele(MarketingModele $m): array
    {
        if (! self::configure()) {
            return ['ok' => false, 'id' => null, 'statut' => null, 'erreur' => self::NON_CONFIGURE];
        }

        $corps = ['type' => 'BODY', 'text' => MarketingModele::versMeta($m->corps)];

        if ($m->exemplesMeta()) {
            $corps['example'] = ['body_text' => [$m->exemplesMeta()]];
        }

        $composants = [$corps];

        if (filled($m->pied)) {
            $composants[] = ['type' => 'FOOTER', 'text' => $m->pied];
        }

        if ($m->bouton_stop) {
            $composants[] = ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Ne plus recevoir']]];
        }

        try {
            $r = self::http()->post(self::url(self::cfg('waba_id').'/message_templates'), [
                'name' => $m->nom_meta,
                'language' => $m->langue,
                'category' => 'MARKETING',
                'components' => $composants,
            ]);

            if ($r->successful() && $r->json('id')) {
                return ['ok' => true, 'id' => (string) $r->json('id'), 'statut' => self::statutLocal((string) $r->json('status', 'PENDING')), 'erreur' => null];
            }

            return ['ok' => false, 'id' => null, 'statut' => null, 'erreur' => (string) ($r->json('error.error_user_msg') ?? $r->json('error.message') ?? 'Refus de Meta')];
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'statut' => null, 'erreur' => $e->getMessage()];
        }
    }

    /** @return array{ok:bool, statut:?string, motif:?string, erreur:?string} */
    public static function lireModele(string $metaId): array
    {
        if (! self::configure()) {
            return ['ok' => false, 'statut' => null, 'motif' => null, 'erreur' => self::NON_CONFIGURE];
        }

        try {
            $r = self::http()->get(self::url($metaId), ['fields' => 'status,rejected_reason']);

            if ($r->successful()) {
                $motif = $r->json('rejected_reason');

                return ['ok' => true, 'statut' => self::statutLocal((string) $r->json('status')), 'motif' => $motif && $motif !== 'NONE' ? $motif : null, 'erreur' => null];
            }

            return ['ok' => false, 'statut' => null, 'motif' => null, 'erreur' => (string) ($r->json('error.message') ?? 'Erreur '.$r->status())];
        } catch (Throwable $e) {
            return ['ok' => false, 'statut' => null, 'motif' => null, 'erreur' => $e->getMessage()];
        }
    }

    /** État du numéro d'envoi : nom, qualité, plafond. */
    public static function etatNumero(): array
    {
        if (! self::configure()) {
            return ['ok' => false, 'erreur' => self::NON_CONFIGURE];
        }

        try {
            $id = self::cfg('phone_number_id');
            $r = self::http()->get(self::url($id), ['fields' => 'display_phone_number,verified_name,quality_rating']);

            if (! $r->successful()) {
                return ['ok' => false, 'erreur' => (string) ($r->json('error.message') ?? 'Erreur '.$r->status())];
            }

            $limite = self::http()->get(self::url($id), ['fields' => 'messaging_limit_tier']);

            return [
                'ok' => true,
                'numero' => $r->json('display_phone_number'),
                'nom' => $r->json('verified_name'),
                'qualite' => $r->json('quality_rating'),
                'plafond' => $limite->successful() ? $limite->json('messaging_limit_tier') : null,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'erreur' => $e->getMessage()];
        }
    }
}
