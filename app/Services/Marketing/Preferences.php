<?php

namespace App\Services\Marketing;

use App\Enums\BilletStatut;
use App\Models\Compagnie;
use App\Models\PreferenceClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Preferences
{
    /** @return array{lignes:int, segments:array<string,int>} */
    public static function recalculer(): array
    {
        $debut = now();
        $lignes = [];
        $segments = ['fidele' => 0, 'regulier' => 0, 'occasionnel' => 0, 'inactif' => 0];

        // Une note par client et par compagnie, la plus haute étant sa compagnie préférée.
        foreach (self::agreger(false)->groupBy('user_id') as $groupe) {
            $total = max(1, (int) $groupe->sum('nb_billets'));
            $notes = $groupe->map(fn ($l) => ['l' => $l, 's' => self::score($l, $total)])->sortByDesc('s')->values();

            foreach ($notes as $i => $n) {
                $ligne = self::ligne($n['l'], $n['s'], $i + 1);
                $segments[$ligne['segment']]++;
                $lignes[] = $ligne;
            }
        }

        // Une note par client, par compagnie et par gare de départ.
        foreach (self::agreger(true)->groupBy(fn ($l) => $l->user_id.'-'.$l->compagnie_id) as $groupe) {
            $total = max(1, (int) $groupe->sum('nb_billets'));

            foreach ($groupe as $l) {
                $lignes[] = self::ligne($l, self::score($l, $total), null);
            }
        }

        foreach (array_chunk($lignes, 500) as $lot) {
            PreferenceClient::upsert(
                $lot,
                ['user_id', 'compagnie_id', 'gare_id'],
                ['nb_billets', 'nb_voyageurs', 'montant', 'premier_achat', 'dernier_achat', 'score', 'rang', 'segment']
            );
        }

        PreferenceClient::where('updated_at', '<', $debut)->delete();

        return ['lignes' => count($lignes), 'segments' => $segments];
    }

    /** Les compagnies d'un client, de la préférée à la moins utilisée (calcul immédiat, sans attendre la nuit). */
    public static function classementClient(int $userId): array
    {
        $lignes = self::agreger(false, $userId);

        if ($lignes->isEmpty()) {
            return [];
        }

        $total = max(1, (int) $lignes->sum('nb_billets'));
        $compagnies = Compagnie::whereIn('id', $lignes->pluck('compagnie_id'))->get(['id', 'slug', 'nom'])->keyBy('id');

        return $lignes
            ->map(fn ($l) => [
                'compagnie' => $compagnies[$l->compagnie_id]->slug ?? null,
                'nom' => $compagnies[$l->compagnie_id]->nom ?? '',
                'score' => self::score($l, $total),
                'billets' => (int) $l->nb_billets,
                'dernier_achat' => Carbon::parse($l->dernier)->toDateString(),
            ])
            ->filter(fn ($r) => $r['compagnie'])
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    private static function agreger(bool $parGare, ?int $userId = null): Collection
    {
        $colonnes = $parGare ? ['b.user_id', 'r.compagnie_id', 'b.gare_id'] : ['b.user_id', 'r.compagnie_id'];

        return DB::table('billets as b')
            ->join('route_transports as r', 'r.id', '=', 'b.route_transport_id')
            ->whereIn('b.statut', BilletStatut::comptabilises())
            ->whereNotNull('b.date_paiement')
            ->when($userId, fn ($q) => $q->where('b.user_id', $userId))
            ->groupBy($colonnes)
            ->selectRaw(
                implode(', ', $colonnes).($parGare ? '' : ', 0 as gare_id').",
                COUNT(*) as nb_billets,
                COALESCE(SUM(b.travelers_count), 0) as nb_voyageurs,
                COALESCE(SUM(b.montant_transport), 0) as montant,
                SUM(CASE WHEN b.statut = 'utilise' THEN 1 ELSE 0 END) as nb_utilises,
                MIN(b.date_paiement) as premier,
                MAX(b.date_paiement) as dernier"
            )
            ->get();
    }

    private static function jours(string $date): int
    {
        return (int) abs(Carbon::parse($date)->startOfDay()->diffInDays(today()));
    }

    /** 40 points de fréquence, 30 de récence, 20 de part dans les achats du client, 10 d'usage réel. */
    private static function score(object $l, int $totalClient): float
    {
        $billets = (int) $l->nb_billets;

        $frequence = min($billets / 5, 1) * 40;
        $recence = 30 * exp(-self::jours($l->dernier) / 90);
        $part = 20 * ($billets / $totalClient);
        $usage = 10 * ((int) $l->nb_utilises / max(1, $billets));

        return round(min(100, $frequence + $recence + $part + $usage), 1);
    }

    private static function segment(float $score, int $billets, int $jours): string
    {
        $c = config('marketing.segments');

        return match (true) {
            $jours > $c['inactif_jours'] => 'inactif',
            $score >= $c['fidele_score'] && $billets >= $c['fidele_billets'] => 'fidele',
            $score >= $c['regulier_score'] && $billets >= $c['regulier_billets'] => 'regulier',
            default => 'occasionnel',
        };
    }

    private static function ligne(object $l, float $score, ?int $rang): array
    {
        return [
            'user_id' => (int) $l->user_id,
            'compagnie_id' => (int) $l->compagnie_id,
            'gare_id' => (int) $l->gare_id,
            'nb_billets' => (int) $l->nb_billets,
            'nb_voyageurs' => (int) $l->nb_voyageurs,
            'montant' => (int) $l->montant,
            'premier_achat' => Carbon::parse($l->premier)->toDateString(),
            'dernier_achat' => Carbon::parse($l->dernier)->toDateString(),
            'score' => $score,
            'rang' => $rang,
            'segment' => self::segment($score, (int) $l->nb_billets, self::jours($l->dernier)),
        ];
    }
}
