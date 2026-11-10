<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppService
{
    /**
     * Envoie le code de vérification avec le modèle « Authentication » approuvé par Meta.
     * Chaque envoi est facturé par Meta : n'appelez cette méthode que pour un vrai besoin.
     *
     * @throws RuntimeException si WhatsApp refuse l'envoi
     */
    public function envoyerOtp(string $telephone, string $code): void
    {
        $config = config('services.whatsapp');

        $reponse = Http::withToken((string) $config['token'])
            ->acceptJson()
            ->timeout(10)
            ->post("https://graph.facebook.com/{$config['version']}/{$config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->numeroInternational($telephone),
                'type' => 'template',
                'template' => [
                    'name' => $config['template'],
                    'language' => ['code' => $config['langue']],
                    'components' => [
                        // Le même code doit apparaître dans le corps ET dans le bouton « copier le code ».
                        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                        [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => '0',
                            'parameters' => [['type' => 'text', 'text' => $code]],
                        ],
                    ],
                ],
            ]);

        if ($reponse->failed()) {
            // Le code n'est jamais écrit dans les journaux.
            throw new RuntimeException(
                "WhatsApp a refusé l'envoi ({$reponse->status()}) : ".$reponse->json('error.message', 'réponse inconnue')
            );
        }
    }

    /** « 70 12 34 56 » devient « 22670123456 » : le format attendu par WhatsApp, sans le « + ». */
    public function numeroInternational(string $telephone): string
    {
        $chiffres = preg_replace('/\D/', '', $telephone) ?? '';
        $indicatif = (string) config('services.whatsapp.indicatif_pays', '226');

        if (str_starts_with($chiffres, '00')) {
            $chiffres = substr($chiffres, 2);
        }

        // Numéro déjà au format international (indicatif + 8 chiffres).
        if (str_starts_with($chiffres, $indicatif) && strlen($chiffres) > 8) {
            return $chiffres;
        }

        return $indicatif.$chiffres;
    }
}
