<?php

namespace App\Services\Marketing;

use App\Models\PreferenceClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Audience
{
    public const SEGMENTS = [
        'fidele' => 'Fidèles',
        'regulier' => 'Réguliers',
        'occasionnel' => 'Occasionnels',
        'inactif' => 'Inactifs (à réactiver)',
    ];

    /** Les clients qu'on a le droit et la possibilité de contacter pour cette cible et ce canal. */
    public static function requete(int $compagnieId, ?int $gareId, array $segments, bool $avecPause = true, string $canal = 'whatsapp'): Builder
    {
        $q = User::query()
            ->where('actif', true)
            ->whereIn('id', PreferenceClient::query()->select('user_id')
                ->where('compagnie_id', $compagnieId)
                ->where('gare_id', $gareId ?: 0)
                ->whereIn('segment', $segments));

        if ($canal === 'app') {
            return $q->where('marketing_app', true);
        }

        $q->where('telephone_verifie', true)
            ->whereNotNull('telephone')
            ->where('marketing_whatsapp', true)
            // numéros qui ne sont pas sur WhatsApp : on n'insiste pas
            ->whereNotIn('id', DB::table('marketing_destinataires')->select('user_id')->where('erreur_code', '131026'));

        if ($avecPause) {
            $q->whereNotIn('id', DB::table('marketing_destinataires')->select('user_id')
                ->whereIn('statut', ['envoye', 'remis', 'lu'])
                ->where('envoye_le', '>=', now()->subDays((int) config('marketing.pause_jours', 7))));
        }

        return $q;
    }

    /** @return array{dans_segments:int, consentants:int, cibles:int, en_pause:int} */
    public static function compter(int $compagnieId, ?int $gareId, array $segments, string $canal = 'whatsapp'): array
    {
        if (! $compagnieId || $segments === []) {
            return ['dans_segments' => 0, 'consentants' => 0, 'cibles' => 0, 'en_pause' => 0];
        }

        $dansSegments = PreferenceClient::query()
            ->where('compagnie_id', $compagnieId)->where('gare_id', $gareId ?: 0)
            ->whereIn('segment', $segments)->distinct()->count('user_id');

        $consentants = self::requete($compagnieId, $gareId, $segments, false, $canal)->count();
        $cibles = self::requete($compagnieId, $gareId, $segments, true, $canal)->count();

        return [
            'dans_segments' => $dansSegments,
            'consentants' => $consentants,
            'cibles' => $cibles,
            'en_pause' => max(0, $consentants - $cibles),
        ];
    }
}
