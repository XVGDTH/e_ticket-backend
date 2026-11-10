<?php

namespace App\Services\Marketing;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Consentement
{
    /** Enregistre l'accord ou le refus du client pour un canal ('whatsapp' ou 'app'), avec la date et l'origine. */
    public static function definir(User $client, bool $accepte, string $source, string $canal = 'whatsapp'): void
    {
        if ($canal === 'app') {
            $client->forceFill(['marketing_app' => $accepte])->save();
        } else {
            $client->forceFill(['marketing_whatsapp' => $accepte, 'marketing_whatsapp_le' => now()])->save();
        }

        DB::table('marketing_consentements')->insert([
            'user_id' => $client->id,
            'accepte' => $accepte,
            'source' => $source,
            'canal' => $canal,
            'created_at' => now(),
        ]);
    }
}
