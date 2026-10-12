<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    public function envoyerOtp(string $telephone, string $code): void
    {
        $config = config('services.whatsapp');

        foreach (['token', 'phone_id', 'template', 'version'] as $cle) {
            if (blank($config[$cle] ?? null)) {
                throw new \RuntimeException("Configuration WhatsApp incomplète : services.whatsapp.{$cle}");
            }
        }

        Http::withToken($config['token'])
            ->timeout(10)
            ->post("https://graph.facebook.com/{$config['version']}/{$config['phone_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $this->formater($telephone),
                'type' => 'template',
                'template' => [
                    'name' => $config['template'],
                    'language' => ['code' => $config['langue'] ?? 'fr'],
                    'components' => [
                        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                        ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
                    ],
                ],
            ])
            ->throw();
    }

    /** 70 12 34 56 devient 22670123456 (format international, sans +). */
    private function formater(string $telephone): string
    {
        $chiffres = preg_replace('/^00/', '', preg_replace('/\D+/', '', $telephone));

        return strlen($chiffres) === 8 ? '226' . $chiffres : $chiffres;
    }
}