<?php

namespace App\Services;

use App\Models\Billet;

/**
 * Contenu du code QR d'un billet : KM1.<référence>.<signature>.
 * La signature ne peut être produite que par ce serveur : un code fabriqué ailleurs est rejeté.
 */
class BilletToken
{
    private static function cle(): string
    {
        return (string) (config('billetterie.qr_secret') ?: config('app.key'));
    }

    private static function signature(Billet $billet): string
    {
        $brut = hash_hmac('sha256', $billet->id.'|'.$billet->reference.'|'.$billet->user_id, self::cle(), true);

        return substr(rtrim(strtr(base64_encode($brut), '+/', '-_'), '='), 0, 16);
    }

    public static function generer(Billet $billet): string
    {
        return 'KM1.'.$billet->reference.'.'.self::signature($billet);
    }

    /** @return array{reference: string, signature: ?string, mode: string} */
    public static function decoder(string $contenu): array
    {
        $contenu = trim($contenu);

        if (preg_match('/^KM1\.([^.\s]+)\.([A-Za-z0-9_\-]{16})$/', $contenu, $m)) {
            return ['reference' => $m[1], 'signature' => $m[2], 'mode' => 'qr'];
        }

        // Référence tapée à la main (ou lue par un lecteur USB sans signature).
        return ['reference' => preg_replace('/\s+/', '', $contenu) ?? '', 'signature' => null, 'mode' => 'manuel'];
    }

    public static function verifier(Billet $billet, string $signature): bool
    {
        return hash_equals(self::signature($billet), $signature);
    }
}
